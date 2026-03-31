<?php
session_start();
include "config.php";

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: logout.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$current_user_name = $_SESSION['full_name'] ?? 'مدير النظام';

// تحديد تاريخ اليوم
$today = date('Y-m-d');

// ==================== إحصائيات سريعة لليوم ====================
$stats = [];

// إجمالي مبيعات اليوم
$stats['sales'] = $conn->query("SELECT COALESCE(SUM(grand_total), 0) as total FROM invoices WHERE DATE(invoice_date) = '$today'")->fetch_assoc()['total'];

// إجمالي مشتريات اليوم
$stats['purchases'] = $conn->query("SELECT COALESCE(SUM(grand_total), 0) as total FROM purchases WHERE DATE(purchase_date) = '$today'")->fetch_assoc()['total'];

// إجمالي سندات القبض اليوم
$stats['payments'] = $conn->query("SELECT COALESCE(SUM(payment_amount), 0) as total FROM customer_payments WHERE DATE(payment_date) = '$today'")->fetch_assoc()['total'];

// إجمالي مرتجعات اليوم
$stats['returns'] = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales_returns WHERE DATE(return_date) = '$today'")->fetch_assoc()['total'];

// إجمالي سندات التسليم اليوم
$stats['credits'] = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM customer_credit_notes WHERE DATE(credit_note_date) = '$today'")->fetch_assoc()['total'];

// عدد الفواتير اليوم
$stats['invoices_count'] = $conn->query("SELECT COUNT(*) as count FROM invoices WHERE DATE(invoice_date) = '$today'")->fetch_assoc()['count'];

// عدد المشتريات اليوم
$stats['purchases_count'] = $conn->query("SELECT COUNT(*) as count FROM purchases WHERE DATE(purchase_date) = '$today'")->fetch_assoc()['count'];

// عدد سندات القبض اليوم
$stats['payments_count'] = $conn->query("SELECT COUNT(*) as count FROM customer_payments WHERE DATE(payment_date) = '$today'")->fetch_assoc()['count'];

// عدد مرتجعات اليوم
$stats['returns_count'] = $conn->query("SELECT COUNT(*) as count FROM sales_returns WHERE DATE(return_date) = '$today'")->fetch_assoc()['count'];

// عدد سندات التسليم اليوم
$stats['credits_count'] = $conn->query("SELECT COUNT(*) as count FROM customer_credit_notes WHERE DATE(credit_note_date) = '$today'")->fetch_assoc()['count'];

// ==================== جلب جميع معاملات اليوم ====================
$transactions = [];

// 1. فواتير المبيعات
$invoices_sql = "SELECT 
                    'فاتورة مبيعات' as type,
                    'invoice' as type_key,
                    invoice_number as number,
                    invoice_date as date,
                    CONCAT(invoice_date, ' ', TIME(created_at)) as full_datetime,
                    customer_id,
                    (SELECT customer_name FROM customers WHERE customer_id = i.customer_id) as party_name,
                    grand_total as amount,
                    paid_amount,
                    remaining_amount,
                    payment_status,
                    created_by,
                    (SELECT full_name FROM users WHERE user_id = i.created_by) as created_by_name,
                    invoice_id as link_id
                FROM invoices i
                WHERE DATE(invoice_date) = '$today'
                ORDER BY created_at DESC";

$invoices_result = $conn->query($invoices_sql);
while ($row = $invoices_result->fetch_assoc()) {
    $transactions[] = $row;
}

// 2. فواتير المشتريات
$purchases_sql = "SELECT 
                    'فاتورة مشتريات' as type,
                    'purchase' as type_key,
                    purchase_number as number,
                    purchase_date as date,
                    CONCAT(purchase_date, ' ', TIME(created_at)) as full_datetime,
                    supplier_id,
                    supplier_name as party_name,
                    grand_total as amount,
                    paid_amount,
                    remaining_amount,
                    payment_status,
                    created_by,
                    (SELECT full_name FROM users WHERE user_id = p.created_by) as created_by_name,
                    id as link_id
                FROM purchases p
                WHERE DATE(purchase_date) = '$today'
                ORDER BY created_at DESC";

$purchases_result = $conn->query($purchases_sql);
while ($row = $purchases_result->fetch_assoc()) {
    $transactions[] = $row;
}

