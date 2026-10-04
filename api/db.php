<?php
// CORS 설정: 외부 브라우저 통신 및 한글 깨짐 방지
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// OPTIONS 요청(Preflight) 대응
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Cafe24 MySQL 접속 정보 (대표님 계정 정보 적용)
$host = "localhost"; 
$db_user = "charm3007";     
$db_pass = "wlsgml8808"; 
$db_name = "charm3007";     

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "DB 연결 실패: " . $e->getMessage()]);
    exit;
}
?>