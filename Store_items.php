<?php
session_start();
include "config.php";

// التحقق من تسجيل الدخول
// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: logout.php");
    exit();
}

// ==================== إحصائيات المخزون ====================
// إجمالي عدد المنتجات
$total_products = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];

// إجمالي قيمة المخزون
$total_value = $conn->query("SELECT COALESCE(SUM(current_quantity * selling_price), 0) as total FROM products")->fetch_assoc()['total'];

// المنتجات منخفضة المخزون
$low_stock = $conn->query("SELECT COUNT(*) as count FROM products WHERE current_quantity <= min_quantity")->fetch_assoc()['count'];

// المنتجات النافدة (كمية = 0)
$out_of_stock = $conn->query("SELECT COUNT(*) as count FROM products WHERE current_quantity = 0")->fetch_assoc()['count'];

// إجمالي الكميات
$total_quantity = $conn->query("SELECT COALESCE(SUM(current_quantity), 0) as total FROM products")->fetch_assoc()['total'];

// استعلام للحصول على أصناف المخزون من جدول products
$sql = "SELECT 
            p.product_id as item_id,
            p.product_code as item_code,
            p.product_name as item_name,
            p.product_category as category,
            p.current_quantity as quantity,
            p.product_unit as unit,
            p.purchase_price as cost_price,
            p.selling_price as selling_price,
            p.min_quantity as min_stock_level,
            p.created_at
        FROM products p
        ORDER BY 
            CASE 
                WHEN p.current_quantity <= p.min_quantity THEN 0 
                ELSE 1 
            END,
            p.current_quantity ASC,
            p.product_name ASC";

$result = $conn->query($sql);
$has_items = ($result && $result->num_rows > 0);

