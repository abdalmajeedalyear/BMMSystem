<?php
session_start();
include "config.php";

// التحقق من وجود معرف الفاتورة
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: invoice_page.php");
    exit();
}

$invoice_id = intval($_GET['id']);

// استعلام للحصول على بيانات الفاتورة مع معلومات العميل
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
    <title>فاتورة <?php echo $invoice['invoice_number']; ?> - نظام المخازن</title>
    
    <!-- Font Awesome محلي -->
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">
    
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
            max-width: 1100px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        /* رأس الصفحة */
        .header {
            background: linear-gradient(135deg, #2196F3, #1976D2);
            color: white;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .status-badge {
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: bold;
            background: rgba(255,255,255,0.2);
        }
        
        /* أزرار التحكم */
        .actions {
            padding: 15px 30px;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
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
            background: #2196F3;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1976D2;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(33,150,243,0.3);
        }
        
        .btn-warning {
            background: #FF9800;
            color: white;
        }
        
        .btn-warning:hover {
            background: #F57C00;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: #f44336;
            color: white;
        }
        
        .btn-danger:hover {
            background: #d32f2f;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        /* محتوى الفاتورة */
        .invoice-content {
            padding: 30px;
        }
        
        /* معلومات الفاتورة */
        .info-section {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
        }
        
        .info-box {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .info-box h3 {
            font-size: 16px;
            color: #2196F3;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #2196F3;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .info-label {
            width: 90px;
            color: #666;
        }
        
        .info-value {
            flex: 1;
            font-weight: bold;
            color: #333;
        }
        
        /* جدول المنتجات */
        .products-section {
            margin-bottom: 30px;
        }
        
        .products-section h2 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        th {
            background: #2196F3;
            color: white;
            padding: 15px;
            text-align: center;
            font-size: 14px;
        }
        
        td {
            padding: 15px;
            text-align: center;
            border-bottom: 1px solid #dee2e6;
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        tr:hover td {
            background: #f8f9fa;
        }
        
        .item-total {
            font-weight: bold;
            color: #2196F3;
        }
        
        /* ملخص المبالغ */
        .summary-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .summary-row:last-child {
            border-bottom: none;
        }
        
        .summary-label {
            color: #666;
            font-weight: 500;
        }
        
        .summary-value {
            font-weight: bold;
        }
        
        .grand-total {
            font-size: 18px;
            color: #2196F3;
        }
        
        /* الملاحظات */
        .notes-section {
            margin-top: 30px;
            padding: 20px;
            background: #fff3cd;
            border-right: 4px solid #ffc107;
            border-radius: 8px;
        }
        
        .notes-section h4 {
            color: #856404;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .notes-section p {
            color: #856404;
            line-height: 1.6;
        }
        
        /* توقيع الفاتورة */
        .signature {
            margin-top: 50px;
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
        }
        
        /* رسالة عدم وجود منتجات */
        .no-items {
            text-align: center;
            padding: 50px;
            color: #999;
        }
        
        .no-items i {
            font-size: 50px;
            margin-bottom: 15px;
            opacity: 0.3;
        }
        
        /* للطباعة */
        @media print {
            .actions, .btn {
                display: none !important;
            }
            
            body {
                background: white;
                padding: 0;
            }
            
            .container {
                box-shadow: none;
            }
            
            .header {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            th {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
        
        /* تجاوب */
        @media (max-width: 768px) {
            .info-section {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .actions {
                justify-content: center;
            }
            
            .signature {
                flex-direction: column;
                align-items: center;
                gap: 30px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- رأس الفاتورة -->
        <div class="header">
            <div>
                <h1><i class="fas fa-file-invoice" style="margin-left: 10px;"></i>فاتورة رقم: <?php echo $invoice['invoice_number']; ?></h1>
                <p>تاريخ الإنشاء: <?php echo date('Y-m-d', strtotime($invoice['created_at'])); ?></p>
            </div>
            <div class="status-badge">
                <i class="fas <?php echo $invoice['payment_status'] == 'مدفوعة' ? 'fa-check-circle' : 'fa-clock'; ?>"></i>
                <?php echo $invoice['payment_status']; ?>
            </div>
        </div>
        
        <!-- أزرار التحكم -->
        <div class="actions">
            <a href="invoice_page.php" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i>
                العودة للقائمة
            </a>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i>
                طباعة
            </button>
            <a href="edit_invoice.php?id=<?php echo $invoice_id; ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i>
                تعديل
            </a>
            <button onclick="deleteInvoice(<?php echo $invoice_id; ?>, '<?php echo $invoice['invoice_number']; ?>')" class="btn btn-danger">
                <i class="fas fa-trash"></i>
                حذف
            </button>
        </div>
        
        <!-- محتوى الفاتورة -->
        <div class="invoice-content">
            <!-- معلومات الفاتورة والعميل -->
            <div class="info-section">
                <!-- معلومات العميل -->
                <div class="info-box">
                    <h3><i class="fas fa-user"></i> بيانات العميل</h3>
                    <div class="info-row">
                        <span class="info-label">الاسم:</span>
                        <span class="info-value"><?php echo $invoice['customer_name'] ?? 'عميل نقدي'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">الهاتف:</span>
                        <span class="info-value"><?php echo $invoice['customer_phone'] ?? '—'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">العنوان:</span>
                        <span class="info-value"><?php echo $invoice['customer_address'] ?? '—'; ?></span>
                    </div>
                </div>
                
                <!-- معلومات الفاتورة -->
                <div class="info-box">
                    <h3><i class="fas fa-file-invoice"></i> بيانات الفاتورة</h3>
                    <div class="info-row">
                        <span class="info-label">التاريخ:</span>
                        <span class="info-value"><?php echo $invoice['invoice_date']; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">طريقة الدفع:</span>
                        <span class="info-value"><?php echo $invoice['payment_method']; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">عدد الأصناف:</span>
                        <span class="info-value"><?php echo $items_count; ?></span>
                    </div>
                </div>
                
                <!-- ملخص المبالغ السريع -->
                <div class="info-box">
                    <h3><i class="fas fa-money-bill"></i> ملخص سريع</h3>
                    <div class="info-row">
                        <span class="info-label">الإجمالي:</span>
                        <span class="info-value" style="color: #2196F3;"><?php echo formatNumber($invoice['grand_total']); ?> ر.ي</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">الخصم:</span>
                        <span class="info-value" style="color: #f44336;"><?php echo formatNumber($invoice['total_discount']); ?> ر.ي</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">الضريبة:</span>
                        <span class="info-value"><?php echo formatNumber($invoice['total_tax']); ?> ر.ي</span>
                    </div>
                </div>
            </div>
            
            <!-- منتجات الفاتورة -->
            <div class="products-section">
                <h2><i class="fas fa-boxes" style="color: #2196F3;"></i> منتجات الفاتورة</h2>
                
                <?php if ($items_count > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>اسم المنتج</th>
                            <th>الوحدة</th>
                            <th>الكمية</th>
                            <th>سعر الوحدة</th>
                            <th>الخصم</th>
                            <th>الإجمالي</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 1;
                        while ($item = $items_result->fetch_assoc()): 
                        ?>
                        <tr>
                            <td><?php echo $counter++; ?></td>
                            <td><strong><?php echo $item['product_name']; ?></strong></td>
                            <td><?php echo $item['product_unit']; ?></td>
                            <td><?php echo number_format($item['product_quantity']); ?></td>
                            <td><?php echo formatNumber($item['product_price']); ?></td>
                            <td><?php echo formatNumber($item['product_discount']); ?></td>
                            <td class="item-total"><?php echo formatNumber($item['product_total']); ?> ر.ي</td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="no-items">
                    <i class="fas fa-box-open"></i>
                    <p>لا توجد منتجات في هذه الفاتورة</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- ملخص المبالغ التفصيلي -->
            <div class="summary-section">
                <div class="summary-row">
                    <span class="summary-label">الإجمالي الفرعي:</span>
                    <span class="summary-value"><?php echo formatNumber($invoice['subtotal']); ?> ر.ي</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">إجمالي الخصم:</span>
                    <span class="summary-value" style="color: #f44336;">- <?php echo formatNumber($invoice['total_discount']); ?> ر.ي</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">ضريبة القيمة المضافة (15%):</span>
                    <span class="summary-value"><?php echo formatNumber($invoice['total_tax']); ?> ر.ي</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label grand-total">الإجمالي النهائي:</span>
                    <span class="summary-value grand-total"><?php echo formatNumber($invoice['grand_total']); ?> ر.ي</span>
                </div>
            </div>
            
            <!-- الملاحظات -->
            <?php if (!empty($invoice['notes'])): ?>
            <div class="notes-section">
                <h4><i class="fas fa-sticky-note"></i> ملاحظات</h4>
                <p><?php echo nl2br($invoice['notes']); ?></p>
            </div>
            <?php endif; ?>
            
            <!-- توقيع الفاتورة -->
            <div class="signature">
                <div class="signature-box">
                    <div class="signature-line">توقيع المستلم</div>
                </div>
                <div class="signature-box">
                    <div class="signature-line">ختم الشركة</div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // دالة حذف الفاتورة
        function deleteInvoice(invoiceId, invoiceNumber) {
            if (confirm('هل أنت متأكد من حذف الفاتورة رقم: ' + invoiceNumber + '؟')) {
                window.location.href = 'delete_invoice.php?id=' + invoiceId;
            }
        }
        
        // اختصارات لوحة المفاتيح
        document.addEventListener('keydown', function(e) {
            // ESC للعودة
            if (e.key === 'Escape') {
                window.location.href = 'invoice_page.php';
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