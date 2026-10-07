<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $config = require __DIR__ . '/config.php';

    $host = $config['db_host'];
    $name = $config['db_name'];
    $port = isset($config['db_port']) ? (int)$config['db_port'] : 3306;

    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $config['db_user'],
        $config['db_password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    $sql = "
        SELECT id, origin_type, maker, model, sorted, shape
        FROM inner_car_model
        WHERE use_yn = 'y'
        ORDER BY maker, sorted IS NULL, sorted, id
    ";

    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $rows
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('get_car_models: ' . $e->getMessage());
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => '차량 목록 조회에 실패했습니다. DB 설정과 테이블을 확인해주세요.'
    ], JSON_UNESCAPED_UNICODE);
}