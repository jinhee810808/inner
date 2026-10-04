<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors','0');
require __DIR__ . '/user_activity.php';
try {
    activitySession();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(array('success'=>false)); exit; }
    if (empty($_SESSION['inner_user_id'])) { http_response_code(401); echo json_encode(array('success'=>false)); exit; }
    $db=activityDb(); activityTouch($db); $db->close();
    echo json_encode(array('success'=>true));
} catch (Throwable $e) {
    error_log('heartbeat: '.$e->getMessage()); http_response_code(500);
    echo json_encode(array('success'=>false));
}
