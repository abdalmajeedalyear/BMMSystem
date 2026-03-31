<?php
session_start();
include "config.php";

// التحقق من وجود البيانات
if (!isset($_POST['item_id']) || !isset($_POST['new_price'])) {
    echo json_encode(['success' => false, 'message' => 'البيانات غير مكتملة']);
    exit();
}

$item_id = intval($_POST['item_id']);
$new_price = floatval($_POST['new_price']);

// التحقق من صحة البيانات
if ($item_id <= 0 || $new_price < 0) {
    echo json_encode(['success' => false, 'message' => 'بيانات غير صحيحة']);
    exit();
}

// التحقق من وجود المنتج
$check = $conn->query("SELECT product_name, purchase_price FROM products WHERE product_id = $item_id");
if ($check->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'المنتج غير موجود']);
    exit();
}

$product = $check->fetch_assoc();

// تحديث سعر البيع
$update = "UPDATE products SET selling_price = $new_price, updated_at = NOW() WHERE product_id = $item_id";

if ($conn->query($update)) { add_direct_items
    echo json_encode([
        'success' => true, 
        'message' => 'تم تحديث سعر البيع بنجاح',
        'product_name' => $product['product_name'],
        'old_price' => $product['purchase_price'],
        'new_price' => $new_price
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'خطأ في التحديث: ' . $conn->error]);
}

$conn->close();
?>