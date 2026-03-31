<?php
session_start();
include "config.php";
// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: logout.php");
    exit();
}


// ==================== إحصائيات الفواتير ====================
$today = date('Y-m-d');
$first_day_month = date('Y-m-01');

// إجمالي المبيعات
$total_sales = $conn->query("SELECT COALESCE(SUM(grand_total), 0) as total FROM invoices")->fetch_assoc()['total'];

// إجمالي المبيعات اليوم
$today_sales = $conn->query("SELECT COALESCE(SUM(grand_total), 0) as total FROM invoices WHERE DATE(invoice_date) = '$today'")->fetch_assoc()['total'];

// عدد الفواتير الكلي
$total_invoices = $conn->query("SELECT COUNT(*) as count FROM invoices")->fetch_assoc()['count'];

// عدد الفواتير اليوم
$today_invoices = $conn->query("SELECT COUNT(*) as count FROM invoices WHERE DATE(invoice_date) = '$today'")->fetch_assoc()['count'];

// الفواتير المدفوعة
$paid_invoices = $conn->query("SELECT COUNT(*) as count, COALESCE(SUM(grand_total), 0) as total FROM invoices WHERE payment_status = 'مدفوعة'")->fetch_assoc();

// الفواتير غير المدفوعة
$unpaid_invoices = $conn->query("SELECT COUNT(*) as count, COALESCE(SUM(grand_total), 0) as total FROM invoices WHERE payment_status != 'مدفوعة'")->fetch_assoc();

// استعلام للحصول على الفواتير
$sql = "SELECT 
            i.invoice_id,
            i.invoice_number,
            i.invoice_date,
            c.customer_name,
            c.customer_id,
            i.subtotal,
            i.total_discount,
            i.total_tax,
            i.grand_total,
            i.payment_status,
            i.payment_method,
            i.notes,
            i.created_at,
            u.full_name as created_by_name
        FROM invoices i
        LEFT JOIN customers c ON i.customer_id = c.customer_id
        LEFT JOIN users u ON i.created_by = u.user_id
        ORDER BY i.invoice_date DESC, i.invoice_id DESC";

$result = $conn->query($sql);
$has_invoices = ($result && $result->num_rows > 0);

