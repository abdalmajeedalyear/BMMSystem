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
    header("Location: suppliers.php");
    exit();
}

$payment_id = intval($_GET['id']);

// استعلام للحصول على بيانات سند التسليم مع معلومات المورد
$sql = "SELECT p.*, s.supplier_name, s.supplier_phone, s.supplier_address 
        FROM supplier_payments p
        JOIN suppliers s ON p.supplier_id = s.supplier_id
        WHERE p.payment_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['alert'] = [
        'type' => 'error',
        'message' => "❌ سند التسليم غير موجود"
    ];
    header("Location: suppliers.php");
    exit();
}

$payment = $result->fetch_assoc();

// تنسيق الأرقام
function formatNumber($number) {
    return number_format($number, 2);
}

// دالة تحويل الرقم إلى كتابة (نص)
function numberToWords($number) {
    $words = [
        0 => 'صفر', 1 => 'واحد', 2 => 'اثنان', 3 => 'ثلاثة', 4 => 'أربعة',
        5 => 'خمسة', 6 => 'ستة', 7 => 'سبعة', 8 => 'ثمانية', 9 => 'تسعة',
        10 => 'عشرة', 11 => 'أحد عشر', 12 => 'اثنا عشر', 13 => 'ثلاثة عشر',
        14 => 'أربعة عشر', 15 => 'خمسة عشر', 16 => 'ستة عشر', 17 => 'سبعة عشر',
        18 => 'ثمانية عشر', 19 => 'تسعة عشر', 20 => 'عشرون', 30 => 'ثلاثون',
        40 => 'أربعون', 50 => 'خمسون', 60 => 'ستون', 70 => 'سبعون',
        80 => 'ثمانون', 90 => 'تسعون', 100 => 'مائة', 200 => 'مائتان',
        300 => 'ثلاثمائة', 400 => 'أربعمائة', 500 => 'خمسمائة', 600 => 'ستمائة',
        700 => 'سبعمائة', 800 => 'ثمانمائة', 900 => 'تسعمائة', 1000 => 'ألف'
    ];
    
    if ($number == 0) return 'صفر';
    
    $num = floor($number);
    $result = '';
    
    if ($num >= 1000) {
        $thousands = floor($num / 1000);
        $num %= 1000;
        if ($thousands == 1) $result .= 'ألف ';
        elseif ($thousands == 2) $result .= 'ألفان ';
        else $result .= $words[$thousands] . ' آلاف ';
    }
    
    if ($num >= 100) {
        $hundreds = floor($num / 100) * 100;
        $num %= 100;
        $result .= $words[$hundreds] . ' و ';
    }
    
    if ($num > 0) {
        if (isset($words[$num])) $result .= $words[$num];
        else {
            $tens = floor($num / 10) * 10;
            $units = $num % 10;
            $result .= $words[$tens] . ' و ' . $words[$units];
        }
    }
    
    return trim($result) . ' ريال سعودي فقط لا غير';
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سند تسليم - <?php echo $payment['payment_number']; ?></title>
    
    <!-- Font Awesome محلي -->
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">
    
    <!-- ملف CSS الرئيسي -->
    <link rel="stylesheet" href="Style/reports.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Tahoma', Arial, sans-serif;
            background: #f0f2f5;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        /* ==================== شريط التنقل العلوي ==================== */
        .top-bar {
            background: white;
            border-radius: 15px;
            padding: 15px 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .page-title h1 {
            font-size: 24px;
            color: #333;
        }
        
        .page-title i {
            font-size: 28px;
            color: #9C27B0;
        }
        
        .date-display {
            background: #f3e5f5;
            color: #9C27B0;
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: bold;
            border: 2px solid #9C27B0;
        }
        
        /* ==================== أزرار الإجراءات ==================== */
        .action-bar {
            background: white;
            border-radius: 15px;
            padding: 15px 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #9C27B0;
            color: white;
        }
        
        .btn-primary:hover {
            background: #7B1FA2;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(156,39,176,0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .btn-print {
            background: #2196F3;
            color: white;
        }
        
        .btn-print:hover {
            background: #1976D2;
            transform: translateY(-2px);
        }
        
        /* ==================== بطاقة السند ==================== */
        .payment-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .payment-header {
            background: linear-gradient(135deg, #9C27B0, #7B1FA2);
            color: white;
            padding: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .payment-title h2 {
            font-size: 24px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .payment-title p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .payment-status {
            background: rgba(255,255,255,0.2);
            padding: 10px 20px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: bold;
            backdrop-filter: blur(10px);
        }
        
        .payment-body {
            padding: 30px;
        }
        
        /* ==================== معلومات السند ==================== */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .info-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
        }
        
        .info-section h3 {
            font-size: 16px;
            font-weight: 700;
            color: #9C27B0;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 2px solid #9C27B0;
            padding-bottom: 10px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 12px;
            font-size: 14px;
        }
        
        .info-label {
            width: 100px;
            color: #666;
            font-weight: 500;
        }
        
        .info-value {
            flex: 1;
            color: #333;
            font-weight: 600;
        }
        
        .amount-box {
            background: #f3e5f5;
            border: 2px solid #9C27B0;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        
        .amount-box .amount {
            font-size: 36px;
            font-weight: bold;
            color: #9C27B0;
            margin-bottom: 10px;
        }
        
        .amount-box .amount-words {
            font-size: 14px;
            color: #666;
        }
        
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .details-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #eee;
        }
        
        .details-table .label {
            font-weight: bold;
            color: #666;
            width: 150px;
        }
        
        .details-table .value {
            color: #333;
        }
        
        .notes-section {
            background: #f3e5f5;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            border-right: 4px solid #9C27B0;
        }
        
        .notes-section h4 {
            color: #9C27B0;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .notes-section p {
            color: #666;
            line-height: 1.6;
        }
        
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px dashed #ccc;
        }
        
        .signature-box {
            text-align: center;
            width: 150px;
        }
        
        .signature-line {
            border-top: 2px dashed #333;
            padding-top: 10px;
            margin-top: 10px;
            color: #666;
            font-size: 12px;
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 12px;
            border-top: 1px solid #eee;
        }
        
        /* ==================== رسالة الخطأ ==================== */
        .alert {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 8px;
            color: white;
            font-weight: bold;
            z-index: 9999;
            animation: slideIn 0.3s ease;
        }
        
        .alert-success {
            background: #4CAF50;
        }
        
        .alert-error {
            background: #f44336;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @media print {
            .action-bar,
            .top-bar,
            .no-print {
                display: none !important;
            }
            
            .payment-card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
            
            .payment-header {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
        
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .payment-header {
                flex-direction: column;
                text-align: center;
            }
            
            .signature-section {
                flex-direction: column;
                align-items: center;
                gap: 20px;
            }
        }
    </style>
</head>
<body>

<!-- رسائل التنبيه -->
<?php if (isset($_SESSION['alert'])): ?>
    <div class="alert alert-<?php echo $_SESSION['alert']['type']; ?>" id="alertMessage">
        <i class="fas <?php echo $_SESSION['alert']['type'] == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
        <?php echo $_SESSION['alert']['message']; ?>
    </div>
    <script>
        setTimeout(() => {
            const alert = document.getElementById('alertMessage');
            if (alert) alert.remove();
        }, 5000);
    </script>
    <?php unset($_SESSION['alert']); ?>
<?php endif; ?>

<div class="container">
    <!-- ==================== شريط التنقل العلوي ==================== -->
    <div class="top-bar no-print">
        <div class="page-title">
            <i class="fas fa-hand-holding-usd"></i>
            <h1>سند تسليم</h1>
        </div>
        <div class="date-display">
            <i class="far fa-calendar-alt"></i>
            <?php echo date('Y-m-d'); ?>
        </div>
    </div>
    
    <!-- ==================== أزرار الإجراءات ==================== -->
    <div class="action-bar no-print">
        <div class="action-buttons">
            <a href="#" onclick="goback()" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i>
                العودة 
            </a>
            <button class="btn btn-print" onclick="window.print()">
                <i class="fas fa-print"></i>
                طباعة
            </button>
        </div>
    </div>
    
    <!-- ==================== بطاقة السند ==================== -->
    <div class="payment-card">
        <div class="payment-header">
            <div class="payment-title">
                <h2>
                    <i class="fas fa-hand-holding-usd"></i>
                    سند تسليم
                </h2>
                <p>رقم: <?php echo $payment['payment_number']; ?></p>
            </div>
            <div class="payment-status">
                <i class="fas fa-check-circle"></i>
                مدفوع
            </div>
        </div>
        
        <div class="payment-body">
            <!-- ==================== معلومات السند ==================== -->
            <div class="info-grid">
                <!-- معلومات المورد -->
                <div class="info-section">
                    <h3><i class="fas fa-handshake"></i> بيانات المورد</h3>
                    <div class="info-row">
                        <span class="info-label">الاسم:</span>
                        <span class="info-value"><?php echo $payment['supplier_name']; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">الهاتف:</span>
                        <span class="info-value"><?php echo $payment['supplier_phone'] ?: '---'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">العنوان:</span>
                        <span class="info-value"><?php echo $payment['supplier_address'] ?: '---'; ?></span>
                    </div>
                </div>
                
                <!-- معلومات السند -->
                <div class="info-section">
                    <h3><i class="fas fa-file-invoice"></i> بيانات السند</h3>
                    <div class="info-row">
                        <span class="info-label">رقم السند:</span>
                        <span class="info-value"><?php echo $payment['payment_number']; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">التاريخ:</span>
                        <span class="info-value"><?php echo $payment['payment_date']; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">طريقة الدفع:</span>
                        <span class="info-value"><?php echo $payment['payment_method']; ?></span>
                    </div>
                    <?php if ($payment['reference_number']): ?>
                    <div class="info-row">
                        <span class="info-label">رقم المرجع:</span>
                        <span class="info-value"><?php echo $payment['reference_number']; ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- ==================== المبلغ ==================== -->
            <div class="amount-box">
                <div class="amount"><?php echo formatNumber($payment['payment_amount']); ?> ر.س</div>
                <div class="amount-words"><?php echo numberToWords($payment['payment_amount']); ?></div>
            </div>
            
            <!-- ==================== تفاصيل إضافية ==================== -->
            <table class="details-table">
                <tr>
                    <td class="label">تاريخ الإضافة:</td>
                    <td class="value"><?php echo date('Y-m-d H:i', strtotime($payment['created_at'])); ?></td>
                </tr>
                <tr>
                    <td class="label">بواسطة:</td>
                    <td class="value"><?php echo $payment['created_by'] ?: 'system'; ?></td>
                </tr>
            </table>
            
            <!-- ==================== الملاحظات ==================== -->
            <?php if (!empty($payment['notes'])): ?>
            <div class="notes-section">
                <h4><i class="fas fa-sticky-note"></i> ملاحظات</h4>
                <p><?php echo nl2br($payment['notes']); ?></p>
            </div>
            <?php endif; ?>
            
            <!-- ==================== التوقيعات ==================== -->
            <div class="signature-section">
                <div class="signature-box">
                    <div>توقيع المستلم</div>
                    <div class="signature-line"></div>
                </div>
                <div class="signature-box">
                    <div>ختم الشركة</div>
                    <div class="signature-line"></div>
                </div>
                <div class="signature-box">
                    <div>المسؤول المالي</div>
                    <div class="signature-line"></div>
                </div>
            </div>
        </div>
        
        <!-- ==================== تذييل ==================== -->
        <div class="footer">
            <p>تم إنشاء هذا السند إلكترونياً بواسطة نظام إدارة المخازن</p>
        </div>
    </div>
</div>

<script>
    function goback(){
        window.history.back();
    }
    // اختصارات لوحة المفاتيح
    document.addEventListener('keydown', function(e) {
        // ESC للعودة للمورد
        if (e.key === 'Escape') {
            window.location.href = 'view_supplier.php?id=<?php echo $payment['supplier_id']; ?>';
        }
        
        // Ctrl + P للطباعة
        if (e.ctrlKey && e.key === 'p') {
            e.preventDefault();
            window.print();
        }
    });
</script>

</body>
</html>