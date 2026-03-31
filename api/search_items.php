<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "bmm_system";

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8mb4");

header('Content-Type: application/json; charset=utf-8');

$sql = "SELECT item_id, item_code, item_name, unit, cost_price, selling_price, quantity 
        FROM store_items 
        ORDER BY item_name ASC 
        LIMIT 50";

$result = $conn->query($sql);

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = [
        'id' => (int)$row['item_id'],
        'code' => $row['item_code'],
        'name' => $row['item_name'],
        'unit' => $row['unit'],
        'cost_price' => (float)$row['cost_price'],
        'selling_price' => (float)$row['selling_price'],
        'quantity' => (float)$row['quantity']
    ];
}

echo json_encode($items, JSON_UNESCAPED_UNICODE);
?>