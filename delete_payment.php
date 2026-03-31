<?php
session_start();
include "config.php";

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: logout.php");
    exit();
}

// التحقق من وجود معرف السند
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['alert'] = [
        'type' => 'error',
        'message' => "❌ معرف سند القبض غير موجود"
    ];
    header("Location: customers.php");
    exit();
}

$payment_id = intval($_GET['id']);
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

// التحقق من وجود السند
$check = $conn->query("SELECT payment_number, customer_id FROM customer_payments WHERE payment_id = $payment_id");
if (!$check || $check->num_rows === 0) {
    $_SESSION['alert'] = [
        'type' => 'error',
        'message' => "❌ سند القبض غير موجود"
    ];
    
    if ($customer_id > 0) {
        header("Location: view_customer.php?id=" . $customer_id);
    } else {
        header("Location: customers.php");
    }
    exit();
}

$payment = $check->fetch_assoc();
$payment_number = $payment['payment_number'];
$customer_id_from_db = $payment['customer_id'];

// حذف السند
$delete = $conn->query("DELETE FROM customer_payments WHERE payment_id = $payment_id");

if ($delete) {
    $_SESSION['alert'] = [
        'type' => 'success',
        'message' => "✅ تم حذف سند القبض رقم <strong>$payment_number</strong> بنجاح"
    ];
} else {
    $_SESSION['alert'] = [
        'type' => 'error',
        'message' => "❌ حدث خطأ أثناء حذف السند: " . $conn->error
    ];
}

// التوجيه إلى صفحة العميل
if ($customer_id > 0) {
    header("Location: view_customer.php?id=" . $customer_id);
} elseif ($customer_id_from_db > 0) {
    header("Location: view_customer.php?id=" . $customer_id_from_db);
} else {
    header("Location: customers.php");
}
exit();
?>