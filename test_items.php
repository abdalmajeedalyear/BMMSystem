<?php
include "config.php";

$result = $conn->query("SELECT * FROM store_items LIMIT 5");
while ($row = $result->fetch_assoc()) {
    print_r($row);
    echo "<br>";
}
?>