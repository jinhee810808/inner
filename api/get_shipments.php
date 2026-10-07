<?php
header('Content-Type: application/json; charset=utf-8');

// 1. DB 접속 정보
$db_host = 'localhost';
$db_name = 'charm3007';
$db_user = 'charm3007';
$db_pass = 'wlsgml8808';

try {
    $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // 2. 전체 출고목록 조회 (순번 기준 정렬)
    $stmt = $pdo->query("
        SELECT
            s.id,
            s.seq_no AS no,
            s.capital,
            s.contract_type AS type,
            s.company_name AS company,
            s.representative AS ceo,
            s.phone,
            s.origin_type AS origin,
            s.maker,
            s.model,
            IFNULL(s.model_modifier, '') AS modifier,
            s.car_price AS price,
            s.lease_period AS period,
            s.release_date AS releaseDate,
            s.return_date AS returnDate,
            s.ag_rate AS ag,
            s.ag_fee AS agFee,
            COALESCE(h.manager_user_id, s.manager_id, '') AS manager_id,
            COALESCE(h.manager_name, s.manager, '') AS manager
        FROM inner_shipments AS s
        LEFT JOIN inner_shipment_managers AS h
            ON h.id = (
                SELECT m.id
                FROM inner_shipment_managers AS m
                WHERE m.shipment_id = s.id
                ORDER BY m.assigned_at DESC, m.id DESC
                LIMIT 1
            )
        ORDER BY s.release_date DESC, s.seq_no DESC;
    ");

    $data = $stmt->fetchAll();

    echo json_encode([
        'status' => 'success',
        'count'  => count($data),
        'data'   => $data
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'DB 조회 실패: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>