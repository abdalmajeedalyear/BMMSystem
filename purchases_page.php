<?php
session_start();
include "config.php";

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: logout.php");
    exit();
}


// ==================== إحصائيات المشتريات ====================
$today = date('Y-m-d');
$first_day_month = date('Y-m-01');

// إجمالي المشتريات
$total_purchases = $conn->query("SELECT COALESCE(SUM(grand_total), 0) as total FROM purchases")->fetch_assoc()['total'];

// إجمالي المشتريات اليوم
$today_purchases = $conn->query("SELECT COALESCE(SUM(grand_total), 0) as total FROM purchases WHERE DATE(purchase_date) = '$today'")->fetch_assoc()['total'];

// عدد فواتير المشتريات الكلي
$total_invoices = $conn->query("SELECT COUNT(*) as count FROM purchases")->fetch_assoc()['count'];

// عدد فواتير المشتريات اليوم
$today_invoices = $conn->query("SELECT COUNT(*) as count FROM purchases WHERE DATE(purchase_date) = '$today'")->fetch_assoc()['count'];

// المشتريات المدفوعة
$paid_purchases = $conn->query("SELECT COUNT(*) as count, COALESCE(SUM(grand_total), 0) as total FROM purchases WHERE payment_status = 'مدفوعة'")->fetch_assoc();

// المشتريات غير المدفوعة
$unpaid_purchases = $conn->query("SELECT COUNT(*) as count, COALESCE(SUM(grand_total), 0) as total FROM purchases WHERE payment_status != 'مدفوعة'")->fetch_assoc();

// متوسط قيمة المشتريات
$avg_purchase = $conn->query("SELECT COALESCE(AVG(grand_total), 0) as avg FROM purchases")->fetch_assoc()['avg'];

// استعلام للحصول على فواتير المشتريات - تم تعديله: purchase_id -> id
$sql = "SELECT 
            p.id as purchase_id,
            p.purchase_number,
            p.purchase_date,
            p.supplier_name,
            p.supplier_id,
            p.supplier_phone,
            p.subtotal,
            p.total_discount,
            p.grand_total,
            p.payment_status,
            p.payment_method,
            p.notes,
            p.created_at,
            p.created_by,
            u.full_name as created_by_name
        FROM purchases p
        LEFT JOIN users u ON p.created_by = u.user_id
        ORDER BY p.purchase_date DESC, p.id DESC";

$result = $conn->query($sql);
$has_purchases = ($result && $result->num_rows > 0);

// بيانات للرسم البياني (آخر 7 أيام)
$chart_labels = [];
$chart_values = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $day_name = date('D', strtotime($date));
    $day_names = ['Sun' => 'الأحد', 'Mon' => 'الإثنين', 'Tue' => 'الثلاثاء', 'Wed' => 'الأربعاء', 'Thu' => 'الخميس', 'Fri' => 'الجمعة', 'Sat' => 'السبت'];
    
    $purchases = $conn->query("SELECT COALESCE(SUM(grand_total), 0) as total FROM purchases WHERE DATE(purchase_date) = '$date'")->fetch_assoc()['total'];
    
    $chart_labels[] = $day_names[$day_name];
    $chart_values[] = floatval($purchases);
}

