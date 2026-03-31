<?php
session_start();
include "config.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: purchases_page.php");
    exit();
}

$purchase_id = intval($_GET['id']);

// التحقق من وجود الفاتورة
$check = $conn->query("SELECT purchase_number FROM purchases WHERE id = $purchase_id");
if ($check->num_rows === 0) {
    $_SESSION['alert'] = [
        'type' => 'error',
        'message' => "❌ فاتورة المشتريات غير موجودة"
    ];
    header("Location: purchases_page.php");
    exit();
}

$purchase = $check->fetch_assoc();

// بدء المعاملة
$conn->begin_transaction();

try {
    // حذف عناصر المشتريات أولاً
    $conn->query("DELETE FROM purchase_items WHERE purchase_id = $purchase_id");
    
    // حذف الفاتورة
    $conn->query("DELETE FROM purchases WHERE id = $purchase_id");
    
    $conn->commit();
    
    $_SESSION['alert'] = [
        'type' => 'success',
        'message' => "✅ تم حذف فاتورة المشتريات رقم {$purchase['purchase_number']} بنجاح"
    ];
    
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['alert'] = [
        'type' => 'error',
        'message' => "❌ حدث خطأ أثناء الحذف: " . $e->getMessage()
    ];
}

header("Location: purchases_page.php");
exit();
?>