// بيانات للرسم البياني (توزيع الفئات)
$category_data = $conn->query("
    SELECT 
        product_category as category,
        COUNT(*) as count,
        COALESCE(SUM(current_quantity), 0) as total_quantity
    FROM products
    GROUP BY product_category
    ORDER BY count DESC
    LIMIT 5
");

$category_labels = [];
$category_counts = [];
while ($row = $category_data->fetch_assoc()) {
    $category_labels[] = $row['category'] ?: 'غير مصنف';
    $category_counts[] = intval($row['count']);
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المخزون - نظام المخازن</title>
    
    <!-- Font Awesome محلي -->
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">
    
    <!-- Chart.js محلي -->
    <script src="assets/chart.js/chart.min.js"></script>
    
    <!-- ملف CSS الرئيسي (نفس ملف التقارير) -->
    <link rel="stylesheet" href="Style/reports.css">
    
    <style>
        /* تنسيقات إضافية لصفحة المخزون */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 24px;
            padding: 0 24px;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .stat-content h3 {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 6px;
            font-weight: 500;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .stat-desc {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .stat-desc i {
            font-size: 12px;
            margin-left: 4px;
        }
        
        .stat-desc.warning {
            color: var(--warning-color);
        }
        
        .stat-desc.danger {
            color: var(--danger-color);
        }
        
        /* تنسيق شريط الأدوات */
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
        
        .toolbar-btn {
            padding: 10px 20px;
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
        
        .toolbar-btn.primary {
            background: var(--primary-color);
            color: white;
        }
        
        .toolbar-btn.primary:hover {
            background: #1976D2;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .toolbar-btn.success {
            background: var(--success-color);
            color: white;
        }
        
        .toolbar-btn.success:hover {
            background: #388E3C;
            transform: translateY(-2px);
        }
        
        .toolbar-btn.warning {
            background: var(--warning-color);
            color: white;
        }
        
        .toolbar-btn.warning:hover {
            background: #F57C00;
        }
        
        .toolbar-search {
            position: relative;
            min-width: 300px;
        }
        
        .toolbar-search i {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 16px;
        }
        
        .toolbar-search input {
            width: 100%;
            padding: 12px 40px 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .toolbar-search input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.1);
        }
        
        /* تنسيق جدول المخزون */
        .inventory-table-container {
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
        
        .filter-tabs {
            display: flex;
            gap: 8px;
            background: var(--bg-color);
            padding: 4px;
            border-radius: var(--radius-lg);
        }
        
        .filter-tab {
            padding: 8px 16px;
            border: none;
            background: transparent;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .filter-tab:hover {
            color: var(--primary-color);
        }
        
        .filter-tab.active {
            background: white;
            color: var(--primary-color);
            box-shadow: var(--shadow-sm);
        }
        
        .table-wrapper {
            overflow-x: auto;
            padding: 0 24px 24px;
        }
        
        .inventory-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .inventory-table th {
            text-align: right;
            padding: 16px 12px;
            background: var(--bg-color);
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 13px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .inventory-table td {
            padding: 16px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            font-size: 14px;
        }
        
        .inventory-table tbody tr {
            transition: all 0.3s ease;
        }
        
        .inventory-table tbody tr:hover {
            background: rgba(33, 150, 243, 0.05);
        }
        
        .product-code {
            font-weight: 600;
            color: var(--primary-color);
            background: rgba(33, 150, 243, 0.1);
            padding: 4px 8px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            display: inline-block;
        }
        
        .product-name {
            font-weight: 600;
        }
        
        .category-badge {
            display: inline-block;
            padding: 4px 12px;
            background: var(--bg-color);
            border-radius: 20px;
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .quantity {
            font-weight: 600;
        }
        
        .quantity.low {
            color: var(--warning-color);
        }
        
        .quantity.critical {
            color: var(--danger-color);
            font-weight: 700;
        }
        
        .price {
            font-weight: 600;
            color: var(--success-color);
        }
        
        .stock-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-normal {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }
        
        .status-warning {
            background: rgba(255, 152, 0, 0.1);
            color: #FF9800;
        }
        
        .status-danger {
            background: rgba(244, 67, 54, 0.1);
            color: #F44336;
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
        
        /* تنسيق الترقيم */
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
        
        /* تنسيق بطاقات الفئات */
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin: 24px;
        }
        
        .category-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 20px;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.3s ease;
        }
        
        .category-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .category-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-md);
            background: rgba(33, 150, 243, 0.1);
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .category-info h4 {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }
        
        .category-info .count {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        /* تنسيق الرسم البياني */
        .chart-section {
            margin: 24px;
        }
        
        /* ==================== تنسيق النافذة المنبثقة ==================== */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-container {
            background: white;
            border-radius: var(--radius-lg);
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, var(--warning-color), #F57C00);
            color: white;
        }

        .modal-header h3 {
            font-size: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
        }

        .modal-header h3 i {
            color: white;
        }

        .modal-close {
            background: rgba(255,255,255,0.2);
            border: none;
            font-size: 20px;
            color: white;
            cursor: pointer;
            width: 36px;
            height: 36px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .modal-close:hover {
            background: rgba(255,255,255,0.3);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 24px;
        }

        .modal-footer {
            padding: 20px 24px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }

        .form-group label i {
            margin-left: 6px;
            color: var(--warning-color);
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--warning-color);
            box-shadow: 0 0 0 3px rgba(255, 152, 0, 0.1);
        }

        .form-control[readonly] {
            background: var(--bg-color);
            cursor: not-allowed;
        }

        .price-input {
            font-size: 18px;
            font-weight: 700;
            color: var(--success-color);
            border: 2px solid var(--success-color);
        }

        .info-box {
            background: #e3f2fd;
            padding: 15px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #1976d2;
            border-right: 4px solid #1976d2;
        }

        .info-box i {
            font-size: 24px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
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

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background: #D32F2F;
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

        /* تلميحات */
        .small-hint {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        .text-success {
            color: var(--success-color);
        }

        .text-warning {
            color: var(--warning-color);
        }

        .text-danger {
            color: var(--danger-color);
        }
        
        @media (max-width: 1200px) {
            .stats-grid,
            .categories-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid,
            .categories-grid {
                grid-template-columns: 1fr;
            }
            
            .toolbar-section {
                flex-direction: column;
                align-items: stretch;
            }
            
            .toolbar-search {
                min-width: auto;
            }
            
            .table-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .filter-tabs {
                width: 100%;
                overflow-x: auto;
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
                <a href="Store_items.php" class="nav-item active">
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
                    <h1><i class="fas fa-boxes" style="color: var(--primary-color); margin-left: 10px;"></i> إدارة المخزون</h1>
                </div>
                
                <div class="top-bar-actions">
                    <button class="btn-refresh" onclick="location.reload()">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                    <div class="notification-wrapper">
                        <i class="fas fa-bell"></i>
                        <?php if ($low_stock > 0): ?>
                            <span class="notification-badge"><?php echo $low_stock; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="date-display">
                        <i class="far fa-calendar-alt"></i>
                        <span><?php echo date('Y-m-d'); ?></span>
                    </div>
                </div>
            </header>
            
            <!-- ==================== بطاقات الإحصائيات ==================== -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(33, 150, 243, 0.1); color: #2196F3;">
                        <i class="fas fa-cubes"></i>
                    </div>
                    <div class="stat-content">
                        <h3>إجمالي المنتجات</h3>
                        <div class="stat-value"><?php echo number_format($total_products); ?></div>
                        <div class="stat-desc">
                            <i class="fas fa-box"></i> <?php echo number_format($total_quantity); ?> وحدة
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(76, 175, 80, 0.1); color: #4CAF50;">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="stat-content">
                        <h3>قيمة المخزون (بيع)</h3>
                        <div class="stat-value"><?php echo number_format($total_value, 2); ?> ر.ي</div>
                        <div class="stat-desc">
                            <i class="fas fa-chart-line"></i> سعر البيع
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(255, 152, 0, 0.1); color: #FF9800;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-content">
                        <h3>منخفض المخزون</h3>
                        <div class="stat-value"><?php echo $low_stock; ?></div>
                        <div class="stat-desc warning">
                            <i class="fas fa-clock"></i> تحتاج إعادة طلب
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(244, 67, 54, 0.1); color: #F44336;">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3>نافد من المخزون</h3>
                        <div class="stat-value"><?php echo $out_of_stock; ?></div>
                        <div class="stat-desc danger">
                            <i class="fas fa-hourglass"></i> كمية = 0
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ==================== شريط الأدوات ==================== -->
            <div class="toolbar-section">
                <div class="toolbar-actions">
                    <button class="toolbar-btn primary" onclick="window.location.href='add_item_tostore.php'">
                        <i class="fas fa-plus"></i>
                        <span>فاتورة مشتريات</span>
                    </button>

                    <button class="toolbar-btn primary" onclick="window.location.href='add_direct_items.php'">
                        <i class="fas fa-plus-circle"></i>
                        <span>إضافة مباشرة</span>
                    </button>

                    <button class="toolbar-btn success" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i>
                        <span>تصدير Excel</span>
                    </button>
                    <button class="toolbar-btn warning" onclick="printReport()">
                        <i class="fas fa-print"></i>
                        <span>طباعة</span>
                    </button>
                </div>
                
                <div class="toolbar-search">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="بحث بالاسم أو الكود..." onkeyup="filterTable()">
                </div>
            </div>
            
            <!-- ==================== فلاتر سريعة ==================== -->
            <div class="inventory-table-container">
                <div class="table-header">
                    <h2>
                        <i class="fas fa-list-ul"></i>
                        قائمة أصناف المخزون
                    </h2>
                    
                    <div class="filter-tabs">
                        <button class="filter-tab active" onclick="filterByStatus('all')">الكل</button>
                        <button class="filter-tab" onclick="filterByStatus('normal')">متوفر</button>
                        <button class="filter-tab" onclick="filterByStatus('warning')">منخفض</button>
                        <button class="filter-tab" onclick="filterByStatus('critical')">ناقص حاد</button>
                        <button class="filter-tab" onclick="filterByStatus('out')">نافد</button>
                    </div>
                </div>
                
                <!-- ==================== جدول المخزون ==================== -->
                <div class="table-wrapper">
                    <table class="inventory-table" id="inventoryTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>كود الصنف</th>
                                <th>اسم المنتج</th>
                                <th>الفئة</th>
                                <th>الكمية</th>
                                <th>الوحدة</th>
                                <th>سعر التكلفة</th>
                                <th>سعر البيع</th>
                                <th>الحد الأدنى</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php if ($has_items): ?>
                                <?php $counter = 1; ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                    <?php 
                                    // تحديد حالة المخزون
                                    $status_class = '';
                                    $status_text = '';
                                    $quantity_class = '';
                                    
                                    if ($row['quantity'] == 0) {
                                        $status_class = 'status-danger';
                                        $status_text = 'نافد';
                                        $quantity_class = 'critical';
                                    } elseif ($row['quantity'] <= $row['min_stock_level'] * 0.5) {
                                        $status_class = 'status-danger';
                                        $status_text = 'نقص حاد';
                                        $quantity_class = 'critical';
                                    } elseif ($row['quantity'] <= $row['min_stock_level']) {
                                        $status_class = 'status-warning';
                                        $status_text = 'منخفض';
                                        $quantity_class = 'low';
                                    } else {
                                        $status_class = 'status-normal';
                                        $status_text = 'متوفر';
                                        $quantity_class = '';
                                    }
                                    ?>
                                    <tr class="inventory-row" data-status="<?php echo $status_text; ?>" data-item-id="<?php echo $row['item_id']; ?>">
                                        <td><?php echo $counter++; ?></td>
                                        <td>
                                            <span class="product-code"><?php echo htmlspecialchars($row['item_code']); ?></span>
                                        </td>
                                        <td class="product-name"><?php echo htmlspecialchars($row['item_name']); ?></td>
                                        <td>
                                            <span class="category-badge"><?php echo htmlspecialchars($row['category'] ?: 'غير مصنف'); ?></span>
                                        </td>
                                        <td>
                                            <span class="quantity <?php echo $quantity_class; ?>">
                                                <?php echo number_format($row['quantity'], 1); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['unit']); ?></td>
                                        <td class="price"><?php echo number_format($row['cost_price'], 2); ?></td>
                                        <td class="price selling-price"><?php echo number_format($row['selling_price'], 2); ?></td>
                                        <td><?php echo number_format($row['min_stock_level']); ?></td>
                                        <td>
                                            <span class="stock-status <?php echo $status_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <!-- <button class="action-btn" onclick="viewItem(<?php echo $row['item_id']; ?>)" title="عرض التفاصيل">
                                                    <i class="fas fa-eye"></i>
                                                </button> -->
                                                <button class="action-btn" onclick="openEditItemModal(
                                                    <?php echo $row['item_id']; ?>, 
                                                    '<?php echo addslashes($row['item_name']); ?>', 
                                                    '<?php echo $row['item_code']; ?>',
                                                    '<?php echo addslashes($row['category'] ?: 'مواد متنوعة'); ?>',
                                                    <?php echo $row['quantity']; ?>,
                                                    '<?php echo $row['unit']; ?>',
                                                    <?php echo $row['cost_price']; ?>, 
                                                    <?php echo $row['selling_price']; ?>,
                                                    <?php echo $row['min_stock_level']; ?>
                                                )" title="تعديل الصنف">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <!-- <button class="action-btn" onclick="addStock(<?php echo $row['item_id']; ?>)" title="إضافة كمية">
                                                    <i class="fas fa-plus-circle"></i>
                                                </button> -->
                                                <?php if($_SESSION['full_name'] =='مدير النظام'){?>
                                                    <button class="action-btn delete" onclick="deleteItem(<?php echo $row['item_id']; ?>, '<?php echo $row['item_name']; ?>')" title="حذف">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php }else{ ?>
                                                    <button class="action-btn delete" style="display: none;" onclick="deleteItem(<?php echo $row['item_id']; ?>, '<?php echo $row['item_name']; ?>')" title="حذف">
                                                            <i class="fas fa-trash"></i>
                                                    </button>

                                                <?php }?>

                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="11" style="text-align: center; padding: 50px;">
                                        <i class="fas fa-boxes" style="font-size: 48px; color: var(--text-secondary); opacity: 0.3; margin-bottom: 15px;"></i>
                                        <p style="color: var(--text-secondary);">لا توجد أصناف في المخزون</p>
                                        <button class="toolbar-btn primary" onclick="window.location.href='add_item_tostore.php'" style="margin-top: 15px; display: inline-flex;">
                                            <i class="fas fa-plus"></i>
                                            إضافة أول صنف
                                        </button>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- ==================== ترقيم الصفحات ==================== -->
                <?php if ($has_items): ?>
                <div class="pagination">
                    <div class="pagination-info">
                        عرض 1 إلى <?php echo min(10, $total_products); ?> من أصل <?php echo $total_products; ?> صنف
                    </div>
                    <div class="pagination-controls">
                        <button class="page-btn disabled">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                        <button class="page-btn active">1</button>
                        <button class="page-btn">2</button>
                        <button class="page-btn">3</button>
                        <span class="page-dots">...</span>
                        <button class="page-btn"><?php echo ceil($total_products / 10); ?></button>
                        <button class="page-btn">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- ==================== توزيع الفئات ==================== -->
            <?php if (!empty($category_labels)): ?>
            <div class="categories-grid">
                <?php foreach ($category_labels as $index => $category): ?>
                <div class="category-card">
                    <div class="category-icon">
                        <i class="fas fa-tag"></i>
                    </div>
                    <div class="category-info">
                        <h4><?php echo $category; ?></h4>
                        <div class="count"><?php echo $category_counts[$index]; ?> منتج</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- ==================== رسم بياني للفئات ==================== -->
            <div class="chart-section">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3>توزيع المنتجات حسب الفئة</h3>
                    </div>
                    <div class="chart-body" style="height: 300px;">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
    
    <!-- ==================== نافذة تعديل بيانات الصنف ==================== -->
    <div class="modal-overlay" id="editItemModal">
        <div class="modal-container">
            <div class="modal-header">
                <h3>
                    <i class="fas fa-edit"></i>
                    تعديل بيانات الصنف
                </h3>
                <button class="modal-close" onclick="closeEditItemModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <div class="info-box">
                    <i class="fas fa-box"></i>
                    <div>
                        <strong>كود الصنف: <span id="modalProductCode">---</span></strong>
                    </div>
                </div>
                
                <form id="editItemForm" onsubmit="event.preventDefault(); updateItem();">
                    <input type="hidden" id="modalItemId" name="item_id" value="">
                    
                    <div class="form-group">
                        <label>
                            <i class="fas fa-tag"></i>
                            اسم الصنف <span style="color: red;">*</span>
                        </label>
                        <input type="text" class="form-control" id="modalProductName" required>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <i class="fas fa-folder"></i>
                            الفئة
                        </label>
                        <select class="form-control" id="modalCategory">
                            <option value="مواد متنوعة">مواد متنوعة</option>
                            <option value="أسمنت">أسمنت</option>
                            <option value="حديد">حديد</option>
                            <option value="خشب">خشب</option>
                            <option value="دهانات">دهانات</option>
                            <option value="أدوات صحية">أدوات صحية</option>
                            <option value="كهرباء">كهرباء</option>
                            <option value="عدد يدوية">عدد يدوية</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <i class="fas fa-cubes"></i>
                            الكمية
                        </label>
                        <input type="number" class="form-control" id="modalQuantity" min="0" step="0.1" value="0">
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <i class="fas fa-balance-scale"></i>
                            الوحدة
                        </label>
                        <select class="form-control" id="modalUnit">
                            <option value="كيس">كيس</option>
                            <option value="طن">طن</option>
                            <option value="لتر">لتر</option>
                            <option value="قطعة">قطعة</option>
                            <option value="متر">متر</option>
                            <option value="كيلو">كيلو</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <i class="fas fa-dollar-sign"></i>
                            سعر الشراء (التكلفة)
                        </label>
                        <input type="number" class="form-control" id="modalPurchasePrice" min="0" step="0.01" value="0" oninput="calculateProfitMargin()">
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <i class="fas fa-chart-line"></i>
                            سعر البيع
                        </label>
                        <input type="number" class="form-control price-input" id="modalSellingPrice" min="0" step="0.01" value="0" oninput="calculateProfitMargin()" required>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <i class="fas fa-calculator"></i>
                            هامش الربح المتوقع
                        </label>
                        <div id="profitMargin" class="small-hint" style="font-size: 16px; font-weight: bold;">0%</div>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <i class="fas fa-exclamation-triangle"></i>
                            الحد الأدنى للمخزون
                        </label>
                        <input type="number" class="form-control" id="modalMinStock" min="0" step="1" value="10">
                        <small class="small-hint">عند وصول الكمية لهذا الحد، يظهر تنبيه</small>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditItemModal()">
                    <i class="fas fa-times"></i> إلغاء
                </button>
                <button type="button" class="btn btn-success" onclick="updateItem()">
                    <i class="fas fa-save"></i> حفظ التغييرات
                </button>
            </div>
        </div>
    </div>
    
    <script>
        // ==================== بيانات الرسم البياني ====================
        const categoryLabels = <?php echo json_encode($category_labels); ?>;
        const categoryCounts = <?php echo json_encode($category_counts); ?>;
        
        // ==================== تهيئة الرسم البياني ====================
        document.addEventListener('DOMContentLoaded', function() {
            if (categoryLabels.length > 0 && categoryCounts.length > 0) {
                initCategoryChart();
            }
        });
        
        function initCategoryChart() {
            const ctx = document.getElementById('categoryChart').getContext('2d');
            
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: categoryLabels,
                    datasets: [{
                        data: categoryCounts,
                        backgroundColor: [
                            '#2196F3',
                            '#4CAF50',
                            '#FF9800',
                            '#F44336',
                            '#9C27B0',
                            '#00BCD4'
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
                                padding: 20,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${label}: ${value} منتج (${percentage}%)`;
                                }
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
            const rows = document.querySelectorAll('.inventory-row');
            
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
            
            const rows = document.querySelectorAll('.inventory-row');
            
            rows.forEach(row => {
                const rowStatus = row.getAttribute('data-status');
                
                if (status === 'all') {
                    row.style.display = '';
                } else if (status === 'normal' && rowStatus === 'متوفر') {
                    row.style.display = '';
                } else if (status === 'warning' && rowStatus === 'منخفض') {
                    row.style.display = '';
                } else if (status === 'critical' && rowStatus === 'نقص حاد') {
                    row.style.display = '';
                } else if (status === 'out' && rowStatus === 'نافد') {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        // ==================== دوال النافذة المنبثقة لتعديل الصنف ====================
        let currentItemId = 0;

        function openEditItemModal(itemId, itemName, itemCode, category, quantity, unit, costPrice, sellingPrice, minStock) {
            currentItemId = itemId;
            
            // تعبئة البيانات في النافذة
            document.getElementById('modalItemId').value = itemId;
            document.getElementById('modalProductName').value = itemName;
            document.getElementById('modalProductCode').textContent = itemCode;
            document.getElementById('modalCategory').value = category || 'مواد متنوعة';
            document.getElementById('modalQuantity').value = quantity;
            document.getElementById('modalUnit').value = unit;
            document.getElementById('modalPurchasePrice').value = costPrice.toFixed(2);
            document.getElementById('modalSellingPrice').value = sellingPrice.toFixed(2);
            document.getElementById('modalMinStock').value = minStock;
            
            // حساب هامش الربح
            calculateProfitMargin();
            
            // إظهار النافذة
            document.getElementById('editItemModal').classList.add('active');
        }

        function closeEditItemModal() {
            document.getElementById('editItemModal').classList.remove('active');
            currentItemId = 0;
        }

        // حساب هامش الربح
        function calculateProfitMargin() {
            const purchasePrice = parseFloat(document.getElementById('modalPurchasePrice').value) || 0;
            const sellingPrice = parseFloat(document.getElementById('modalSellingPrice').value) || 0;
            
            if (purchasePrice > 0) {
                const profit = sellingPrice - purchasePrice;
                const margin = (profit / purchasePrice) * 100;
                const profitElement = document.getElementById('profitMargin');
                
                profitElement.textContent = margin.toFixed(1) + '%';
                
                // تغيير اللون حسب النسبة
                if (margin >= 25) {
                    profitElement.style.color = '#4CAF50';
                } else if (margin >= 10) {
                    profitElement.style.color = '#FF9800';
                } else {
                    profitElement.style.color = '#f44336';
                }
            } else {
                document.getElementById('profitMargin').textContent = '---';
            }
        }

        // تحديث بيانات الصنف
        function updateItem() {
            const itemId = document.getElementById('modalItemId').value;
            const itemName = document.getElementById('modalProductName').value;
            const category = document.getElementById('modalCategory').value;
            const quantity = document.getElementById('modalQuantity').value;
            const unit = document.getElementById('modalUnit').value;
            const purchasePrice = parseFloat(document.getElementById('modalPurchasePrice').value);
            const sellingPrice = parseFloat(document.getElementById('modalSellingPrice').value);
            const minStock = parseInt(document.getElementById('modalMinStock').value);
            
            if (!itemId || !itemName) {
                alert('الرجاء إدخال اسم المنتج');
                return;
            }
            
            if (isNaN(quantity) || quantity < 0) {
                alert('الرجاء إدخال كمية كسرية صحيحة');
                return;
            }
            
            if (isNaN(purchasePrice) || purchasePrice < 0) {
                alert('الرجاء إدخال سعر شراء صحيح');
                return;
            }
            
            if (isNaN(sellingPrice) || sellingPrice < 0) {
                alert('الرجاء إدخال سعر بيع صحيح');
                return;
            }
            
            if (isNaN(minStock) || minStock < 0) {
                alert('الرجاء إدخال حد أدنى صحيح');
                return;
            }
            
            // إرسال طلب AJAX لتحديث بيانات الصنف
            fetch('update_item.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'item_id=' + itemId + 
                      '&item_name=' + encodeURIComponent(itemName) +
                      '&category=' + encodeURIComponent(category) +
                      '&quantity=' + quantity +
                      '&unit=' + encodeURIComponent(unit) +
                      '&purchase_price=' + purchasePrice +
                      '&selling_price=' + sellingPrice +
                      '&min_stock=' + minStock
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // تحديث البيانات في الجدول
                    const row = document.querySelector(`tr[data-item-id="${itemId}"]`);
                    if (row) {
                        row.querySelector('.product-name').textContent = itemName;
                        row.querySelector('.category-badge').textContent = category;
                        row.querySelector('.quantity').textContent = quantity;
                        row.querySelector('td:nth-child(6)').textContent = unit;
                        const priceCells = row.querySelectorAll('.price');
                        if (priceCells.length >= 2) {
                            priceCells[0].textContent = purchasePrice.toFixed(2);
                            priceCells[1].textContent = sellingPrice.toFixed(2);
                        }
                        row.querySelector('td:nth-child(9)').textContent = minStock;
                    }
                    
                    // إغلاق النافذة
                    closeEditItemModal();
                    
                    // عرض رسالة نجاح
                    showNotification('success', '✅ تم تحديث بيانات الصنف بنجاح');
                    
                    // إعادة تحميل الصفحة بعد ثانيتين لتحديث جميع البيانات
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    alert('خطأ: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('حدث خطأ في الاتصال بالخادم');
            });
        }

        // دالة لعرض الإشعارات
        function showNotification(type, message) {
            const notification = document.createElement('div');
            notification.className = 'alert alert-' + type;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 25px;
                border-radius: 4px;
                color: white;
                font-weight: bold;
                z-index: 10000;
                background: ${type === 'success' ? '#4CAF50' : '#f44336'};
                animation: slideIn 0.3s ease;
            `;
            notification.innerHTML = '<i class="fas ' + (type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle') + '"></i> ' + message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
        
        // ==================== دوال الإجراءات الأخرى ====================
        function viewItem(itemId) {
            window.location.href = 'view_item.php?id=' + itemId;
        }
        
        function editItem(itemId) {
            // تم استبدالها بـ openEditItemModal
        }
        
        function addStock(itemId) {
            window.location.href = 'add_stock.php?id=' + itemId;
        }
        
        function deleteItem(itemId, itemName) {
            if (confirm('هل أنت متأكد من حذف المنتج: ' + itemName + '؟')) {
                window.location.href = 'delete_item.php?id=' + itemId;
            }
        }
        
        function exportToExcel() {
            alert('سيتم تصدير بيانات المخزون إلى Excel قريباً');
        }
        
        function printReport() {
            window.print();
        }
        
        // ==================== قائمة جانبية قابلة للطي ====================
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('collapsed');
            document.querySelector('.main-content').classList.toggle('expanded');
        });
    </script>
</body>
</html>