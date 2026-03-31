<?php
// config.php
$servername = "localhost";
$username = "root";
$password = ""; // فارغة في XAMPP
$dbname = "bmm_system";

// إنشاء الاتصال
$conn = new mysqli($servername, $username, $password, $dbname);

// التحقق من الاتصال
if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
}

// تعيين الترميز UTF-8 للدعم العربي
$conn->set_charset("utf8mb4");

// دالة للحماية من SQL Injection
function sanitize($data) {
    global $conn;
    return mysqli_real_escape_string($conn, htmlspecialchars(trim($data)));
}

// دالة لإعادة صياغة التاريخ
function formatDate($date) {
    return date('Y-m-d', strtotime($date));
}


?>


































