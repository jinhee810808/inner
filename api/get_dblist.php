<?php
header('Content-Type: application/json; charset=utf-8');

// ==========================================
// inner_dblist 조회 API
// ==========================================

$host = 'localhost';
$user = 'charm3007';
$password = 'wlsgml8808';
$dbname = 'charm3007';

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'message' => 'DB 접속 실패: ' . $conn->connect_error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$conn->set_charset('utf8mb4');

// 실제 inner_dblist 컬럼명 기준
$sql = "
    SELECT
        id,
        phone,
        customer_name,
        `year`,
        `month`,
        ask_date,
        domestic_import,
        maker,
        car_name,
        desired_car,
        source2,
        source,
        rent_lease,
        manager,
        delivery_status,
        memo
    FROM inner_dblist
    ORDER BY ask_date DESC
";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode([
        'success' => false,
        'message' => 'SQL 오류: ' . $conn->error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = [
        'id'            => (int)$row['id'],
        'phone'         => $row['phone'],
        'name'          => $row['customer_name'],
        'year'          => $row['year'],
        'month'         => $row['month'],
        'askDate'       => $row['ask_date'],
        'origin'        => $row['domestic_import'],
        'maker'         => $row['maker'],
        'carName'       => $row['car_name'],
        'hopeCar'       => $row['desired_car'],
        'source2'       => $row['source2'],
        'source'        => $row['source'],
        'rentLease'     => $row['rent_lease'],
        'consultant'    => $row['manager'],
        'releaseStatus' => $row['delivery_status'],
        'memo'          => $row['memo']
    ];
}

echo json_encode([
    'success' => true,
    'data' => $data
], JSON_UNESCAPED_UNICODE);

$conn->close();
?>
