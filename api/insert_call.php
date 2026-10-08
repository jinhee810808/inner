<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors', '0');

require_once __DIR__ . '/user_activity.php';

function callReply($status, $data) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function validCallDate($value) {
    if (!is_string($value) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }

    $parts = explode('-', $value);

    return checkdate(
        (int)$parts[1],
        (int)$parts[2],
        (int)$parts[0]
    );
}

try {
    activitySession();

    if (empty($_SESSION['inner_user_id'])) {
        callReply(401, array(
            'success' => false,
            'message' => '로그인이 필요합니다.'
        ));
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        callReply(405, array(
            'success' => false,
            'message' => 'POST 요청만 가능합니다.'
        ));
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!is_array($data)) {
        callReply(400, array(
            'success' => false,
            'message' => '입력 데이터가 올바르지 않습니다.'
        ));
    }

    $categoryId = isset($data['category_id'])
        && is_scalar($data['category_id'])
        ? (string)$data['category_id'] : '';

    $callDate = isset($data['call_date']) ? $data['call_date'] : '';
    $callType = isset($data['call_type']) ? $data['call_type'] : '';
    $content = isset($data['call_content']) ? $data['call_content'] : '';
    $nextDate = isset($data['next_call_date']) ? $data['next_call_date'] : '';

    $allowedTypes = array(
        '통화성공', '부재', '전원꺼짐', '유효하지않는번호', '기타'
    );

    if (!preg_match('/^[1-9][0-9]*$/', $categoryId) ||
        !validCallDate($callDate) ||
        !in_array($callType, $allowedTypes, true) ||
        !is_string($content) ||
        ($nextDate !== '' && !validCallDate($nextDate))) {

        callReply(400, array(
            'success' => false,
            'message' => '출고 ID, 날짜 또는 통화구분을 확인해주세요.'
        ));
    }

    $managerId = (string)$_SESSION['inner_user_id'];
    $db = activityDb();

    // 로그인 사용자의 이름과 등급 확인
    $stmt = $db->prepare(
        'SELECT name, grade FROM inner_user WHERE user_id = ?'
    );
    $stmt->bind_param('s', $managerId);
    $stmt->execute();
    $stmt->bind_result($managerName, $grade);
    $foundUser = $stmt->fetch();
    $stmt->close();

    if (!$foundUser) {
        callReply(401, array(
            'success' => false,
            'message' => '로그인 사용자 정보를 찾을 수 없습니다.'
        ));
    }

    // 출고 존재 여부 및 현재 담당자 확인
    $stmt = $db->prepare("
        SELECT COALESCE(h.manager_user_id, s.manager_id, '')
        FROM inner_shipments s
        LEFT JOIN inner_shipment_managers h ON h.id = (
            SELECT m.id
            FROM inner_shipment_managers m
            WHERE m.shipment_id = s.id
            ORDER BY m.assigned_at DESC, m.id DESC
            LIMIT 1
        )
        WHERE s.id = ?
    ");

    $stmt->bind_param('s', $categoryId);
    $stmt->execute();
    $stmt->bind_result($assignedManagerId);
    $foundShipment = $stmt->fetch();
    $stmt->close();

    if (!$foundShipment) {
        callReply(404, array(
            'success' => false,
            'message' => '출고 정보를 찾을 수 없습니다.'
        ));
    }

    if (trim($grade) === '일반' &&
        (string)$assignedManagerId !== $managerId) {

        callReply(403, array(
            'success' => false,
            'message' => '본인 담당 출고에만 등록할 수 있습니다.'
        ));
    }

    // DB 컬럼이 DATETIME이므로 날짜를 자정으로 저장
    $callDate .= ' 00:00:00';
    $nextDate = $nextDate === '' ? null : $nextDate . ' 00:00:00';
    $content = trim($content);
    $category = '출고';

    $stmt = $db->prepare('
        INSERT INTO inner_call (
            category_id, call_date, call_type, call_content,
            next_call_date, category, manager_name, manager_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ');

    $stmt->bind_param(
        'ssssssss',
        $categoryId, $callDate, $callType, $content,
        $nextDate, $category, $managerName, $managerId
    );

    if (!$stmt->execute()) {
        throw new Exception('통화이력 저장 실패');
    }

    $stmt->close();

    callReply(200, array('success' => true));

} catch (Exception $e) {
    error_log('insert_call: ' . $e->getMessage());

    callReply(500, array(
        'success' => false,
        'message' => '통화이력 저장에 실패했습니다. 서버 로그를 확인해주세요.'
    ));
}