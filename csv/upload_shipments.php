<?php
header('Content-Type: text/html; charset=utf-8');

// 1. DB 접속 설정 (환경에 맞게 수정하세요)
$db_host = 'localhost';
$db_name = 'charm3007'; // 사용자 프롬프트에 표기된 DB명 기준
$db_user = 'charm3007';
$db_pass = 'wlsgml8808';

$message = '';
$inserted_count = 0;
$skipped_count = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = "<div style='color: red;'>파일 업로드 중 오류가 발생했습니다. (코드: {$file['error']})</div>";
    } else {
        try {
            $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);

            $handle = fopen($file['tmp_name'], 'r');
            if ($handle !== FALSE) {
                // 트랜잭션 시작 (대량 처리 속도 향상 및 안전성 보장)
                $pdo->beginTransaction();

                $sql = "INSERT INTO inner_shipments (
                    seq_no, capital, contract_type, company_name,
                    representative, phone, origin_type, maker,
                    model, model_modifier, car_price, lease_period,
                    release_date, return_date, ag_rate, ag_fee, manager2
                ) VALUES (
                    :seq_no, :capital, :contract_type, :company_name,
                    :representative, :phone, :origin_type, :maker,
                    :model, :model_modifier, :car_price, :lease_period,
                    :release_date, :return_date, :ag_rate, :ag_fee, :manager2
                )";

                $stmt = $pdo->prepare($sql);
                $row_index = 0;

                while (($raw_row = fgetcsv($handle, 5000, ",")) !== FALSE) {
                    $row_index++;

                    // 1행 헤더 스킵
                    if ($row_index === 1) {
                        continue;
                    }

                    // 빈 줄 스킵
                    if (empty(array_filter($raw_row))) {
                        continue;
                    }

                    // 엑셀 내보내기 한글(CP949/EUC-KR) 인코딩 UTF-8 자동 변환
                    $row = array_map(function($val) {
                        $val = trim($val);
                        $encoding = mb_detect_encoding($val, ['UTF-8', 'CP949', 'EUC-KR'], true);
                        return ($encoding && $encoding !== 'UTF-8') ? iconv($encoding, 'UTF-8//IGNORE', $val) : $val;
                    }, $raw_row);

                    // 컬럼 수가 부족한 행 방어 처리
                    if (count($row) < 17) {
                        $skipped_count++;
                        continue;
                    }

                    // 데이터 정제 및 타입 변환
                    $seq_no         = (int)preg_replace('/[^0-9]/', '', $row[0]);
                    $capital        = $row[1];
                    $contract_type  = $row[2] ?: '렌트';
                    $company_name   = $row[3];
                    $representative = $row[4];
                    $phone          = $row[5];
                    $origin_type    = $row[6] ?: '국산';
                    $maker          = $row[7];
                    $model          = $row[8];
                    $model_modifier = $row[9];
                    
                    // 금액: 쉼표, 원화 기호 제거 후 정수 변환
                    $car_price      = (int)preg_replace('/[^0-9]/', '', $row[10]);
                    $lease_period   = (int)preg_replace('/[^0-9]/', '', $row[11]);
                    
                    // 날짜: YYYY-MM-DD 포맷 맞춤 (2022-1-18 -> 2022-01-18)
                    $release_date   = !empty($row[12]) ? date('Y-m-d', strtotime(str_replace('.', '-', $row[12]))) : date('Y-m-d');
                    $return_date    = !empty($row[13]) ? date('Y-m-d', strtotime(str_replace('.', '-', $row[13]))) : date('Y-m-d');
                    
                    // AG 요율 및 수수료
                    $ag_rate        = (float)preg_replace('/[^0-9.]/', '', $row[14]);
                    $ag_fee         = (int)preg_replace('/[^0-9]/', '', $row[15]);
                    $manager2       = $row[16] ?: null;

                    // 바인딩 및 실행
                    $stmt->execute([
                        ':seq_no'         => $seq_no,
                        ':capital'        => $capital,
                        ':contract_type'  => $contract_type,
                        ':company_name'   => $company_name,
                        ':representative' => $representative,
                        ':phone'          => $phone,
                        ':origin_type'    => $origin_type,
                        ':maker'          => $maker,
                        ':model'          => $model,
                        ':model_modifier' => $model_modifier,
                        ':car_price'      => $car_price,
                        ':lease_period'   => $lease_period,
                        ':release_date'   => $release_date,
                        ':return_date'    => $return_date,
                        ':ag_rate'        => $ag_rate,
                        ':ag_fee'         => $ag_fee,
                        ':manager2'       => $manager2
                    ]);

                    $inserted_count++;
                }

                $pdo->commit();
                fclose($handle);
                $message = "<div style='color: green; font-weight: bold;'>성공: 총 {$inserted_count}건이 등록되었습니다. (스킵: {$skipped_count}건)</div>";
            }
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = "<div style='color: red;'>DB 처리 에러: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>출고 목록 CSV 일괄 등록</title>
    <style>
        body { font-family: 'Pretendard', sans-serif; background: #f4f6f9; padding: 40px; }
        .upload-card { background: #fff; max-width: 550px; margin: 0 auto; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        h2 { font-size: 18px; margin-bottom: 20px; color: #1a2a3a; }
        input[type="file"] { margin-bottom: 15px; width: 100%; }
        .btn-submit { background: #0056b3; color: #fff; border: none; padding: 10px 18px; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .btn-submit:hover { background: #004494; }
        .result-box { margin-top: 15px; padding: 12px; background: #f8f9fa; border-radius: 4px; border: 1px solid #e9ecef; }
    </style>
</head>
<body>

<div class="upload-card">
    <h2>출고 목록 CSV 업로드</h2>
    <form action="" method="POST" enctype="multipart/form-data">
        <input type="file" name="csv_file" accept=".csv" required>
        <br>
        <button type="submit" class="btn-submit">DB 일괄 등록하기</button>
    </form>

    <?php if (!empty($message)): ?>
        <div class="result-box">
            <?= $message ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>