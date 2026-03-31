<?php
session_start();
include "config.php";
// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: logout.php");
    exit();
}


// التحقق من وجود معرف الفاتورة
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: invoice_page.php");
    exit();
}

$invoice_id = intval($_GET['id']);
$autoPrint = $_GET['auto_print'] ?? 0;

// استعلام للحصول على بيانات الفاتورة مع معلومات العميل
$sql = "SELECT 
            i.invoice_id,
            i.invoice_number,
            i.invoice_date,
            c.customer_id,
            c.customer_name,
            c.customer_phone,
            c.customer_address,
            c.tax_number,
            i.subtotal,
            i.total_discount,
            i.total_tax,
            i.grand_total,
            i.paid_amount,
            i.remaining_amount,
            i.payment_method,
            i.payment_status,
            i.notes,
            i.created_at,
            i.created_by,
            u.full_name as created_by_name
        FROM invoices i
        LEFT JOIN customers c ON i.customer_id = c.customer_id
        LEFT JOIN users u ON i.created_by = u.user_id
        WHERE i.invoice_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['alert'] = [
        'type' => 'error',
        'message' => "❌ الفاتورة غير موجودة"
    ];
    header("Location: invoice_page.php");
    exit();
}

$invoice = $result->fetch_assoc();

// استعلام للحصول على منتجات الفاتورة
$items_sql = "SELECT 
                item_id,
                product_name,
                product_unit,
                product_quantity,
                product_price,
                product_discount,
                product_total
            FROM invoice_items 
            WHERE invoice_id = ?
            ORDER BY item_id ASC";

$items_stmt = $conn->prepare($items_sql);
$items_stmt->bind_param("i", $invoice_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

// تنسيق الأرقام
function formatNumber($number) {
    return number_format($number, 2);
}


// تحديد اسم منشئ الفاتورة
$created_by_name = $invoice['created_by_name'] ? $invoice['created_by_name'] : '---';

// دالة تحويل الرقم إلى كتابة (نص) - مختصرة
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
        else $result .= $words[$thousands] . ' آلاف و';
    }
    
    if ($num >= 100) {
        $hundreds = floor($num / 100) * 100;
        $num %= 100;
        $result .= $words[$hundreds] . ' ';
    }
    
    if ($num > 0) {
        if (isset($words[$num])) $result .= $words[$num];
        else {
            $tens = floor($num / 10) * 10;
            $units = $num % 10;
            $result .= $words[$tens] . ' و ' . $words[$units];
        }
    }
    
    return trim($result) . ' ريال يمني فقط لا غير';
}

// تحديد حالة الدفع
$payment_status_text = '';
$payment_status_color = '';

