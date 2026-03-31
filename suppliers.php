<?php
session_start();
include "config.php";

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: logout.php");
    exit();
}


// ==================== إحصائيات الموردين ====================
// إجمالي عدد الموردين
$total_suppliers = $conn->query("SELECT COUNT(*) as count FROM suppliers")->fetch_assoc()['count'];

// الموردين النشطين (لديهم مشتريات)
$active_suppliers = $conn->query("SELECT COUNT(DISTINCT supplier_name) as count FROM purchases")->fetch_assoc()['count'];

// إجمالي المشتريات من الموردين
$total_purchases = $conn->query("SELECT COALESCE(SUM(grand_total), 0) as total FROM purchases")->fetch_assoc()['total'];

// متوسط المشتريات لكل مورد
$avg_purchase = $active_suppliers > 0 ? $total_purchases / $active_suppliers : 0;

// أفضل 5 موردين من حيث المشتريات
$top_suppliers = $conn->query("
    SELECT 
        supplier_id,
        supplier_name,
        COUNT(*) as purchase_count,
        COALESCE(SUM(grand_total), 0) as total_purchases,
        MAX(purchase_date) as last_purchase
    FROM purchases
    GROUP BY supplier_name
    ORDER BY total_purchases DESC
    LIMIT 5
");

// استعلام للحصول على جميع الموردين
$sql = "SELECT 
            s.supplier_id,
            s.supplier_name,
            s.supplier_phone,
            s.supplier_email,
            s.supplier_address,
            s.tax_number,
            s.created_at,
            COUNT(p.id) as purchase_count,
            COALESCE(SUM(p.grand_total), 0) as total_purchases,
            MAX(p.purchase_date) as last_purchase,
            COALESCE(AVG(p.grand_total), 0) as avg_purchase
        FROM suppliers s
        LEFT JOIN purchases p ON s.supplier_name = p.supplier_name
        GROUP BY s.supplier_id
        ORDER BY s.supplier_name ASC";

$result = $conn->query($sql);
$has_suppliers = ($result && $result->num_rows > 0);

// تنسيق الأرقام
function formatNumber($number) {
    return number_format($number, 2);
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الموردين - نظام المخازن</title>
    
    <!-- Font Awesome محلي -->
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">
    
    <!-- ملف CSS الرئيسي -->
    <link rel="stylesheet" href="Style/reports.css">
    
    <style>
        /* ==================== تنسيقات إضافية لصفحة الموردين ==================== */
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
        
        /* ==================== شريط الأدوات ==================== */
        .toolbar-section {
            background: white;
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            margin: 0 24px 24px;
            box-shadow: var(--shadow-sm);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .toolbar-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: #1976D2;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-success {
            background: var(--success-color);
            color: white;
        }
        
        .btn-success:hover {
            background: #388E3C;
            transform: translateY(-2px);
        }
        
        .btn-warning {
            background: var(--warning-color);
            color: white;
        }
        
        .btn-warning:hover {
            background: #F57C00;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: var(--bg-color);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        
        .btn-secondary:hover {
            background: #e2e8f0;
        }
        
        .search-box {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--bg-color);
            padding: 5px 15px;
            border-radius: 50px;
            min-width: 300px;
        }
        
        .search-box i {
            color: var(--text-secondary);
        }
        
        .search-box input {
            border: none;
            background: transparent;
            padding: 12px 0;
            width: 100%;
            font-size: 14px;
            outline: none;
        }
        
        /* ==================== بطاقات الموردين المميزين ==================== */
        .featured-section {
            margin: 0 24px 30px;
        }
        
        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .section-title h2 {
            font-size: 20px;
            color: var(--text-primary);
        }
        
        .section-title i {
            color: var(--primary-color);
            font-size: 24px;
        }
        
        .suppliers-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
        }
        
        .supplier-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 20px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .supplier-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .supplier-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--success-color));
        }
        
        .supplier-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(76, 175, 80, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }
        
        .supplier-avatar i {
            font-size: 30px;
            color: #4CAF50;
        }
        
        .supplier-card h3 {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--text-primary);
        }
        
        .supplier-stats {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
        }
        
        .stat-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 8px;
        }
        
        .stat-label {
            color: var(--text-secondary);
        }
        
        .stat-value {
            font-weight: bold;
            color: #4CAF50;
        }
        
        /* ==================== جدول الموردين ==================== */
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
        
        .table-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .table-header h3 i {
            color: #4CAF50;
        }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
        }
        
        .filter-tab {
            padding: 8px 16px;
            border: none;
            background: var(--bg-color);
            border-radius: 20px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s;
        }
        
        .filter-tab:hover {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }
        
        .filter-tab.active {
            background: #4CAF50;
            color: white;
        }
        
        .table-wrapper {
            overflow-x: auto;
            padding: 0 24px 24px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            text-align: right;
            padding: 16px 12px;
            background: var(--bg-color);
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 13px;
            border-bottom: 2px solid var(--border-color);
        }
        
        td {
            padding: 16px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            font-size: 14px;
        }
        
        tbody tr:hover {
            background: rgba(76, 175, 80, 0.05);
        }
        
        .supplier-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .supplier-info i {
            width: 32px;
            height: 32px;
            background: rgba(76, 175, 80, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4CAF50;
        }
        
        .amount {
            font-weight: bold;
            color: #4CAF50;
        }
        
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }
        
        .badge-active {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }
        
        .badge-inactive {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
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
            color: var(--text-secondary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        
        .action-btn:hover {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }
        
        .action-btn.delete:hover {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
        }
        
        /* ==================== ترقيم الصفحات ==================== */
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
            transition: all 0.3s;
        }
        
        .page-btn:hover {
            border-color: #4CAF50;
            color: #4CAF50;
        }
        
        .page-btn.active {
            background: #4CAF50;
            border-color: #4CAF50;
            color: white;
        }
        
        .page-dots {
            color: var(--text-secondary);
            padding: 0 4px;
        }
        
        /* ==================== رسالة عدم وجود بيانات ==================== */
        .no-data {
            text-align: center;
            padding: 50px;
            color: var(--text-secondary);
        }
        
        .no-data i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.3;
        }
        
        /* ==================== تجاوب مع الشاشات ==================== */
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
            
            .toolbar-section {
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
            
            .filter-tabs {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <div class="layout">
        <!-- ==================== الشريط الجانبي ==================== -->
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
                <a href="customers.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>العملاء</span>
                </a>
                <a href="suppliers.php" class="nav-item active">
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
                    <h1><i class="fas fa-handshake" style="color: #4CAF50; margin-left: 10px;"></i> إدارة الموردين</h1>
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
            </header>
            
            <!-- ==================== بطاقات الإحصائيات ==================== -->
            <div class="stats-mini-grid">
                <div class="stat-mini-card">
                    <div class="stat-mini-icon" style="background: rgba(76, 175, 80, 0.1); color: #4CAF50;">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <div class="stat-mini-content">
                        <h4>إجمالي الموردين</h4>
                        <div class="value"><?php echo $total_suppliers; ?></div>
                        <div class="small">مسجل في النظام</div>
                    </div>
                </div>
                
                <div class="stat-mini-card">
                    <div class="stat-mini-icon" style="background: rgba(33, 150, 243, 0.1); color: #2196F3;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-mini-content">
                        <h4>موردين نشطين</h4>
                        <div class="value"><?php echo $active_suppliers; ?></div>
                        <div class="small">لديهم مشتريات</div>
                    </div>
                </div>
                
                <div class="stat-mini-card">
                    <div class="stat-mini-icon" style="background: rgba(255, 152, 0, 0.1); color: #FF9800;">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-mini-content">
                        <h4>إجمالي المشتريات</h4>
                        <div class="value"><?php echo formatNumber($total_purchases); ?></div>
                        <div class="small">ر.ي</div>
                    </div>
                </div>
                
                <div class="stat-mini-card">
                    <div class="stat-mini-icon" style="background: rgba(156, 39, 176, 0.1); color: #9C27B0;">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-mini-content">
                        <h4>متوسط المشتريات</h4>
                        <div class="value"><?php echo formatNumber($avg_purchase); ?></div>
                        <div class="small">لكل مورد</div>
                    </div>
                </div>
            </div>
            
            <!-- ==================== شريط الأدوات ==================== -->
            <div class="toolbar-section">
                <div class="toolbar-actions">
                    <button class="btn btn-primary" onclick="window.location.href='add_supplier.php'">
                        <i class="fas fa-plus"></i>
                        إضافة مورد جديد
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
                    <input type="text" id="searchInput" placeholder="بحث باسم المورد أو رقم الهاتف..." onkeyup="filterTable()">
                </div>
            </div>
            
            <!-- ==================== أفضل الموردين ==================== -->
            <?php if ($top_suppliers->num_rows > 0): ?>
            <div class="featured-section">
                <div class="section-title">
                    <i class="fas fa-star" style="color: #ffc107;"></i>
                    <h2>أفضل الموردين</h2>
                </div>
                
                <div class="suppliers-grid">
                    <?php while ($supplier = $top_suppliers->fetch_assoc()): ?>
                    <div class="supplier-card">
                        <a href="view_supplier.php?id=<?php echo $supplier['supplier_id']; ?>" class="customer-avatar"><div class="supplier-avatar">
                            <i class="fas fa-handshake"></i>
                        </div></a>
                        <h3 title="<?php echo $supplier['supplier_name']; ?>"><?php echo $supplier['supplier_name']; ?></h3>
                        <div class="supplier-stats">
                            <div class="stat-row">
                                <span class="stat-label">عدد المشتريات:</span>
                                <span class="stat-value"><?php echo $supplier['purchase_count']; ?></span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">إجمالي المشتريات:</span>
                                <span class="stat-value"><?php echo formatNumber($supplier['total_purchases']); ?> ر.ي</span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">آخر شراء:</span>
                                <span class="stat-value"><?php echo $supplier['last_purchase'] ? date('Y-m-d', strtotime($supplier['last_purchase'])) : '—'; ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- ==================== جدول الموردين ==================== -->
            <div class="table-container">
                <div class="table-header">
                    <h3>
                        <i class="fas fa-list-ul"></i>
                        قائمة الموردين
                    </h3>
                    <div class="filter-tabs">
                        <button class="filter-tab active" onclick="filterByStatus('all')">الكل</button>
                        <button class="filter-tab" onclick="filterByStatus('active')">نشطين</button>
                        <button class="filter-tab" onclick="filterByStatus('inactive')">غير نشطين</button>
                    </div>
                </div>
                
                <div class="table-wrapper">
                    <table id="suppliersTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المورد</th>
                                <th>رقم الهاتف</th>
                                <th>البريد الإلكتروني</th>
                                <th>عدد المشتريات</th>
                                <th>إجمالي المشتريات</th>
                                <th>متوسط المشتريات</th>
                                <th>آخر شراء</th>
                                <th>تاريخ التسجيل</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php if ($has_suppliers): ?>
                                <?php $counter = 1; ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr data-status="<?php echo $row['purchase_count'] > 0 ? 'active' : 'inactive'; ?>">
                                        <td><?php echo $counter++; ?></td>
                                        <td>
                                            <div class="supplier-info">
                                                <i class="fas fa-handshake"></i>
                                                <span><?php echo $row['supplier_name']; ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo $row['supplier_phone'] ?: '—'; ?></td>
                                        <td><?php echo $row['supplier_email'] ?: '—'; ?></td>
                                        <td><?php echo $row['purchase_count']; ?></td>
                                        <td class="amount"><?php echo formatNumber($row['total_purchases']); ?> ر.ي</td>
                                        <td><?php echo formatNumber($row['avg_purchase']); ?> ر.ي</td>
                                        <td><?php echo $row['last_purchase'] ? date('Y-m-d', strtotime($row['last_purchase'])) : '—'; ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($row['created_at'])); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="action-btn" onclick="viewSupplier(<?php echo $row['supplier_id']; ?>)" title="عرض">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <!-- <button class="action-btn" onclick="editSupplier(<?php echo $row['supplier_id']; ?>)" title="تعديل">
                                                    <i class="fas fa-edit"></i>
                                                </button> -->
                                                <!-- <button class="action-btn" onclick="viewPurchases(<?php echo $row['supplier_id']; ?>)" title="مشتريات المورد">
                                                    <i class="fas fa-truck"></i>
                                                </button> -->
                                                <?php if ($_SESSION['full_name'] == 'مدير النظام'): ?>
                                                    <button class="action-btn delete" onclick="deleteSupplier(<?php echo $row['supplier_id']; ?>, '<?php echo $row['supplier_name']; ?>')" title="حذف">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button class="action-btn delete" style="display: none;" onclick="deleteSupplier(<?php echo $row['supplier_id']; ?>, '<?php echo $row['supplier_name']; ?>')" title="حذف">
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
                                        <i class="fas fa-handshake"></i>
                                        <p>لا يوجد موردين لعرضهم</p>
                                        <button class="btn btn-primary" onclick="window.location.href='add_supplier.php'" style="margin-top: 15px;">
                                            <i class="fas fa-plus"></i>
                                            إضافة أول مورد
                                        </button>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- ==================== ترقيم الصفحات ==================== -->
                <?php if ($has_suppliers): ?>
                <div class="pagination">
                    <div class="pagination-info">
                        عرض 1 إلى <?php echo min(10, $total_suppliers); ?> من أصل <?php echo $total_suppliers; ?> مورد
                    </div>
                    <div class="pagination-controls">
                        <button class="page-btn disabled">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                        <button class="page-btn active">1</button>
                        <button class="page-btn">2</button>
                        <button class="page-btn">3</button>
                        <span class="page-dots">...</span>
                        <button class="page-btn"><?php echo ceil($total_suppliers / 10); ?></button>
                        <button class="page-btn">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script>
        // ==================== وظيفة البحث ====================
        function filterTable() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#tableBody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        }
        
        // ==================== تصفية حسب الحالة ====================
        function filterByStatus(status) {
            const tabs = document.querySelectorAll('.filter-tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            const rows = document.querySelectorAll('#tableBody tr');
            
            rows.forEach(row => {
                if (status === 'all') {
                    row.style.display = '';
                } else {
                    const rowStatus = row.getAttribute('data-status');
                    row.style.display = rowStatus === status ? '' : 'none';
                }
            });
        }
        
        // ==================== دوال الإجراءات ====================
        function viewSupplier(supplierId) {
            window.location.href = 'view_supplier.php?id=' + supplierId;
        }
        
        function editSupplier(supplierId) {
            window.location.href = 'edit_supplier.php?id=' + supplierId;
        }
        
        function viewPurchases(supplierId) {
            window.location.href = 'supplier_purchases.php?id=' + supplierId;
        }
        
        function deleteSupplier(supplierId, supplierName) {
            if (confirm('هل أنت متأكد من حذف المورد: ' + supplierName + '؟')) {
                window.location.href = 'delete_supplier.php?id=' + supplierId;
            }
        }
        
        function exportToExcel() {
            alert('سيتم تصدير بيانات الموردين إلى Excel قريباً');
        }
        
        function exportToPDF() {
            alert('سيتم تصدير بيانات الموردين إلى PDF قريباً');
        }
        
        // ==================== قائمة جانبية قابلة للطي ====================
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('collapsed');
            document.querySelector('.main-content').classList.toggle('expanded');
        });
        
        // ==================== اختصارات لوحة المفاتيح ====================
        document.addEventListener('keydown', function(e) {
            // Ctrl + N لإضافة مورد جديد
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                window.location.href = 'add_supplier.php';
            }
            
            // / للبحث
            if (e.key === '/' && e.target.tagName !== 'INPUT') {
                e.preventDefault();
                document.getElementById('searchInput').focus();
            }
        });
    </script>
</body>
</html>