<?php
session_start();
include "config.php";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_payment'])) {
    
    $supplier_id = intval($_POST['supplier_id']);
    $payment_amount = floatval($_POST['payment_amount']);
    $payment_date = $conn->real_escape_string($_POST['payment_date']);
    $payment_method = $conn->real_escape_string($_POST['payment_method']);
    $reference_number = $conn->real_escape_string($_POST['reference_number'] ?? '');
    $notes = $conn->real_escape_string($_POST['notes'] ?? '');
    $created_by = $_SESSION['user_id'] ?? 0;
    if ($payment_amount <= 0) {
        $_SESSION['alert'] = ['type' => 'error', 'message' => "❌ المبلغ يجب أن يكون أكبر من صفر"];
        header("Location: view_supplier.php?id=" . $supplier_id);
        exit();
    }
    
    // توليد رقم سند فريد
    $year = date('Y');
    $month = date('m');
    $payment_number = "SP-" . $year . $month . "-" . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    $insert_sql = "INSERT INTO supplier_payments 
                   (payment_number, supplier_id, payment_date, payment_amount, payment_method, reference_number, notes, created_by) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("sisdssss", $payment_number, $supplier_id, $payment_date, $payment_amount, $payment_method, $reference_number, $notes, $created_by);
    
    if ($insert_stmt->execute()) {
        $_SESSION['alert'] = [
            'type' => 'success',
            'message' => "✅ تم إضافة سند تسليم رقم <strong>$payment_number</strong> بنجاح"
        ];
    } else {
        $_SESSION['alert'] = [
            'type' => 'error',
            'message' => "❌ حدث خطأ: " . $conn->error
        ];
    }
    
    header("Location: view_supplier.php?id=" . $supplier_id);
    exit();
}
?>