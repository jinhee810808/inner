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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (empty($_SESSION['inner_user_id'])) reply(401, array('success' => false, 'message' => '로그인이 필요합니다.'));
    reply(200, array('success' => true, 'name' => $_SESSION['inner_user_name'], 'csrfToken' => $_SESSION['csrf_token']));
}
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $_SESSION = array();
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    reply(200, array('success' => true));
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') reply(405, array('success' => false, 'message' => '허용되지 않은 요청입니다.'));

$input = json_decode(file_get_contents('php://input'), true);
$userId = is_array($input) && isset($input['userId']) ? trim((string)$input['userId']) : '';
$password = is_array($input) && isset($input['password']) ? (string)$input['password'] : '';
if ($userId === '' || $password === '') reply(400, array('success' => false, 'message' => '아이디와 비밀번호를 입력해주세요.'));

if (!is_file(__DIR__ . '/config.php')) reply(503, array('success' => false, 'message' => 'api/config.php 파일이 없습니다. DB 접속값을 설정해주세요.'));
$config = require __DIR__ . '/config.php';
if (empty($config['db_user']) || empty($config['db_name'])) reply(503, array('success' => false, 'message' => 'api/config.php의 DB 접속값을 입력해주세요.'));
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn = new mysqli($config['db_host'], $config['db_user'], $config['db_password'], $config['db_name']);
    $conn->set_charset('utf8mb4');
    $stmt = $conn->prepare('SELECT user_id, password_hash, name FROM inner_user WHERE user_id = ? LIMIT 1');
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $stmt->bind_result($foundId, $hash, $name);
    $found = $stmt->fetch();
    if (!$found || !password_verify($password, $hash)) {
        reply(401, array('success' => false, 'message' => '아이디 또는 비밀번호가 올바르지 않습니다.'));
    }
    session_regenerate_id(true);
    $_SESSION['inner_user_id'] = $foundId;
    $_SESSION['inner_user_name'] = $name;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    reply(200, array('success' => true, 'name' => $name, 'csrfToken' => $_SESSION['csrf_token']));
} catch (Exception $e) {
    error_log('inner login: ' . $e->getMessage());
    reply(500, array('success' => false, 'message' => '로그인 서버 오류입니다.'));
}
