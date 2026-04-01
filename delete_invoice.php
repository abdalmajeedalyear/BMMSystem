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

// جلب جميع منتجات الفاتورة قبل الحذف
$items_sql = "SELECT product_name, product_quantity, product_unit FROM invoice_items WHERE invoice_id = $invoice_id";
$items_result = $conn->query($items_sql);
$items = [];
while ($row = $items_result->fetch_assoc()) {
    $items[] = $row;
}

// بدء المعاملة
$conn->begin_transaction();

try {
    // ==================== إرجاع الكميات إلى المخزون ====================
    foreach ($items as $item) {
        $product_name = $conn->real_escape_string($item['product_name']);
        $quantity = floatval($item['product_quantity']);
        $unit = $conn->real_escape_string($item['product_unit']);
        
        // تحديث المخزون: إضافة الكمية مرة أخرى (زيادة)
        $update_stock = "UPDATE products 
                         SET current_quantity = current_quantity + $quantity 
                         WHERE product_name = '$product_name' AND product_unit = '$unit'";
        
        if (!$conn->query($update_stock)) {
            throw new Exception("خطأ في إرجاع الكمية للمنتج: " . $product_name);
        }
    }
    
    // حذف عناصر الفاتورة
    $conn->query("DELETE FROM invoice_items WHERE invoice_id = $invoice_id");
    
    // حذف الفاتورة
    $conn->query("DELETE FROM invoices WHERE invoice_id = $invoice_id");
    
    $conn->commit();
    
    $_SESSION['alert'] = [
        'type' => 'success',
        'message' => "✅ تم حذف الفاتورة رقم {$invoice['invoice_number']} بنجاح، وتم إرجاع الكميات إلى المخزون"
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