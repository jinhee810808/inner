<?php
// 공통 DB/세션 처리. 기존 api/config.php를 그대로 사용합니다.
function activitySession() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params(0, '/', '', !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', true);
        if (!session_start()) throw new RuntimeException('세션 시작 실패');
    }
}
function activityDb() {
    $path = __DIR__ . '/config.php';
    if (!is_file($path)) throw new RuntimeException('api/config.php 파일이 없습니다.');
    $c = require $path;
    if (!is_array($c) || empty($c['db_user']) || empty($c['db_name'])) throw new RuntimeException('config.php DB 설정 오류');
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli($c['db_host'], $c['db_user'], $c['db_password'], $c['db_name']);
    $db->set_charset('utf8mb4');
    $db->query("SET time_zone = '+09:00'");
    return $db;
}
function activityHash() { return hash('sha256', session_id()); }
function activityLog($db, $id, $name, $result) {
    // 비밀번호, 세션 ID, CSRF 토큰은 이력에 저장하지 않습니다.
    $ip = substr(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '', 0, 45);
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    $device = preg_match('/Mobile|Android|iPhone|iPad/i', $ua) ? '모바일' : 'PC';
    if (stripos($ua, 'Edg/') !== false) $device .= ' · Edge';
    elseif (stripos($ua, 'Chrome/') !== false) $device .= ' · Chrome';
    elseif (stripos($ua, 'Firefox/') !== false) $device .= ' · Firefox';
    elseif (stripos($ua, 'Safari/') !== false) $device .= ' · Safari';
    else $device .= ' · 기타';
    $stmt = $db->prepare('INSERT INTO inner_login_log (user_id,name,result,logged_at,ip,device) VALUES (?,?,?,NOW(),?,?)');
    $stmt->bind_param('sssss', $id, $name, $result, $ip, $device);
    $stmt->execute(); $stmt->close();
}
function activityTouch($db) {
    if (empty($_SESSION['inner_user_id'])) return;
    $hash = activityHash(); $id = (string)$_SESSION['inner_user_id'];
    // 기존 로그인 세션도 heartbeat부터 추적합니다. 과거 로그인 이력은 만들지 않습니다.
    $stmt = $db->prepare('INSERT INTO inner_user_session (session_hash,user_id,logged_at,last_seen) VALUES (?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE last_seen=IF(ended_at IS NULL,NOW(),last_seen)');
    $stmt->bind_param('ss', $hash, $id); $stmt->execute(); $stmt->close();
}
function activityEnd($db) {
    $hash = activityHash();
    $stmt = $db->prepare('UPDATE inner_user_session SET ended_at=NOW() WHERE session_hash=? AND ended_at IS NULL');
    $stmt->bind_param('s', $hash); $stmt->execute(); $stmt->close();
}
function activityLogout() {
    // 추적 DB 장애가 있어도 PHP 로그인 세션은 종료합니다.
    try { $db = activityDb(); activityEnd($db); $db->close(); }
    catch (Throwable $e) { error_log('logout tracking: ' . $e->getMessage()); }
    $_SESSION = array();
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    if (!session_destroy()) throw new RuntimeException('세션 종료 실패');
}
