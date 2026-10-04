<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors','0');
require __DIR__ . '/user_activity.php';
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); header('Allow: POST');
        echo json_encode(array('success'=>false,'message'=>'POST 요청만 허용됩니다.'),JSON_UNESCAPED_UNICODE); exit;
    }
    activitySession(); activityLogout();
    echo json_encode(array('success'=>true));
} catch (Throwable $e) {
    error_log('logout: '.$e->getMessage()); http_response_code(500);
    echo json_encode(array('success'=>false,'message'=>'서버 로그아웃에 실패했습니다.'),JSON_UNESCAPED_UNICODE);
}
