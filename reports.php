<?php
session_start();
include "config.php";
// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: logout.php");
    exit();
}


// ==================== استلام نوع التقرير المحدد ====================
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'sales';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// ==================== دوال جلب البيانات حسب نوع التقرير ====================

// دالة جلب إحصائيات سريعة حسب نوع التقرير
function getQuickStats($conn, $report_type, $start_date, $end_date) {
    $stats = [];
    
    switch($report_type) {
        case 'sales':
            // إجمالي المبيعات في الفترة
            $stats['total'] = $conn->query("SELECT COALESCE(SUM(grand_total), 0) as total FROM invoices WHERE invoice_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['total'];
            
            // عدد الفواتير
            $stats['count'] = $conn->query("SELECT COUNT(*) as count FROM invoices WHERE invoice_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['count'];
            
            // متوسط قيمة الفاتورة
            $stats['average'] = $stats['count'] > 0 ? $stats['total'] / $stats['count'] : 0;
            
            // أعلى فاتورة
            $stats['max'] = $conn->query("SELECT COALESCE(MAX(grand_total), 0) as max FROM invoices WHERE invoice_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['max'];
            break;
            
        case 'purchases':
            // إجمالي المشتريات
            $stats['total'] = $conn->query("SELECT COALESCE(SUM(grand_total), 0) as total FROM purchases WHERE purchase_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['total'];
            
            // عدد فواتير المشتريات
            $stats['count'] = $conn->query("SELECT COUNT(*) as count FROM purchases WHERE purchase_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['count'];
            
            // متوسط قيمة المشتريات
            $stats['average'] = $stats['count'] > 0 ? $stats['total'] / $stats['count'] : 0;
            break;
            
        case 'inventory':
            // إجمالي المنتجات
            $stats['total_products'] = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];
            
            // قيمة المخزون
            $stats['stock_value'] = $conn->query("SELECT COALESCE(SUM(current_quantity * purchase_price), 0) as total FROM products")->fetch_assoc()['total'];
            
            // المنتجات منخفضة المخزون
            $stats['low_stock'] = $conn->query("SELECT COUNT(*) as count FROM products WHERE current_quantity <= min_quantity")->fetch_assoc()['count'];
            
            // إجمالي الكميات
            $stats['total_qty'] = $conn->query("SELECT COALESCE(SUM(current_quantity), 0) as total FROM products")->fetch_assoc()['total'];
            break;
            
        case 'profits':
            // إجمالي المبيعات
            $sales_total = $conn->query("SELECT COALESCE(SUM(grand_total), 0) as total FROM invoices WHERE invoice_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['total'];
            
            // إجمالي المشتريات (التكلفة)
            $purchases_total = $conn->query("SELECT COALESCE(SUM(grand_total), 0) as total FROM purchases WHERE purchase_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['total'];
            
            // صافي الربح
            $stats['net_profit'] = $sales_total - $purchases_total;
            
            // نسبة الربح
            $stats['profit_margin'] = $purchases_total > 0 ? ($stats['net_profit'] / $purchases_total) * 100 : 0;
            
            // إجمالي المبيعات
            $stats['sales'] = $sales_total;
            
            // إجمالي المشتريات
            $stats['purchases'] = $purchases_total;
            break;
            
        case 'customers':
            // عدد العملاء
            $stats['total_customers'] = $conn->query("SELECT COUNT(*) as count FROM customers")->fetch_assoc()['count'];
            
            // إجمالي مشتريات العملاء
            $stats['total_purchases'] = $conn->query("SELECT COALESCE(SUM(grand_total), 0) as total FROM invoices WHERE invoice_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['total'];
            
            // متوسط إنفاق العميل
            $customers_count = $conn->query("SELECT COUNT(DISTINCT customer_id) as count FROM invoices WHERE invoice_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['count'];
            $stats['avg_per_customer'] = $customers_count > 0 ? $stats['total_purchases'] / $customers_count : 0;
            break;
            
        case 'suppliers':
            // عدد الموردين
            $stats['total_suppliers'] = $conn->query("SELECT COUNT(*) as count FROM suppliers")->fetch_assoc()['count'];
            
            // إجمالي المشتريات من الموردين
            $stats['total_purchases'] = $conn->query("SELECT COALESCE(SUM(grand_total), 0) as total FROM purchases WHERE purchase_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['total'];
            break;
    }
    
    return $stats;
}

// ==================== دالة جلب بيانات الرسم البياني ====================
function getChartData($conn, $report_type, $start_date, $end_date) {
    $chart_data = [];
    
    switch($report_type) {
        case 'sales':
        case 'profits':
            // بيانات يومية لآخر 7 أيام
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days", strtotime($end_date)));
                $day_name = date('D', strtotime($date));
                $day_names = ['Sun' => 'الأحد', 'Mon' => 'الإثنين', 'Tue' => 'الثلاثاء', 'Wed' => 'الأربعاء', 'Thu' => 'الخميس', 'Fri' => 'الجمعة', 'Sat' => 'السبت'];
                
                $sales = $conn->query("SELECT COALESCE(SUM(grand_total), 0) as total FROM invoices WHERE DATE(invoice_date) = '$date'")->fetch_assoc()['total'];
                
                $chart_data[] = [
                    'label' => $day_names[$day_name],
                    'value' => floatval($sales),
                    'date' => $date
                ];
            }
            break;
            
        case 'purchases':
            // بيانات المشتريات اليومية
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days", strtotime($end_date)));
                $day_name = date('D', strtotime($date));
                $day_names = ['Sun' => 'الأحد', 'Mon' => 'الإثنين', 'Tue' => 'الثلاثاء', 'Wed' => 'الأربعاء', 'Thu' => 'الخميس', 'Fri' => 'الجمعة', 'Sat' => 'السبت'];
                
                $purchases = $conn->query("SELECT COALESCE(SUM(grand_total), 0) as total FROM purchases WHERE DATE(purchase_date) = '$date'")->fetch_assoc()['total'];
                
                $chart_data[] = [
                    'label' => $day_names[$day_name],
                    'value' => floatval($purchases),
                    'date' => $date
                ];
            }
            break;
            
        case 'inventory':
            // توزيع المنتجات حسب الفئة
            $result = $conn->query("
                SELECT 
                    product_category as label,
                    COUNT(*) as value
                FROM products
                GROUP BY product_category
                ORDER BY value DESC
                LIMIT 5
            ");
            while ($row = $result->fetch_assoc()) {
                $chart_data[] = $row;
            }
            break;
            
        case 'customers':
            // أفضل 5 عملاء
            $result = $conn->query("
                SELECT 
                    c.customer_name as label,
                    COALESCE(SUM(i.grand_total), 0) as value
                FROM customers c
                LEFT JOIN invoices i ON c.customer_id = i.customer_id
                WHERE i.invoice_date BETWEEN '$start_date' AND '$end_date'
                GROUP BY c.customer_id
                ORDER BY value DESC
                LIMIT 5
            ");
            while ($row = $result->fetch_assoc()) {
                $chart_data[] = $row;
            }
            break;
            
        case 'suppliers':
            // أفضل 5 موردين
            $result = $conn->query("
                SELECT 
                    supplier_name as label,
                    COALESCE(SUM(grand_total), 0) as value
                FROM purchases
                WHERE purchase_date BETWEEN '$start_date' AND '$end_date'
                GROUP BY supplier_name
                ORDER BY value DESC
                LIMIT 5
            ");
            while ($row = $result->fetch_assoc()) {
                $chart_data[] = $row;
            }
            break;
    }
    
    return $chart_data;
}

// ==================== دالة جلب بيانات الجدول ====================
function getTableData($conn, $report_type, $start_date, $end_date) {
    $table_data = [];
    
    switch($report_type) {
        case 'sales':
            $result = $conn->query("
                SELECT 
                    invoice_number,
                    invoice_date,
                    customer_name,
                    grand_total,
                    payment_status
                FROM invoices i
                LEFT JOIN customers c ON i.customer_id = c.customer_id
                WHERE i.invoice_date BETWEEN '$start_date' AND '$end_date'
                ORDER BY i.invoice_date DESC
                LIMIT 10
            ");
            while ($row = $result->fetch_assoc()) {
                $table_data[] = $row;
            }
            break;
            
        case 'purchases':
            $result = $conn->query("
                SELECT 
                    purchase_number,
                    purchase_date,
                    supplier_name,
                    grand_total,
                    payment_status
                FROM purchases
                WHERE purchase_date BETWEEN '$start_date' AND '$end_date'
                ORDER BY purchase_date DESC
                LIMIT 10
            ");
            while ($row = $result->fetch_assoc()) {
                $table_data[] = $row;
            }
            break;
            
        case 'inventory':
            $result = $conn->query("
                SELECT 
                    product_code,
                    product_name,
                    current_quantity,
                    product_unit,
                    purchase_price,
                    selling_price,
                    (current_quantity * purchase_price) as total_value
                FROM products
                ORDER BY current_quantity ASC
                LIMIT 10
            ");
            while ($row = $result->fetch_assoc()) {
                $table_data[] = $row;
            }
            break;
            
        case 'profits':
            $result = $conn->query("
                SELECT 
                    DATE_FORMAT(invoice_date, '%Y-%m') as month,
                    COUNT(*) as invoice_count,
                    COALESCE(SUM(grand_total), 0) as sales_total,
                    COALESCE((
                        SELECT SUM(grand_total) 
                        FROM purchases 
                        WHERE DATE_FORMAT(purchase_date, '%Y-%m') = DATE_FORMAT(i.invoice_date, '%Y-%m')
                    ), 0) as purchases_total,
                    COALESCE(SUM(grand_total), 0) - COALESCE((
                        SELECT SUM(grand_total) 
                        FROM purchases 
                        WHERE DATE_FORMAT(purchase_date, '%Y-%m') = DATE_FORMAT(i.invoice_date, '%Y-%m')
                    ), 0) as profit
                FROM invoices i
                WHERE invoice_date BETWEEN '$start_date' AND '$end_date'
                GROUP BY DATE_FORMAT(invoice_date, '%Y-%m')
                ORDER BY month DESC
                LIMIT 6
            ");
            while ($row = $result->fetch_assoc()) {
                $table_data[] = $row;
            }
            break;
    }
    
    return $table_data;
}

// ==================== جلب البيانات حسب نوع التقرير ====================
$quick_stats = getQuickStats($conn, $report_type, $start_date, $end_date);
$chart_data = getChartData($conn, $report_type, $start_date, $end_date);
$table_data = getTableData($conn, $report_type, $start_date, $end_date);

// ==================== عناوين وأيقونات التقارير ====================
$report_titles = [
    'sales' => ['تقرير المبيعات', 'fa-chart-line', '#2196F3'],
    'purchases' => ['تقرير المشتريات', 'fa-truck', '#4CAF50'],
    'inventory' => ['تقرير المخزون', 'fa-boxes', '#FF9800'],
    'profits' => ['تقرير الأرباح', 'fa-coins', '#9C27B0'],
    'customers' => ['تقرير العملاء', 'fa-users', '#00BCD4'],
    'suppliers' => ['تقرير الموردين', 'fa-handshake', '#F44336']
];

$current_report = $report_titles[$report_type];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $current_report[0]; ?> - نظام المخازن</title>
    
    <!-- Font Awesome محلي -->
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">
    
    <!-- Chart.js محلي -->
    <script src="assets/chart.js/chart.min.js"></script>
    
    <!-- ملف CSS الرئيسي -->
    <link rel="stylesheet" href="Style/reports.css">
    
    <style>
        /* تنسيقات إضافية للتقرير النشط */
        .report-select option[value="<?php echo $report_type; ?>"] {
            background-color: var(--primary-color);
            color: white;
        }
        
        .chart-container {
            transition: all 0.3s ease;
        }
        
        .no-data-message {
            text-align: center;
            padding: 50px;
            color: var(--text-secondary);
            background: var(--bg-color);
            border-radius: var(--radius-md);
            margin: 20px 0;
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
                <a href="reports.php" class="nav-item active">
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
                    <h1><i class="fas <?php echo $current_report[1]; ?>" style="color: <?php echo $current_report[2]; ?>; margin-left: 10px;"></i><?php echo $current_report[0]; ?></h1>
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
            
            <!-- ==================== شريط أدوات التقارير ==================== -->
            <div class="reports-toolbar">
                <div class="toolbar-left">
                    <button class="btn btn-primary" onclick="exportToPDF()">
                        <i class="fas fa-file-pdf"></i>
                        <span>تصدير PDF</span>
                    </button>
                    <button class="btn btn-success" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i>
                        <span>تصدير Excel</span>
                    </button>
                    <button class="btn btn-info" onclick="printReport()">
                        <i class="fas fa-print"></i>
                        <span>طباعة</span>
                    </button>
                </div>
                
                <form method="GET" action="reports.php" class="toolbar-right" id="reportForm">
                    <select name="report_type" class="report-select" id="reportType" onchange="this.form.submit()">
                        <option value="sales" <?php echo $report_type == 'sales' ? 'selected' : ''; ?>>📊 تقرير المبيعات</option>
                        <option value="purchases" <?php echo $report_type == 'purchases' ? 'selected' : ''; ?>>📦 تقرير المشتريات</option>
                        <option value="inventory" <?php echo $report_type == 'inventory' ? 'selected' : ''; ?>>📋 تقرير المخزون</option>
                        <option value="profits" <?php echo $report_type == 'profits' ? 'selected' : ''; ?>>💰 تقرير الأرباح</option>
                        <option value="customers" <?php echo $report_type == 'customers' ? 'selected' : ''; ?>>👥 تقرير العملاء</option>
                        <option value="suppliers" <?php echo $report_type == 'suppliers' ? 'selected' : ''; ?>>🤝 تقرير الموردين</option>
                    </select>
                    
                    <input type="date" name="start_date" class="report-date" value="<?php echo $start_date; ?>">
                    <span>إلى</span>
                    <input type="date" name="end_date" class="report-date" value="<?php echo $end_date; ?>">
                    
                    <button type="submit" class="btn btn-secondary">
                        <i class="fas fa-filter"></i>
                        <span>تطبيق</span>
                    </button>
                </form>
            </div>
            
            <!-- ==================== بطاقات الإحصائيات السريعة ==================== -->
            <div class="stats-grid">
                <?php foreach ($quick_stats as $key => $value): ?>
                    <?php if ($key == 'total'): ?>
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(33, 150, 243, 0.1); color: #2196F3;">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="stat-content">
                                <h3>الإجمالي</h3>
                                <div class="stat-value"><?php echo number_format($value, 2); ?> ر.ي</div>
                            </div>
                        </div>
                    <?php elseif ($key == 'count'): ?>
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(76, 175, 80, 0.1); color: #4CAF50;">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="stat-content">
                                <h3>العدد</h3>
                                <div class="stat-value"><?php echo $value; ?></div>
                            </div>
                        </div>
                    <?php elseif ($key == 'average'): ?>
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(255, 152, 0, 0.1); color: #FF9800;">
                                <i class="fas fa-calculator"></i>
                            </div>
                            <div class="stat-content">
                                <h3>المتوسط</h3>
                                <div class="stat-value"><?php echo number_format($value, 2); ?> ر.ي</div>
                            </div>
                        </div>
                    <?php elseif ($key == 'max'): ?>
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(244, 67, 54, 0.1); color: #F44336;">
                                <i class="fas fa-trophy"></i>
                            </div>
                            <div class="stat-content">
                                <h3>أعلى قيمة</h3>
                                <div class="stat-value"><?php echo number_format($value, 2); ?> ر.ي</div>
                            </div>
                        </div>
                    <?php elseif ($key == 'stock_value'): ?>
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(33, 150, 243, 0.1); color: #2196F3;">
                                <i class="fas fa-coins"></i>
                            </div>
                            <div class="stat-content">
                                <h3>قيمة المخزون</h3>
                                <div class="stat-value"><?php echo number_format($value, 2); ?> ر.ي</div>
                            </div>
                        </div>
                    <?php elseif ($key == 'total_products'): ?>
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(76, 175, 80, 0.1); color: #4CAF50;">
                                <i class="fas fa-box"></i>
                            </div>
                            <div class="stat-content">
                                <h3>عدد المنتجات</h3>
                                <div class="stat-value"><?php echo $value; ?></div>
                            </div>
                        </div>
                    <?php elseif ($key == 'low_stock'): ?>
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(244, 67, 54, 0.1); color: #F44336;">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="stat-content">
                                <h3>منتجات منخفضة</h3>
                                <div class="stat-value"><?php echo $value; ?></div>
                            </div>
                        </div>
                    <?php elseif ($key == 'net_profit'): ?>
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(76, 175, 80, 0.1); color: #4CAF50;">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="stat-content">
                                <h3>صافي الربح</h3>
                                <div class="stat-value"><?php echo number_format($value, 2); ?> ر.ي</div>
                            </div>
                        </div>
                    <?php elseif ($key == 'profit_margin'): ?>
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(33, 150, 243, 0.1); color: #2196F3;">
                                <i class="fas fa-percent"></i>
                            </div>
                            <div class="stat-content">
                                <h3>نسبة الربح</h3>
                                <div class="stat-value"><?php echo number_format($value, 1); ?>%</div>
                            </div>
                        </div>
                    <?php elseif ($key == 'total_customers'): ?>
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(255, 152, 0, 0.1); color: #FF9800;">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-content">
                                <h3>عدد العملاء</h3>
                                <div class="stat-value"><?php echo $value; ?></div>
                            </div>
                        </div>
                    <?php elseif ($key == 'total_suppliers'): ?>
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(156, 39, 176, 0.1); color: #9C27B0;">
                                <i class="fas fa-handshake"></i>
                            </div>
                            <div class="stat-content">
                                <h3>عدد الموردين</h3>
                                <div class="stat-value"><?php echo $value; ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
            <!-- ==================== الرسوم البيانية ==================== -->
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3>
                            <?php
                            $chart_titles = [
                                'sales' => 'المبيعات اليومية',
                                'purchases' => 'المشتريات اليومية',
                                'inventory' => 'توزيع المنتجات حسب الفئة',
                                'profits' => 'المبيعات اليومية',
                                'customers' => 'أفضل 5 عملاء',
                                'suppliers' => 'أفضل 5 موردين'
                            ];
                            echo $chart_titles[$report_type];
                            ?>
                        </h3>
                    </div>
                    <div class="chart-body">
                        <?php if (empty($chart_data)): ?>
                            <div class="no-data-message">
                                <i class="fas fa-chart-pie fa-3x" style="opacity: 0.3; margin-bottom: 15px;"></i>
                                <p>لا توجد بيانات كافية لعرض الرسم البياني</p>
                            </div>
                        <?php else: ?>
                            <canvas id="dynamicChart"></canvas>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-header">
                        <h3>إحصائيات سريعة</h3>
                    </div>
                    <div class="chart-body" style="display: flex; flex-direction: column; justify-content: center;">
                        <div style="text-align: center; padding: 20px;">
                            <div style="font-size: 48px; color: var(--primary-color); margin-bottom: 15px;">
                                <i class="fas <?php echo $current_report[1]; ?>"></i>
                            </div>
                            <h2 style="font-size: 24px; margin-bottom: 10px;"><?php echo $current_report[0]; ?></h2>
                            <p style="color: var(--text-secondary);">للفترة من <?php echo $start_date; ?> إلى <?php echo $end_date; ?></p>
                            
                            <div style="margin-top: 30px; text-align: right; padding: 0 20px;">
                                <?php foreach ($quick_stats as $key => $value): ?>
                                    <?php if (in_array($key, ['total', 'count', 'average', 'max', 'stock_value', 'net_profit', 'profit_margin'])): ?>
                                        <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border-color);">
                                            <span style="color: var(--text-secondary);">
                                                <?php
                                                $labels = [
                                                    'total' => 'الإجمالي',
                                                    'count' => 'العدد',
                                                    'average' => 'المتوسط',
                                                    'max' => 'أعلى قيمة',
                                                    'stock_value' => 'قيمة المخزون',
                                                    'net_profit' => 'صافي الربح',
                                                    'profit_margin' => 'نسبة الربح'
                                                ];
                                                echo $labels[$key] ?? $key;
                                                ?>:
                                            </span>
                                            <span style="font-weight: bold; color: var(--primary-color);">
                                                <?php echo in_array($key, ['profit_margin']) ? number_format($value, 1) . '%' : number_format($value, 2) . ' ر.ي'; ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ==================== جدول البيانات ==================== -->
            <div class="table-card" style="margin-top: 24px;">
                <div class="table-header">
                    <h3>
                        <?php
                        $table_titles = [
                            'sales' => 'آخر 10 فواتير مبيعات',
                            'purchases' => 'آخر 10 فواتير مشتريات',
                            'inventory' => 'أقل 10 منتجات في المخزون',
                            'profits' => 'تحليل الأرباح الشهرية',
                            'customers' => 'أفضل 10 عملاء',
                            'suppliers' => 'أفضل 10 موردين'
                        ];
                        echo $table_titles[$report_type];
                        ?>
                    </h3>
                    <a href="#" class="view-all">عرض الكل <i class="fas fa-arrow-left"></i></a>
                </div>
                <div class="table-body">
                    <?php if (empty($table_data)): ?>
                        <div class="no-data-message">
                            <i class="fas fa-database fa-3x" style="opacity: 0.3; margin-bottom: 15px;"></i>
                            <p>لا توجد بيانات لعرضها</p>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <?php if ($report_type == 'sales'): ?>
                                        <th>رقم الفاتورة</th>
                                        <th>التاريخ</th>
                                        <th>العميل</th>
                                        <th>الإجمالي</th>
                                        <th>الحالة</th>
                                    <?php elseif ($report_type == 'purchases'): ?>
                                        <th>رقم الفاتورة</th>
                                        <th>التاريخ</th>
                                        <th>المورد</th>
                                        <th>الإجمالي</th>
                                        <th>الحالة</th>
                                    <?php elseif ($report_type == 'inventory'): ?>
                                        <th>كود المنتج</th>
                                        <th>اسم المنتج</th>
                                        <th>الكمية</th>
                                        <th>الوحدة</th>
                                        <th>سعر الشراء</th>
                                        <th>القيمة</th>
                                    <?php elseif ($report_type == 'profits'): ?>
                                        <th>الشهر</th>
                                        <th>عدد الفواتير</th>
                                        <th>المبيعات</th>
                                        <th>المشتريات</th>
                                        <th>الربح</th>
                                    <?php elseif ($report_type == 'customers'): ?>
                                        <th>اسم العميل</th>
                                        <th>عدد الفواتير</th>
                                        <th>إجمالي المشتريات</th>
                                    <?php elseif ($report_type == 'suppliers'): ?>
                                        <th>اسم المورد</th>
                                        <th>عدد الفواتير</th>
                                        <th>إجمالي المشتريات</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($table_data as $row): ?>
                                    <tr>
                                        <?php if ($report_type == 'sales'): ?>
                                            <td><?php echo $row['invoice_number']; ?></td>
                                            <td><?php echo $row['invoice_date']; ?></td>
                                            <td><?php echo $row['customer_name']; ?></td>
                                            <td><?php echo number_format($row['grand_total'], 2); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo str_replace(' ', '-', $row['payment_status']); ?>">
                                                    <?php echo $row['payment_status']; ?>
                                                </span>
                                            </td>
                                        <?php elseif ($report_type == 'purchases'): ?>
                                            <td><?php echo $row['purchase_number']; ?></td>
                                            <td><?php echo $row['purchase_date']; ?></td>
                                            <td><?php echo $row['supplier_name']; ?></td>
                                            <td><?php echo number_format($row['grand_total'], 2); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo str_replace(' ', '-', $row['payment_status']); ?>">
                                                    <?php echo $row['payment_status']; ?>
                                                </span>
                                            </td>
                                        <?php elseif ($report_type == 'inventory'): ?>
                                            <td><?php echo $row['product_code']; ?></td>
                                            <td><?php echo $row['product_name']; ?></td>
                                            <td><?php echo $row['current_quantity']; ?></td>
                                            <td><?php echo $row['product_unit']; ?></td>
                                            <td><?php echo number_format($row['purchase_price'], 2); ?></td>
                                            <td><?php echo number_format($row['total_value'], 2); ?></td>
                                        <?php elseif ($report_type == 'profits'): ?>
                                            <td><?php echo $row['month']; ?></td>
                                            <td><?php echo $row['invoice_count']; ?></td>
                                            <td><?php echo number_format($row['sales_total'], 2); ?></td>
                                            <td><?php echo number_format($row['purchases_total'], 2); ?></td>
                                            <td style="color: <?php echo $row['profit'] >= 0 ? '#4CAF50' : '#F44336'; ?>; font-weight: bold;">
                                                <?php echo number_format($row['profit'], 2); ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- ==================== تقارير إضافية ==================== -->
            <div class="additional-reports">
                <div class="report-card">
                    <div class="report-card-header">
                        <i class="fas fa-chart-bar" style="color: #2196F3;"></i>
                        <h4>تقرير مقارن</h4>
                    </div>
                    <div class="report-card-body">
                        <p>مقارنة المبيعات والمشتريات والأرباح</p>
                        <button class="btn-report"  onclick="comparison_report()">
                            <span>عرض التقرير</span>
                            <i class="fas fa-arrow-left"></i>
                        </button>
                    </div>
                </div>
                
                <div class="report-card">
                    <div class="report-card-header">
                        <i class="fas fa-chart-line" style="color: #4CAF50;"></i>
                        <h4>تقرير الاتجاهات</h4>
                    </div>
                    <div class="report-card-body">
                        <p>تحليل اتجاهات المبيعات الشهرية</p>
                        <button class="btn-report" onclick="trends_report()">
                            <span>عرض التقرير</span>
                            <i class="fas fa-arrow-left"></i>
                        </button>
                    </div>
                </div>
                
                <div class="report-card">
                    <div class="report-card-header">
                        <i class="fas fa-file-pdf" style="color: #F44336;"></i>
                        <h4>تصدير شامل</h4>
                    </div>
                    <div class="report-card-body">
                        <p>تصدير جميع التقارير بصيغة PDF</p>
                        <button class="btn-report" onclick="exportAllReports()">
                            <span>تصدير</span>
                            <i class="fas fa-download"></i>
                        </button>
                    </div>
                </div>
                
                <div class="report-card">
                    <div class="report-card-header">
                        <i class="fas fa-envelope" style="color: #FF9800;"></i>
                        <h4>إرسال بالبريد</h4>
                    </div>
                    <div class="report-card-body">
                        <p>إرسال التقارير عبر البريد الإلكتروني</p>
                        <button class="btn-report" onclick="emailReport()">
                            <span>إرسال</span>
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // ==================== بيانات الرسم البياني من PHP ====================
        const chartData = <?php echo json_encode($chart_data); ?>;
        const reportType = '<?php echo $report_type; ?>';
        
        // ==================== تهيئة الرسم البياني ====================
        document.addEventListener('DOMContentLoaded', function() {
            if (chartData && chartData.length > 0) {
                initDynamicChart();
            }
        });
        
        function initDynamicChart() {
            const ctx = document.getElementById('dynamicChart').getContext('2d');
            
            let chartType = 'line';
            let labels = [];
            let values = [];
            let backgroundColor = [];
            
            if (reportType === 'inventory' || reportType === 'customers' || reportType === 'suppliers') {
                chartType = 'bar';
                labels = chartData.map(item => item.label);
                values = chartData.map(item => item.value);
                backgroundColor = ['#2196F3', '#4CAF50', '#FF9800', '#F44336', '#9C27B0'];
            } else {
                chartType = 'line';
                labels = chartData.map(item => item.label);
                values = chartData.map(item => item.value);
            }
            
            new Chart(ctx, {
                type: chartType,
                data: {
                    labels: labels,
                    datasets: [{
                        label: reportType === 'inventory' ? 'عدد المنتجات' : 'القيمة (ر.ي)',
                        data: values,
                        backgroundColor: chartType === 'bar' ? backgroundColor : 'rgba(33, 150, 243, 0.1)',
                        borderColor: '#2196F3',
                        borderWidth: chartType === 'line' ? 3 : 0,
                        pointBackgroundColor: '#2196F3',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        tension: 0.4,
                        fill: chartType === 'line'
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
                                    let label = context.raw || 0;
                                    if (reportType === 'inventory') {
                                        return label + ' منتج';
                                    }
                                    return label.toFixed(2) + ' ر.ي';
                                }
                            }
                        }
                    },
                    scales: chartType === 'line' ? {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString() + ' ر.ي';
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    } : {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // ==================== دوال التصدير ====================
        function exportToPDF() {
            alert('سيتم تصدير التقرير الحالي كـ PDF قريباً');
        }
        
        function exportToExcel() {
            alert('سيتم تصدير التقرير الحالي كـ Excel قريباً');
        }
        
        function printReport() {
            window.print();
        }
        
        function exportAllReports() {
            alert('سيتم تصدير جميع التقارير كـ PDF قريباً');
        }
        
        function emailReport() {
            alert('سيتم إرسال التقارير عبر البريد الإلكتروني قريباً');
        }
        function trends_report() {
            alert('سيتم عمل تحليل اتجاهات المبيعات الشهرية قريباً');
        }
        function comparison_report() {
            alert('سيتم عمل مقارنة المبيعات والمشتريات والأرباح قريباً');
        }
        
        // ==================== قائمة جانبية قابلة للطي ====================
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('collapsed');
            document.querySelector('.main-content').classList.toggle('expanded');
        });
    </script>
</body>
</html>