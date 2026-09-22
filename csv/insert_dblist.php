<?php
// ==========================================
// dblist.csv → MySQL INSERT
// ==========================================

// DB 접속정보
$host = 'localhost';
$user = 'charm3007';
$password = 'wlsgml8808';
$dbname = 'charm3007';

// CSV 파일 경로
$csv_file = __DIR__ . '/dblist.csv';

// ------------------------------------------
// MySQL 접속
// ------------------------------------------
$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die('DB 접속 실패: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

// ------------------------------------------
// CSV 파일 확인
// ------------------------------------------
if (!file_exists($csv_file)) {
    die('CSV 파일을 찾을 수 없습니다: ' . $csv_file);
}

$fp = fopen($csv_file, 'r');

if (!$fp) {
    die('CSV 파일을 열 수 없습니다.');
}

// ------------------------------------------
// 첫 번째 줄 = CSV 제목행
// ------------------------------------------
$header = fgetcsv($fp);

$count = 0;
$error_count = 0;

// ------------------------------------------
// INSERT SQL
// ------------------------------------------
$sql = "
    INSERT INTO inner_dblist (
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
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die('SQL 준비 실패: ' . $conn->error);
}

// ------------------------------------------
// CSV 한 줄씩 읽기
// ------------------------------------------
while (($row = fgetcsv($fp)) !== false) {

    // 빈 줄 무시
    if (count($row) < 14) {
        continue;
    }

    // CSV 데이터
    $phone            = trim($row[0]);
    $customer_name    = trim($row[1]);
    $year             = trim($row[2]);
    $month            = trim($row[3]);
    $ask_date         = trim($row[4]);
    $domestic_import  = trim($row[5]);
    $maker            = trim($row[6]);
    $car_name         = trim($row[7]);
    $desired_car      = trim($row[8]);
    $source2          = trim($row[9]);
    $source           = trim($row[10]);
    $rent_lease       = trim($row[11]);
    $manager          = trim($row[12]);
    $delivery_status  = trim($row[13]);

    // memo는 CSV에 없으므로 빈 값
    $memo = '';

    // 숫자값 처리
    $year  = ($year !== '') ? (int)$year : null;
    $month = ($month !== '') ? (int)$month : null;

    // NULL 처리
    $phone           = ($phone === '') ? null : $phone;
    $customer_name   = ($customer_name === '') ? null : $customer_name;
    $ask_date        = ($ask_date === '') ? null : $ask_date;
    $domestic_import = ($domestic_import === '') ? null : $domestic_import;
    $maker           = ($maker === '') ? null : $maker;
    $car_name        = ($car_name === '') ? null : $car_name;
    $desired_car     = ($desired_car === '') ? null : $desired_car;
    $source2         = ($source2 === '') ? null : $source2;
    $source          = ($source === '') ? null : $source;
    $rent_lease      = ($rent_lease === '') ? null : $rent_lease;
    $manager         = ($manager === '') ? null : $manager;
    $delivery_status = ($delivery_status === '') ? null : $delivery_status;

    // INSERT
    $stmt->bind_param(
        "ssiisssssssssss",
        $phone,
        $customer_name,
        $year,
        $month,
        $ask_date,
        $domestic_import,
        $maker,
        $car_name,
        $desired_car,
        $source2,
        $source,
        $rent_lease,
        $manager,
        $delivery_status,
        $memo
    );

    if ($stmt->execute()) {
        $count++;
    } else {
        $error_count++;
        echo "INSERT 오류: " . $stmt->error . "<br>";
    }
}

// ------------------------------------------
// 종료
// ------------------------------------------
$stmt->close();
fclose($fp);
$conn->close();

echo "<hr>";
echo "데이터 입력 완료<br>";
echo "정상 입력: {$count}건<br>";
echo "오류: {$error_count}건<br>";
?>
```
