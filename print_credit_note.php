<?php
session_start();
include "config.php";

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: logout.php");
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: customers.php");
    exit();
}

$credit_note_id = intval($_GET['id']);
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

$sql = "SELECT 
            ccn.*, 
            c.customer_name, 
            c.customer_phone, 
            c.customer_address,
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
    header("Location: customers.php");
    exit();
}

$credit_note = $result->fetch_assoc();

// تنسيق الأرقام
function formatNumber($number) {
    return number_format($number, 2);
}

/**
 * دالة احترافية لتحويل الأرقام إلى كلمات باللغة العربية
 */
function numberToWords($number) {
    if (!is_numeric($number)) return 'ريال يمني فقط لا غير';
    
    $number = floor(floatval($number));
    
    if ($number == 0) return 'صفر ريال يمني فقط لا غير';
    
    $ones = [
        1 => 'واحد', 2 => 'اثنان', 3 => 'ثلاثة', 4 => 'أربعة',
        5 => 'خمسة', 6 => 'ستة', 7 => 'سبعة', 8 => 'ثمانية', 9 => 'تسعة'
    ];
    
    $tens = [
        1 => 'عشرة', 2 => 'عشرون', 3 => 'ثلاثون', 4 => 'أربعون',
        5 => 'خمسون', 6 => 'ستون', 7 => 'سبعون', 8 => 'ثمانون', 9 => 'تسعون'
    ];
    
    $hundreds = [
        1 => 'مئة', 2 => 'مئتان', 3 => 'ثلاثمئة', 4 => 'أربعمئة',
        5 => 'خمسمئة', 6 => 'ستمئة', 7 => 'سبعمئة', 8 => 'ثمانمئة', 9 => 'تسعمئة'
    ];
    
    $thousands = [
        1 => 'ألف', 2 => 'ألفان', 3 => 'ثلاثة آلاف', 4 => 'أربعة آلاف',
        5 => 'خمسة آلاف', 6 => 'ستة آلاف', 7 => 'سبعة آلاف',
        8 => 'ثمانية آلاف', 9 => 'تسعة آلاف'
    ];
    
    $millions = [
        1 => 'مليون', 2 => 'مليونان', 3 => 'ثلاثة ملايين', 4 => 'أربعة ملايين',
        5 => 'خمسة ملايين', 6 => 'ستة ملايين', 7 => 'سبعة ملايين',
        8 => 'ثمانية ملايين', 9 => 'تسعة ملايين'
    ];
    
    $result = '';
    $original_number = $number;
    
    if ($number >= 1000000) {
        $million = floor($number / 1000000);
        $number = $number % 1000000;
        
        if ($million == 1) {
            $result .= 'مليون ';
        } elseif ($million == 2) {
            $result .= 'مليونان ';
        } elseif ($million >= 3 && $million <= 10) {
            $result .= $ones[$million] . ' ملايين ';
        } else {
            $million_text = convertLessThanOneThousand($million, $ones, $tens, $hundreds);
            $result .= $million_text . ' مليون ';
        }
    }
    
    if ($number >= 1000) {
        $thousand = floor($number / 1000);
        $number = $number % 1000;
        
        if ($thousand == 1) {
            $result .= 'ألف ';
        } elseif ($thousand == 2) {
            $result .= 'ألفان ';
        } elseif ($thousand >= 3 && $thousand <= 10) {
            $result .= $ones[$thousand] . ' آلاف ';
        } else {
            $thousand_text = convertLessThanOneThousand($thousand, $ones, $tens, $hundreds);
            $result .= $thousand_text . ' ألف ';
        }
    }
    
    if ($number > 0) {
        if ($original_number >= 1000) {
            $result .= 'و ';
        }
        $result .= convertLessThanOneThousand($number, $ones, $tens, $hundreds);
    }
    
    return trim($result) . ' ريال يمني فقط لا غير';
}

