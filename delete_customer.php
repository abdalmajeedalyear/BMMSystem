<?php
session_start();
include "config.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: customers.php");
    exit();
}

$customer_id = intval($_GET['id']);

// التحقق من وجود العميل
$check = $conn->query("SELECT customer_name FROM customers WHERE customer_id = $customer_id");
if ($check->num_rows === 0) {
    $_SESSION['alert'] = [
        'type' => 'error',
        'message' => "❌ العميل غير موجود"
    ];
    header("Location: customers.php");
    exit();
}

$customer = $check->fetch_assoc();

// التحقق من عدم وجود فواتير مرتبطة بالعميل
$invoices = $conn->query("SELECT COUNT(*) as count FROM invoices WHERE customer_id = $customer_id")->fetch_assoc()['count'];

if ($invoices > 0) {
    $_SESSION['alert'] = [
        'type' => 'error',
        'message' => "❌ لا يمكن حذف العميل لأنه لديه $invoices فاتورة مسجلة"
    ];
    header("Location: customers.php");
    exit();
}

// حذف العميل
if ($conn->query("DELETE FROM customers WHERE customer_id = $customer_id")) {
    $_SESSION['alert'] = [
        'type' => 'success',
        'message' => "✅ تم حذف العميل {$customer['customer_name']} بنجاح"
    ];
} else {
    $_SESSION['alert'] = [
        'type' => 'error',
        'message' => "❌ حدث خطأ أثناء الحذف: " . $conn->error
    ];
}

header("Location: customers.php");
exit();
?>