switch($invoice['payment_status']) {
    case 'مدفوعة':
        $payment_status_text = 'مدفوعة';
        $payment_status_color = '#4CAF50';
        break;
    case 'غير مدفوعة':
        $payment_status_text = 'غير مدفوعة';
        $payment_status_color = '#f44336';
        break;
    case 'جزئي':
        $payment_status_text = 'مدفوعة جزئياً';
        $payment_status_color = '#FF9800';
        break;
    default:
        $payment_status_text = $invoice['payment_status'];
        $payment_status_color = '#666';
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طباعة فاتورة - <?php echo $invoice['invoice_number']; ?></title>
    
    <!-- Font Awesome محلي -->
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Tahoma', 'Arial', sans-serif;
        }
        
        body {
            background: #e0e0e0;
            padding: 20px;
            display: flex;
            justify-content: center;
        }
        
        .print-wrapper {
            max-width: 1000px;
            width: 100%;
        }
        
        .invoice-container {
            border-top: 2px solid #17120a;
            border-right: 2px solid #17120a;
            border-left: 2px solid #17120a;
            border-bottom: 2px solid #17120a;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        /* ==================== ترويسة الفاتورة المحسنة (مطابقة للصورة) ==================== */
        .invoice-header {
            
            color: white;
            padding: 20px;
            position: relative;
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

        
        .company-logo {
            margin-right: 64px;
            width: 100px;
            height: 100px;
            
            border-radius: 50%;
            display: flex;
            
            justify-content: center;

            font-size: 35px;
            color: #1e3c72;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }
        
        .company-details{
            border: 2px solid #17120a;
            height: 150px;
            margin-top: -13px;   
            background: rgba(255,255,255,0.1);
            padding: 10px 10px;
            border-radius: 8px;
            
        }
        .company-detailss{
            width: 230px;
            height: 150px;
            border: 2px solid #17120a;
            text-align: right;
            background: rgba(255,255,255,0.1);
            padding: 10px 20px;
            border-radius: 8px;
            
        }
        .company-detailss span{
        
            font-size: 14px;
            color: #15110c;
        }
       
        .company-details h2 {
            font-size: 14px;
            color: #1b1814;
            margin-bottom: 3px;
        }
        
        .company-details .subtitle {
            color: #1b1814;
            font-size: 13px;
            opacity: 0.9;
            line-height: 1.8;
            font-weight:500;
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
            color: #1b1814;
            font-size: 18px;
            text-align: center;
            
            padding: 7px 7px;
            border-radius: 8px;
            border: 2px solid rgba(23, 18, 10, 0.84);
        }



        .invoice-titlee {
            align-items: center;
            text-align: center;
            width: 240px;
            height:180px;
            background: rgba(255,255,255,0.1);
            padding: 5px 5px;
            border-radius:50%;
            border: 2px solid rgba(23, 18, 10, 0.84);
            
               
        }
        .invoice-titlee p {
            text-align:center;
            font-size: 16px;
            font-family: 'Amiri', serif;
            margin-top: -20px;
            color: #1b1814;
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
        
        .address-bar {
            margin-top: 15px;
            padding-top: 10px;
            border-top: 2px solid rgba(255,255,255,0.2);
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 10px;
            font-size: 12px;
        }
        
        .address-bar span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .address-bar i {
            color: #FFD700;
            width: 16px;
        }
        
        /* ==================== معلومات الفاتورة والعميل (مصغرة) ==================== */
        .info-row-compact {
            display: flex;
            background: #f8f9fa;
            border-top: 2px solid #17120a;
            border-bottom: 2px solid #17120a;
            font-size: 13px;
        }
        
        .info-block {
            text-align: center;
            flex: 1;
            padding: 10px 15px;
            border-left: 1px solid #ddd;
        }
        
        .info-block:last-child {
            border-left: none;
        }
        
        .info-block .label {
            color: #666;
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 5px;
        }
        
        .info-block .value {
            color: #333;
            font-weight: 500;
        }
        
        .payment-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: bold;
            color: white;
            background: <?php echo $payment_status_color; ?>;
        }
        
        /* ==================== جدول المنتجات (مصغر) ==================== */
        .items-section {
            padding: 15px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        
        th {
            background: #f0f0f0;
            color: #333;
            font-weight: 600;
            padding: 8px 5px;
            text-align: center;
            border: 1px solid #ddd;
            font-size: 12px;
        }
        
        td {
            padding: 6px 5px;
            text-align: center;
            border: 1px solid #ddd;
        }
        
        .product-name {
            text-align: right;
        }
        
        .amount-col {
            font-weight: bold;
            color: #2196F3;
        }
        
        /* ==================== ملخص الفاتورة المصغر ==================== */
        .summary-compact {
            margin: 10px 15px 15px;
            display: flex;
            justify-content: flex-end;
        }
        
        .summary-box-compact {
            width: 350px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 8px;
            background: white;
            border-radius: 3px;
            border: 1px solid #eee;
            font-size: 12px;
        }
        
        .summary-item.full-width {
            grid-column: span 2;
            background: #e3f2fd;
            border-color: #2196F3;
            font-weight: bold;
        }
        
        .summary-item .label {
            color: #666;
        }
        
        .summary-item .value {
            font-weight: 600;
            color: #2196F3;
        }
        
        .summary-item.full-width .value {
            color: #2196F3;
            font-size: 14px;
        }
        
        .amount-words-mini {
            margin-top: 8px;
            padding: 6px 10px;
            background: #e8f5e9;
            border-radius: 3px;
            font-size: 11px;
            color: #2e7d32;
            text-align: center;
            border-right: 3px solid #4CAF50;
        }
        
        /* ==================== ملاحظات ==================== */
        .notes-mini {
            margin: 10px 15px;
            padding: 8px 12px;
            background: #fff3cd;
            border: 1px solid #ffeeba;
            border-radius: 3px;
            font-size: 11px;
            color: #856404;
            border-right: 3px solid #ffc107;
        }
        
        /* ==================== توقيعات مصغرة ==================== */
        .signature-mini {
            margin: 15px;
            padding-top: 10px;
            border-top: 2px dashed #ccc;
            display: flex;
            justify-content: space-around;
            margin-bottom: 30px;
        }
        
        .signature-mini-box {
            text-align: center;
            width: 120px;
        }
        
        .signature-mini-line {
            border-top: 1px dashed #333;
            padding-top: 5px;
            margin-top: 5px;
            font-size: 10px;
            color: #666;
        }
        
        /* ==================== تذييل ==================== */
        .footer-mini {
            background: #f0f0f0;
            padding: 8px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
        }
        
        /* ==================== أزرار التحكم ==================== */
        .action-buttons {
            text-align: center;
            margin-bottom: 15px;
        }
        
        .btn {
            padding: 8px 20px;
            border: none;
            border-radius: 5px;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin: 0 3px;
        }
        
        .btn-print {
            background: #2196F3;
            color: white;
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
        }
        
        /* ==================== تحسينات الطباعة ==================== */
        @media print {
            body {
                background: white;
                padding: 5px;
            }
            
            .action-buttons,
            .no-print {
                display: none !important;
            }
            
            .invoice-header {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            @page {
                size: A4;
                margin: 1cm;
            }
        }

                /* ==================== ملخص الفاتورة بعرض كامل ==================== */
        .summary-fullwidth {
            margin: 15px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
        }

        .summary-row {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 8px;
        }

        .summary-cell {
            flex: 1;
            min-width: 120px;
            background: white;
            border: 1px solid #eee;
            border-radius: 4px;
            padding: 8px 10px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .summary-cell.highlight {
            background: #e3f2fd;
            border-color: #2196F3;
        }

        .summary-cell.remaining {
            background: #ffebee;
            border-color: #f44336;
        }

        .summary-cell-label {
            display: block;
            font-size: 11px;
            color: #666;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .summary-cell-value {
            display: block;
            font-size: 15px;
            font-weight: 700;
            color: #2196F3;
        }

        .summary-cell-value.discount {
            color: #f44336;
        }

        .summary-cell-value.paid {
            color: #4CAF50;
        }

        .summary-cell-value.total {
            color: #2196F3;
            font-size: 16px;
        }

        .summary-cell-value.remaining {
            color: #f44336;
        }

        .amount-words-full {
            margin-top: 10px;
            padding: 8px 12px;
            background: #e8f5e9;
            border-radius: 4px;
            font-size: 12px;
            color: #2e7d32;
            text-align: center;
            border-right: 3px solid #4CAF50;
        }
    </style>
</head>
<body>
    <div class="print-wrapper">
         <?php if(!$autoPrint): ?>
            <!-- أزرار التحكم -->
            <div class="action-buttons no-print">
                <button class="btn btn-print" onclick="window.print()">
                    <i class="fas fa-print"></i> طباعة
                </button>
                <button class="btn btn-back" onclick="window.close()">
                    <i class="fas fa-times"></i> إغلاق
                </button>
            </div>
        <?php endif; ?>
        
        <!-- الفاتورة -->
        <div class="invoice-container">
            <!-- ==================== ترويسة محسنة ==================== -->
            <div class="invoice-header">
                <div class="header-content">
                    <div class="company-info">
                        
                        <div class="company-details">
                            <h2>محلات و مكتب اليعري</h2>
                            <div class="subtitle">
                                لمواد البناء والكهرباء والسباكة والأسمنت <br> مواد صحية وأدوات نجارة ومستلزمات الورش<br>
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
                        فاتورة مبيعات
                    </div>
                    <div style="width:100%; display:flex; justify-content:10px; margin-top: 10px; align-items:right;">
                        <div class="invoice-number" style="border: 2px solid #ccc; padding: 5px; margin-right: 10px; background-color: #f0f0f0; padding-left: 10px; padding-right: 10px;" ><span style="color: #333;">رقم الفاتورة:</span><span style="color: #d40e0e; font-weight: bold;"> <?php echo $invoice['invoice_number']; ?></span></div>
                        <div class="invoice-number" style="border: 2px solid #ccc; padding: 5px; margin-right: 10px; background-color: #f0f0f0; color: #333; padding-left: 10px; padding-right: 10px; font-weight: bold;"> نوع الفاتورة: <?php if($invoice['remaining_amount'] > 0): ?> آجل <?php else: ?> نقدي <?php endif; ?></div>
                        <div class="invoice-number" style="border: 2px solid #ccc; padding: 5px; margin-right: 10px; background-color: #f0f0f0; color: #333; padding-left: 10px; padding-right: 10px; font-weight: bold;">تاريخ الفاتورة: <?php echo $invoice['invoice_date']; ?></div>
                        
                    </div>
                    <div class="address-bar">
                        
                    
                        <!-- <span><i class="fas fa-calendar-alt"></i> تاريخ الفاتورة: <?php echo $invoice['invoice_date']; ?></span>
                        <span><i class="fas fa-user"></i> العميل: <?php echo $invoice['customer_name'] ?? 'عميل نقدي'; ?></span>
                        <span><i class="fas fa-phone"></i> الهاتف: <?php echo $invoice['customer_phone'] ?: '---'; ?></span>
                        <span><i class="fas fa-map-marker-alt"></i> العنوان: <?php echo $invoice['customer_address'] ?: '---'; ?></span>                        
                    -->
                    </div>
                     
                </div>
                
                
            </div>
            
            <!-- ==================== معلومات مصغرة ==================== -->
            <div class="info-row-compact">
                <div class="info-block">
                    <div class="label">اسم العميل :</div>
                    <div class="value"><?php echo $invoice['customer_name'] ?? 'عميل نقدي'; ?></div>
                </div>
                <div class="info-block">
                    <div class="label">الهاتف</div>
                    <div class="value"><?php echo $invoice['customer_phone'] ?: '---'; ?></div>
                </div>
            
                <div class="info-block">
                    <div class="label">طريقة الدفع</div>
                    <div class="value"><?php echo $invoice['payment_method']; ?></div>
                </div>
                <div class="info-block">
                    <div class="label">الحالة</div>
                    <div class="value">
                        <span class="payment-badge"><?php echo $payment_status_text; ?></span>
                    </div>
                </div>
            </div>
            
            <!-- ==================== جدول المنتجات ==================== -->
            <div class="items-section">
                <table>
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th width="40%">المنتج</th>
                            <th width="8%">الوحدة</th>
                            <th width="8%">الكمية</th>
                            <th width="10%">السعر</th>
                            <th width="10%">الخصم</th>
                            <th width="12%">الإجمالي</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 1;
                        while ($item = $items_result->fetch_assoc()): 
                        ?>
                        <tr>
                            <td><?php echo $counter++; ?></td>
                            <td class="product-name"><?php echo $item['product_name']; ?></td>
                            <td><?php echo $item['product_unit']; ?></td>
                            <td><?php echo number_format($item['product_quantity']); ?></td>
                            <td><?php echo formatNumber($item['product_price']); ?></td>
                            <td><?php echo formatNumber($item['product_discount']); ?></td>
                            <td class="amount-col"><?php echo formatNumber($item['product_total']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- ==================== ملخص مصغر ==================== -->
            <!-- ==================== ملخص الفاتورة بعرض كامل ==================== -->
            <div class="summary-fullwidth">
                <div class="summary-row">
                    <div class="summary-cell">
                        <span class="summary-cell-label">الإجمالي الفرعي</span>
                        <span class="summary-cell-value"><?php echo formatNumber($invoice['subtotal']); ?> ر.ي</span>
                    </div>
                    <div class="summary-cell">
                        <span class="summary-cell-label">إجمالي الخصم</span>
                        <span class="summary-cell-value discount">- <?php echo formatNumber($invoice['total_discount']); ?> ر.ي</span>
                    </div>
                    <div class="summary-cell">
                        <span class="summary-cell-label">الضريبة (15%)</span>
                        <span class="summary-cell-value"><?php echo formatNumber($invoice['total_tax']); ?> ر.ي</span>
                    </div>
                    <div class="summary-cell">
                        <span class="summary-cell-label">المبلغ المدفوع</span>
                        <span class="summary-cell-value paid"><?php echo formatNumber($invoice['paid_amount']); ?> ر.ي</span>
                    </div>
                    
                    
                </div>
                <div style="display: flex; justify-content: space-between; margin-top: 10px;">
                    <div class="summary-cell highlight">
                            <span class="summary-cell-label">الإجمالي النهائي</span>
                            <span class="summary-cell-value total"><?php echo formatNumber($invoice['grand_total']); ?> ر.ي</span>
                                <div class='amount-words'>  <?php echo numberToWords($invoice['grand_total']); ?></div>
                    </div>
                    <?php if ($invoice['remaining_amount'] > 0): ?>
                        <div class="summary-cell remaining">
                            <span class="summary-cell-label">المتبقي</span>
                            <span class="summary-cell-value remaining"><?php echo formatNumber($invoice['remaining_amount']); ?> ر.ي    </span>
                            <div class='amount-words'>  <?php echo numberToWords($invoice['grand_total']); ?></div>
                        </div>
                        <?php endif; ?>
                </div>
                
                <!-- المبلغ بالكتابة في سطر منفصل -->
                
            </div>
            
            <!-- ==================== ملاحظات ==================== -->
            <?php if (!empty($invoice['notes'])): ?>
            <div class="notes-mini">
                <i class="fas fa-sticky-note"></i> <?php echo nl2br($invoice['notes']); ?>
            </div>
            <?php endif; ?>
            
            <!-- ==================== توقيعات مصغرة ==================== -->
            <div class="signature-mini">
                <div class="signature-mini-box">
                    <div>توقيع المستلم</div>
                    <br>
                    <div class="signature-mini-line"></div>
                    
                </div>
                <div class="signature-mini-box">
                    <div>ختم الشركة</div>
                    <br>
                    <div class="signature-mini-line"></div>
                </div>
                <div class="signature-mini-box">
                    <div>المسؤول المالي</div>
                    <br>
                    <div class="signature-mini-line"></div>
                </div>
            </div>
            
            <!-- ==================== تذييل ==================== -->
            <div class="footer-mini">
                شكراً لتعاملكم - نظام إدارة المخازن |تم الإنشاء بواسطة: <?php echo $created_by_name; ?> |تم الطباعة بواسطة: <?php echo $_SESSION['full_name']; ?>|جميع الحقوق محفوظة
            </div>
        </div>
    </div>
    <script>
        <?php if($autoPrint): ?>
            // طباعة تلقائية عند تحميل الصفحة
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 500);
            };
            
            // بعد الطباعة، أغلق النافذة تلقائياً
            window.onafterprint = function() {
                setTimeout(function() {
                    window.close();
                }, 1000);
            };
        <?php endif; ?>
    </script>
</body>
</html>