// 3. سندات القبض
$payments_sql = "SELECT 
                    'سند قبض' as type,
                    'payment' as type_key,
                    payment_number as number,
                    payment_date as date,
                    CONCAT(payment_date, ' ', TIME(created_at)) as full_datetime,
                    customer_id,
                    (SELECT customer_name FROM customers WHERE customer_id = cp.customer_id) as party_name,
                    payment_amount as amount,
                    payment_method,
                    reference_number,
                    notes,
                    created_by,
                    (SELECT full_name FROM users WHERE user_id = cp.created_by) as created_by_name,
                    payment_id as link_id
                FROM customer_payments cp
                WHERE DATE(payment_date) = '$today'
                ORDER BY created_at DESC";

$payments_result = $conn->query($payments_sql);
while ($row = $payments_result->fetch_assoc()) {
    $transactions[] = $row;
}

// 4. مرتجعات مبيعات
$returns_sql = "SELECT 
                    'مرتجع مبيعات' as type,
                    'return' as type_key,
                    return_number as number,
                    return_date as date,
                    CONCAT(return_date, ' ', TIME(created_at)) as full_datetime,
                    customer_id,
                    customer_name as party_name,
                    total_amount as amount,
                    reason,
                    invoice_id,
                    created_by,
                    (SELECT full_name FROM users WHERE user_id = sr.created_by) as created_by_name,
                    return_id as link_id
                FROM sales_returns sr
                WHERE DATE(return_date) = '$today'
                ORDER BY created_at DESC";

$returns_result = $conn->query($returns_sql);
while ($row = $returns_result->fetch_assoc()) {
    $transactions[] = $row;
}

// 5. سندات التسليم
$credits_sql = "SELECT 
                    'سند تسليم' as type,
                    'credit' as type_key,
                    credit_note_number as number,
                    credit_note_date as date,
                    CONCAT(credit_note_date, ' ', TIME(created_at)) as full_datetime,
                    customer_id,
                    (SELECT customer_name FROM customers WHERE customer_id = cc.customer_id) as party_name,
                    amount as amount,
                    payment_method,
                    reference_number,
                    reason as notes,
                    created_by,
                    (SELECT full_name FROM users WHERE user_id = cc.created_by) as created_by_name,
                    credit_note_id as link_id
                FROM customer_credit_notes cc
                WHERE DATE(credit_note_date) = '$today'
                ORDER BY created_at DESC";

$credits_result = $conn->query($credits_sql);
while ($row = $credits_result->fetch_assoc()) {
    $transactions[] = $row;
}

// 6. سندات تسليم الموردين (مدفوعات للموردين)
$supplier_payments_sql = "SELECT 
                    'سند تسليم مورد' as type,
                    'supplier_payment' as type_key,
                    payment_number as number,
                    payment_date as date,
                    CONCAT(payment_date, ' ', TIME(created_at)) as full_datetime,
                    supplier_id,
                    (SELECT supplier_name FROM suppliers WHERE supplier_id = sp.supplier_id) as party_name,
                    payment_amount as amount,
                    payment_method,
                    reference_number,
                    notes,
                    created_by,
                    (SELECT full_name FROM users WHERE user_id = sp.created_by) as created_by_name,
                    payment_id as link_id
                FROM supplier_payments sp
                WHERE DATE(payment_date) = '$today'
                ORDER BY created_at DESC";

$supplier_payments_result = $conn->query($supplier_payments_sql);
while ($row = $supplier_payments_result->fetch_assoc()) {
    $transactions[] = $row;
}

// إجمالي سندات تسليم الموردين اليوم
$today_supplier_payments = $conn->query("SELECT COALESCE(SUM(payment_amount), 0) as total FROM supplier_payments WHERE DATE(payment_date) = '$today'")->fetch_assoc()['total'];

// عدد سندات تسليم الموردين اليوم
$supplier_payments_count = $conn->query("SELECT COUNT(*) as count FROM supplier_payments WHERE DATE(payment_date) = '$today'")->fetch_assoc()['count'];

// ترتيب المعاملات حسب التاريخ والوقت (الأحدث أولاً)
usort($transactions, function($a, $b) {
    return strtotime($b['full_datetime']) - strtotime($a['full_datetime']);
});




