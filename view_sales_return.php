<?php
session_start();
include "config.php";

// التحقق من وجود معرف المرتجع
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: returns_page.php");
    exit();
}

$return_id = intval($_GET['id']);

// استعلام للحصول على بيانات مرتجع المبيعات
$sql = "SELECT 
            r.return_id,
            r.return_number,
            r.return_date,
            r.invoice_id,
            i.invoice_number as original_invoice,
            r.customer_id,
            r.customer_name,
            r.total_amount,
            r.reason,
            r.created_at,
            r.created_by
        FROM sales_returns r
        LEFT JOIN invoices i ON r.invoice_id = i.invoice_id
        WHERE r.return_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $return_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['alert'] = [
        'type' => 'error',
        'message' => "❌ مرتجع المبيعات غير موجود"
    ];
    header("Location: returns_page.php");
    exit();
}

$return = $result->fetch_assoc();

// استعلام للحصول على منتجات المرتجع
$items_sql = "SELECT 
                item_id,
                product_name,
                product_unit,
                return_quantity,
                return_price,
                return_total
            FROM sales_return_items 
            WHERE return_id = ?
            ORDER BY item_id ASC";

$items_stmt = $conn->prepare($items_sql);
$items_stmt->bind_param("i", $return_id);
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
    <title>فاتورة مرتجع مبيعات <?php echo $return['return_number']; ?> - نظام المخازن</title>
    
    <!-- Font Awesome محلي -->
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">
    
    <style>
        /* نفس تنسيقات فاتورة المبيعات بالكامل */
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
            outline: 2px solid #FF9800; 
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
            background: #FF9800; 
            color: white; 
        }
        
        .btn-primary:hover { 
            background: #F57C00; 
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
            background: #fff3e0; 
            padding: 20px; 
            border: 1px solid #FF9800; 
            border-radius: 8px; 
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
            background: #fff3e0;
            color: #FF9800;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: bold;
            margin-right: auto;
            margin-left: 150px;
            margin-bottom: -20px;
            border: 3px solid #FF9800;
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
        
        .status-return {
            background: #fff3e0;
            color: #FF9800;
            border: 1px solid #FF9800;
        }
        
        .amount-highlight {
            font-weight: bold;
            color: #FF9800;
            font-size: 18px;
        }
        
        .items-count {
            background: #FF9800;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-right: 10px;
        }
        
        .product-row:hover {
            background: #fff3e0;
        }
        
        .product-total {
            font-weight: bold;
            color: #FF9800;
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
    <a href="returns_page.php" class="back-link">
        <i class="fas fa-arrow-right"></i>
        <span>العودة للمرتجعات</span>
    </a>
    <div class="date-badge">
        <i class="far fa-calendar-alt ml-1"></i> <?php echo date('Y-m-d'); ?>
    </div>
</div>

<div class="container">
    
    <h1>
        <i class="fas fa-undo-alt" style="color: #FF9800; margin-left: 10px;"></i>
        فاتورة مرتجع مبيعات رقم: <?php echo $return['return_number']; ?>
        <span class="items-count"><?php echo $items_count; ?> منتج</span>
    </h1>
    
    <!-- ==================== رأس الفاتورة (بنفس تنسيق فاتورة المبيعات) ==================== -->
    <div class="invoice-header">
        <!-- رقم المرتجع -->
        <div class="header-item">
            <label>رقم المرتجع</label>
            <div class="value"><?php echo $return['return_number']; ?></div>
        </div>
        
        <!-- تاريخ المرتجع -->
        <div class="header-item">
            <label>تاريخ المرتجع</label>
            <div class="value"><?php echo $return['return_date']; ?></div>
        </div>
        
        <!-- رقم الفاتورة الأصلية (يمتد على عمودين) -->
        <div class="header-item col-span-2">
            <label>الفاتورة الأصلية</label>
            <div class="value">
                <i class="fas fa-file-invoice" style="margin-left: 8px; color: #FF9800;"></i>
                <?php echo $return['original_invoice'] ?? 'غير محدد'; ?>
            </div>
        </div>
        
        <!-- اسم العميل (يمتد على عمودين) -->
        <div class="header-item col-span-2">
            <label>اسم العميل</label>
            <div class="value">
                <i class="fas fa-user" style="margin-left: 8px; color: #FF9800;"></i>
                <?php echo $return['customer_name'] ?? 'غير محدد'; ?>
            </div>
        </div>
        
        <!-- الحالة -->
        <div class="header-item">
            <label>الحالة</label>
            <div class="value">
                <span class="status-badge status-return">
                    <i class="fas fa-undo-alt"></i> مرتجع
                </span>
            </div>
        </div>
        
        <!-- تاريخ الإنشاء -->
        <div class="header-item">
            <label>تاريخ الإنشاء</label>
            <div class="value">
                <i class="far fa-calendar-alt" style="margin-left: 8px; color: #FF9800;"></i>
                <?php echo date('Y-m-d', strtotime($return['created_at'])); ?>
            </div>
        </div>
    </div>
    
    <!-- ==================== شريط الأدوات ==================== -->
    <div class="toolbar">
        <div class="shortcuts">
            <span>بواسطة: <?php echo $return['created_by'] ?? 'system'; ?></span>
        </div>
    </div>
    
    <!-- ==================== جدول المنتجات المرتجعة ==================== -->
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>اسم المنتج</th>
                <th>الوحدة</th>
                <th>الكمية المرتجعة</th>
                <th>سعر الوحدة</th>
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
                    <td class="text-center"><?php echo number_format($item['return_quantity']); ?></td>
                    <td class="text-center"><?php echo formatNumber($item['return_price']); ?> ر.ي</td>
                    <td class="text-center product-total"><?php echo formatNumber($item['return_total']); ?> ر.ي</td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 30px; color: #999;">
                        <i class="fas fa-box-open" style="font-size: 24px; margin-bottom: 10px;"></i>
                        <br>لا توجد منتجات في هذا المرتجع
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- ==================== الملخص ==================== -->
    <div class="summary-section">
        <div>
            <label style="font-weight: bold; display: block; margin-bottom: 10px;">
                <i class="fas fa-sticky-note" style="color: #FF9800;"></i>
                سبب المرتجع
            </label>
            <?php if (!empty($return['reason'])): ?>
                <div class="notes-section">
                    <i class="fas fa-quote-right"></i>
                    <?php echo nl2br($return['reason']); ?>
                </div>
            <?php else: ?>
                <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; color: #999; text-align: center;">
                    <i class="fas fa-sticky-note"></i>
                    لا يوجد سبب محدد
                </div>
            <?php endif; ?>
        </div>
        
        <div class="totals">
            <h3 style="margin-top:0; color: #FF9800;">
                <i class="fas fa-calculator"></i>
                ملخص المرتجع
            </h3>
            <div style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #ddd;">
                <span>عدد الأصناف:</span>
                <span class="amount-highlight"><?php echo $items_count; ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; padding:10px 0; font-size:18px; font-weight:bold;">
                <span>إجمالي المرتجع:</span>
                <span class="amount-highlight"><?php echo formatNumber($return['total_amount']); ?> ر.ي</span>
            </div>
            
            <div style="margin-top:15px; padding:10px; background:#fff3e0; border-radius:4px; font-size:13px; color:#FF9800;">
                <i class="fas fa-info-circle"></i>
                تم إضافة المنتجات إلى المخزون بتاريخ <?php echo date('Y-m-d', strtotime($return['created_at'])); ?>
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
        <button type="button" onclick="window.print()" class="btn btn-primary">
            <i class="fas fa-print"></i> طباعة
        </button>
        <div>
            <button onclick="goBack()" class="btn btn-secondary" style="margin-left:10px;">
                <i class="fas fa-arrow-right"></i> رجوع
            </button>
        </div>
    </div>
    
</div>

<script>
    // ==================== دالة الرجوع ====================
    function goBack() {
        window.location.href = 'returns_page.php';
    }
    
    // ==================== اختصارات لوحة المفاتيح ====================
    document.addEventListener('keydown', function(e) {
        // ESC للعودة للقائمة
        if (e.key === 'Escape') {
            window.location.href = 'returns_page.php';
        }
        
        // Ctrl + P للطباعة
        if (e.ctrlKey && e.key === 'p') {
            e.preventDefault();
            window.print();
        }
    });
    
    // ==================== قائمة جانبية قابلة للطي ====================
    document.getElementById('menuToggle').addEventListener('click', function() {
        document.querySelector('.sidebar').classList.toggle('collapsed');
        document.querySelector('.main-content').classList.toggle('expanded');
    });
</script>

</body>
</html>