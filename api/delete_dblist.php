<?php
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST 요청만 가능합니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$ids = is_array($input) && isset($input['ids']) ? $input['ids'] : null;
if (!is_array($ids) || count($ids) < 1 || count($ids) > 500) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '삭제할 항목을 1~500건 선택해주세요.'], JSON_UNESCAPED_UNICODE);
    exit;
}
foreach ($ids as $id) {
    if (!is_int($id) || $id < 1) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID 형식이 올바르지 않습니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
$ids = array_values(array_unique($ids));

$host = 'localhost';
$user = 'charm3007';
$password = 'wlsgml8808';
$dbname = 'charm3007';

$conn = null;
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn = new mysqli($host, $user, $password, $dbname);
    $conn->set_charset('utf8mb4');
    $conn->begin_transaction();
    $stmt = $conn->prepare('DELETE FROM inner_dblist WHERE id = ?');
    $stmt->bind_param('i', $id);
    $deletedCount = 0;
    foreach ($ids as $id) {
        $stmt->execute();
        $deletedCount += $stmt->affected_rows;
    }
    $conn->commit();
    echo json_encode(['success' => true, 'deletedCount' => $deletedCount], JSON_UNESCAPED_UNICODE);
} catch (Exception $error) {
    if ($conn !== null) {
        $conn->rollback();
    }
    error_log('delete_dblist: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB 삭제에 실패했습니다. 서버 오류 로그를 확인해주세요.'], JSON_UNESCAPED_UNICODE);
}
