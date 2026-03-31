<?php
session_start();
include "config.php";

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: logout.php");
    exit();
}


// ==================== إحصائيات العملاء ====================
// إجمالي عدد العملاء
$total_customers = $conn->query("SELECT COUNT(*) as count FROM customers")->fetch_assoc()['count'];

// العملاء النشطين (لديهم فواتير)
$active_customers = $conn->query("SELECT COUNT(DISTINCT customer_id) as count FROM invoices")->fetch_assoc()['count'];

// إجمالي مشتريات العملاء
$total_purchases = $conn->query("SELECT COALESCE(SUM(grand_total), 0) as total FROM invoices")->fetch_assoc()['total'];

// متوسط إنفاق العميل
$avg_purchase = $active_customers > 0 ? $total_purchases / $active_customers : 0;

// أفضل 5 عملاء من حيث المشتريات
$top_customers = $conn->query("
    SELECT 
        c.customer_id,
        c.customer_name,
        c.customer_phone,
        c.customer_email,
        COUNT(i.invoice_id) as invoice_count,
        COALESCE(SUM(i.grand_total), 0) as total_spent,
        MAX(i.invoice_date) as last_purchase
    FROM customers c
    LEFT JOIN invoices i ON c.customer_id = i.customer_id
    GROUP BY c.customer_id
    ORDER BY total_spent DESC
    LIMIT 5
");

// استعلام للحصول على جميع العملاء
$sql = "SELECT 
            c.customer_id,
            c.customer_name,
            c.customer_phone,
            c.customer_email,
            c.customer_address,
            c.customer_type,
            c.tax_number,
            c.created_at,
            COUNT(i.invoice_id) as invoice_count,
            COALESCE(SUM(i.grand_total), 0) as total_spent,
            MAX(i.invoice_date) as last_purchase
        FROM customers c
        LEFT JOIN invoices i ON c.customer_id = i.customer_id
        GROUP BY c.customer_id
        ORDER BY c.customer_name ASC";

$result = $conn->query($sql);
$has_customers = ($result && $result->num_rows > 0);

// بيانات للرسم البياني (توزيع العملاء حسب النوع)
$customer_types = $conn->query("
    SELECT 
        customer_type,
        COUNT(*) as count
    FROM customers
    GROUP BY customer_type
");

$type_labels = [];
$type_counts = [];
while ($row = $customer_types->fetch_assoc()) {
    $type_labels[] = $row['customer_type'] ?: 'غير محدد';
    $type_counts[] = intval($row['count']);
}

function formatNumber($number) {
    return number_format($number, 2);
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة العملاء - نظام المخازن</title>
    
    <!-- Font Awesome محلي -->
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">
    
    <!-- Chart.js محلي -->
    <script src="assets/chart.js/chart.min.js"></script>

    <!-- ملف CSS الرئيسي (نفس ملف التقارير) -->
    <link rel="stylesheet" href="Style/reports.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        
        
        .container {
            max-width: 1400px;
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
            color: #2196F3;
        }
        
        .date-display {
            background: #e3f2fd;
            color: #1976d2;
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: bold;
            border: 2px solid #3a94dd;
        }
        
        /* ==================== بطاقات الإحصائيات ==================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .stat-content h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-desc {
            font-size: 12px;
            color: #999;
        }
        
        /* ==================== شريط الأدوات ==================== */
        .toolbar {
            background: white;
            border-radius: 15px;
            padding: 20px 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .toolbar-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
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
        
        .btn-success {
            background: #4CAF50;
            color: white;
        }
        
        .btn-success:hover {
            background: #388E3C;
            transform: translateY(-2px);
        }
        
        .btn-warning {
            background: #ff9800;
            color: white;
        }
        
        .btn-warning:hover {
            background: #f57c00;
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
        
        .search-box {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f5f5f5;
            padding: 5px 15px;
            border-radius: 50px;
            min-width: 300px;
        }
        
        .search-box i {
            color: #999;
        }
        
        .search-box input {
            border: none;
            background: transparent;
            padding: 12px 0;
            width: 100%;
            font-size: 14px;
            outline: none;
        }
        
        /* ==================== بطاقات العملاء المميزين ==================== */
        .featured-section {
            margin-bottom: 30px;
        }
        
        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .section-title h2 {
            font-size: 20px;
            color: #333;
        }
        
        .section-title i {
            color: #2196F3;
            font-size: 24px;
        }
        
        .customers-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
        }
        
        .customer-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .customer-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .customer-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #2196F3, #4CAF50);
        }
        
        .customer-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #e3f2fd;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }
        
        .customer-avatar i {
            font-size: 30px;
            color: #2196F3;
        }
        
        .customer-card h3 {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }
        
        .customer-type {
            font-size: 12px;
            color: #2196F3;
            background: #e3f2fd;
            padding: 3px 10px;
            border-radius: 20px;
            display: inline-block;
            margin-bottom: 15px;
        }
        
        .customer-stats {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .stat-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 8px;
        }
        
        .stat-label {
            color: #666;
        }
        
        .stat-value {
            font-weight: bold;
            color: #2196F3;
        }
        
        /* ==================== جدول العملاء ==================== */
        .table-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .table-header {
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-header h3 {
            font-size: 18px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
        }
        
        .filter-tab {
            padding: 8px 16px;
            border: none;
            background: #f5f5f5;
            border-radius: 20px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s;
        }
        
        .filter-tab:hover {
            background: #e3f2fd;
            color: #2196F3;
        }
        
        .filter-tab.active {
            background: #2196F3;
            color: white;
        }
        
        .table-wrapper {
            overflow-x: auto;
            padding: 0 25px 25px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            text-align: right;
            padding: 15px 10px;
            background: #f8f9fa;
            color: #666;
            font-weight: 600;
            font-size: 13px;
            border-bottom: 2px solid #ddd;
        }
        
        td {
            padding: 15px 10px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
            color: #333;
        }
        
        tbody tr:hover {
            background: #f5f5f5;
        }
        
        .customer-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .customer-info i {
            width: 32px;
            height: 32px;
            background: #e3f2fd;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #2196F3;
        }
        
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }
        
        .badge-individual {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-company {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .badge-government {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .amount {
            font-weight: bold;
            color: #2196F3;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        tr:hover .action-buttons {
            opacity: 1;
        }
        
        .action-btn {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 6px;
            background: transparent;
            color: #666;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        
        .action-btn:hover {
            background: #e3f2fd;
            color: #2196F3;
        }
        
        .action-btn.delete:hover {
            background: #ffebee;
            color: #f44336;
        }
        
        /* ==================== ترقيم الصفحات ==================== */
        .pagination {
            padding: 20px 25px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .pagination-info {
            color: #666;
            font-size: 14px;
        }
        
        .pagination-controls {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .page-btn {
            width: 36px;
            height: 36px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
            color: #333;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        
        .page-btn:hover {
            border-color: #2196F3;
            color: #2196F3;
        }
        
        .page-btn.active {
            background: #2196F3;
            border-color: #2196F3;
            color: white;
        }
        
        .page-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* ==================== الرسم البياني ==================== */
        .chart-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .chart-header h3 {
            font-size: 18px;
            color: #333;
        }
        
        .chart-container {
            height: 300px;
        }
        
        /* ==================== رسالة عدم وجود بيانات ==================== */
        .no-data {
            text-align: center;
            padding: 50px;
            color: #999;
        }
        
        .no-data i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.3;
        }
        
        /* ==================== تجاوب مع الشاشات ==================== */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .customers-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .customers-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .toolbar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                min-width: auto;
            }
            
            .table-header {
                flex-direction: column;
                gap: 15px;
            }
        }

        
    </style>
</head>
<body>
    <div class="layout">
        <aside class="sidebar">
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
                <a href="add_item_tostore.php" class="nav-item">
                    <i class="fas fa-truck"></i>
                    <span>فاتورة مشتريات</span>
                </a> -->
                <a href="invoice_page.php" class="nav-item">
                    <i class="fas fa-list"></i>
                    <span>المبيعات</span>
                </a>
                <a href="purchases_page.php" class="nav-item">
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
                <a href="customers.php" class="nav-item active">
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
        <div class="main-content">
            <!-- ==================== شريط التنقل العلوي ==================== -->
            <div class="top-bar">
                <button class="menu-toggle" id="menuToggle">
                    <i class="fas fa-bars"></i>
                </button>

                <div class="page-title">
                    <i class="fas fa-users"></i>
                    <h1>إدارة العملاء</h1>
                </div>

                <div class="top-bar-actions">
                    <button class="btn-refresh" onclick="location.reload()">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                    <div class="date-display">
                        <i class="far fa-calendar-alt"></i>
                        <span><?php echo date('Y-m-d'); ?></span>
                    </div>
                </div>
                
            </div>

            
            
            <!-- ==================== بطاقات الإحصائيات ==================== -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #e3f2fd; color: #2196F3;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3>إجمالي العملاء</h3>
                        <div class="stat-value"><?php echo $total_customers; ?></div>
                        <div class="stat-desc">مسجل في النظام</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: #e8f5e9; color: #4CAF50;">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3>عملاء نشطين</h3>
                        <div class="stat-value"><?php echo $active_customers; ?></div>
                        <div class="stat-desc">لديهم مشتريات</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: #fff3e0; color: #ff9800;">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-content">
                        <h3>إجمالي المشتريات</h3>
                        <div class="stat-value"><?php echo formatNumber($total_purchases); ?> ر.ي</div>
                        <div class="stat-desc">جميع الفواتير</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: #f3e5f5; color: #9c27b0;">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-content">
                        <h3>متوسط الإنفاق</h3>
                        <div class="stat-value"><?php echo formatNumber($avg_purchase); ?> ر.ي</div>
                        <div class="stat-desc">لكل عميل نشط</div>
                    </div>
                </div>
            </div>
            
            <!-- ==================== شريط الأدوات ==================== -->
            <div class="toolbar">
                <div class="toolbar-actions">
                    <button class="btn btn-primary" onclick="window.location.href='add_customer.php'">
                        <i class="fas fa-plus"></i>
                        إضافة عميل جديد
                    </button>
                    <button class="btn btn-success" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i>
                        تصدير Excel
                    </button>
                    <button class="btn btn-warning" onclick="exportToPDF()">
                        <i class="fas fa-file-pdf"></i>
                        تصدير PDF
                    </button>
                </div>
                
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="بحث باسم العميل أو رقم الهاتف..." onkeyup="filterTable()">
                </div>
            </div>
            
            <!-- ==================== أفضل العملاء ==================== -->
            <?php if ($top_customers->num_rows > 0): ?>
            <div class="featured-section">
                <div class="section-title">
                    <i class="fas fa-star" style="color: #ffc107;"></i>
                    <h2>أفضل العملاء</h2>
                </div>
                
                <div class="customers-grid">
                    <?php while ($customer = $top_customers->fetch_assoc()): ?>
                    <div class="customer-card">
                        <a href="view_customer.php?id=<?php echo $customer['customer_id']; ?>" class="customer-avatar">
                            <i class="fas fa-user-circle"></i>
                        </a>
                        <h3 title="<?php echo $customer['customer_name']; ?>"><?php echo $customer['customer_name']; ?></h3>
                        <span class="customer-type"><?php echo $customer['customer_type'] ?? 'فرد'; ?></span>
                        <div class="customer-stats">
                            <div class="stat-row">
                                <span class="stat-label">عدد الفواتير:</span>
                                <span class="stat-value"><?php echo $customer['invoice_count']; ?></span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">إجمالي المشتريات:</span>
                                <span class="stat-value"><?php echo formatNumber($customer['total_spent']); ?> ر.ي</span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">آخر شراء:</span>
                                <span class="stat-value"><?php echo $customer['last_purchase'] ? date('Y-m-d', strtotime($customer['last_purchase'])) : '—'; ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- ==================== جدول العملاء ==================== -->
            <div class="table-container">
                <div class="table-header">
                    <h3>
                        <i class="fas fa-list-ul" style="color: #2196F3;"></i>
                        قائمة العملاء
                    </h3>
                    <div class="filter-tabs">
                        <button class="filter-tab active" onclick="filterByType('all')">الكل</button>
                        <button class="filter-tab" onclick="filterByType('فرد')">أفراد</button>
                        <button class="filter-tab" onclick="filterByType('شركة')">شركات</button>
                        <button class="filter-tab" onclick="filterByType('جهة حكومية')">جهات حكومية</button>
                    </div>
                </div>
                
                <div class="table-wrapper">
                    <table id="customersTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>العميل</th>
                                <th>رقم الهاتف</th>
                                <th>البريد الإلكتروني</th>
                                <th>النوع</th>
                                <th>عدد الفواتير</th>
                                <th>إجمالي المشتريات</th>
                                <th>آخر شراء</th>
                                <th>تاريخ التسجيل</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php if ($has_customers): ?>
                                <?php $counter = 1; ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr data-type="<?php echo $row['customer_type'] ?: 'فرد'; ?>">
                                        <td><?php echo $counter++; ?></td>
                                        <td>
                                            <div class="customer-info">
                                                    <button class="action-btn" onclick="viewCustomer(<?php echo $row['customer_id']; ?>)" title="عرض حساب العميل">
                                                        <i class="fas fa-user-circle"></i>
                                                     </button>
                                                
                                                
                                                <span><?php echo $row['customer_name']; ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo $row['customer_phone'] ?: '—'; ?></td>
                                        <td><?php echo $row['customer_email'] ?: '—'; ?></td>
                                        <td>
                                            <span class="badge badge-<?php 
                                                echo $row['customer_type'] == 'شركة' ? 'company' : 
                                                    ($row['customer_type'] == 'جهة حكومية' ? 'government' : 'individual'); 
                                            ?>">
                                                <?php echo $row['customer_type'] ?: 'فرد'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $row['invoice_count']; ?></td>
                                        <td class="amount"><?php echo formatNumber($row['total_spent']); ?> ر.ي</td>
                                        <td><?php echo $row['last_purchase'] ? date('Y-m-d', strtotime($row['last_purchase'])) : '—'; ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($row['created_at'])); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="action-btn" onclick="viewCustomer(<?php echo $row['customer_id']; ?>)" title="عرض">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <!-- <button class="action-btn" onclick="editCustomer(<?php echo $row['customer_id']; ?>)" title="تعديل">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="action-btn" onclick="viewInvoices(<?php echo $row['customer_id']; ?>)" title="فواتير العميل">
                                                    <i class="fas fa-file-invoice"></i>
                                                </button> -->
                                                <?php if ($_SESSION['full_name'] == 'مدير النظام'): ?>
                                                    <button class="action-btn delete" onclick="deleteCustomer(<?php echo $row['customer_id']; ?>, '<?php echo $row['customer_name']; ?>')" title="حذف">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button class="action-btn delete" style="display: none;" onclick="deleteCustomer(<?php echo $row['customer_id']; ?>, '<?php echo $row['customer_name']; ?>')" title="حذف">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="no-data">
                                        <i class="fas fa-users"></i>
                                        <p>لا يوجد عملاء لعرضهم</p>
                                        <button class="btn btn-primary" onclick="window.location.href='add_customer.php'" style="margin-top: 15px;">
                                            <i class="fas fa-plus"></i>
                                            إضافة أول عميل
                                        </button>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- ==================== ترقيم الصفحات ==================== -->
                <?php if ($has_customers): ?>
                <!-- <div class="pagination">
                    <div class="pagination-info">
                        عرض 1 إلى <?php echo min(10, $total_customers); ?> من أصل <?php echo $total_customers; ?> عميل
                    </div>
                    <div class="pagination-controls">
                        <button class="page-btn disabled">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                        <button class="page-btn active">1</button>
                        <button class="page-btn">2</button>
                        <button class="page-btn">3</button>
                        <span class="page-dots">...</span>
                        <button class="page-btn"><?php echo ceil($total_customers / 10); ?></button>
                        <button class="page-btn">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                    </div>
                </div> -->
                <?php endif; ?>
            </div>
            
            <!-- ==================== الرسم البياني ==================== -->
            <?php if (!empty($type_labels)): ?>
            <div class="chart-section">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-pie" style="color: #2196F3;"></i> توزيع العملاء حسب النوع</h3>
                </div>
                <div class="chart-container">
                    <canvas id="typeChart"></canvas>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>

        // ==================== بيانات الرسم البياني ====================
        const typeLabels = <?php echo json_encode($type_labels); ?>;
        const typeCounts = <?php echo json_encode($type_counts); ?>;
        
        // ==================== تهيئة الرسم البياني ====================
        document.addEventListener('DOMContentLoaded', function() {
            if (typeLabels.length > 0 && typeCounts.length > 0) {
                initTypeChart();
            }
        });
        
        function initTypeChart() {
            const ctx = document.getElementById('typeChart').getContext('2d');
            
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: typeLabels,
                    datasets: [{
                        data: typeCounts,
                        backgroundColor: [
                            '#2196F3',
                            '#4CAF50',
                            '#FF9800',
                            '#9C27B0',
                            '#F44336'
                        ],
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        }
                    },
                    cutout: '60%'
                }
            });
        }
        
        // ==================== وظيفة البحث ====================
        function filterTable() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#tableBody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        }
        
        // ==================== تصفية حسب النوع ====================
        function filterByType(type) {
            const tabs = document.querySelectorAll('.filter-tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            const rows = document.querySelectorAll('#tableBody tr');
            
            rows.forEach(row => {
                if (type === 'all') {
                    row.style.display = '';
                } else {
                    const rowType = row.getAttribute('data-type');
                    row.style.display = rowType === type ? '' : 'none';
                }
            });
        }
        
        // ==================== دوال الإجراءات ====================
        function viewCustomer(customerId) {
            window.location.href = 'view_customer.php?id=' + customerId;
        }
        
        function editCustomer(customerId) {
            window.location.href = 'edit_customer.php?id=' + customerId;
        }
        
        function viewInvoices(customerId) {
            window.location.href = 'customer_invoices.php?id=' + customerId;
        }
        
        function deleteCustomer(customerId, customerName) {
            if (confirm('هل أنت متأكد من حذف العميل: ' + customerName + '؟')) {
                window.location.href = 'delete_customer.php?id=' + customerId;
            }
        }
        
        function exportToExcel() {
            alert('سيتم تصدير بيانات العملاء إلى Excel قريباً');
        }
        
        function exportToPDF() {
            alert('سيتم تصدير بيانات العملاء إلى PDF قريباً');
        }
        
        // ==================== اختصارات لوحة المفاتيح ====================
        document.addEventListener('keydown', function(e) {
            // Ctrl + N لإضافة عميل جديد
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                window.location.href = 'add_customer.php';
            }
            
            // / للبحث
            if (e.key === '/' && e.target.tagName !== 'INPUT') {
                e.preventDefault();
                document.getElementById('searchInput').focus();
            }
        });


        document.getElementById('menuToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('collapsed');
            document.querySelector('.main-content').classList.toggle('expanded');
        });

        
    </script>
</body>
</html>