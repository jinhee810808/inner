<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(0, '/', '', !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', true);
session_start();

function reply($status, $data) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') reply(405, array('success' => false, 'message' => 'POST 요청만 가능합니다.'));
if (empty($_SESSION['inner_user_id'])) reply(401, array('success' => false, 'message' => '다시 로그인해주세요.'));
$csrf = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    reply(403, array('success' => false, 'message' => '인증 정보가 만료되었습니다. 다시 로그인해주세요.'));
}
$input = json_decode(file_get_contents('php://input'), true);
$ids = is_array($input) && isset($input['ids']) ? $input['ids'] : null;
$message = is_array($input) && isset($input['message']) ? trim((string)$input['message']) : '';
if (!is_array($ids) || count($ids) < 1 || count($ids) > 100 || $message === '' || strlen($message) > 2000) {
    reply(400, array('success' => false, 'message' => '대상은 1~100명, 내용은 2000바이트 이하로 입력해주세요.'));
}
foreach ($ids as $id) {
    if (!is_int($id) || $id < 1) reply(400, array('success' => false, 'message' => '고객 ID가 올바르지 않습니다.'));
}
$ids = array_values(array_unique($ids));
if (!function_exists('curl_init')) reply(500, array('success' => false, 'message' => '서버에 PHP cURL이 필요합니다.'));
$apiKey = getenv('SOLAPI_API_KEY');
$apiSecret = getenv('SOLAPI_API_SECRET');
$sender = preg_replace('/\D/', '', (string)getenv('SOLAPI_SENDER'));
if (!$apiKey || !$apiSecret || !$sender || !getenv('KLASS_DB_USER') || !getenv('KLASS_DB_NAME')) {
    reply(503, array('success' => false, 'message' => '서버의 DB·솔라피 설정이 완료되지 않았습니다.'));
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn = new mysqli(getenv('KLASS_DB_HOST') ?: 'localhost', getenv('KLASS_DB_USER'), getenv('KLASS_DB_PASSWORD'), getenv('KLASS_DB_NAME'));
    $conn->set_charset('utf8mb4');
    $stmt = $conn->prepare('SELECT phone FROM inner_dblist WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $numbers = array();
    foreach ($ids as $id) {
        $stmt->execute();
        $stmt->bind_result($phone);
        if ($stmt->fetch()) {
            $number = preg_replace('/\D/', '', (string)$phone);
            if (preg_match('/^01[016789][0-9]{7,8}$/', $number)) $numbers[$number] = true;
        }
        $stmt->free_result();
    }
    if (!$numbers) reply(400, array('success' => false, 'message' => '발송 가능한 휴대폰번호가 없습니다.'));

    $messages = array();
    foreach (array_keys($numbers) as $number) {
        $messages[] = array('from' => $sender, 'to' => $number, 'text' => $message);
    }
    $date = gmdate('Y-m-d\TH:i:s\Z');
    $salt = bin2hex(random_bytes(16));
    $signature = hash_hmac('sha256', $date . $salt, $apiSecret);
    $auth = "HMAC-SHA256 apiKey={$apiKey}, date={$date}, salt={$salt}, signature={$signature}";
    $ch = curl_init('https://api.solapi.com/messages/v4/send-many/detail');
    curl_setopt_array($ch, array(
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array('Authorization: ' . $auth, 'Content-Type: application/json'),
        CURLOPT_POSTFIELDS => json_encode(array('messages' => $messages, 'allowDuplicates' => false), JSON_UNESCAPED_UNICODE),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30
    ));
    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($raw === false) {
        error_log('solapi connection: ' . curl_error($ch));
        reply(502, array('success' => false, 'message' => '발송 요청 결과를 확인할 수 없습니다. 재시도 전에 솔라피 발송 내역을 확인해주세요.'));
    }
    $result = json_decode($raw, true);
    if ($httpCode < 200 || $httpCode >= 300 || !is_array($result)) {
        error_log('solapi HTTP ' . $httpCode . ': ' . substr($raw, 0, 1000));
        reply(502, array('success' => false, 'message' => '솔라피가 요청을 거절했습니다. 관리자에게 발송 내역 확인을 요청해주세요.'));
    }
    $count = isset($result['groupInfo']['count']) ? $result['groupInfo']['count'] : array();
    $accepted = isset($count['registeredSuccess']) ? (int)$count['registeredSuccess'] : 0;
    $failed = isset($count['registeredFailed']) ? (int)$count['registeredFailed'] : count(isset($result['failedMessageList']) ? $result['failedMessageList'] : array());
    $groupId = isset($result['groupInfo']['groupId']) ? $result['groupInfo']['groupId'] : null;
    reply(200, array('success' => true, 'accepted' => $accepted, 'failed' => $failed,
        'skipped' => count($ids) - count($numbers), 'groupId' => $groupId,
        'message' => "발송 접수 {$accepted}건, 접수 실패 {$failed}건입니다. 최종 발송 결과는 솔라피에서 확인해주세요."));
} catch (Exception $e) {
    error_log('send_sms: ' . $e->getMessage());
    reply(500, array('success' => false, 'message' => '서버 오류입니다. 발송 내역을 확인한 뒤 문의해주세요.'));
}