// ==================== نشاط المستخدمين اليوم ====================
// ==================== نشاط المستخدمين اليوم ====================
$user_activity = $conn->query("
    SELECT 
        u.user_id,
        u.full_name,
        u.user_role,
        COUNT(DISTINCT i.invoice_id) as invoices_count,
        COUNT(DISTINCT p.id) as purchases_count,
        COUNT(DISTINCT cp.payment_id) as payments_count,
        COUNT(DISTINCT sr.return_id) as returns_count,
        COUNT(DISTINCT cc.credit_note_id) as credits_count,
        COUNT(DISTINCT sp.payment_id) as supplier_payments_count
    FROM users u
    LEFT JOIN invoices i ON u.user_id = i.created_by AND DATE(i.created_at) = '$today'
    LEFT JOIN purchases p ON u.user_id = p.created_by AND DATE(p.created_at) = '$today'
    LEFT JOIN customer_payments cp ON u.user_id = cp.created_by AND DATE(cp.created_at) = '$today'
    LEFT JOIN sales_returns sr ON u.user_id = sr.created_by AND DATE(sr.created_at) = '$today'
    LEFT JOIN customer_credit_notes cc ON u.user_id = cc.created_by AND DATE(cc.created_at) = '$today'
    LEFT JOIN supplier_payments sp ON u.user_id = sp.created_by AND DATE(sp.created_at) = '$today'
    WHERE u.is_active = 1
    GROUP BY u.user_id
    ORDER BY u.full_name ASC
");

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
    <title>التقرير اليومي - <?php echo $today; ?></title>
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">
    <link rel="stylesheet" href="Style/reports.css">
    <style>
        /* تنسيقات إضافية */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            margin: 24px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .stat-icon {
            width: 55px;
            height: 55px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-content h3 {
            font-size: 13px;
            color: #666;
            margin-bottom: 6px;
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }

        .stat-sub {
            font-size: 11px;
            color: #999;
            margin-top: 3px;
        }

        /* نشاط المستخدمين */
        .user-activity {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin: 0 24px 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .user-activity h2 {
            font-size: 18px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #1a3e6f;
        }

        .user-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
        }

        .user-item {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            border-right: 4px solid #1a3e6f;
            transition: all 0.3s;
        }

        .user-item.current-user {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            border-right-color: #4CAF50;
            box-shadow: 0 2px 8px rgba(76,175,80,0.2);
        }

        .user-name {
            font-weight: bold;
            color: #1a3e6f;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .current-user .user-name {
            color: #2e7d32;
        }

        .user-badge {
            background: #4CAF50;
            color: white;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 20px;
        }

        .user-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            font-size: 12px;
        }

        .user-stat {
            background: white;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        /* الخط الزمني */
        .timeline {
            background: white;
            border-radius: 16px;
            margin: 0 24px 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .timeline-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 2px solid #1a3e6f;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .timeline-header h2 {
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .timeline-filter {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 6px 16px;
            border: 1px solid #ddd;
            border-radius: 30px;
            background: white;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
        }

        .filter-btn:hover {
            border-color: #1a3e6f;
            color: #1a3e6f;
        }

        .filter-btn.active {
            background: #1a3e6f;
            color: white;
            border-color: #1a3e6f;
        }

        .timeline-body {
            padding: 20px;
            max-height: 600px;
            overflow-y: auto;
        }

        .timeline-item {
            display: flex;
            gap: 20px;
            padding: 18px;
            margin-bottom: 12px;
            background: #f8f9fa;
            border-radius: 14px;
            transition: all 0.3s;
            border-right: 4px solid;
            position: relative;
        }

        .timeline-item:hover {
            transform: translateX(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .timeline-item.current-user-item {
            background: linear-gradient(135deg, #f1f8e9, #e8f5e9);
        }

        .item-time {
            min-width: 90px;
            font-weight: bold;
            color: #1a3e6f;
            font-size: 13px;
        }

        .item-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .item-content {
            flex: 1;
        }

        .item-title {
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 15px;
        }

        .item-details {
            font-size: 12px;
            color: #666;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }

        .item-amount {
            font-weight: bold;
            font-size: 14px;
        }

        .item-user {
            color: #1a3e6f;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .item-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .action-btn {
            width: 34px;
            height: 34px;
            border: none;
            border-radius: 8px;
            background: white;
            color: #666;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .action-btn:hover {
            background: #1a3e6f;
            color: white;
            transform: scale(1.05);
        }

        .action-btn.print:hover {
            background: #2196F3;
        }

        .action-btn.return:hover {
            background: #FF9800;
        }

        /* ألوان حسب النوع */
        .type-invoice { border-color: #2196F3; }
        .type-purchase { border-color: #FF9800; }
        .type-payment { border-color: #4CAF50; }
        .type-credit { border-color: #FF9800; }
        .type-return { border-color: #F44336; }

        .icon-invoice { background: #e3f2fd; color: #2196F3; }
        .icon-purchase { background: #fff3e0; color: #FF9800; }
        .icon-payment { background: #e8f5e9; color: #4CAF50; }
        .icon-credit { background: #fff3e0; color: #FF9800; }
        .icon-return { background: #ffebee; color: #F44336; }

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

        @media (max-width: 1200px) {
            .stats-cards {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            .timeline-item {
                flex-direction: column;
                gap: 12px;
            }
            .item-time {
                min-width: auto;
            }
            .item-actions {
                justify-content: flex-start;
            }
        }
        /* تنسيق سند تسليم المورد */
        .type-supplier-payment { border-color: #9C27B0; }
        .icon-supplier-payment { background: #f3e5f5; color: #9C27B0; }

        /* إضافة للفلتر */
        .filter-btn.supplier-payment.active {
            background: #9C27B0;
            color: white;
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
                <a href="index.php" class="nav-item"><i class="fas fa-tachometer-alt"></i><span>لوحة التحكم</span></a>
                <a href="Daily_Dashboard.php" class="nav-item active"><i class="fas fa-calendar-day"></i><span>التقرير اليومي</span></a>
                <a href="Store_items.php" class="nav-item"><i class="fas fa-boxes"></i><span>المخزون</span></a>
                <a href="invoice_page.php" class="nav-item"><i class="fas fa-list"></i><span>المبيعات</span></a>
                <a href="purchases_page.php" class="nav-item"><i class="fas fa-truck-loading"></i><span>المشتريات</span></a>
                <a href="returns_page.php" class="nav-item"><i class="fas fa-undo-alt"></i><span>المرتجعات</span></a>
                <a href="reports.php" class="nav-item"><i class="fas fa-chart-pie"></i><span>التقارير</span></a>
                <a href="customers.php" class="nav-item"><i class="fas fa-users"></i><span>العملاء</span></a>
                <a href="suppliers.php" class="nav-item"><i class="fas fa-truck"></i><span>الموردين</span></a>
            </nav>
            <div class="sidebar-footer">
                <div class="user-info">
                    <i class="fas fa-user-circle"></i>
                    <div style="display: flex; flex-direction: column;">
                        <span style="font-weight: bold;"><?php echo $current_user_name; ?></span>
                        <span style="font-size: 11px; opacity: 0.8;"><?php echo $_SESSION['user_role'] ?? ''; ?></span>
                    </div>
                </div>
                <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>تسجيل الخروج</span></a>
            </div>
        </aside>

        <!-- ==================== المحتوى الرئيسي ==================== -->
        <main class="main-content">
            <header class="top-bar">
                <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
                <div class="page-title"><h1><i class="fas fa-calendar-day" style="color: var(--primary-color); margin-left: 10px;"></i> التقرير اليومي</h1></div>
                <div class="top-bar-actions">
                    <button class="btn-refresh" onclick="location.reload()"><i class="fas fa-sync-alt"></i></button>
                    <div class="date-display"><i class="far fa-calendar-alt"></i> <span><?php echo date('Y-m-d'); ?></span></div>
                </div>
            </header>

            <!-- ==================== بطاقات الإحصائيات ==================== -->
            <div class="stats-cards">
                <div class="stat-card"><div class="stat-icon" style="background: #e3f2fd; color: #2196F3;"><i class="fas fa-file-invoice"></i></div>
                    <div class="stat-content"><h3>فواتير المبيعات</h3><div class="stat-number"><?php echo $stats['invoices_count']; ?></div><div class="stat-sub"><?php echo formatNumber($stats['sales']); ?> ر.ي</div></div></div>
                <div class="stat-card"><div class="stat-icon" style="background: #fff3e0; color: #FF9800;"><i class="fas fa-truck-loading"></i></div>
                    <div class="stat-content"><h3>فواتير المشتريات</h3><div class="stat-number"><?php echo $stats['purchases_count']; ?></div><div class="stat-sub"><?php echo formatNumber($stats['purchases']); ?> ر.ي</div></div></div>
                <div class="stat-card"><div class="stat-icon" style="background: #e8f5e9; color: #4CAF50;"><i class="fas fa-hand-holding-usd"></i></div>
                    <div class="stat-content"><h3>سندات القبض</h3><div class="stat-number"><?php echo $stats['payments_count']; ?></div><div class="stat-sub"><?php echo formatNumber($stats['payments']); ?> ر.ي</div></div></div>
                <div class="stat-card"><div class="stat-icon" style="background: #fff3e0; color: #FF9800;"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="stat-content"><h3>سندات التسليم</h3><div class="stat-number"><?php echo $stats['credits_count']; ?></div><div class="stat-sub"><?php echo formatNumber($stats['credits']); ?> ر.ي</div></div></div>
                <div class="stat-card"><div class="stat-icon" style="background: #ffebee; color: #F44336;"><i class="fas fa-undo-alt"></i></div>
                    <div class="stat-content"><h3>المرتجعات</h3><div class="stat-number"><?php echo $stats['returns_count']; ?></div><div class="stat-sub"><?php echo formatNumber($stats['returns']); ?> ر.ي</div></div></div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: #f3e5f5; color: #9C27B0;">
                        <i class="fas fa-truck"></i>
                    </div>
                    <div class="stat-content">
                        <h3>سندات تسليم موردين</h3>
                        <div class="stat-number"><?php echo $supplier_payments_count ?? 0; ?></div>
                        <div class="stat-sub"><?php echo formatNumber($today_supplier_payments ?? 0); ?> ر.ي</div>
                    </div>
                </div>
            </div>

            <!-- ==================== نشاط المستخدمين ==================== -->
            <?php if ($user_activity && $user_activity->num_rows > 0): ?>
            <div class="user-activity">
                <h2><i class="fas fa-users"></i> نشاط المستخدمين اليوم</h2>
                <div class="user-grid">
                    <?php while ($user = $user_activity->fetch_assoc()): 
                        $is_current = ($user['user_id'] == $current_user_id);
                    ?>
                    <div class="user-item <?php echo $is_current ? 'current-user' : ''; ?>">
                        <div class="user-name">
                            <i class="fas fa-user-circle"></i> <?php echo $user['full_name']; ?>
                            <?php if ($is_current): ?>
                            <span class="user-badge"><i class="fas fa-check-circle"></i> أنت</span>
                            <?php endif; ?>
                            <span style="font-size: 11px; color: #999;">(<?php echo $user['user_role']; ?>)</span>
                        </div>
                        <div class="user-stats">
                            <?php if ($user['invoices_count'] > 0): ?><span class="user-stat" style="background: #e3f2fd;"><i class="fas fa-file-invoice"></i> <?php echo $user['invoices_count']; ?></span><?php endif; ?>
                            <?php if ($user['purchases_count'] > 0): ?><span class="user-stat" style="background: #fff3e0;"><i class="fas fa-truck-loading"></i> <?php echo $user['purchases_count']; ?></span><?php endif; ?>
                            <?php if ($user['payments_count'] > 0): ?><span class="user-stat" style="background: #e8f5e9;"><i class="fas fa-hand-holding-usd"></i> <?php echo $user['payments_count']; ?></span><?php endif; ?>
                            <?php if ($user['credits_count'] > 0): ?><span class="user-stat" style="background: #fff3e0;"><i class="fas fa-money-bill-wave"></i> <?php echo $user['credits_count']; ?></span><?php endif; ?>
                            <?php if ($user['returns_count'] > 0): ?><span class="user-stat" style="background: #ffebee;"><i class="fas fa-undo-alt"></i> <?php echo $user['returns_count']; ?></span><?php endif; ?>
                            <?php if ($user['supplier_payments_count'] > 0): ?><span class="user-stat" style="background: #f3e5f5;"><i class="fas fa-truck"></i> <?php echo $user['supplier_payments_count']; ?></span><?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ==================== الخط الزمني ==================== -->
            <div class="timeline">
                <div class="timeline-header">
                    <h2><i class="fas fa-clock"></i> الأحداث اليوم (<?php echo count($transactions); ?>)</h2>
                    <div class="timeline-filter">
                        <button class="filter-btn active" onclick="filterTimeline('all')">الكل</button>
                        <button class="filter-btn" onclick="filterTimeline('invoice')">فواتير مبيعات</button>
                        <button class="filter-btn" onclick="filterTimeline('purchase')">فواتير مشتريات</button>
                        <button class="filter-btn" onclick="filterTimeline('payment')">سندات قبض</button>
                        <button class="filter-btn" onclick="filterTimeline('credit')">سندات تسليم</button>
                        <button class="filter-btn" onclick="filterTimeline('supplier_payment')">سندات موردين</button>
                        <button class="filter-btn" onclick="filterTimeline('return')">مرتجعات</button>
                    </div>
                </div>
                <div class="timeline-body" id="timelineBody">
                    <?php if (count($transactions) > 0): ?>
                        <?php foreach ($transactions as $trans): 
                            $type_class = '';
                            $icon_class = '';
                            $link = '';
                            $is_current_user = ($trans['created_by'] == $current_user_id);
                            
                            if ($trans['type'] == 'فاتورة مبيعات') {
                                $type_class = 'type-invoice';
                                $icon_class = 'icon-invoice';
                                $link = 'view_sales_invoice.php?id=' . $trans['link_id'];
                            } elseif ($trans['type'] == 'فاتورة مشتريات') {
                                $type_class = 'type-purchase';
                                $icon_class = 'icon-purchase';
                                $link = 'view_purchase.php?id=' . $trans['link_id'];
                            } elseif ($trans['type'] == 'سند قبض') {
                                $type_class = 'type-payment';
                                $icon_class = 'icon-payment';
                                $link = 'view_payment.php?id=' . $trans['link_id'];
                            } elseif ($trans['type'] == 'سند تسليم') {
                                $type_class = 'type-credit';
                                $icon_class = 'icon-credit';
                                $link = 'view_credit_note.php?id=' . $trans['link_id'];
                            } elseif ($trans['type'] == 'مرتجع مبيعات') {
                                $type_class = 'type-return';
                                $icon_class = 'icon-return';
                                $link = 'view_sales_return.php?id=' . $trans['link_id'];
                            } elseif ($trans['type'] == 'سند تسليم مورد') {
                                $type_class = 'type-supplier-payment';
                                $icon_class = 'icon-supplier-payment';
                                $link = 'view_supplier_payment.php?id=' . $trans['link_id'];
                            }
                        ?>
                        <div class="timeline-item <?php echo $type_class . ($is_current_user ? ' current-user-item' : ''); ?>" data-type="<?php echo $trans['type']; ?>">
                            <div class="item-time">
                                <i class="far fa-clock"></i> <?php echo date('h:i A', strtotime($trans['full_datetime'])); ?>
                            </div>
                            <div class="item-icon <?php echo $icon_class; ?>">
                                <?php
                                if ($trans['type'] == 'فاتورة مبيعات') echo '<i class="fas fa-file-invoice"></i>';
                                elseif ($trans['type'] == 'فاتورة مشتريات') echo '<i class="fas fa-truck-loading"></i>';
                                elseif ($trans['type'] == 'سند قبض') echo '<i class="fas fa-hand-holding-usd"></i>';
                                elseif ($trans['type'] == 'مرتجع مبيعات') echo '<i class="fas fa-undo-alt"></i>';
                                elseif ($trans['type'] == 'سند تسليم') echo '<i class="fas fa-money-bill-wave"></i>';
                                elseif ($trans['type'] == 'سند تسليم مورد') echo '<i class="fas fa-truck"></i>';
                                ?>
                            </div>
                            <div class="item-content">
                                <div class="item-title">
                                    <?php echo $trans['type']; ?> - <?php echo $trans['number']; ?>
                                </div>
                                <div class="item-details">
                                    <span><i class="fas fa-user"></i> <?php echo $trans['party_name'] ?: 'غير محدد'; ?></span>
                                    <span class="item-amount"><i class="fas fa-money-bill"></i> <?php echo formatNumber($trans['amount']); ?> ر.ي</span>
                                    <span class="item-user <?php echo $is_current_user ? 'current-user' : ''; ?>">
                                        <i class="fas fa-user-cog"></i> <?php echo $trans['created_by_name'] ?: 'غير محدد'; ?>
                                        <?php if ($is_current_user): ?><span style="color: #4CAF50;"> (أنت)</span><?php endif; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="item-actions">
                                <a href="<?php echo $link; ?>" class="action-btn" title="عرض التفاصيل"><i class="fas fa-eye"></i></a>
                                <?php if ($trans['type'] == 'فاتورة مبيعات'): ?>
                                    <button class="action-btn print" onclick="printInvoice(<?php echo $trans['link_id']; ?>)" title="طباعة"><i class="fas fa-print"></i></button>
                                    <button class="action-btn return" onclick="returnInvoice(<?php echo $trans['link_id']; ?>)" title="مرتجع"><i class="fas fa-undo-alt"></i></button>
                                <?php endif; ?>
                                <?php if ($trans['type'] == 'سند قبض'): ?>
                                    <button class="action-btn print" onclick="printPayment(<?php echo $trans['link_id']; ?>, <?php echo $trans['customer_id'] ?? 0; ?>)" title="طباعة"><i class="fas fa-print"></i></button>
                                <?php endif; ?>
                                <?php if ($trans['type'] == 'سند تسليم'): ?>
                                    <button class="action-btn print" onclick="printCreditNote(<?php echo $trans['link_id']; ?>, <?php echo $trans['customer_id'] ?? 0; ?>)" title="طباعة"><i class="fas fa-print"></i></button>
                                <?php endif; ?>
                                <?php if ($trans['type'] == 'فاتورة مشتريات'): ?>
                                    <button class="action-btn print" onclick="printPurchase(<?php echo $trans['link_id']; ?>)" title="طباعة"><i class="fas fa-print"></i></button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <div class="no-data"><i class="fas fa-calendar-day"></i><p>لا توجد معاملات اليوم</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // حفظ حالة الشريط الجانبي
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            const menuToggle = document.getElementById('menuToggle');
            const savedState = localStorage.getItem('sidebarCollapsed');
            if (savedState === 'true') {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
            }
            if (menuToggle) {
                menuToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('expanded');
                    localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
                });
            }
        });

        // فلترة المعاملات
        function filterTimeline(type) {
            const items = document.querySelectorAll('.timeline-item');
            const buttons = document.querySelectorAll('.filter-btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            items.forEach(item => {
                if (type === 'all') {
                    item.style.display = 'flex';
                } else {
                    const itemType = item.getAttribute('data-type');
                    if (type === 'invoice' && itemType === 'فاتورة مبيعات') item.style.display = 'flex';
                    else if (type === 'purchase' && itemType === 'فاتورة مشتريات') item.style.display = 'flex';
                    else if (type === 'payment' && itemType === 'سند قبض') item.style.display = 'flex';
                    else if (type === 'credit' && itemType === 'سند تسليم') item.style.display = 'flex';
                    else if (type === 'return' && itemType === 'مرتجع مبيعات') item.style.display = 'flex';
                    else if (itemType.includes('تسليم مورد') && type === 'supplier_payment') item.style.display = 'flex';
                    else item.style.display = 'none';
                }
            });
        }

        // دوال الإجراءات
        function printInvoice(invoiceId) { window.open('print_sales_invoice.php?id=' + invoiceId, '_blank'); }
        function printPurchase(purchaseId) { window.open('print_purchase.php?id=' + purchaseId, '_blank'); }
        function printPayment(paymentId, customerId) { window.open('print_payment.php?id=' + paymentId + '&customer_id=' + customerId, '_blank'); }
        function printCreditNote(creditId, customerId) { window.open('print_credit_note.php?id=' + creditId + '&customer_id=' + customerId, '_blank'); }
        function returnInvoice(invoiceId) { window.location.href = 'sales_return.php?invoice_id=' + invoiceId; }
    </script>
</body>
</html>