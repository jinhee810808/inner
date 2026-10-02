<?php
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST 요청만 가능합니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '입력 형식이 올바르지 않습니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$name = trim((string)(isset($input['name']) ? $input['name'] : ''));
$askDate = trim((string)(isset($input['askDate']) ? $input['askDate'] : ''));
if ($name === '' || ($askDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $askDate))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '고객명과 접수일을 확인해주세요.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($askDate !== '') {
    list($year, $month, $day) = array_map('intval', explode('-', $askDate));
    if (!checkdate($month, $day, $year)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '접수일이 올바르지 않습니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
} else {
    $askDate = null;
}

$host = 'localhost';
$user = 'charm3007';
$password = 'wlsgml8808';
$dbname = 'charm3007';

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn = new mysqli($host, $user, $password, $dbname);
    $conn->set_charset('utf8mb4');
    $sql = 'INSERT INTO inner_dblist
        (phone, customer_name, ask_date, maker, car_name, desired_car,
         source, rent_lease, manager, delivery_status, memo)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $stmt = $conn->prepare($sql);
    $phone = trim((string)(isset($input['phone']) ? $input['phone'] : '')) ?: null;
    $maker = trim((string)(isset($input['maker']) ? $input['maker'] : '')) ?: null;
    $carName = trim((string)(isset($input['carName']) ? $input['carName'] : '')) ?: null;
    $hopeCar = trim((string)(isset($input['hopeCar']) ? $input['hopeCar'] : '')) ?: null;
    $source = trim((string)(isset($input['source']) ? $input['source'] : '')) ?: null;
    $rentLease = trim((string)(isset($input['rentLease']) ? $input['rentLease'] : '')) ?: null;
    $consultant = trim((string)(isset($input['consultant']) ? $input['consultant'] : '')) ?: null;
    $releaseStatus = trim((string)(isset($input['releaseStatus']) ? $input['releaseStatus'] : '')) ?: null;
    $memo = '';
    $stmt->bind_param('sssssssssss', $phone, $name, $askDate,
        $maker, $carName, $hopeCar, $source, $rentLease,
        $consultant, $releaseStatus, $memo);
    $stmt->execute();
    echo json_encode(['success' => true, 'id' => $conn->insert_id], JSON_UNESCAPED_UNICODE);
} catch (Exception $error) {
    error_log('create_dblist: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB 저장에 실패했습니다: ' . $error->getMessage()], JSON_UNESCAPED_UNICODE);
}

?>