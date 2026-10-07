<?php
/** CSV 컬럼: origin_type,maker,model,sorted,use_yn,shape
 * 상위 폴더의 config.php에 DB 접속정보 설정 → 브라우저 실행.
 * 동일 origin_type + maker + model은 건너뜁니다.
 * 입력 완료 후 서버에서 이 파일을 삭제하세요.
 */
// config.php는 이 파일의 한 단계 위 폴더에 둡니다.
// config.php는 db_host, db_user, db_password, db_name 배열을 반환합니다.
$configPath = dirname(__DIR__) . '/config.php';

session_start();
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
if (empty($_SESSION['car_csv_token'])) {
    $_SESSION['car_csv_token'] = bin2hex(random_bytes(32));
}
function h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $connection = null;
    $stream = null;
    $locked = false;
    try {
        if (!hash_equals($_SESSION['car_csv_token'], (string)($_POST['token'] ?? ''))) {
            throw new RuntimeException('잘못된 요청입니다. 페이지를 다시 열어주세요.');
        }
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('CSV 업로드에 실패했습니다. 파일 선택과 서버 업로드 용량 제한을 확인해주세요.');
        }
        $upload = $_FILES['csv_file'];
        if (!is_uploaded_file($upload['tmp_name']) || $upload['size'] > 5 * 1024 * 1024) {
            throw new RuntimeException('5MB 이하의 CSV 파일을 선택해주세요.');
        }
        $raw = file_get_contents($upload['tmp_name']);
        if ($raw === false || $raw === '') { throw new RuntimeException('빈 파일이거나 파일을 읽을 수 없습니다.'); }
        // Excel CSV UTF-8의 BOM 제거. 일반 한국어 Excel CSV(CP949)도 지원.
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        $isCp949 = preg_match('//u', $raw) !== 1;
        if ($isCp949) {
            if (!function_exists('iconv')) { throw new RuntimeException('UTF-8 CSV로 다시 저장해주세요.'); }
            $converted = @iconv('CP949', 'UTF-8', $raw);
            if ($converted === false) { throw new RuntimeException('문자 인코딩을 확인해주세요. CSV UTF-8로 저장하는 것을 권장합니다.'); }
            $raw = $converted;
        }
        // 업로드 파일을 직접 읽습니다. php://temp 쓰기/되감기에 의존하지 않습니다.
        $stream = fopen($upload['tmp_name'], 'rb');
        if ($stream === false) {
            throw new RuntimeException('업로드 파일을 열 수 없습니다. 서버 파일 읽기 권한을 확인해주세요.');
        }
        $prefix = fread($stream, 3);
        if ($prefix !== "\xEF\xBB\xBF" && fseek($stream, 0) !== 0) {
            throw new RuntimeException('CSV 파일의 시작 위치로 이동할 수 없습니다.');
        }
        if ($isCp949) {
            $filter = stream_filter_append($stream, 'convert.iconv.CP949/UTF-8', STREAM_FILTER_READ);
            if ($filter === false) {
                throw new RuntimeException('한글 변환 필터를 사용할 수 없습니다. CSV UTF-8로 저장해주세요.');
            }
        }
        $header = fgetcsv($stream, 0, ',', '"', chr(92));
        if ($header === false || $header === [null]) { throw new RuntimeException('CSV 첫 행을 읽을 수 없습니다. 파일이 비어 있거나 첫 행이 빈 줄인지 확인해주세요.'); }
        $header = array_map('trim', $header);
        if (count($header) !== count(array_unique($header))) { throw new RuntimeException('CSV 헤더에 중복 컬럼이 있습니다.'); }
        $required = ['origin_type', 'maker', 'model', 'sorted', 'use_yn', 'shape'];
        $missing = array_diff($required, $header);
        if ($missing) { throw new RuntimeException('누락된 CSV 컬럼: ' . implode(', ', $missing)); }
        $map = array_flip($header);
        $rows = [];
        $record = 1;
        while (($fields = fgetcsv($stream, 0, ',', '"', chr(92))) !== false) {
            $record++;
            if (count(array_filter($fields, function ($v) { return trim((string)$v) !== ''; })) === 0) { continue; }
            if (count($fields) !== count($header)) { throw new RuntimeException("CSV {$record}번째 레코드: 컬럼 수가 헤더와 다릅니다."); }
            $row = [];
            foreach ($required as $column) { $row[$column] = trim((string)$fields[$map[$column]]); }
            foreach (['origin_type' => 10, 'maker' => 50, 'model' => 100, 'shape' => 20] as $column => $limit) {
                $length = preg_match_all('/./us', $row[$column]);
                if ($length === false || $length > $limit) { throw new RuntimeException("CSV {$record}번째 레코드: {$column}은 {$limit}글자 이하여야 합니다."); }
            }
            foreach (['origin_type', 'maker', 'model'] as $column) {
                if ($row[$column] === '') { throw new RuntimeException("CSV {$record}번째 레코드: {$column}이 비어 있습니다."); }
            }
            if ($row['sorted'] === '') {
                $row['sorted'] = null;
            } else {
                if (!preg_match('/^-?\d+$/', $row['sorted']) || (float)$row['sorted'] < -2147483648 || (float)$row['sorted'] > 2147483647) {
                    throw new RuntimeException("CSV {$record}번째 레코드: sorted는 INT 범위의 정수 또는 빈 값이어야 합니다.");
                }
                $row['sorted'] = (int)$row['sorted'];
            }
            $row['use_yn'] = strtolower($row['use_yn'] === '' ? 'y' : $row['use_yn']);
            if (!in_array($row['use_yn'], ['y', 'n'], true)) { throw new RuntimeException("CSV {$record}번째 레코드: use_yn은 y 또는 n이어야 합니다."); }
            $row['shape'] = $row['shape'] === '' ? null : $row['shape'];
            $rows[] = $row;
        }
        if (!$rows) { throw new RuntimeException('입력할 데이터가 없습니다.'); }
        if (!is_file($configPath)) {
            throw new RuntimeException('한 단계 위 폴더에 config.php 파일을 넣어주세요.');
        }
        $config = require $configPath;
        if (!is_array($config)) {
            throw new RuntimeException('config.php는 설정 배열을 반환해야 합니다.');
        }
        foreach (['db_host', 'db_user', 'db_password', 'db_name'] as $key) {
            if (!array_key_exists($key, $config) || !is_string($config[$key])) {
                throw new RuntimeException('config.php의 ' . $key . ' 설정을 확인해주세요.');
            }
        }
        foreach (['db_host', 'db_user', 'db_name'] as $key) {
            if (trim($config[$key]) === '') {
                throw new RuntimeException('config.php의 ' . $key . ' 값을 입력해주세요.');
            }
        }
        $dbHost = $config['db_host'];
        $dbName = $config['db_name'];
        $dbPort = isset($config['db_port']) ? (int)$config['db_port'] : 3306;
        if (strpos($dbHost, ';') !== false || strpos($dbName, ';') !== false || $dbPort < 1 || $dbPort > 65535) {
            throw new RuntimeException('DB 호스트, 이름 또는 포트 설정이 올바르지 않습니다.');
        }
        $connection = new PDO(
            "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
            $config['db_user'],
            $config['db_password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
        );
        $pdo = $connection;
        $databaseName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        $lockName = 'car_import_' . substr(hash('sha256', $databaseName), 0, 40);
        $lock = $pdo->prepare('SELECT GET_LOCK(?, 10)');
        $lock->execute([$lockName]);
        $locked = (int)$lock->fetchColumn() === 1;
        if (!$locked) { throw new RuntimeException('다른 입력 작업이 진행 중입니다. 잠시 후 다시 시도해주세요.'); }
        $pdo->beginTransaction();
        $check = $pdo->prepare('SELECT id FROM inner_car_model WHERE origin_type = ? AND maker = ? AND model = ? LIMIT 1');
        $insert = $pdo->prepare('INSERT INTO inner_car_model (origin_type, maker, model, sorted, use_yn, shape) VALUES (?, ?, ?, ?, ?, ?)');
        $inserted = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            $check->execute([$row['origin_type'], $row['maker'], $row['model']]);
            if ($check->fetchColumn() !== false) { $skipped++; continue; }
            $insert->execute(array_values($row));
            $inserted++;
        }
        $pdo->commit();
        $message = '입력 완료: 전체 ' . count($rows) . '건 / 신규 ' . $inserted . '건 / 중복 제외 ' . $skipped . '건';
    } catch (Throwable $e) {
        if ($connection && $connection->inTransaction()) { $connection->rollBack(); }
        $message = '입력 실패: ' . $e->getMessage();
    } finally {
        if (is_resource($stream)) { fclose($stream); }
        if ($connection && $locked) {
            try {
                $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
                $release->execute([$lockName]);
            } catch (Throwable $ignored) { /* 연결 종료 시 잠금도 해제됩니다. */ }
        }
    }
}
?>
<!doctype html>
<html lang="ko">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>차량 CSV 입력</title>
<style>body{font-family:Arial,sans-serif;background:#f5f6f8;color:#202936;padding:24px;line-height:1.7}main{max-width:720px;margin:40px auto;padding:28px;background:white;border-radius:12px}button{background:#245cbd;color:white;border:0;border-radius:6px;padding:12px 20px;font-size:16px;cursor:pointer}input{display:block;margin:20px 0;max-width:100%}.result,pre{background:#eef3fc;padding:16px;border-radius:6px;overflow-wrap:anywhere}pre{overflow:auto}</style>
</head><body><main>
<h1>차량 CSV 입력</h1>
<p>CSV 파일을 업로드하여 inner_car_model 테이블에 입력합니다.</p>
<p>첫 번째 행에 아래 컬럼명이 필요합니다. 컬럼 순서는 바뀌어도 됩니다.</p>
<pre>origin_type,maker,model,sorted,use_yn,shape
국산,KGM,렉스턴,1,y,RV
국산,KGM,무쏘,,y,RV</pre>
<p>Excel에서는 <strong>CSV UTF-8(쉼표로 분리)</strong>로 저장해주세요. 빈 sorted는 NULL, 빈 use_yn은 y로 입력됩니다.</p>
<?php if ($message !== ''): ?><p class="result"><?= h($message) ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="token" value="<?= h($_SESSION['car_csv_token']) ?>">
<label for="csv_file">CSV 파일 선택 (최대 5MB)</label>
<input type="file" name="csv_file" id="csv_file" accept=".csv,text/csv" required>
<button type="submit">CSV 데이터 입력</button>
</form>
<p>같은 origin_type + maker + model이 있으면 건너뜁니다. 기존 데이터를 수정하지 않습니다.</p>
<p>id는 자동 생성되며 col1~col5는 기본값을 사용합니다. 오류가 나면 이번 입력을 취소합니다(InnoDB 기준).</p>
<p>상위 폴더의 config.php에 db_host, db_user, db_password, db_name 값을 설정해주세요. 입력 완료 후 서버에서 이 파일을 삭제해주세요.</p>
</main></body></html>