function convertLessThanOneThousand($number, $ones, $tens, $hundreds) {
    $result = '';
    
    if ($number >= 100) {
        $hundred = floor($number / 100);
        $number = $number % 100;
        $result .= $hundreds[$hundred];
        
        if ($number > 0) {
            $result .= ' و ';
        } else {
            $result .= ' ';
        }
    }
    
    if ($number >= 20) {
        $ten = floor($number / 10);
        $one = $number % 10;
        if ($one > 0) {
            $result .= $ones[$one] . ' و ';
        }
        $result .= $tens[$ten];
    } 
    elseif ($number >= 10) {
        if ($number == 10) {
            $result .= 'عشرة';
        } elseif ($number == 11) {
            $result .= 'أحد عشر';
        } elseif ($number == 12) {
            $result .= 'اثنا عشر';
        } elseif ($number <= 19) {
            $result .= $ones[$number - 10] . ' عشر';
        }
    } 
    elseif ($number > 0) {
        $result .= $ones[$number];
    }
    
    return $result;
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طباعة سند تسليم - <?php echo $credit_note['credit_note_number']; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Tahoma', Arial, sans-serif;
        }
        
        body {
            background: #f0f0f0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        
        .print-container {
            max-width: 800px;
            width: 100%;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .print-header {
            background: linear-gradient(135deg, #FF9800, #F57C00);
            color: white;
            padding: 30px;
            text-align: center;
            border-bottom: 3px solid #F57C00;
        }
        
        .print-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .print-header h2 {
            font-size: 18px;
            font-weight: normal;
            opacity: 0.9;
        }
        
        .print-body {
            padding: 30px;
        }
        
        .company-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            
        }
        
        .company-name {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }
        
        .credit-number {
            background: #fff3e0;
            color: #FF9800;
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: bold;
            border: 2px solid #FF9800;  
        }
        
        .info-table {
            width: 100%;
            border: 2px solid #1a18185d;    
            border-collapse: collapse;
            margin-bottom: 20px;
            margin-top:17px;
            border-radius: 8px;
        }
        
        .info-table td {
            padding: 15px 10px;
            border-bottom: 1px solid #eee;
        }
        .info-table tr {
            padding: 15px 10px;
            border: 2px solid #948c8c;
        }   
        
        .info-table .label {
            width: 150px;
            font-weight: bold;
            color: #666;
        }
        
        .info-table .value {
            color: #333;
        }
        
        .amount-box {
            background: #fff3e0;
            border: 2px solid #FF9800;
            border-radius: 10px;
            padding: 5px;
            margin: 10px 0;
            text-align: center;
        }
        
        .amount-box .amount {
            font-size: 36px;
            font-weight: bold;
            color: #FF9800;
            margin-bottom: 10px;
        }
        
        .amount-box .amount-words {
            font-size: 16px;
            color: #666;
        }
        
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
            padding-top: 30px;
            border-top: 2px dashed #FF9800;
        }
        
        .signature {
            text-align: center;
            width: 200px;
        }
        
        .signature-line {
            border-top: 1px dashed #333;
            padding-top: 10px;
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            color: #666;
            font-size: 12px;
            border-top: 1px solid #ddd;
        }
        
        .no-print {
            text-align: center;
            margin-top: 20px;
        }
        
        .no-print button {
            padding: 12px 30px;
            background: #FF9800;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .no-print button:hover {
            background: #F57C00;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255,152,0,0.3);
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .print-container {
                box-shadow: none;
                border: 1px solid #ddd;
            }
            
            .no-print {
                display: none;
            }
            
            .print-header {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
        .invoice-header {
            background: #1e3c72;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 20px;
            position: relative;
        }
        .invoice-header {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        .header-content {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
        }
        .company-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .company-infon {
            border: 2px solid #FF9800;
            padding: 8px; 
            border-radius: 15px 15px 0px 0px;
            display: flex;
            align-items: center;
            gap: 130px;
            margin-bottom: -10px;
        }
        .company-details{
            border: 2px solid rgba(255,255,255,0.2);
            height: 150px;
            margin-top: -13px;   
            background: rgba(255,255,255,0.1);
            padding: 10px 10px;
            border-radius: 8px;
            
        }
        .company-detailss{
            width: 230px;
            height: 150px;
            border: 2px solid rgba(255,255,255,0.2);
            text-align: right;
            background: rgba(255,255,255,0.1);
            padding: 10px 20px;
            border-radius: 8px;
            
        }
        .company-detailss span{
        
            font-size: 14px;
            color: #f5f2ee;
        }
       
        .company-details h1 {
            font-size: 14px;
            
            margin-bottom: 3px;
        }
        
        .company-details .subtitle {
            font-size: 13px;
            opacity: 0.9;
            
        }
        .invoice-title {
            border: 2px solid rgba(255, 255, 255, 0.52);
            text-align: left;
            background: rgba(255,255,255,0.1);
            padding: 10px 20px;
            border-radius: 8px;
        }
        .invoice-titleee {
            width: 100%;
            text-align: center;
            background: rgba(255,255,255,0.1);
            padding: 7px 7px;
            border-radius: 8px;
            border: 2px solid rgba(240, 229, 229, 0.84);
        }



        .invoice-titlee {
            align-items: center;
            text-align: center;
            width: 240px;
            height:180px;
            background: rgba(255,255,255,0.1);
            padding: 5px 5px;
            border-radius:50%;
            border: 2px solid rgba(255,255,255,0.2);
            
               
        }
        .invoice-titlee p {
            text-align:center;
            font-size: 16px;
            font-family: 'Amiri', serif;
            margin-top: -20px;
            color: #f5f2ee;
            margin-bottom: 5px;
        }

        .invoice-titlee div{
            align-items: center;
        }

        


        .invoice-title h2 {
            font-size: 24px;
            font-weight: bold;
            color: #FFD700;
            margin-bottom: 5px;
        }
        .invoice-number {
            font-size: 14px;
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 50px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="print-container">
        <div class="invoice-header">
                <div class="header-content">
                    <div class="company-info">
                        
                        <div class="company-details">
                            <h2>محلات و مكتب اليعري</h2>
                            <div class="subtitle">
                                لواد البناء والكهرباء والسباكة والأسمنت <br> مواد صحية وأدوات نجارة ومستلزمات الورش<br>
                                بلاط - مقاولات عامة - رفع مساحي <br> تصاميم ومخططات - إشراف هندسي
                            </div>
                        </div>
                    </div>
                    <div class="invoice-titlee">
                        
                            <img src="img/Photo_1724861002314.png" alt="شعار المحل" style="margin-top: 10px; width: 240px; height: 150px; border-radius: 50%; object-fit: cover;">
                        
                        
                        <p>اليعري للمقاولات</p>
                    </div>
                    <div class="company-info">
                        
                        <div class="company-detailss">
                            <span><i class="fas fa-map-marker-alt"></i> الجمهورية اليمنية - صنعاء <br><pre>    </per> بني مطر   <pre>    </per> النقطة  (مفرق بيت ردم)<br><pre>    </per> امام الاصدار الالي لفرع<br><pre>    </per> المرور متنة <br><pre>    </per> الشارع الرئيسي لخط الحديدة </span></span> <pre></per><span style="text-align:right; display:inline-block; margin-top: -15px;"><i class="fas fa-phone"></i> 777859275 - 774608082</span>
                        </div>
                    </div>
                    
                    <div class="invoice-titleee" style="margin-top: 10px;">
                            سند تسليم
                    </div>
                    
                    
                     
                </div>
                
                
            </div>
        
        <div class="print-body">
            <div class="company-infon">
                
                <div class="credit-number">رقم السند: <?php echo $credit_note['credit_note_number']; ?></div>
                <div class="credit-number">تاريخ السند: <?php echo $credit_note['credit_note_date']; ?></div>
            </div>
            
            <table class="info-table">
                
                
                 <tr>
                    <td class="label">اسم العميل:</td>
                    <td class="value"><?php echo $credit_note['customer_name']; ?></td>
                
                
                    <td class="label">رقم الهاتف:</td>
                    <td class="value"><?php echo $credit_note['customer_phone'] ?: '---'; ?></td>
                </tr>
                
                <tr>
                    <td class="label">طريقة الدفع:</td>
                    <td class="value"><?php echo $credit_note['payment_method']; ?></td>
                </tr>
                <?php if ($credit_note['reference_number']): ?>
                <tr>
                    <td class="label">رقم المرجع:</td>
                    <td class="value"><?php echo $credit_note['reference_number']; ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($credit_note['reason']): ?>
                <tr>
                    <td class="label">السبب:</td>
                    <td class="value"><?php echo $credit_note['reason']; ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($credit_note['notes']): ?>
                <tr>
                    <td class="label">ملاحظات:</td>
                    <td class="value"><?php echo $credit_note['notes']; ?></td>
                </tr>
                <?php endif; ?>
             </table>
            
            <div class="amount-box">
                <div class="amount"><?php echo formatNumber($credit_note['amount']); ?> ر.ي</div>
               
            </div>
            
            <div class="signature-section">
                <div class="signature">
                    <div>المستلم</div>
                    <div class="signature-line"></div>
                </div>
                <div class="signature">
                    <div>المسؤول المالي</div>
                    <div class="signature-line"></div>
                </div>
                <div class="signature">
                    <div>ختم المحل</div>
                    <div class="signature-line"></div>
                </div>
            </div>
        </div>
        
        <div class="footer">
           شكراً لتعاملكم - نظام إدارة المخازن |تم الإنشاء بواسطة:  <?php echo $credit_note['created_by_name'] ?? 'غير محدد'; ?> | تم الطباعة بواسطة: <?php echo $_SESSION['full_name'] ?? 'غير محدد'; ?> | جميع الحقوق محفوظة
        </div>
    </div>
    
    <div class="no-print">
        <button onclick="window.print()">
            <i class="fas fa-print"></i> طباعة
        </button>
        
        <button onclick="viewCustomer(<?php echo $customer_id; ?>)" style="background: #6c757d; margin-right: 10px;">
            <i class="fas fa-times"></i> إغلاق
        </button>
    </div>
    
    <script>
        function viewCustomer(id_customer){
            window.location.href = 'view_customer.php?id=' + id_customer;
        }
    </script>
</body>
</html>