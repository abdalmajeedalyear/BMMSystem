<?php
session_start();
include "config.php";

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: logout.php");
    exit();
}

// التحقق من وجود معرف السند
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: customers.php");
    exit();
}

$credit_note_id = intval($_GET['id']);

$sql = "SELECT 
            ccn.*, 
            c.customer_name,
            u.full_name as created_by_name
        FROM customer_credit_notes ccn
        JOIN customers c ON ccn.customer_id = c.customer_id
        LEFT JOIN users u ON ccn.created_by = u.user_id
        WHERE ccn.credit_note_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $credit_note_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['alert'] = [
        'type' => 'error',
        'message' => "❌ سند التسليم غير موجود"
    ];
    header("Location: customers.php");
    exit();
}

$credit_note = $result->fetch_assoc();

// تنسيق الأرقام
function formatNumber($number) {
    return number_format($number, 2);
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سند تسليم - <?php echo $credit_note['credit_note_number']; ?></title>
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">
    <style>
        body { 
            font-family: 'Tahoma', sans-serif; 
            background: #f5f5f5; 
            padding: 20px; 
        }
        .container { 
            max-width: 600px; 
            margin: 0 auto; 
            background: white; 
            padding: 30px; 
            border-radius: 15px; 
            box-shadow: 0 5px 20px rgba(0,0,0,0.1); 
        }
        h1 { 
            color: #FF9800; 
            margin-bottom: 20px; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
        }
        .info-item { 
            display: flex; 
            margin-bottom: 15px; 
            padding: 10px; 
            background: #f8f9fa; 
            border-radius: 8px; 
        }
        .info-label { 
            width: 120px; 
            font-weight: bold; 
            color: #666; 
        }
        .info-value { 
            flex: 1; 
            color: #333; 
        }
        .amount-box {
            background: #fff3e0;
            border: 2px solid #FF9800;
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
        }
        .amount-box .amount {
            font-size: 24px;
            font-weight: bold;
            color: #FF9800;
        }
        .btn { 
            padding: 10px 20px; 
            background: #FF9800; 
            color: white; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            text-decoration: none; 
            display: inline-block; 
            margin-top: 20px;
            transition: all 0.3s;
        }
        .btn:hover { 
            background: #F57C00; 
        }
        .btn-secondary {
            background: #6c757d;
            margin-left: 10px;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .print-btn {
            background: #2196F3;
        }
        .print-btn:hover {
            background: #1976D2;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            <i class="fas fa-money-bill-wave" style="color: #FF9800;"></i> 
            سند تسليم رقم: <?php echo $credit_note['credit_note_number']; ?>
        </h1>
        
        <div class="info-item">
            <span class="info-label"><i class="fas fa-user"></i> العميل:</span>
            <span class="info-value"><?php echo $credit_note['customer_name']; ?></span>
        </div>
        
        <div class="info-item">
            <span class="info-label"><i class="fas fa-calendar"></i> التاريخ:</span>
            <span class="info-value"><?php echo $credit_note['credit_note_date']; ?></span>
        </div>
        <div class="info-item">
            <span class="info-label"><i class="fas fa-calendar"></i> المبلغ:</span>
            <span class="info-value"><?php echo $credit_note['amount']; ?></span>
        </div>
        <div class="info-item">
            <span class="info-label"><i class="fas fa-calendar"></i>طريقة الدفع:</span>
            <span class="info-value"><?php echo $credit_note['payment_method']; ?></span>
        </div>
        
        <div class="info-item"><span class="info-label">تاريخ الإضافة:</span> <span class="info-value"><?php echo $credit_note['created_at']; ?></span></div>
        <!-- <div class="amount-box">
            <div class="amount"><?php echo formatNumber($credit_note['amount']); ?> ر.ي</div>
            <div style="font-size: 14px; color: #666; margin-top: 5px;">المبلغ المسلم للعميل</div>
        </div> -->
        
        <!-- <div class="info-item">
            <span class="info-label"><i class="fas fa-credit-card"></i> طريقة الدفع:</span>
            <span class="info-value"><?php echo $credit_note['payment_method']; ?></span>
        </div> -->
        
        <?php if ($credit_note['reference_number']): ?>
        <div class="info-item">
            <span class="info-label"><i class="fas fa-hashtag"></i> رقم المرجع:</span>
            <span class="info-value"><?php echo $credit_note['reference_number']; ?></span>
        </div>
        <?php endif; ?>
        
        <?php if ($credit_note['reason']): ?>
        <div class="info-item">
            <span class="info-label"><i class="fas fa-sticky-note"></i> السبب:</span>
            <span class="info-value"><?php echo $credit_note['reason']; ?></span>
        </div>
        <?php endif; ?>
        
        <?php if ($credit_note['notes']): ?>
        <div class="info-item">
            <span class="info-label"><i class="fas fa-comment"></i> ملاحظات:</span>
            <span class="info-value"><?php echo $credit_note['notes']; ?></span>
        </div>
        <?php endif; ?>
        
        <!-- <div class="info-item">
            <span class="info-label"><i class="fas fa-clock"></i> تاريخ الإضافة:</span>
            <span class="info-value"><?php echo date('Y-m-d h:i A', strtotime($credit_note['created_at'])); ?></span>
        </div> -->
        
        <div class="info-item">
            <span class="info-label"><i class="fas fa-user-cog"></i> بواسطة:</span>
            <span class="info-value"><?php echo $credit_note['created_by_name'] ?: $credit_note['created_by'] ?: 'غير محدد'; ?></span>
        </div>
        
        <div style="margin-top: 30px; display: flex; justify-content: center; gap: 10px;">
            <a href="view_customer.php?id=<?php echo $credit_note['customer_id']; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i> العودة للعميل
            </a>
            <!-- <button class="btn print-btn" onclick="window.print()">
                <i class="fas fa-print"></i> طباعة
            </button> -->
        </div>
    </div>
</body>
</html>