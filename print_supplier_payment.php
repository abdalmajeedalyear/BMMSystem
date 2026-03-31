<?php
session_start();
include "config.php";

// التحقق من وجود معرف السند
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: suppliers.php");
    exit();
}

$payment_id = intval($_GET['id']);

// استعلام للحصول على بيانات سند التسليم
$sql = "SELECT p.*, s.supplier_name, s.supplier_phone, s.supplier_address 
        FROM supplier_payments p
        JOIN suppliers s ON p.supplier_id = s.supplier_id
        WHERE p.payment_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: suppliers.php");
    exit();
}

$payment = $result->fetch_assoc();

// تنسيق الأرقام
function formatNumber($number) {
    return number_format($number, 2);
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>طباعة سند تسليم - <?php echo $payment['payment_number']; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Tahoma', Arial, sans-serif; }
        body { background: white; padding: 20px; display: flex; justify-content: center; }
        .print-container { max-width: 800px; width: 100%; background: white; border: 1px solid #ddd; border-radius: 10px; overflow: hidden; }
        .header { background: linear-gradient(135deg, #9C27B0, #7B1FA2); color: white; padding: 30px; text-align: center; }
        .header h1 { font-size: 28px; margin-bottom: 10px; }
        .header .number { font-size: 16px; background: rgba(255,255,255,0.2); padding: 5px 15px; border-radius: 50px; display: inline-block; }
        .body { padding: 30px; }
        .info-row { display: flex; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .label { width: 150px; font-weight: bold; color: #666; }
        .value { flex: 1; color: #333; }
        .amount-box { background: #f3e5f5; border: 2px solid #9C27B0; border-radius: 10px; padding: 20px; margin: 20px 0; text-align: center; }
        .amount { font-size: 36px; font-weight: bold; color: #9C27B0; }
        .footer { text-align: center; padding: 20px; background: #f0f0f0; font-size: 12px; color: #666; }
        .no-print { text-align: center; margin-top: 20px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="print-container">
        <div class="header">
            <h1><i class="fas fa-hand-holding-usd"></i> سند تسليم</h1>
            <div class="number">رقم: <?php echo $payment['payment_number']; ?></div>
        </div>
        <div class="body">
            <div class="info-row"><span class="label">المورد:</span> <span class="value"><?php echo $payment['supplier_name']; ?></span></div>
            <div class="info-row"><span class="label">التاريخ:</span> <span class="value"><?php echo $payment['payment_date']; ?></span></div>
            <div class="info-row"><span class="label">طريقة الدفع:</span> <span class="value"><?php echo $payment['payment_method']; ?></span></div>
            <?php if ($payment['reference_number']): ?>
            <div class="info-row"><span class="label">رقم المرجع:</span> <span class="value"><?php echo $payment['reference_number']; ?></span></div>
            <?php endif; ?>
            <div class="amount-box">
                <div class="amount"><?php echo formatNumber($payment['payment_amount']); ?> ر.س</div>
            </div>
            <?php if ($payment['notes']): ?>
            <div class="info-row"><span class="label">ملاحظات:</span> <span class="value"><?php echo $payment['notes']; ?></span></div>
            <?php endif; ?>
        </div>
        <div class="footer">تم الإنشاء: <?php echo date('Y-m-d H:i', strtotime($payment['created_at'])); ?></div>
    </div>
    <div class="no-print">
        <button onclick="window.print()" style="padding: 10px 20px; background: #9C27B0; color: white; border: none; border-radius: 5px; cursor: pointer;">طباعة</button>
        <button onclick="window.close()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer;">إغلاق</button>
    </div>
</body>
</html>