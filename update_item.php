<?php
session_start();
include "config.php";

// التحقق من وجود البيانات
if (!isset($_POST['item_id']) || !isset($_POST['item_name'])) {
    echo json_encode(['success' => false, 'message' => 'البيانات غير مكتملة']);
    exit();
}

$item_id = intval($_POST['item_id']);
$item_name = $conn->real_escape_string($_POST['item_name']);
$category = $conn->real_escape_string($_POST['category'] ?? 'مواد متنوعة');
$quantity = floatval($_POST['quantity'] ?? 0);
$unit = $conn->real_escape_string($_POST['unit'] ?? 'قطعة');
$purchase_price = floatval($_POST['purchase_price'] ?? 0);
$selling_price = floatval($_POST['selling_price'] ?? 0);
$min_stock = floatval($_POST['min_stock'] ?? 10);

// التحقق من صحة البيانات
if ($item_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'معرف المنتج غير صحيح']);
    exit();
}

// التحقق من وجود المنتج
$check = $conn->query("SELECT product_name FROM products WHERE product_id = $item_id");
if ($check->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'المنتج غير موجود']);
    exit();
}

// تحديث بيانات المنتج
$update = "UPDATE products SET 
           product_name = '$item_name',
           product_category = '$category',
           current_quantity = $quantity,
           product_unit = '$unit',
           purchase_price = $purchase_price,
           selling_price = $selling_price,
           min_quantity = $min_stock,
           updated_at = NOW() 
           WHERE product_id = $item_id";

if ($conn->query($update)) {
    echo json_encode([
        'success' => true, 
        'message' => 'تم تحديث بيانات المنتج بنجاح'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'خطأ في التحديث: ' . $conn->error]);
}

$conn->close();
?>