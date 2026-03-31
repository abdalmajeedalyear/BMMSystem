<?php
session_start();
include "config.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: invoice_page.php");
    exit();
}

$invoice_id = intval($_GET['id']);

// التحقق من وجود الفاتورة
$check = $conn->query("SELECT invoice_number FROM invoices WHERE invoice_id = $invoice_id");
if ($check->num_rows === 0) {
    $_SESSION['alert'] = [
        'type' => 'error',
        'message' => "❌ الفاتورة غير موجودة"
    ];
    header("Location: invoice_page.php");
    exit();
}

$invoice = $check->fetch_assoc();

// بدء المعاملة
$conn->begin_transaction();

try {
    // حذف عناصر الفاتورة أولاً
    $conn->query("DELETE FROM invoice_items WHERE invoice_id = $invoice_id");
    
    // حذف الفاتورة
    $conn->query("DELETE FROM invoices WHERE invoice_id = $invoice_id");
    
    $conn->commit();
    
    $_SESSION['alert'] = [
        'type' => 'success',
        'message' => "✅ تم حذف الفاتورة رقم {$invoice['invoice_number']} بنجاح"
    ];
    
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['alert'] = [
        'type' => 'error',
        'message' => "❌ حدث خطأ أثناء الحذف: " . $e->getMessage()
    ];
}

header("Location: invoice_page.php");
exit();
?>