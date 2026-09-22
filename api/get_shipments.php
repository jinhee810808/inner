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
            id,
            seq_no AS no,
            capital,
            contract_type AS type,
            company_name AS company,
            representative AS ceo,
            phone,
            origin_type AS origin,
            maker,
            model,
            IFNULL(model_modifier, '') AS modifier,
            car_price AS price,
            lease_period AS period,
            release_date AS releaseDate,
            return_date AS returnDate,
            ag_rate AS ag,
            ag_fee AS agFee,
            IFNULL(manager2, '') AS manager2
        FROM inner_shipments
        ORDER BY release_date DESC, seq_no DESC
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