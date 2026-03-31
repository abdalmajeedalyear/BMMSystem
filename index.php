<?php
session_start();
include "config.php";        
// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: logout.php");
    exit();
}


// ==================== إحصائيات لوحة التحكم ====================
$today = date('Y-m-d');
$first_day_month = date('Y-m-01');
$first_day_year = date('Y-01-01');

// إجمالي المبيعات اليوم
$today_sales = $conn->query("SELECT COALESCE(SUM(grand_total), 0) as total FROM invoices WHERE DATE(invoice_date) = '$today'")->fetch_assoc()['total'];

// إجمالي المبيعات الشهر
$month_sales = $conn->query("SELECT COALESCE(SUM(grand_total), 0) as total FROM invoices WHERE invoice_date BETWEEN '$first_day_month' AND '$today'")->fetch_assoc()['total'];

// عدد الفواتير اليوم
$today_invoices = $conn->query("SELECT COUNT(*) as count FROM invoices WHERE DATE(invoice_date) = '$today'")->fetch_assoc()['count'];

// عدد الفواتير الكلي
$total_invoices = $conn->query("SELECT COUNT(*) as count FROM invoices")->fetch_assoc()['count'];

// المنتجات منخفضة المخزون
$low_stock_count = $conn->query("SELECT COUNT(*) as count FROM products WHERE current_quantity <= min_quantity")->fetch_assoc()['count'];

// إجمالي المنتجات
$total_products = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];

// قيمة المخزون
$stock_value = $conn->query("SELECT COALESCE(SUM(current_quantity * purchase_price), 0) as total FROM products")->fetch_assoc()['total'];