// بيانات للرسم البياني (آخر 7 أيام)
$chart_labels = [];
$chart_values = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $day_name = date('D', strtotime($date));
    $day_names = ['Sun' => 'الأحد', 'Mon' => 'الإثنين', 'Tue' => 'الثلاثاء', 'Wed' => 'الأربعاء', 'Thu' => 'الخميس', 'Fri' => 'الجمعة', 'Sat' => 'السبت'];
    
    $sales = $conn->query("SELECT COALESCE(SUM(grand_total), 0) as total FROM invoices WHERE DATE(invoice_date) = '$date'")->fetch_assoc()['total'];
    
    $chart_labels[] = $day_names[$day_name];
    $chart_values[] = floatval($sales);
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فواتير المبيعات - نظام المخازن</title>
    
    <!-- Font Awesome محلي -->
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">
    
    <!-- Chart.js محلي -->
    <script src="assets/chart.js/chart.min.js"></script>
    
    <!-- ملف CSS الرئيسي (نفس ملف التقارير) -->
    <link rel="stylesheet" href="Style/reports.css">
    
    <style>
        /* تنسيقات إضافية لصفحة الفواتير */
        .filters-section {
            background: white;
            border-radius: var(--radius-lg);
            padding: 24px;
            margin-bottom: 24px;
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
        
        .filter-input {
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .filter-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.1);
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
            padding-right: 40px;
        }
        
        .filter-actions {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }
        
        .btn-filter {
            padding: 10px 20px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 14px;
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
            padding: 10px 20px;
            background: var(--bg-color);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-reset:hover {
            background: #e2e8f0;
        }
        
        .stats-mini-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-mini-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
        }
        
        .stat-mini-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-mini-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .stat-mini-content h4 {
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }
        
        .stat-mini-content .value {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .stat-mini-content .small {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .table-container {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-bottom: 24px;
        }
        
        .table-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        }
        
        .btn-excel {
            padding: 8px 16px;
            background: var(--success-color);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-excel:hover {
            background: #388E3C;
        }
        
        .btn-pdf {
            padding: 8px 16px;
            background: var(--danger-color);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-pdf:hover {
            background: #D32F2F;
        }
        
        .table-wrapper {
            overflow-x: auto;
            padding: 0 20px 20px;
        }
        
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .invoice-table th {
            text-align: center;
            padding: 7px 5px;
            background: var(--bg-color);
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 13px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .invoice-table td {
            padding: 5px 3px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            font-size: 14px;
        }
        
        .invoice-table tbody tr {
            transition: all 0.3s ease;
        }
        
        .invoice-table tbody tr:hover {
            background: rgba(33, 150, 243, 0.05);
        }
        
        .invoice-number {
            font-weight: 600;
            color: var(--primary-color);
            background: rgba(33, 150, 243, 0.1);
            padding: 4px 8px;
            border-radius: var(--radius-sm);
            font-size: 13px;
        }
        
        .customer-name {
            display:flex;
            font-weight: 600;
            
        }
        
        .amount {
            font-weight: 700;
            color: var(--primary-color);
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
        
        .pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px;
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
        
        .page-dots {
            color: var(--text-secondary);
            padding: 0 4px;
        }
        
        @media (max-width: 768px) {
            .stats-mini-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filter-actions {
                flex-direction: column;
            }
            
            .table-header {
                flex-direction: column;
                gap: 16px;
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
                <a href="add_item_tostore.php" class="nav-item">
                    <i class="fas fa-truck"></i>
                    <span>فاتورة مشتريات</span>
                </a> -->
                <a href="invoice_page.php" class="nav-item active">
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
                    <h1><i class="fas fa-file-invoice" style="color: var(--primary-color); margin-left: 10px;"></i> فواتير المبيعات</h1>
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
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-mini-content">
                        <h4>مدفوعة</h4>
                        <div class="value"><?php echo $paid_invoices['count']; ?></div>
                        <div class="small"><?php echo number_format($paid_invoices['total'], 2); ?> ر.ي</div>
                    </div>
                </div>
                
                <div class="stat-mini-card">
                    <div class="stat-mini-icon" style="background: rgba(244, 67, 54, 0.1); color: #F44336;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-mini-content">
                        <h4>غير مدفوعة</h4>
                        <div class="value"><?php echo $unpaid_invoices['count']; ?></div>
                        <div class="small"><?php echo number_format($unpaid_invoices['total'], 2); ?> ر.ي</div>
                    </div>
                </div>
                
                <div class="stat-mini-card">
                    <div class="stat-mini-icon" style="background: rgba(255, 152, 0, 0.1); color: #FF9800;">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-mini-content">
                        <h4>مبيعات اليوم</h4>
                        <div class="value"><?php echo number_format($today_sales, 2); ?></div>
                        <div class="small">ر.ي</div>
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
                            <input type="text" class="filter-input" id="invoiceNumberFilter" placeholder="مثال: INV-2024-001">
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <label>اسم العميل</label>
                        <div class="filter-input-wrapper">
                            <i class="fas fa-user"></i>
                            <input type="text" class="filter-input" id="customerFilter" placeholder="بحث عن عميل...">
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <label>من تاريخ</label>
                        <div class="filter-input-wrapper">
                            <i class="fas fa-calendar"></i>
                            <input type="date" class="filter-input" id="dateFrom">
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <label>إلى تاريخ</label>
                        <div class="filter-input-wrapper">
                            <i class="fas fa-calendar"></i>
                            <input type="date" class="filter-input" id="dateTo">
                        </div>
                    </div>
                    
                    <!-- <div class="filter-group">
                        <label>حالة الدفع</label>
                        <div class="filter-input-wrapper">
                            <i class="fas fa-filter"></i>
                            <select class="filter-input" id="statusFilter">
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
                            <select class="filter-input" id="methodFilter">
                                <option value="">الكل</option>
                                <option value="نقدي">نقدي</option>
                                <option value="بطاقة">بطاقة</option>
                                <option value="تحويل">تحويل</option>
                            </select>
                        </div>
                    </div> -->
                </div>
                
                <div class="filter-actions" style="margin-top: 20px;">
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
            
            <!-- ==================== جدول الفواتير ==================== -->
            <div class="table-container">
                <div class="table-header">
                    <h2>
                        <i class="fas fa-list-ul"></i>
                        قائمة فواتير المبيعات
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
                        <button class="btn-filter" onclick="window.location.href='New_sales_invoice.php'" style="background: var(--success-color);">
                            <i class="fas fa-plus"></i>
                            <span>فاتورة جديدة</span>
                        </button>
                    </div>
                </div>
                
                <div class="table-wrapper">
                    <table class="invoice-table" id="invoicesTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>رقم الفاتورة</th>
                                <th>التاريخ</th>
                                <th>العميل</th>
                                <th>الإجمالي</th>
                                <th>الصافي</th>
                                <th>طريقة الدفع</th>
                                <th>الحالة</th>
                                <th>بواسطة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php if ($has_invoices): ?>
                                <?php $counter = 1; ?>
                                <?php while($invoice = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td>
                                            <span class="invoice-number"><?php echo htmlspecialchars($invoice['invoice_number']); ?></span>
                                        </td>
                                        <td><?php echo $invoice['invoice_date']; ?></td>
                                        <td class="customer-name">
                                            <div class="action-buttons">
                                                        <button class="action-btn" onclick="viewCustomer(<?php echo $invoice['customer_id']; ?>)" title="عرض حساب العميل">
                                                            <i class="fas fa-user"></i>
                                                        </button>
                                            </div>
                                            <?php echo htmlspecialchars($invoice['customer_name'] ?? 'غير محدد'); ?>
                                            
                                        </td>
                                        <td><?php echo number_format($invoice['subtotal'], 2); ?></td>
                                        
                                        <td class="amount"><?php echo number_format($invoice['grand_total'], 2); ?> ر.ي</td>
                                        <td><?php echo $invoice['payment_method']; ?></td>
                                        <td>
                                            <span class="status-badge status-<?php 
                                                echo $invoice['payment_status'] == 'مدفوعة' ? 'مدفوعة' : 
                                                     ($invoice['payment_status'] == 'غير مدفوعة' ? 'غير-مدفوعة' : 'مدفوعة-جزئياً'); 
                                            ?>">
                                                <?php echo $invoice['payment_status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                        <div style="display: flex; align-items: center; gap: 5px;">
                                                <i class="fas fa-user-circle" style="color: #2196F3;"></i>
                                                <?php echo $invoice['created_by_name'] ?? 'غير محدد'; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="action-btn" onclick="viewInvoice(<?php echo $invoice['invoice_id']; ?>)" title="عرض">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="action-btn" onclick="printInvoice(<?php echo $invoice['invoice_id']; ?>)" title="طباعة">
                                                    <i class="fas fa-print"></i>
                                                </button>
                                                <button class="action-btn" onclick="printInvoicee(<?php echo $invoice['invoice_id']; ?>)" title="طباعة">
                                                    <i class="fas fa-bolt"></i>  <!-- أيقونة برق -->
                                                    <i class="fas fa-print"></i>
                                                </button>
                                                
                                                <button class="action-btn" style="color: #FF9800;" onclick="returnInvoice(<?php echo $invoice['invoice_id']; ?>)" title="مرتجع">
                                                    <i class="fas fa-undo-alt"></i>
                                                </button>
                                                <?php if ($_SESSION['full_name'] == 'مدير النظام'): ?>
                                                    <button class="action-btn" onclick="deleteInvoice(<?php echo $invoice['invoice_id']; ?>, '<?php echo $invoice['invoice_number']; ?>')" title="حذف">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <!--  -->
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" style="text-align: center; padding: 50px;">
                                        <i class="fas fa-file-invoice" style="font-size: 48px; color: var(--text-secondary); opacity: 0.3; margin-bottom: 15px;"></i>
                                        <p style="color: var(--text-secondary);">لا توجد فواتير لعرضها</p>
                                        <button class="btn-filter" onclick="window.location.href='New_sales_invoice.php'" style="margin-top: 15px;">
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
                <?php if ($has_invoices): ?>
                <!-- <div class="pagination">
                    <div class="pagination-info">
                        عرض 1 إلى <?php echo min(10, $total_invoices); ?> من أصل <?php echo $total_invoices; ?> فاتورة
                    </div>
                    <div class="pagination-controls">
                        <button class="page-btn">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                        <button class="page-btn active">1</button>
                        <button class="page-btn">2</button>
                        <button class="page-btn">3</button>
                        <span class="page-dots">...</span>
                        <button class="page-btn">10</button>
                        <button class="page-btn">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                    </div>
                </div> -->
                <?php endif; ?>
            </div>
            
            <!-- ==================== رسم بياني صغير للمبيعات ==================== -->
            <?php if ($has_invoices): ?>
            <div class="chart-card" style="margin-top: 24px;">
                <div class="chart-header">
                    <h3>اتجاهات المبيعات (آخر 7 أيام)</h3>
                </div>
                <div class="chart-body" style="height: 250px;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
                // ==================== بحث تلقائي ====================
        function setupAutoSearch() {
            const invoiceInput = document.getElementById('invoiceNumberFilter');
            const customerInput = document.getElementById('customerFilter');
            const dateFromInput = document.getElementById('dateFrom');
            const dateToInput = document.getElementById('dateTo');
            
            const searchFields = [invoiceInput, customerInput, dateFromInput, dateToInput];
            
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

        
        // ==================== بيانات الرسم البياني ====================
        const chartLabels = <?php echo json_encode($chart_labels); ?>;
        const chartValues = <?php echo json_encode($chart_values); ?>;
        
        // ==================== تهيئة الرسم البياني ====================
        document.addEventListener('DOMContentLoaded', function() {
            if (chartLabels.length > 0 && chartValues.length > 0) {
                initSalesChart();
            }
            
             // تعيين تاريخ اليوم كحد أقصى
            const today = new Date().toISOString().split('T')[0];
            const dateTo = document.getElementById('dateTo');
            const dateFrom = document.getElementById('dateFrom');
            if (dateTo) dateTo.max = today;
            if (dateFrom) dateFrom.max = today;
            
            // تعيين تاريخ أول الشهر كقيمة افتراضية
            const firstDay = new Date();
            firstDay.setDate(1);
            if (dateFrom) dateFrom.value = firstDay.toISOString().split('T')[0];
            
            // إعداد البحث التلقائي
            setupAutoSearch();
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
                            }
                        }
                    }
                }
            });
        }
        
        // ==================== دوال الفلاتر ====================
        // ==================== دوال الفلاتر ====================
        function applyFilters() {
            const invoiceNumber = document.getElementById('invoiceNumberFilter').value.toLowerCase().trim();
            const customer = document.getElementById('customerFilter').value.toLowerCase().trim();
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            
            const rows = document.querySelectorAll('#tableBody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                let showRow = true;
                
                // الحصول على البيانات من الخلايا
                // row.cells[0] = # (رقم)
                // row.cells[1] = رقم الفاتورة
                // row.cells[2] = التاريخ
                // row.cells[3] = العميل
                // row.cells[4] = الإجمالي
                // row.cells[5] = الصافي
                // row.cells[6] = طريقة الدفع
                // row.cells[7] = الحالة
                // row.cells[8] = بواسطة
                // row.cells[9] = الإجراءات
                
                const invoiceNumberCell = row.cells[1]?.textContent?.toLowerCase().trim() || '';
                const customerCell = row.cells[3]?.textContent?.toLowerCase().trim() || '';
                const dateCell = row.cells[2]?.textContent?.trim() || '';
                
                // فلترة حسب رقم الفاتورة
                if (invoiceNumber && !invoiceNumberCell.includes(invoiceNumber)) {
                    showRow = false;
                }
                
                // فلترة حسب اسم العميل
                if (customer && !customerCell.includes(customer)) {
                    showRow = false;
                }
                
                // فلترة حسب التاريخ
                if (dateFrom || dateTo) {
                    const invoiceDate = new Date(dateCell);
                    
                    if (dateFrom && !isNaN(invoiceDate) && invoiceDate < new Date(dateFrom)) {
                        showRow = false;
                    }
                    
                    if (dateTo && !isNaN(invoiceDate) && invoiceDate > new Date(dateTo)) {
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
                    msgRow.innerHTML = `<td colspan="10" style="text-align: center; padding: 50px;">
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
            document.getElementById('customerFilter').value = '';
            
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
        function viewInvoice(invoiceId) {
            window.location.href = 'view_sales_invoice.php?id=' + invoiceId;
        }
        
        function editInvoice(invoiceId) {
            window.location.href = 'edit_invoice.php?id=' + invoiceId;
        }
        
        function deleteInvoice(invoiceId, invoiceNumber) {
            if (confirm('هل أنت متأكد من حذف الفاتورة رقم: ' + invoiceNumber + '؟')) {
                window.location.href = 'delete_invoice.php?id=' + invoiceId;
            }
        }
        
        function printInvoice(invoiceId) {
            window.open('print_sales_invoice.php?id=' + invoiceId, '_blank');
        }
        function printInvoicee(invoiceId) {
                // تحديد أبعاد النافذة
            var width = 800;
            var height = 600;
            
            // حساب منتصف الشاشة
            var left = (screen.width - width) / 2;
            var top = (screen.height - height) / 2;
            
            // فتح النافذة في المنتصف
            var printWindow = window.open(
                'print_sales_invoice.php?id=' + invoiceId + '&auto_print=1',
                'printWindow',
                'width=' + width + 
                ',height=' + height + 
                ',left=' + left + 
                ',top=' + top + 
                ',scrollbars=yes,resizable=yes'

            );
            
            // منع فتح النافذة إذا كان المانع مفعل
            if (!printWindow) {
                alert('يرجى السماح للنوافذ المنبثقة لهذا الموقع');
            }
        }
        
        function exportToExcel() {
            alert('سيتم تصدير البيانات إلى Excel قريباً');
        }
        
        function exportToPDF() {
            alert('سيتم تصدير البيانات إلى PDF قريباً');
        }
        
        // ==================== اختصارات لوحة المفاتيح ====================
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                window.location.href = 'New_sales_invoice.php';
            }
            
            if (e.key === '/' && e.target.tagName !== 'INPUT') {
                e.preventDefault();
                document.getElementById('customerFilter').focus();
            }
        });
        
        // ==================== قائمة جانبية قابلة للطي ====================
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('collapsed');
            document.querySelector('.main-content').classList.toggle('expanded');
        });


                // ==================== دالة الارجاع ====================
        function returnInvoice(invoiceId) {
                window.location.href = 'sales_return.php?invoice_id=' + invoiceId;
        }
        function viewCustomer(id_customer){
            window.location.href = 'view_customer.php?id=' + id_customer;
        }
    </script>
    
    <?php
    $conn->close();
    ?>
</body>
</html>