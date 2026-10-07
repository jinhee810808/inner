<?php
// 저장 위치: inner/api/get_shipment_manager_history.php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors', '0');
require_once __DIR__ . '/user_activity.php';
function reply($status, $data) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
try {
    activitySession();
    if (empty($_SESSION['inner_user_id'])) reply(401, array('success'=>false, 'message'=>'로그인이 필요합니다.'));
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') reply(405, array('success'=>false, 'message'=>'GET 요청만 가능합니다.'));
    $id = $_GET['shipment_id'] ?? '';
    if (!is_string($id) || !preg_match('/^[1-9][0-9]{0,19}$/', $id)) reply(400, array('success'=>false, 'message'=>'출고 ID가 올바르지 않습니다.'));
    $db = activityDb();
    $stmt = $db->prepare('
        SELECT 
            h.id, h.manager_user_id, h.manager_name, h.assigned_at, h.changed_by, u.name AS changed_by_name 
        FROM inner_shipment_managers AS h 
        LEFT JOIN inner_user AS u 
            ON u.user_id COLLATE utf8mb4_unicode_ci 
               = h.changed_by COLLATE utf8mb4_unicode_ci 
        WHERE h.shipment_id = ? 
        ORDER BY h.assigned_at DESC, h.id DESC
    ');
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $stmt->bind_result($historyId, $managerId, $managerName, $assignedAt, $changedBy, $changedByName);
    $history = array();
    while ($stmt->fetch()) {
        $history[] = array('id'=>(string)$historyId, 'manager_user_id'=>$managerId, 'manager_name'=>$managerName, 'assigned_at'=>$assignedAt, 'changed_by'=>$changedBy, 'changed_by_name'=>$changedByName);
    }
    $stmt->close();
    reply(200, array('success'=>true, 'history'=>$history));
} catch (Throwable $e) {
    error_log('shipment manager history: '.$e->getMessage());
    // reply(500, array('success'=>false, 'message'=>'담당자 이력 조회에 실패했습니다. 서버 로그를 확인해주세요.'));
    reply(500, array(
    'success' => false,
    'message' => '담당자 이력 조회 오류: ' . $e->getMessage()
    ));
}
