<?php
session_start();
include "config.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: customers.php");
    exit();
}

$payment_id = intval($_GET['id']);

$sql = "SELECT p.*,
               c.customer_name,
               u.full_name as created_by_name
        FROM customer_payments p
        JOIN customers c ON p.customer_id = c.customer_id
        LEFT JOIN users u ON P.created_by = u.user_id
        WHERE p.payment_id = ?";
// $sql = "SELECT 
//             ccn.*, 
//             c.customer_name,
//             u.full_name as created_by_name
//         FROM customer_credit_notes ccn
//         JOIN customers c ON ccn.customer_id = c.customer_id
//         LEFT JOIN users u ON ccn.created_by = u.user_id
//         WHERE ccn.credit_note_id = ?";



$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: customers.php");
    exit();
}

$payment = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>سند قبض - <?php echo $payment['payment_number']; ?></title>
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">
    <style>
        body { font-family: 'Tahoma', sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        h1 { color: #9C27B0; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .info-item { display: flex; margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 8px; }
        .info-label { width: 120px; font-weight: bold; color: #666; }
        .info-value { flex: 1; color: #333; }
        .btn { padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; display: inline-block; margin-top: 20px; }
        .btn:hover { background: #5a6268; }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-hand-holding-usd"></i> سند قبض رقم: <?php echo $payment['payment_number']; ?></h1>
        
        <div class="info-item"><span class="info-label">العميل:</span> <span class="info-value"><?php echo $payment['customer_name']; ?></span></div>
        <div class="info-item"><span class="info-label">التاريخ:</span> <span class="info-value"><?php echo $payment['payment_date']; ?></span></div>
        <div class="info-item"><span class="info-label">المبلغ:</span> <span class="info-value" style="color: #4CAF50; font-weight: bold;"><?php echo number_format($payment['payment_amount'], 2); ?> ر.ي</span></div>
        <div class="info-item"><span class="info-label">طريقة الدفع:</span> <span class="info-value"><?php echo $payment['payment_method']; ?></span></div>
        <?php if ($payment['reference_number']): ?>
        <div class="info-item"><span class="info-label">رقم المرجع:</span> <span class="info-value"><?php echo $payment['reference_number']; ?></span></div>
        <?php endif; ?>
        <?php if ($payment['notes']): ?>
        <div class="info-item"><span class="info-label">ملاحظات:</span> <span class="info-value"><?php echo $payment['notes']; ?></span></div>
        <?php endif; ?>
        <div class="info-item"><span class="info-label">تاريخ الإضافة:</span> <span class="info-value"><?php echo $payment['created_at']; ?></span></div>
        <div class="info-item"><span class="info-label">بواسطة:</span> <span class="info-value"><?php echo $payment['created_by_name'] ?: 'system'; ?></span></div>
        
        <a href="view_customer.php?id=<?php echo $payment['customer_id']; ?>" class="btn"><i class="fas fa-arrow-right"></i> العودة للعميل</a>
    </div>
</body>
</html>