<?php
// 저장 위치: inner/api/update_shipment_manager.php (config.php와 같은 폴더)
// login.php와 같은 세션 설정 및 로그인 아이디를 사용합니다.
declare(strict_types=1);
require_once __DIR__ . '/user_activity.php';
activitySession();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function reply(int $status, array $data): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') reply(405, ['success'=>false, 'message'=>'POST 요청만 가능합니다.']);
if (empty($_SESSION['inner_user_id'])) reply(401, ['success'=>false, 'message'=>'로그인이 필요합니다. 다시 로그인해주세요.']);
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) reply(415, ['success'=>false, 'message'=>'JSON 요청만 가능합니다.']);
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $origin = parse_url($_SERVER['HTTP_ORIGIN']);
    $host = ($origin['host'] ?? '') . (isset($origin['port']) ? ':' . $origin['port'] : '');
    if (strcasecmp($host, $_SERVER['HTTP_HOST'] ?? '') !== 0) reply(403, ['success'=>false, 'message'=>'허용되지 않은 요청입니다.']);
}
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || !isset($input['ids'], $input['manager_id']) || !is_array($input['ids']) || !is_string($input['manager_id'])) reply(400, ['success'=>false, 'message'=>'요청 형식이 올바르지 않습니다.']);
$managerId = trim($input['manager_id']);
if ($managerId === '' || strlen($managerId) > 200 || count($input['ids']) < 1 || count($input['ids']) > 1000) reply(400, ['success'=>false, 'message'=>'담당자와 선택 행을 확인해주세요. 한 번에 최대 1000건 변경 가능합니다.']);
$ids = [];
foreach ($input['ids'] as $id) {
    if ((!is_string($id) && !is_int($id)) || !preg_match('/^[1-9][0-9]{0,19}$/', (string)$id)) reply(400, ['success'=>false, 'message'=>'출고 ID가 올바르지 않습니다.']);
    $ids[] = (string)$id;
}
$ids = array_values(array_unique($ids));
$pdo = null;
try {
    $config = require __DIR__ . '/config.php';
    $pdo = new PDO('mysql:host='.$config['db_host'].';dbname='.$config['db_name'].';charset=utf8mb4', $config['db_user'], $config['db_password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT user_id FROM inner_user WHERE user_id = ?');
    $stmt->execute([(string)$_SESSION['inner_user_id']]);
    if (!$stmt->fetchColumn()) { $pdo->rollBack(); reply(403, ['success'=>false, 'message'=>'로그인 사용자를 확인할 수 없습니다.']); }
    $stmt = $pdo->prepare('SELECT user_id, name FROM inner_user WHERE user_id = ? LOCK IN SHARE MODE');
    $stmt->execute([$managerId]);
    $manager = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$manager) { $pdo->rollBack(); reply(400, ['success'=>false, 'message'=>'선택한 담당자가 존재하지 않습니다.']); }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare('SELECT id FROM inner_shipments WHERE id IN ('.$placeholders.') ORDER BY id FOR UPDATE');
    $stmt->execute($ids);
    if (count($stmt->fetchAll(PDO::FETCH_COLUMN)) !== count($ids)) { $pdo->rollBack(); reply(409, ['success'=>false, 'message'=>'선택한 출고 중 삭제된 항목이 있습니다. 새로고침 후 다시 선택해주세요.']); }
    $stmt = $pdo->prepare('UPDATE inner_shipments SET manager_id = ?, manager = ?, updated_at = CURRENT_TIMESTAMP WHERE id IN ('.$placeholders.')');
    $stmt->execute(array_merge([$manager['user_id'], $manager['name']], $ids));
    // 담당자 변경 이력: 출고 한 건마다 한 행 저장
    $history = $pdo->prepare('INSERT INTO inner_shipment_managers (shipment_id, manager_user_id, manager_name, changed_by) VALUES (?, ?, ?, ?)');
    foreach ($ids as $shipmentId) {
        $history->execute([$shipmentId, $manager['user_id'], $manager['name'], (string)$_SESSION['inner_user_id']]);
    }
    $pdo->commit();
    reply(200, ['success'=>true, 'manager_id'=>(string)$manager['user_id'], 'manager'=>$manager['name'], 'updated_count'=>count($ids)]);
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Shipment manager update: '.$e->getMessage());
    reply(500, ['success'=>false, 'message'=>'담당자 저장에 실패했습니다. 서버 로그와 config.php 설정을 확인해주세요.']);
}
