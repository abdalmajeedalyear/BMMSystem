<?php
session_start();
include "config.php";

// التحقق من وجود معرف الفاتورة
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: invoice_page.php");
    exit();
}

$invoice_id = intval($_GET['id']);

// استعلام للحصول على بيانات فاتورة المبيعات مع معلومات العميل - تم إضافة paid_amount و remaining_amount
$sql = "SELECT 
            i.invoice_id,
            i.invoice_number,
            i.invoice_date,
            c.customer_id,
            c.customer_name,
            c.customer_phone,
            c.customer_address,
            i.subtotal,
            i.total_discount,
            i.total_tax,
            i.grand_total,
            i.paid_amount,
            i.remaining_amount,
            i.payment_method,
            i.payment_status,
            i.notes,
            i.created_at
        FROM invoices i
        LEFT JOIN customers c ON i.customer_id = c.customer_id
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

// استعلام للحصول على منتجات الفاتورة - تم التعديل: id -> item_id
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
$items_count = $items_result->num_rows;

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
    <title>فاتورة مبيعات <?php echo $invoice['invoice_number']; ?> - نظام المخازن</title>
    
    <!-- Font Awesome محلي -->
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">
    
    <style>
        /* نفس تنسيقات ملف New_sales_invoice.php */
        body { 
            font-family: 'Tahoma', sans-serif; 
            background: #f5f5f5; 
            margin: 0; 
            padding: 20px; 
        }
        
        .container { 
            max-width: 1200px;
            margin-top: 25px;
            margin-right: 90px;
            background: white; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }
        
        h1 { 
            color: #333; 
            margin-bottom: 20px; 
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* تنسيق الجدول */
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 15px 0; 
        }
        
        th { 
            background: #f0f0f0; 
            padding: 12px; 
            text-align: center; 
            border: 1px solid #ddd; 
        }
        
        td { 
            border: 1px solid #ddd; 
            padding: 8px; 
        }
        
        input, select { 
            width: 100%; 
            padding: 8px; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
            box-sizing: border-box; 
        }
        
        input:focus, select:focus { 
            outline: 2px solid #2196F3; 
        }
        
        input[readonly] {
            background: #f5f5f5;
            border: 1px solid #ddd;
        }
        
        /* الأزرار */
        .btn { 
            padding: 10px 20px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            font-weight: bold; 
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary { 
            background: #2196F3; 
            color: white; 
        }
        
        .btn-primary:hover { 
            background: #1976D2; 
        }
        
        .btn-success { 
            background: #4CAF50; 
            color: white; 
        }
        
        .btn-success:hover { 
            background: #388E3C; 
        }
        
        .btn-danger { 
            background: #f44336; 
            color: white; 
        }
        
        .btn-danger:hover { 
            background: #d32f2f; 
        }
        
        .btn-warning { 
            background: #ff9800; 
            color: white; 
        }
        
        .btn-warning:hover { 
            background: #f57c00; 
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        /* رأس الفاتورة */
        .invoice-header {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }
        
        .header-item { 
            display: flex; 
            flex-direction: column; 
        }
        
        .header-item label { 
            font-size: 12px; 
            color: #666; 
            margin-bottom: 5px; 
            font-weight: bold; 
        }
        
        .header-item .value { 
            background: #f5f5f5; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
            padding: 10px; 
            font-size: 14px; 
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .col-span-2 { 
            grid-column: span 2; 
        }
        
        /* شريط الأدوات */
        .toolbar {
            background: #f9f9f9;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-bottom: none;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .shortcuts span {
            background: #eee;
            padding: 3px 6px;
            border-radius: 4px;
            margin: 0 5px;
            font-size: 12px;
        }
        
        /* الملخص */
        .summary-section { 
            margin-top: 20px; 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 20px; 
        }
        
        .totals { 
            background: #f0f8ff; 
            padding: 20px; 
            border: 1px solid #2196F3; 
            border-radius: 8px; 
        }
        
        /* تنسيق خاص لحقول الدفع */
        .payment-details {
            margin-top: 20px;
            padding: 20px;
            background: #f0f8ff;
            border-radius: 8px;
            border: 1px solid #2196F3;
        }
        
        .payment-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .payment-item {
            display: flex;
            flex-direction: column;
        }
        
        .payment-item label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .payment-item .value {
            font-size: 18px;
            font-weight: bold;
            padding: 10px;
            border-radius: 4px;
        }
        
        .paid-value {
            background: #e8f5e9;
            color: #4CAF50;
            border: 1px solid #4CAF50;
        }
        
        .remaining-value {
            background: #ffebee;
            color: #f44336;
            border: 1px solid #f44336;
        }
        
        /* زر الحذف */
        .delete-btn {
            background: none;
            border: none;
            color: #f44336;
            cursor: pointer;
            font-size: 16px;
        }
        
        /* أزرار التحكم السفلية */
        .footer-actions { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-top: 20px; 
            padding-top: 20px; 
            border-top: 1px solid #ddd; 
        }
        
        /* تنسيق شريط العنوان */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: white;
            padding: 10px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-decoration: none;
            color: #333;
            margin-right: 90px;
            margin-bottom: -20px;
        }
        
        .back-link:hover {
            background: #f5f5f5;
        }
        
        .date-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: bold;
            margin-right: auto;
            margin-left: 150px;
            margin-bottom: -20px;
            border: 3px solid #3a94dd;
        }
        
        .head_link {
            display: flex;
            align-items: center;
            width: 100%;
        }
        
        /* تنسيقات إضافية */
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
        }
        
        .status-paid {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-unpaid {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .status-partial {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        
        .amount-highlight {
            font-weight: bold;
            color: #2196F3;
            font-size: 18px;
        }
        
        .discount-highlight {
            color: #f44336;
        }
        
        .tax-highlight {
            color: #2196F3;
        }
        
        .grand-total {
            font-size: 20px;
            font-weight: bold;
            color: #4CAF50;
        }
        
        .items-count {
            background: #2196F3;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-right: 10px;
        }
        
        .product-row:hover {
            background: #f5f5f5;
        }
        
        .product-total {
            font-weight: bold;
            color: #2196F3;
        }
        
        .notes-section {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            border-right: 4px solid #ffc107;
            color: #856404;
        }
        
        .notes-section i {
            margin-left: 8px;
            color: #ffc107;
        }
        
        .signature {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
            padding: 20px 0;
        }
        
        .signature-box {
            text-align: center;
            width: 200px;
        }
        
        .signature-line {
            border-top: 2px dashed #999;
            padding-top: 10px;
            margin-top: 10px;
            color: #666;
            font-size: 14px;
        }
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: var(--radius-sm);
            border: none;
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        
        .action-btn:hover {
            background: var(--bg-color);
            color: var(--primary-color);
        }
        
        @media (max-width: 768px) {
            .container {
                margin-right: 10px;
                margin-left: 10px;
            }
            
            .invoice-header {
                grid-template-columns: 1fr;
            }
            
            .col-span-2 {
                grid-column: span 1;
            }
            
            .summary-section {
                grid-template-columns: 1fr;
            }
            
            .head_link {
                flex-direction: column;
                gap: 10px;
            }
            
            .date-badge {
                margin-left: 0;
                margin-right: 0;
            }
            
            .payment-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media print {
            .back-link,
            .footer-actions,
            .btn,
            .head_link {
                display: none !important;
            }
            
            .container {
                margin: 0;
                box-shadow: none;
            }
            
            .invoice-header {
                break-inside: avoid;
            }
        }

        
    </style>
</head>
<body>

<!-- رسائل التنبيه (إذا وجدت) -->
<?php if (isset($_SESSION['alert'])): ?>
    <div class="alert alert-<?php echo $_SESSION['alert']['type']; ?>" id="alertMessage" style="position: fixed; top: 20px; right: 20px; padding: 15px 25px; border-radius: 4px; color: white; font-weight: bold; z-index: 9999; background: <?php echo $_SESSION['alert']['type'] == 'success' ? '#4CAF50' : '#f44336'; ?>;">
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

<div class="head_link">
    <a href="#" onclick="goback()" class="back-link">
        <i class="fas fa-arrow-right"></i>
        <span>رجوع </span>
    </a>
    <div class="date-badge">
        <i class="far fa-calendar-alt ml-1"></i> <?php echo date('Y-m-d'); ?>
    </div>
</div>

<div class="container">
    
    <h1>
        <i class="fas fa-shopping-cart" style="color: #2196F3; margin-left: 10px;"></i>
        فاتورة مبيعات رقم: <?php echo $invoice['invoice_number']; ?>
        <span class="items-count"><?php echo $items_count; ?> منتج</span>
    </h1>
    
    <!-- ==================== رأس الفاتورة (مع إضافة الحقول الجديدة) ==================== -->
    <div class="invoice-header">
        <!-- رقم الفاتورة -->
        <div class="header-item">
            <label>رقم الفاتورة</label>
            <div class="value"><?php echo $invoice['invoice_number']; ?></div>
        </div>
        
        <!-- التاريخ -->
        <div class="header-item">
            <label>تاريخ الفاتورة</label>
            <div class="value"><?php echo $invoice['invoice_date']; ?></div>
        </div>
        
        <!-- اسم العميل (يمتد على عمودين) -->
        <div class="header-item col-span-2">
            <label>اسم العميل</label>
            <div class="value">
                <i class="fas fa-user" style="margin-left: 8px; color: #2196F3;"></i>
                <?php echo $invoice['customer_name'] ?? 'عميل نقدي'; ?>
            </div>
        </div>
        
        <!-- رقم هاتف العميل (يمتد على عمودين) -->
        <div class="header-item col-span-2">
            <label>رقم هاتف العميل</label>
            <div class="value">
                <i class="fas fa-phone" style="margin-left: 8px; color: #2196F3;"></i>
                <?php echo $invoice['customer_phone'] ?: '—'; ?>
            </div>
        </div>
        
        <!-- طريقة الدفع -->
        <div class="header-item">
            <label>طريقة الدفع</label>
            <div class="value"><?php echo $invoice['payment_method']; ?></div>
        </div>
        
        <!-- حالة الدفع -->
        <div class="header-item">
            <label>حالة الدفع</label>
            <div class="value">
                <span class="status-badge status-<?php 
                    echo $invoice['payment_status'] == 'مدفوعة' ? 'paid' : 
                         ($invoice['payment_status'] == 'غير مدفوعة' ? 'unpaid' : 'partial'); 
                ?>">
                    <?php echo $invoice['payment_status']; ?>
                </span>
            </div>
        </div>
        
        <!-- ===== الحقول الجديدة: المبلغ المسلم والمتبقي ===== -->
        <!-- المبلغ المسلم -->
        <div class="header-item">
            <label>المبلغ المسلم</label>
            <div class="value" style="background: #e8f5e9; color: #4CAF50; font-weight: bold;">
                <i class="fas fa-check-circle" style="color: #4CAF50;"></i>
                <?php echo formatNumber($invoice['paid_amount'] ?? 0); ?> ر.ي
            </div>
        </div>
        
        <!-- المبلغ المتبقي -->
        <div class="header-item">
            <label>المبلغ المتبقي</label>
            <div class="value" style="background: #ffebee; color: #f44336; font-weight: bold;">
                <i class="fas fa-exclamation-circle" style="color: #f44336;"></i>
                <?php echo formatNumber($invoice['remaining_amount'] ?? $invoice['grand_total']); ?> ر.ي
            </div>
        </div>
    </div>
    
    <!-- ==================== شريط الأدوات (للإجراءات) ==================== -->
    <div class="toolbar">
        <div class="shortcuts">
            <span>تم الإنشاء: <?php echo date('Y-m-d', strtotime($invoice['created_at'])); ?></span>
        </div>
    </div>
    
    <!-- ==================== جدول المنتجات ==================== -->
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>اسم المنتج</th>
                <th>الوحدة</th>
                <th>الكمية</th>
                <th>السعر</th>
                <th>الخصم</th>
                <th>الإجمالي</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($items_count > 0): ?>
                <?php $counter = 1; ?>
                <?php while ($item = $items_result->fetch_assoc()): ?>
                <tr class="product-row">
                    <td class="text-center"><?php echo $counter++; ?></td>
                    <td><strong><?php echo $item['product_name']; ?></strong></td>
                    <td><?php echo $item['product_unit']; ?></td>
                    <td class="text-center"><?php echo $item['product_quantity']; ?></td>
                    <td class="text-center"><?php echo formatNumber($item['product_price']); ?> ر.ي</td>
                    <td class="text-center discount-highlight"><?php echo formatNumber($item['product_discount']); ?> ر.ي</td>
                    <td class="text-center product-total"><?php echo formatNumber($item['product_total']); ?> ر.ي</td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 30px; color: #999;">
                        <i class="fas fa-box-open" style="font-size: 24px; margin-bottom: 10px;"></i>
                        <br>لا توجد منتجات في هذه الفاتورة
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- ==================== الملخص ==================== -->
    <div class="summary-section">
        <div>
            <label style="font-weight: bold; display: block; margin-bottom: 10px;">
                <i class="fas fa-sticky-note" style="color: #2196F3;"></i>
                ملاحظات الفاتورة
            </label>
            <?php if (!empty($invoice['notes'])): ?>
                <div class="notes-section">
                    <i class="fas fa-quote-right"></i>
                    <?php echo nl2br($invoice['notes']); ?>
                </div>
            <?php else: ?>
                <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; color: #999; text-align: center;">
                    <i class="fas fa-sticky-note"></i>
                    لا توجد ملاحظات
                </div>
            <?php endif; ?>
        </div>
        
        <div class="totals">
            <h3 style="margin-top:0; color: #2196F3;">
                <i class="fas fa-calculator"></i>
                ملخص الفاتورة
            </h3>
            <div style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #ddd;">
                <span>الإجمالي الفرعي:</span>
                <span class="amount-highlight"><?php echo formatNumber($invoice['subtotal']); ?> ر.ي</span>
            </div>
            <div style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #ddd;">
                <span>إجمالي الخصم:</span>
                <span class="discount-highlight">- <?php echo formatNumber($invoice['total_discount']); ?> ر.ي</span>
            </div>
            <div style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #ddd;">
                <span>الضريبة (15%):</span>
                <span class="tax-highlight"><?php echo formatNumber($invoice['total_tax']); ?> ر.ي</span>
            </div>
            <div style="display:flex; justify-content:space-between; padding:15px 0; font-size:18px; font-weight:bold;">
                <span>الإجمالي النهائي:</span>
                <span class="grand-total"><?php echo formatNumber($invoice['grand_total']); ?> ر.ي</span>
            </div>
            
            <!-- إضافة ملخص الدفع -->
            <div style="margin-top: 20px; padding: 15px; background: #f0f8ff; border-radius: 8px; border: 1px solid #2196F3;">
                <h4 style="margin-bottom: 15px; color: #2196F3;">ملخص الدفع</h4>
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span>المبلغ المدفوع:</span>
                    <span style="font-weight: bold; color: #4CAF50;"><?php echo formatNumber($invoice['paid_amount'] ?? 0); ?> ر.ي</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span>المبلغ المتبقي:</span>
                    <span style="font-weight: bold; color: <?php echo ($invoice['remaining_amount'] ?? $invoice['grand_total']) > 0 ? '#f44336' : '#4CAF50'; ?>;">
                        <?php echo formatNumber($invoice['remaining_amount'] ?? $invoice['grand_total']); ?> ر.ي
                    </span>
                </div>
            </div>
            
            <div style="margin-top:15px; padding:10px; background:#e8f5e9; border-radius:4px; font-size:13px; color:#2e7d32;">
                <i class="fas fa-info-circle"></i>
                تم خصم المنتجات من المخزون بتاريخ <?php echo date('Y-m-d', strtotime($invoice['created_at'])); ?>
            </div>
        </div>
    </div>
    
    <!-- ==================== توقيع الفاتورة ==================== -->
    <div class="signature">
        <div class="signature-box">
            <div class="signature-line">توقيع المستلم</div>
        </div>
        <div class="signature-box">
            <div class="signature-line">ختم الشركة</div>
        </div>
    </div>
    
    <!-- ==================== أزرار التحكم ==================== -->
    <div class="footer-actions">
        <button class="action-btn" onclick="printInvoice(<?php echo $invoice['invoice_id']; ?>)" title="طباعة">
            <i class="fas fa-print"></i>
        </button>
        <div>
            <?php if($_SESSION['full_name'] =='مدير النظام'){?>
                <button onclick="deleteInvoice(<?php echo $invoice_id; ?>, '<?php echo $invoice['invoice_number']; ?>')" class="btn btn-danger">
                    <i class="fas fa-trash"></i> حذف
                </button>
            <?php }else{ ?>
                <button style="display: none;" onclick="deleteInvoice(<?php echo $invoice_id; ?>, '<?php echo $invoice['invoice_number']; ?>')" class="btn btn-danger">
                    <i class="fas fa-trash"></i> حذف
                </button>
            <?php }?>
        </div>
    </div>
    
</div>

<script>
    function printInvoice(invoiceId) {
            window.open('print_sales_invoice.php?id=' + invoiceId, '_blank');
        }
    // ==================== حذف الفاتورة ====================
    function deleteInvoice(invoiceId, invoiceNumber) {
        if (confirm('هل أنت متأكد من حذف الفاتورة رقم: ' + invoiceNumber + '؟')) {
            window.location.href = 'delete_invoice.php?id=' + invoiceId;
        }
    }
    
    // ==================== اختصارات لوحة المفاتيح ====================
    document.addEventListener('keydown', function(e) {
        // ESC للعودة للقائمة
        if (e.key === 'Escape') {
            window.location.href = 'invoice_page.php';
        }
        
        // Ctrl + P للطباعة
        if (e.ctrlKey && e.key === 'p') {
            e.preventDefault();
            window.print();
        }
    });
    function goback(){
        window.history.back();
    }
</script>

</body>
</html>