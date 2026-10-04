<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors', '0');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(0, '/', '', !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', true);

function passwordReply($status, $body) {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        passwordReply(405, array('success'=>false, 'message'=>'POST 요청만 허용됩니다.'));
    }
    if (!session_start()) throw new RuntimeException('세션 시작 실패');
    $id = isset($_SESSION['inner_user_id']) ? (string)$_SESSION['inner_user_id'] : '';
    if ($id === '') passwordReply(401, array('success'=>false, 'message'=>'다시 로그인해주세요.'));
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) passwordReply(400, array('success'=>false, 'message'=>'요청 형식이 올바르지 않습니다.'));
    $token = isset($input['csrfToken']) && is_string($input['csrfToken']) ? $input['csrfToken'] : '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        passwordReply(403, array('success'=>false, 'message'=>'인증 정보가 만료되었습니다. 다시 로그인해주세요.'));
    }
    $current = isset($input['currentPassword']) && is_string($input['currentPassword']) ? $input['currentPassword'] : '';
    $new = isset($input['newPassword']) && is_string($input['newPassword']) ? $input['newPassword'] : '';
    $confirm = isset($input['confirmPassword']) && is_string($input['confirmPassword']) ? $input['confirmPassword'] : '';
    if ($current === '' || $new === '' || $confirm === '') passwordReply(400, array('success'=>false,'message'=>'비밀번호를 모두 입력해주세요.'));
    if ($new !== $confirm) passwordReply(400,array('success'=>false,'message'=>'새 비밀번호와 확인 값이 다릅니다.'));
    // bcrypt는 최대 72바이트까지 처리합니다. 공백은 비밀번호의 일부로 유지합니다.
    if (strlen($new)<8 || strlen($new)>72 || strpos($new, "\0")!==false) {
        passwordReply(400,array('success'=>false,'message'=>'새 비밀번호는 8~72바이트로 입력해주세요. 한글은 한 글자가 여러 바이트입니다.'));
    }
    if ($current === $new) passwordReply(400,array('success'=>false,'message'=>'현재와 다른 새 비밀번호를 입력해주세요.'));
    $attempts = isset($_SESSION['password_attempts']) ? $_SESSION['password_attempts'] : array('count'=>0,'start'=>time());
    if (time()-$attempts['start']>=300) $attempts=array('count'=>0,'start'=>time());
    if ($attempts['count']>=5) passwordReply(429,array('success'=>false,'message'=>'현재 비밀번호 확인에 여러 번 실패했습니다. 잠시 후 다시 시도해주세요.'));
    $config = require __DIR__ . '/config.php';
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn = new mysqli($config['db_host'],$config['db_user'],$config['db_password'],$config['db_name']);
    $conn->set_charset('utf8mb4');
    $stmt=$conn->prepare('SELECT password_hash FROM inner_user WHERE user_id=? LIMIT 1');
    $stmt->bind_param('s',$id); $stmt->execute(); $stmt->bind_result($oldHash); $found=$stmt->fetch(); $stmt->close();
    if (!$found || !password_verify($current,$oldHash)) {
        $attempts['count']++;
        $_SESSION['password_attempts']=$attempts;
        passwordReply(400,array('success'=>false,'message'=>'현재 비밀번호가 올바르지 않습니다.'));
    }
    $newHash=password_hash($new,PASSWORD_BCRYPT);
    if ($newHash===false) throw new RuntimeException('해시 생성 실패');
    // 동시에 다른 요청이 비밀번호를 변경했다면 덮어쓰지 않습니다.
    $stmt=$conn->prepare('UPDATE inner_user SET password_hash=? WHERE user_id=? AND password_hash=?');
    $stmt->bind_param('sss',$newHash,$id,$oldHash); $stmt->execute(); $changed=$stmt->affected_rows; $stmt->close(); $conn->close();
    if ($changed!==1) passwordReply(409,array('success'=>false,'message'=>'계정 정보가 변경되었습니다. 다시 시도해주세요.'));
    unset($_SESSION['password_attempts']);
    passwordReply(200,array('success'=>true,'message'=>'비밀번호가 변경되었습니다. 다음 로그인부터 새 비밀번호를 사용해주세요.'));
} catch (Throwable $e) {
    error_log('change_password: '.$e->getMessage());
    passwordReply(500,array('success'=>false,'message'=>'비밀번호 변경 중 서버 오류가 발생했습니다.'));
}