// آخر 5 فواتير
$recent_invoices = $conn->query("
    SELECT 
        i.invoice_id,
        i.invoice_number,
        i.invoice_date,
        c.customer_name,
        i.grand_total,
        i.payment_status
    FROM invoices i
    LEFT JOIN customers c ON i.customer_id = c.customer_id
    ORDER BY i.invoice_date DESC
    LIMIT 5
");

// أفضل 5 منتجات مبيعاً
$top_products = $conn->query("
    SELECT 
        p.product_name,
        COALESCE(SUM(ii.product_quantity), 0) as total_sold,
        COALESCE(SUM(ii.product_total), 0) as total_revenue
    FROM products p
    LEFT JOIN invoice_items ii ON p.product_name = ii.product_name
    GROUP BY p.product_id
    ORDER BY total_sold DESC
    LIMIT 5
");

// بيانات المبيعات لآخر 7 أيام للرسم البياني
$chart_data = [];
$chart_dates = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $sales = $conn->query("SELECT COALESCE(SUM(grand_total), 0) as total FROM invoices WHERE DATE(invoice_date) = '$date'")->fetch_assoc()['total'];
    
    $day_name = date('D', strtotime($date));
    $day_names = ['Sun' => 'الأحد', 'Mon' => 'الإثنين', 'Tue' => 'الثلاثاء', 'Wed' => 'الأربعاء', 'Thu' => 'الخميس', 'Fri' => 'الجمعة', 'Sat' => 'السبت'];
    
    $chart_data[] = floatval($sales);
    $chart_dates[] = $day_names[$day_name];
}

// حالة المخزون لبعض المنتجات
$stock_status = $conn->query("
    SELECT 
        product_name,
        current_quantity,
        min_quantity,
        ((current_quantity / (min_quantity * 2)) * 100) as percentage
    FROM products
    WHERE current_quantity > 0
    ORDER BY percentage ASC
    LIMIT 4
");
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - نظام إدارة المخازن</title>
    
    <!-- Font Awesome محلي -->
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">
    
    <!-- Chart.js محلي -->
    <script src="assets/chart.js/chart.min.js"></script>
    
    <!-- ملف CSS الرئيسي (نفس ملف التقارير) -->
    <link rel="stylesheet" href="Style/reports.css">
    
    <style>
        /* تنسيقات إضافية خاصة بلوحة التحكم */
        .welcome-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, #1976D2 100%);
            color: white;
            padding: 30px;
            border-radius: var(--radius-lg);
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .welcome-text h2 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .welcome-text p {
            opacity: 0.9;
            font-size: 16px;
        }
        
        .welcome-date {
            background: rgba(255, 255, 255, 0.2);
            padding: 12px 24px;
            border-radius: var(--radius-lg);
            font-size: 18px;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .action-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .action-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        .action-icon {
            width: 50px;
            height: 50px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .action-content h4 {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .action-content p {
            font-size: 13px;
            color: var(--text-secondary);
        }
        
        .progress-item {
            margin-bottom: 20px;
        }
        
        .progress-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .progress-bar-bg {
            height: 8px;
            background: var(--bg-color);
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        
        .progress-fill.primary { background: var(--primary-color); }
        .progress-fill.success { background: var(--success-color); }
        .progress-fill.warning { background: var(--warning-color); }
        .progress-fill.danger { background: var(--danger-color); }
        
        .inventory-alert {
            background: rgba(244, 67, 54, 0.1);
            border: 1px solid rgba(244, 67, 54, 0.2);
            border-radius: var(--radius-md);
            padding: 16px;
            margin-top: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--danger-color);
        }
        
        @media (max-width: 768px) {
            .welcome-section {
                flex-direction: column;
                text-align: center;
                gap: 16px;
            }
            
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
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
            
            <nav class="sidebar-nav" >
                <a href="index.php" class="nav-item active">
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
                <button class="menu-toggle"  id="menuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                
                
                <div class="page-title">
                    
                    <h1>لوحة التحكم</h1>
                </div>
                <button class="menu-toggle" id="menuToggle" style="margin-left:-340px;">
                    <a href="backup.php" class="btn-backup" title="النسخ الاحتياطي">
                        <i class="fas fa-database"></i>
                    </a>
                    
                </button>
                
                <div class="top-bar-actions">
                    <button class="btn-refresh" onclick="location.reload()">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                    <div class="notification-wrapper">
                        <i class="fas fa-bell"></i>
                        <?php if ($low_stock_count > 0): ?>
                            <span class="notification-badge"><?php echo $low_stock_count; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="date-display">
                        <i class="far fa-calendar-alt"></i>
                        <span><?php echo date('Y-m-d'); ?></span>
                    </div>
                    
                </div>
                
            </header>
            
            
            <!-- ==================== قسم الترحيب ==================== -->
            <div class="welcome-section">
                <div class="welcome-text">
                    <h2>مرحباً بك يا <?php echo $_SESSION['full_name'] ?? 'مدير النظام'; ?> في نظام إدارة المخازن</h2>
                    <p>نظرة عامة على أداء المخزن والمبيعات اليوم</p>
                </div>
                <div class="welcome-date">
                    <i class="far fa-calendar-alt ml-2"></i>
                    <?php echo date('l, d F Y'); ?>
                </div>
            </div>
            
            <!-- ==================== الإجراءات السريعة ==================== -->
            <div class="quick-actions">
                <div class="action-card" onclick="window.location.href='New_sales_invoice.php'">
                    <div class="action-icon" style="background: rgba(33, 150, 243, 0.1); color: #2196F3;">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <div class="action-content">
                        <h4>فاتورة جديدة</h4>
                        <p>إنشاء فاتورة مبيعات</p>
                    </div>
                </div>
                
                <div class="action-card" onclick="window.location.href='add_item_tostore.php'">
                    <div class="action-icon" style="background: rgba(76, 175, 80, 0.1); color: #4CAF50;">
                        <i class="fas fa-truck"></i>
                    </div>
                    <div class="action-content">
                        <h4>إضافة للمخزون</h4>
                        <p>تحديث الكميات</p>
                    </div>
                </div>
                
                <div class="action-card" onclick="window.location.href='Store_items.php'">
                    <div class="action-icon" style="background: rgba(255, 152, 0, 0.1); color: #FF9800;">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div class="action-content">
                        <h4>المخزون</h4>
                        <p>عرض المنتجات</p>
                    </div>
                </div>
                
                <div class="action-card" onclick="window.location.href='reports.php'">
                    <div class="action-icon" style="background: rgba(156, 39, 176, 0.1); color: #9C27B0;">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="action-content">
                        <h4>التقارير</h4>
                        <p>تحليل الأداء</p>
                    </div>
                </div>
            </div>
            
            <!-- ==================== بطاقات الإحصائيات ==================== -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(33, 150, 243, 0.1); color: #2196F3;">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-content">
                        <h3>مبيعات اليوم</h3>
                        <div class="stat-value"><?php echo number_format($today_sales, 2); ?> ر.ي</div>
                        <div class="stat-change positive">+<?php echo $today_sales > 0 ? '12' : '0'; ?>%</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(76, 175, 80, 0.1); color: #4CAF50;">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-content">
                        <h3>مبيعات الشهر</h3>
                        <div class="stat-value"><?php echo number_format($month_sales, 2); ?> ر.ي</div>
                        <div class="stat-change positive">+8%</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(255, 152, 0, 0.1); color: #FF9800;">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-content">
                        <h3>فواتير اليوم</h3>
                        <div class="stat-value"><?php echo $today_invoices; ?></div>
                        <div class="stat-change">من <?php echo $total_invoices; ?> إجمالي</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(244, 67, 54, 0.1); color: #F44336;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-content">
                        <h3>منخفض المخزون</h3>
                        <div class="stat-value"><?php echo $low_stock_count; ?></div>
                        <div class="stat-change">من <?php echo $total_products; ?> منتج</div>
                    </div>
                </div>
            </div>
            
            <!-- ==================== الشبكة الرئيسية ==================== -->
            <div class="dashboard-grid">
                <!-- رسم بياني للمبيعات -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3>اتجاهات المبيعات (آخر 7 أيام)</h3>
                        <div class="chart-actions">
                            <select class="chart-select" onchange="changeChartPeriod(this.value)">
                                <option value="7">آخر 7 أيام</option>
                                <option value="30">آخر 30 يوم</option>
                                <option value="90">آخر 3 أشهر</option>
                            </select>
                        </div>
                    </div>
                    <div class="chart-body">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
                
                <!-- نظرة على المخزون -->
                <div class="inventory-card">
                    <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 20px;">حالة المخزون</h3>
                    
                    <div class="progress-list">
                        <?php 
                        $stock_status->data_seek(0);
                        while ($item = $stock_status->fetch_assoc()): 
                            $percentage = min(100, $item['percentage']);
                            $color_class = $percentage < 30 ? 'danger' : ($percentage < 60 ? 'warning' : 'success');
                        ?>
                        <div class="progress-item">
                            <div class="progress-header">
                                <span><?php echo $item['product_name']; ?></span>
                                <span style="font-weight: 700;"><?php echo round($percentage); ?>%</span>
                            </div>
                            <div class="progress-bar-bg">
                                <div class="progress-fill <?php echo $color_class; ?>" style="width: <?php echo $percentage; ?>%;"></div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    
                    <?php if ($low_stock_count > 0): ?>
                    <div class="inventory-alert">
                        <i class="fas fa-exclamation-circle fa-lg"></i>
                        <span>يوجد <?php echo $low_stock_count; ?> منتجات تحتاج إلى إعادة طلب</span>
                    </div>
                    <?php endif; ?>
                    
                    <button class="btn-report" onclick="window.location.href='Store_items.php'" style="margin-top: 20px;">
                        <span>عرض المخزون كاملاً</span>
                        <i class="fas fa-arrow-left"></i>
                    </button>
                </div>
            </div>
            
            <!-- ==================== جدول آخر الفواتير ==================== -->
            <div class="transactions-card" style="margin-top: 24px;">
                <div class="table-header">
                    <h3 style="font-size: 18px; font-weight: 700;">آخر الفواتير</h3>
                    <a href="invoices_list.php" class="view-all">عرض الكل <i class="fas fa-arrow-left"></i></a>
                </div>
                
                <div class="table-body">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>رقم الفاتورة</th>
                                <th>العميل</th>
                                <th>التاريخ</th>
                                <th>المبلغ</th>
                                <th>الحالة</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($invoice = $recent_invoices->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <span class="transaction-id"><?php echo $invoice['invoice_number']; ?></span>
                                </td>
                                <td><?php echo $invoice['customer_name'] ?? 'عميل نقدي'; ?></td>
                                <td><?php echo date('Y-m-d', strtotime($invoice['invoice_date'])); ?></td>
                                <td style="font-weight: 600; color: var(--primary-color);">
                                    <?php echo number_format($invoice['grand_total'], 2); ?> ر.ي
                                </td>
                                <td>
                                    <span class="status-badge status-<?php 
                                        echo $invoice['payment_status'] == 'مدفوعة' ? 'مدفوعة' : 
                                             ($invoice['payment_status'] == 'غير مدفوعة' ? 'غير-مدفوعة' : 'مدفوعة-جزئياً'); 
                                    ?>">
                                        <?php echo $invoice['payment_status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="action-btn" onclick="viewInvoice(<?php echo $invoice['invoice_id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- ==================== أفضل المنتجات مبيعاً ==================== -->
            <div class="transactions-card" style="margin-top: 24px;">
                <div class="table-header">
                    <h3 style="font-size: 18px; font-weight: 700;">أفضل المنتجات مبيعاً</h3>
                </div>
                
                <div class="table-body">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>اسم المنتج</th>
                                <th>الكمية المباعة</th>
                                <th>الإيرادات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = 1;
                            while ($product = $top_products->fetch_assoc()): 
                            ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td><?php echo $product['product_name']; ?></td>
                                <td><?php echo number_format($product['total_sold']); ?></td>
                                <td style="font-weight: 600; color: var(--success-color);">
                                    <?php echo number_format($product['total_revenue'], 2); ?> ر.ي
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <script>

        
        // بيانات الرسم البياني من PHP
        const chartLabels = <?php echo json_encode($chart_dates); ?>;
        const chartValues = <?php echo json_encode($chart_data); ?>;
        
        // تهيئة الرسم البياني
        document.addEventListener('DOMContentLoaded', function() {
            initSalesChart();
        });
        
        function initSalesChart() {
            const ctx = document.getElementById('salesChart').getContext('2d');
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'المبيعات (ر.ي)',
                        data: chartValues,
                        borderColor: '#2196F3',
                        backgroundColor: 'rgba(33, 150, 243, 0.1)',
                        borderWidth: 3,
                        pointBackgroundColor: '#2196F3',
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
                    }
                }
            });
        }
        
        function changeChartPeriod(days) {
            // يمكن إضافة تحديث الرسم البياني حسب الفترة المختارة
            alert('سيتم تحديث الرسم البياني لآخر ' + days + ' أيام قريباً');
        }
        
        function viewInvoice(invoiceId) {
            window.location.href = 'view_sales_invoice.php?id=' + invoiceId;
        }
        
        // قائمة جانبية قابلة للطي
        document.getElementById('menuToggle').addEventListener('click', function() {
            
            document.querySelector('.sidebar').classList.toggle('collapsed');
            document.querySelector('.main-content').classList.toggle('expanded');
        });
    </script>

    
</body>
</html>