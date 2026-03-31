<?php
session_start();
include "config.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: suppliers.php");
    exit();
}

$supplier_id = intval($_GET['id']);

// التحقق من وجود المورد
$check = $conn->query("SELECT supplier_name FROM suppliers WHERE supplier_id = $supplier_id");
if ($check->num_rows === 0) {
    $_SESSION['alert'] = [
        'type' => 'error',
        'message' => "❌ المورد غير موجود"
    ];
    header("Location: suppliers.php");
    exit();
}

$supplier = $check->fetch_assoc();

// التحقق من وجود مشتريات مرتبطة بالمورد
$purchases = $conn->query("SELECT COUNT(*) as count FROM purchases WHERE supplier_name = '{$supplier['supplier_name']}'")->fetch_assoc()['count'];

if ($purchases > 0) {
    $_SESSION['alert'] = [
        'type' => 'error',
        'message' => "❌ لا يمكن حذف المورد لأنه لديه $purchases فاتورة مشتريات مسجلة"
    ];
    header("Location: suppliers.php");
    exit();
}

// حذف المورد
if ($conn->query("DELETE FROM suppliers WHERE supplier_id = $supplier_id")) {
    $_SESSION['alert'] = [
        'type' => 'success',
        'message' => "✅ تم حذف المورد {$supplier['supplier_name']} بنجاح"
    ];
} else {
    $_SESSION['alert'] = [
        'type' => 'error',
        'message' => "❌ حدث خطأ أثناء الحذف: " . $conn->error
    ];
}

header("Location: suppliers.php");
exit();
?>