// أفضل 5 موردين
$top_suppliers = $conn->query("
    SELECT 
        supplier_name,
        COUNT(*) as purchase_count,
        COALESCE(SUM(grand_total), 0) as total_purchases
    FROM purchases
    GROUP BY supplier_name
    ORDER BY total_purchases DESC
    LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فواتير المشتريات - نظام المخازن</title>
    
    <!-- Font Awesome محلي -->
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">
    <!-- Chart.js محلي -->
    <script src="assets/chart.js/chart.min.js"></script>
    
    <!-- ملف CSS الرئيسي (نفس ملف التقارير) -->
    <link rel="stylesheet" href="Style/reports.css">
    
    <style>
        /* تنسيقات إضافية لصفحة المشتريات */
        .stats-mini-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 24px;
            padding: 0 24px;
        }
        
        .stat-mini-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
        }
        
        .stat-mini-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-mini-icon {
            width: 56px;
            height: 56px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .stat-mini-content h4 {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 6px;
            font-weight: 500;
        }
        
        .stat-mini-content .value {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .stat-mini-content .small {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .filters-section {
            background: white;
            border-radius: var(--radius-lg);
            padding: 24px;
            margin: 0 24px 24px;
            box-shadow: var(--shadow-sm);
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .filter-group label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
        }
        
        .filter-input-wrapper {
            position: relative;
        }
        
        .filter-input-wrapper i {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 16px;
        }
        
        .filter-input-wrapper input,
        .filter-input-wrapper select {
            width: 100%;
            padding: 12px 40px 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .filter-input-wrapper input:focus,
        .filter-input-wrapper select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.1);
        }
        
        .filter-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
        
        .btn-filter {
            padding: 12px 24px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-filter:hover {
            background: #1976D2;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-reset {
            padding: 12px 24px;
            background: var(--bg-color);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-reset:hover {
            background: #e2e8f0;
        }
        
        .table-container {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            margin: 0 24px 24px;
            overflow: hidden;
        }
        
        .table-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .table-header h2 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .table-header h2 i {
            color: var(--primary-color);
        }
        
        .table-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .btn-excel {
            padding: 10px 20px;
            background: var(--success-color);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-excel:hover {
            background: #388E3C;
            transform: translateY(-2px);
        }
        
        .btn-pdf {
            padding: 10px 20px;
            background: var(--danger-color);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-pdf:hover {
            background: #D32F2F;
            transform: translateY(-2px);
        }
        
        .btn-add {
            padding: 10px 20px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-add:hover {
            background: #1976D2;
            transform: translateY(-2px);
        }
        
        .table-wrapper {
            overflow-x: auto;
            padding: 0 20px 20px;
        }
        
        .purchases-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .purchases-table th {
           text-align: center;
            padding: 7px 5px;
            background: var(--bg-color);
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 13px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .purchases-table td {
            padding: 5px 3px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            font-size: 14px;
        }
        
        .purchases-table tbody tr {
            transition: all 0.3s ease;
        }
        
        .purchases-table tbody tr:hover {
            background: rgba(33, 150, 243, 0.05);
        }
        
        .purchase-number {
            font-weight: 600;
            color: var(--primary-color);
            background: rgba(33, 150, 243, 0.1);
            padding: 4px 8px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            display: inline-block;
        }
        
        .supplier-name {
            display:flex;
            font-weight: 600;
        }
        
        .amount {
            font-weight: 700;
            color: var(--success-color);
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        tr:hover .action-buttons {
            opacity: 1;
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
        
        .action-btn.delete:hover {
            color: var(--danger-color);
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-paid {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }
        
        .status-unpaid {
            background: rgba(244, 67, 54, 0.1);
            color: #F44336;
        }
        
        .status-partial {
            background: rgba(255, 152, 0, 0.1);
            color: #FF9800;
        }
        
        .pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 24px;
            border-top: 1px solid var(--border-color);
        }
        
        .pagination-info {
            font-size: 14px;
            color: var(--text-secondary);
        }
        
        .pagination-controls {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .page-btn {
            width: 36px;
            height: 36px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            background: white;
            color: var(--text-primary);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .page-btn:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }
        
        .page-btn.active {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }
        
        .page-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .suppliers-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            margin: 0 24px 24px;
        }
        
        .supplier-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 20px;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
        }
        
        .supplier-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .supplier-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-md);
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .supplier-info h4 {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 150px;
        }
        
        .supplier-info .count {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .chart-section {
            margin: 0 24px 24px;
        }
        
        @media (max-width: 1200px) {
            .stats-mini-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .suppliers-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .stats-mini-grid {
                grid-template-columns: 1fr;
            }
            
            .suppliers-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filter-actions {
                flex-direction: column;
            }
            
            .table-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="layout">
        <!-- ==================== الشريط الجانبي ==================== -->
        <aside class="sidebar" >
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-warehouse"></i>
                    <span>نظام المخازن</span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <a href="index.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>لوحة التحكم</span>
                </a>
                <a href="Daily_Dashboard.php" class="nav-item">
                    <i class="fas fa-calendar-day"></i>
                    <span>التقرير اليومي</span>
                </a>
                <a href="Store_items.php" class="nav-item">
                    <i class="fas fa-boxes"></i>
                    <span>المخزون</span>
                </a>
                <!-- <a href="New_sales_invoice.php" class="nav-item">
                    <i class="fas fa-file-invoice"></i>
                    <span>فاتورة مبيعات</span>
                </a>
                <a href="add_item_tostore.php"  class="nav-item">
                    <i class="fas fa-truck"></i>
                    <span>فاتورة مشتريات</span>
                </a> -->
                <a href="invoice_page.php" class="nav-item">
                    <i class="fas fa-list"></i>
                    <span>المبيعات</span>
                </a>
                <a href="purchases_page.php" class="nav-item active">
                    <i class="fas fa-truck-loading"></i>
                    <span>المشتريات</span>
                </a>
                <a href="returns_page.php" class="nav-item">
                    <i class="fas fa-undo-alt"></i>
                    <span>المرتجعات</span>
                </a>
                <a href="reports.php" class="nav-item">
                    <i class="fas fa-chart-pie"></i>
                    <span>التقارير</span>
                </a>
                <a href="customers.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>العملاء</span>
                </a>
                <a href="suppliers.php" class="nav-item">
                    <i class="fas fa-truck"></i>
                    <span>الموردين</span>   
                </a>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <i class="fas fa-user-circle"></i>
                    <div style="display: flex; flex-direction: column;">
                        <span style="font-weight: bold;"><?php echo $_SESSION['full_name'] ?? 'مدير النظام'; ?></span>
                        <span style="font-size: 11px; opacity: 0.8;"><?php echo $_SESSION['user_role'] ?? ''; ?></span>
                    </div>
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>تسجيل الخروج</span>
                </a>
            </div>
        </aside>
        
        <!-- ==================== المحتوى الرئيسي ==================== -->
        <main class="main-content">
            <!-- ==================== شريط التنقل العلوي ==================== -->
            <header class="top-bar">
                <button class="menu-toggle" id="menuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                
                <div class="page-title">
                    <h1><i class="fas fa-truck-loading" style="color: var(--primary-color); margin-left: 10px;"></i> فواتير المشتريات</h1>
                </div>
                
                <div class="top-bar-actions">
                    <button class="btn-refresh" onclick="location.reload()">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                    <div class="notification-wrapper">
                        <i class="fas fa-bell"></i>
                        <?php if ($unpaid_purchases['count'] > 0): ?>
                            <span class="notification-badge"><?php echo $unpaid_purchases['count']; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="date-display">
                        <i class="far fa-calendar-alt"></i>
                        <span><?php echo date('Y-m-d'); ?></span>
                    </div>
                </div>
            </header>
            
            <!-- ==================== إحصائيات سريعة ==================== -->
            <div class="stats-mini-grid">
                <div class="stat-mini-card">
                    <div class="stat-mini-icon" style="background: rgba(33, 150, 243, 0.1); color: #2196F3;">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div class="stat-mini-content">
                        <h4>إجمالي الفواتير</h4>
                        <div class="value"><?php echo $total_invoices; ?></div>
                        <div class="small"><?php echo $today_invoices; ?> اليوم</div>
                    </div>
                </div>
                
                <div class="stat-mini-card">
                    <div class="stat-mini-icon" style="background: rgba(76, 175, 80, 0.1); color: #4CAF50;">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-mini-content">
                        <h4>إجمالي المشتريات</h4>
                        <div class="value"><?php echo number_format($total_purchases, 2); ?></div>
                        <div class="small">ر.ي</div>
                    </div>
                </div>
                
                <div class="stat-mini-card">
                    <div class="stat-mini-icon" style="background: rgba(76, 175, 80, 0.1); color: #4CAF50;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-mini-content">
                        <h4>مدفوعة</h4>
                        <div class="value"><?php echo $paid_purchases['count']; ?></div>
                        <div class="small"><?php echo number_format($paid_purchases['total'], 2); ?> ر.ي</div>
                    </div>
                </div>
                
                <div class="stat-mini-card">
                    <div class="stat-mini-icon" style="background: rgba(244, 67, 54, 0.1); color: #F44336;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-mini-content">
                        <h4>غير مدفوعة</h4>
                        <div class="value"><?php echo $unpaid_purchases['count']; ?></div>
                        <div class="small"><?php echo number_format($unpaid_purchases['total'], 2); ?> ر.ي</div>
                    </div>
                </div>
            </div>
            
            <!-- ==================== قسم الفلاتر ==================== -->
            <div class="filters-section">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label>رقم الفاتورة</label>
                        <div class="filter-input-wrapper">
                            <i class="fas fa-hashtag"></i>
                            <input type="text" id="invoiceNumberFilter" placeholder="مثال: PUR-2024-001">
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <label>اسم المورد</label>
                        <div class="filter-input-wrapper">
                            <i class="fas fa-user-tie"></i>
                            <input type="text" id="supplierFilter" placeholder="بحث عن مورد...">
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <label>من تاريخ</label>
                        <div class="filter-input-wrapper">
                            <i class="fas fa-calendar"></i>
                            <input type="date" id="dateFrom">
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <label>إلى تاريخ</label>
                        <div class="filter-input-wrapper">
                            <i class="fas fa-calendar"></i>
                            <input type="date" id="dateTo">
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <label>حالة الدفع</label>
                        <div class="filter-input-wrapper">
                            <i class="fas fa-filter"></i>
                            <select id="statusFilter">
                                <option value="">جميع الحالات</option>
                                <option value="مدفوعة">مدفوعة</option>
                                <option value="غير مدفوعة">غير مدفوعة</option>
                                <option value="جزئي">مدفوعة جزئياً</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <label>طريقة الدفع</label>
                        <div class="filter-input-wrapper">
                            <i class="fas fa-credit-card"></i>
                            <select id="methodFilter">
                                <option value="">الكل</option>
                                <option value="نقدي">نقدي</option>
                                <option value="بطاقة">بطاقة</option>
                                <option value="تحويل">تحويل بنكي</option>
                                <option value="شيك">شيك</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button class="btn-filter" onclick="applyFilters()">
                        <i class="fas fa-search"></i>
                        <span>بحث</span>
                    </button>
                    <button class="btn-reset" onclick="resetFilters()">
                        <i class="fas fa-redo-alt"></i>
                        <span>إعادة تعيين</span>
                    </button>
                </div>
            </div>
            
            <!-- ==================== جدول فواتير المشتريات ==================== -->
            <div class="table-container">
                <div class="table-header">
                    <h2>
                        <i class="fas fa-list-ul"></i>
                        قائمة فواتير المشتريات
                    </h2>
                    <div class="table-actions">
                        <button class="btn-excel" onclick="exportToExcel()">
                            <i class="fas fa-file-excel"></i>
                            <span>تصدير Excel</span>
                        </button>
                        <button class="btn-pdf" onclick="exportToPDF()">
                            <i class="fas fa-file-pdf"></i>
                            <span>تصدير PDF</span>
                        </button>
                        <button class="btn-add" onclick="window.location.href='add_item_tostore.php'">
                            <i class="fas fa-plus"></i>
                            <span>فاتورة جديدة</span>
                        </button>
                    </div>
                </div>
                
                <div class="table-wrapper">
                    <table class="purchases-table" id="purchasesTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>رقم الفاتورة</th>
                                <th>التاريخ</th>
                                <th>المورد</th>
                                <th>رقم الهاتف</th>
                                <th>الإجمالي</th>
                                <th>الصافي</th>
                                <th>طريقة الدفع</th>
                                <th>الحالة</th>
                                <th>بواسطة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php if ($has_purchases): ?>
                                <?php $counter = 1; ?>
                                <?php while($purchase = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td>
                                            <span class="purchase-number"><?php echo htmlspecialchars($purchase['purchase_number']); ?></span>
                                        </td>
                                        <td><?php echo $purchase['purchase_date']; ?></td>
                                        <td class="supplier-name">
                                            <div class="action-buttons">
                                                    <button class="action-btn" onclick="viewSupplier(<?php echo $purchase['supplier_id'] ?>)" title="عرض حساب المورد">
                                                        <i class="fas fa-handshake"></i>
                                                    </button>
                                            </div>
                                            <?php echo htmlspecialchars($purchase['supplier_name']); ?>

                                        </td>
                                        <td><?php echo $purchase['supplier_phone'] ?: '—'; ?></td>
                                        <td><?php echo number_format($purchase['subtotal'], 2); ?></td>
                                        
                                        <td class="amount"><?php echo number_format($purchase['grand_total'], 2); ?> ر.ي</td>
                                        <td><?php echo $purchase['payment_method']; ?></td>
                                        <td>
                                            <span class="status-badge status-<?php 
                                                echo $purchase['payment_status'] == 'مدفوعة' ? 'paid' : 
                                                     ($purchase['payment_status'] == 'غير مدفوعة' ? 'unpaid' : 'partial'); 
                                            ?>">
                                                <?php echo $purchase['payment_status']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $purchase['created_by_name'] ?: 'غير معروف'; ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="action-btn" onclick="viewPurchase(<?php echo $purchase['purchase_id']; ?>)" title="عرض">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <!-- <button class="action-btn" onclick="printPurchase(<?php echo $purchase['purchase_id']; ?>)" title="طباعة">
                                                    <i class="fas fa-print"></i>
                                                </button> -->
                                                <!-- <button class="action-btn" onclick="editPurchase(<?php echo $purchase['purchase_id']; ?>)" title="تعديل">
                                                    <i class="fas fa-edit"></i>
                                                </button> -->
                                                <?php if ($_SESSION['full_name'] == 'مدير النظام'): ?>
                                                    <button class="action-btn delete" onclick="deletePurchase(<?php echo $purchase['purchase_id']; ?>, '<?php echo $purchase['purchase_number']; ?>')" title="حذف">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button style="display: none;" class="action-btn delete" onclick="deletePurchase(<?php echo $purchase['purchase_id']; ?>, '<?php echo $purchase['purchase_number']; ?>')" title="حذف">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="11" style="text-align: center; padding: 50px;">
                                        <i class="fas fa-file-invoice" style="font-size: 48px; color: var(--text-secondary); opacity: 0.3; margin-bottom: 15px;"></i>
                                        <p style="color: var(--text-secondary);">لا توجد فواتير مشتريات لعرضها</p>
                                        <button class="btn-add" onclick="window.location.href='add_item_tostore.php'" style="margin-top: 15px; display: inline-flex;">
                                            <i class="fas fa-plus"></i>
                                            إنشاء فاتورة جديدة
                                        </button>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- ==================== ترقيم الصفحات ==================== -->
                <?php if ($has_purchases): ?>
                <!-- <div class="pagination">
                    <div class="pagination-info">
                        عرض 1 إلى <?php echo min(10, $total_invoices); ?> من أصل <?php echo $total_invoices; ?> فاتورة
                    </div>
                    <div class="pagination-controls">
                        <button class="page-btn disabled">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                        <button class="page-btn active">1</button>
                        <button class="page-btn">2</button>
                        <button class="page-btn">3</button>
                        <span class="page-dots">...</span>
                        <button class="page-btn"><?php echo ceil($total_invoices / 10); ?></button>
                        <button class="page-btn">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                    </div>
                </div> -->
                <?php endif; ?>
            </div>
            
            <!-- ==================== أفضل الموردين ==================== -->
            <?php if ($top_suppliers && $top_suppliers->num_rows > 0): ?>
            <div class="suppliers-grid">
                <?php while($supplier = $top_suppliers->fetch_assoc()): ?>
                <div class="supplier-card">
                    <div class="supplier-icon">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <div class="supplier-info">
                        <h4 title="<?php echo $supplier['supplier_name']; ?>"><?php echo $supplier['supplier_name']; ?></h4>
                        <div class="count"><?php echo $supplier['purchase_count']; ?> فاتورة</div>
                        <div class="small"><?php echo number_format($supplier['total_purchases'], 0); ?> ر.ي</div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php endif; ?>
            
            <!-- ==================== رسم بياني للمشتريات ==================== -->
            <?php if ($has_purchases): ?>
            <div class="chart-section">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3>اتجاهات المشتريات (آخر 7 أيام)</h3>
                    </div>
                    <div class="chart-body" style="height: 250px;">
                        <canvas id="purchasesChart"></canvas>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        // ==================== بيانات الرسم البياني ====================
        const chartLabels = <?php echo json_encode($chart_labels); ?>;
        const chartValues = <?php echo json_encode($chart_values); ?>;
        
        // ==================== تهيئة الرسم البياني ====================
        document.addEventListener('DOMContentLoaded', function() {
            if (chartLabels.length > 0 && chartValues.length > 0) {
                initPurchasesChart();
            }
            
            // تعيين تاريخ اليوم كحد أقصى
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('dateTo').max = today;
            document.getElementById('dateFrom').max = today;
            
            // تعيين تاريخ أول الشهر كقيمة افتراضية
            const firstDay = new Date();
            firstDay.setDate(1);
            document.getElementById('dateFrom').value = firstDay.toISOString().split('T')[0];
        });
        
        function initPurchasesChart() {
            const ctx = document.getElementById('purchasesChart').getContext('2d');
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'المشتريات (ر.ي)',
                        data: chartValues,
                        borderColor: '#4CAF50',
                        backgroundColor: 'rgba(76, 175, 80, 0.1)',
                        borderWidth: 3,
                        pointBackgroundColor: '#4CAF50',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.raw.toFixed(2) + ' ر.ي';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString() + ' ر.ي';
                                }
                            }
                        }
                    }
                }
            });
        }
        // ==================== بحث تلقائي ====================
        function setupAutoSearch() {
            const invoiceInput = document.getElementById('invoiceNumberFilter');
            const supplierInput = document.getElementById('supplierFilter');
            const statusSelect = document.getElementById('statusFilter');
            const methodSelect = document.getElementById('methodFilter');
            const dateFromInput = document.getElementById('dateFrom');
            const dateToInput = document.getElementById('dateTo');
            
            const searchFields = [invoiceInput, supplierInput, statusSelect, methodSelect, dateFromInput, dateToInput];
            
            searchFields.forEach(field => {
                if (field) {
                    field.addEventListener('input', function() {
                        applyFilters();
                    });
                    field.addEventListener('change', function() {
                        applyFilters();
                    });
                }
            });
        }

        // تهيئة البحث التلقائي عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            // ... الكود الموجود ...
            
            // إضافة البحث التلقائي
            setupAutoSearch();
        });
        
        // ==================== دوال الفلاتر ====================
        // ==================== دوال الفلاتر ====================
        function applyFilters() {
            const invoiceNumber = document.getElementById('invoiceNumberFilter').value.toLowerCase().trim();
            const supplier = document.getElementById('supplierFilter').value.toLowerCase().trim();
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            const status = document.getElementById('statusFilter').value;
            const method = document.getElementById('methodFilter').value;
            
            const rows = document.querySelectorAll('#tableBody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                let showRow = true;
                
                // الحصول على البيانات من الخلايا
                const invoiceNumberCell = row.cells[1]?.textContent?.toLowerCase().trim() || '';
                const supplierCell = row.cells[3]?.textContent?.toLowerCase().trim() || '';
                const dateCell = row.cells[2]?.textContent?.trim() || '';
                const methodCell = row.cells[7]?.textContent?.trim() || '';
                const statusCell = row.cells[8]?.querySelector('.status-badge')?.textContent?.trim() || '';
                
                // فلترة حسب رقم الفاتورة
                if (invoiceNumber && !invoiceNumberCell.includes(invoiceNumber)) {
                    showRow = false;
                }
                
                // فلترة حسب اسم المورد
                if (supplier && !supplierCell.includes(supplier)) {
                    showRow = false;
                }
                
                // فلترة حسب حالة الدفع
                if (status && statusCell !== status) {
                    showRow = false;
                }
                
                // فلترة حسب طريقة الدفع
                if (method && methodCell !== method) {
                    showRow = false;
                }
                
                // فلترة حسب التاريخ
                if (dateFrom || dateTo) {
                    const purchaseDate = new Date(dateCell);
                    
                    if (dateFrom && !isNaN(purchaseDate) && purchaseDate < new Date(dateFrom)) {
                        showRow = false;
                    }
                    
                    if (dateTo && !isNaN(purchaseDate) && purchaseDate > new Date(dateTo)) {
                        showRow = false;
                    }
                }
                
                row.style.display = showRow ? '' : 'none';
                if (showRow) visibleCount++;
            });
            
            // عرض رسالة إذا لم تكن هناك نتائج
            const noResultsMsg = document.getElementById('noResultsMessage');
            if (visibleCount === 0 && rows.length > 0) {
                if (!noResultsMsg) {
                    const tbody = document.getElementById('tableBody');
                    const msgRow = document.createElement('tr');
                    msgRow.id = 'noResultsMessage';
                    msgRow.innerHTML = `<td colspan="11" style="text-align: center; padding: 50px;">
                        <i class="fas fa-search" style="font-size: 48px; color: var(--text-secondary); opacity: 0.3; margin-bottom: 15px;"></i>
                        <p style="color: var(--text-secondary);">لا توجد نتائج تطابق معايير البحث</p>
                    </td>`;
                    tbody.appendChild(msgRow);
                }
            } else if (noResultsMsg) {
                noResultsMsg.remove();
            }
        }
        
        function resetFilters() {
            document.getElementById('invoiceNumberFilter').value = '';
            document.getElementById('supplierFilter').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('methodFilter').value = '';
            
            const firstDay = new Date();
            firstDay.setDate(1);
            document.getElementById('dateFrom').value = firstDay.toISOString().split('T')[0];
            document.getElementById('dateTo').value = '';
            
            // إعادة تعيين جميع الصفوف للظهور
            const rows = document.querySelectorAll('#tableBody tr');
            rows.forEach(row => {
                row.style.display = '';
            });
            
            // إزالة رسالة "لا توجد نتائج" إذا كانت موجودة
            const noResultsMsg = document.getElementById('noResultsMessage');
            if (noResultsMsg) {
                noResultsMsg.remove();
            }
        }
        
        // ==================== دوال الإجراءات ====================
        function viewPurchase(purchaseId) {
            window.location.href = 'view_purchase.php?id=' + purchaseId;
        }
        
        function editPurchase(purchaseId) {
            window.location.href = 'edit_purchase.php?id=' + purchaseId;
        }
        
        function deletePurchase(purchaseId, purchaseNumber) {
            if (confirm('هل أنت متأكد من حذف فاتورة المشتريات رقم: ' + purchaseNumber + '؟')) {
                window.location.href = 'delete_purchase.php?id=' + purchaseId;
            }
        }
        
        function printPurchase(purchaseId) {
            window.open('print_purchase.php?id=' + purchaseId, '_blank');
        }
        
        function exportToExcel() {
            alert('سيتم تصدير بيانات المشتريات إلى Excel قريباً');
        }
        
        function exportToPDF() {
            alert('سيتم تصدير بيانات المشتريات إلى PDF قريباً');
        }
        
        // ==================== اختصارات لوحة المفاتيح ====================
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                window.location.href = 'add_item_tostore.php';
            }
            
            if (e.key === '/' && e.target.tagName !== 'INPUT') {
                e.preventDefault();
                document.getElementById('supplierFilter').focus();
            }
        });
        
        // ==================== قائمة جانبية قابلة للطي ====================
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('collapsed');
            document.querySelector('.main-content').classList.toggle('expanded');
        });

        function viewCustomer(id_supper){
            window.location.href = 'view_supplier.php?id='+id_supper;
        }
        function viewSupplier(supplierId) {
            window.location.href = 'view_supplier.php?id=' + supplierId;
        }
    </script>
    
    <?php
    $conn->close();
    ?>
</body>
</html>