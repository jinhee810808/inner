<?php
// 설치 경로: inner/api/get_shipment_contact_history.php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function fail_history($code, $message) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/user_activity.php';
activitySession();

$userId = isset($_SESSION['inner_user_id'])
    ? (string)$_SESSION['inner_user_id']
    : '';

$grade = isset($_SESSION['grade'])
    ? trim((string)$_SESSION['grade'])
    : '';

if ($userId === '') {
    fail_history(401, '로그인이 필요합니다.');
}

session_write_close();

$id = filter_input(INPUT_GET, 'shipment_id', FILTER_VALIDATE_INT);
$type = isset($_GET['type'])
    ? $_GET['type']
    : '';
if (!$id || $id < 1 || !in_array($type, ['call', 'sms'], true)) {
    fail_history(400, '출고 ID 또는 이력 구분이 올바르지 않습니다.');
}

try {
    $config = require __DIR__ . '/config.php';
    $pdo = new PDO(
        'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4',
        $config['db_user'], $config['db_password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    // 출고의 현재 담당자를 확인합니다. 일반 사용자는 본인 담당 건만 조회합니다.
    $stmt = $pdo->prepare("SELECT COALESCE(h.manager_user_id, s.manager_id, '') AS manager_id
        FROM inner_shipments s
        LEFT JOIN inner_shipment_managers h ON h.id = (
            SELECT m.id FROM inner_shipment_managers m WHERE m.shipment_id = s.id
            ORDER BY m.assigned_at DESC, m.id DESC LIMIT 1
        ) WHERE s.id = :id");
    $stmt->execute(['id' => $id]);
    $shipment = $stmt->fetch();
    if (!$shipment) fail_history(404, '출고 정보를 찾을 수 없습니다.');
    if ($grade === '일반' && (string)$shipment['manager_id'] !== $userId) {
        fail_history(403, '본인 담당 출고의 이력만 조회할 수 있습니다.');
    }

    if ($type === 'call') {
        $sql = "SELECT id, call_date, call_type, call_content, next_call_date
            FROM inner_call WHERE category = '출고' AND category_id = :id
            ORDER BY call_date DESC, id DESC";
    } else {
        // 상태 컬럼이 아직 없으면 성공을 추정하지 않고 미확인으로 표시합니다.
        $hasStatus = $pdo->query("SHOW COLUMNS FROM inner_sms LIKE 'sms_status'")->fetch();
        $statusColumn = $hasStatus ? 'sms_status' : "'미확인' AS sms_status";
        $sql = "SELECT id, sms_date, sms_content, {$statusColumn}
            FROM inner_sms WHERE category = '출고' AND category_id = :id
            ORDER BY sms_date DESC, id DESC";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    echo json_encode(['success' => true, 'history' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Shipment contact history: ' . $e->getMessage());
    fail_history(500, '이력 조회에 실패했습니다. 서버 로그를 확인해주세요.');
}
