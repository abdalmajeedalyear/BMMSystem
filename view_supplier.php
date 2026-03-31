<?php
session_start();
include "config.php";

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: logout.php");
    exit();
}


// التحقق من وجود معرف المورد
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: suppliers.php");
    exit();
}

$supplier_id = intval($_GET['id']);

// ==================== إحصائيات المشتريات والمدفوعات ====================

// 1. إجمالي المشتريات والمبالغ
$purchases_stats = $conn->query("
    SELECT 
        COUNT(*) as total_purchases,
        COALESCE(SUM(grand_total), 0) as total_purchase_amount,
        COALESCE(SUM(paid_amount), 0) as total_paid_in_purchases,
        COALESCE(SUM(remaining_amount), 0) as total_remaining
    FROM purchases 
    WHERE supplier_id = $supplier_id
")->fetch_assoc();

// 2. إحصائيات سندات التسليم (مدفوعات نقدية للمورد)
$payments_stats = $conn->query("
    SELECT 
        COUNT(*) as total_payments,
        COALESCE(SUM(payment_amount), 0) as total_payments_amount
    FROM supplier_payments 
    WHERE supplier_id = $supplier_id
")->fetch_assoc();

// 3. حساب صافي المستحق للمورد
$total_remaining = $purchases_stats['total_remaining'];
$total_paid_cash = $payments_stats['total_payments_amount'];
$net_due = $total_remaining - $total_paid_cash;

// استعلام للحصول على بيانات المورد
$sql = "SELECT 
            s.supplier_id,
            s.supplier_name,
            s.supplier_phone,
            s.supplier_email,
            s.supplier_address,
            s.tax_number,
            s.created_at,
            COUNT(p.id) as total_purchases,
            COALESCE(SUM(p.grand_total), 0) as total_spent,
            COALESCE(SUM(p.paid_amount), 0) as total_paid_in_purchases,
            COALESCE(SUM(p.remaining_amount), 0) as total_remaining,
            COALESCE(AVG(p.grand_total), 0) as avg_purchase,
            MAX(p.purchase_date) as last_purchase_date,
            MIN(p.purchase_date) as first_purchase_date
        FROM suppliers s
        LEFT JOIN purchases p ON s.supplier_id = p.supplier_id
        WHERE s.supplier_id = ?
        GROUP BY s.supplier_id";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['alert'] = [
        'type' => 'error',
        'message' => "❌ المورد غير موجود"
    ];
    header("Location: suppliers.php");
    exit();
}

$supplier = $result->fetch_assoc();

// استعلام للحصول على آخر 5 فواتير مشتريات للمورد
$purchases_sql = "SELECT 
                    id as purchase_id,
                    purchase_number,
                    purchase_date,
                    grand_total,
                    paid_amount,
                    remaining_amount,
                    payment_status,
                    payment_method,
                    created_at
                FROM purchases 
                WHERE supplier_id = ?
                ORDER BY purchase_date DESC
                ";

$purchases_stmt = $conn->prepare($purchases_sql);
$purchases_stmt->bind_param("i", $supplier_id);
$purchases_stmt->execute();
$purchases_result = $purchases_stmt->get_result();
$has_purchases = ($purchases_result && $purchases_result->num_rows > 0);

// استعلام للحصول على جميع سندات التسليم للمورد
$payments_sql = "SELECT 
                    payment_id,
                    payment_number,
                    payment_date,
                    payment_amount,
                    payment_method,
                    reference_number,
                    notes,
                    created_at
                FROM supplier_payments 
                WHERE supplier_id = ?
                ORDER BY payment_date DESC";

$payments_stmt = $conn->prepare($payments_sql);
$payments_stmt->bind_param("i", $supplier_id);
$payments_stmt->execute();
$payments_result = $payments_stmt->get_result();
$has_payments = ($payments_result && $payments_result->num_rows > 0);
$total_payments_count = $has_payments ? $payments_result->num_rows : 0;

// إحصائيات إضافية
$year_start = date('Y-01-01');
$year_end = date('Y-12-31');

// مشتريات هذا العام
$yearly_purchases = $conn->query("SELECT COALESCE(SUM(grand_total), 0) as total FROM purchases 
                                  WHERE supplier_id = $supplier_id 
                                  AND purchase_date BETWEEN '$year_start' AND '$year_end'")->fetch_assoc()['total'];

// مشتريات هذا الشهر
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
$monthly_purchases = $conn->query("SELECT COALESCE(SUM(grand_total), 0) as total FROM purchases 
                                   WHERE supplier_id = $supplier_id 
                                   AND purchase_date BETWEEN '$month_start' AND '$month_end'")->fetch_assoc()['total'];

// أكبر فاتورة
$max_purchase = $conn->query("SELECT MAX(grand_total) as max FROM purchases 
                              WHERE supplier_id = $supplier_id")->fetch_assoc()['max'] ?: 0;

// آخر فاتورة
$last_purchase = $conn->query("
    SELECT purchase_date, grand_total, paid_amount, remaining_amount 
    FROM purchases 
    WHERE supplier_id = $supplier_id 
    ORDER BY purchase_date DESC 
    LIMIT 1
")->fetch_assoc();

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
    <title>عرض المورد - <?php echo $supplier['supplier_name']; ?></title>
    
    <!-- Font Awesome محلي -->
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">
    
    <!-- ملف CSS الرئيسي -->
    <link rel="stylesheet" href="Style/reports.css">
    
    <!-- Chart.js محلي -->
    <script src="assets/chart.js/chart.min.js"></script>
    
    <style>
        /* تنسيقات إضافية لصفحة عرض المورد */
        .supplier-view-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* ==================== أزرار الإجراءات ==================== */
        .action-bar {
            background: white;
            border-radius: var(--radius-lg);
            padding: 15px 25px;
            margin-bottom: 25px;
            box-shadow: var(--shadow-sm);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
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
            text-decoration: none;
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
        
        .btn-warning {
            background: var(--warning-color);
            color: white;
        }
        
        .btn-warning:hover {
            background: #F57C00;
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
        
        .btn-payment {
            background: #9C27B0;
            color: white;
        }
        
        .btn-payment:hover {
            background: #7B1FA2;
            transform: translateY(-2px);
        }
        
        /* ==================== بطاقة المورد ==================== */
        .supplier-card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .supplier-header {
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
            color: white;
            padding: 30px;
            display: flex;
            align-items: center;
            gap: 25px;
            flex-wrap: wrap;
        }
        
        .supplier-avatar {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .supplier-avatar i {
            font-size: 40px;
            color: #4CAF50;
        }
        
        .supplier-header-info h2 {
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .supplier-header-info p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .supplier-status {
            background: rgba(255,255,255,0.2);
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-right: auto;
        }
        
        .supplier-body {
            padding: 30px;
        }
        
        /* ==================== بطاقات الإحصائيات ==================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 25px;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.3s;
            border: 1px solid var(--border-color);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .stat-content h3 {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: var(--text-primary);
            margin-bottom: 5px;
        }
        
        .stat-desc {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        /* بطاقة صافي المستحق */
        .stat-card.due-positive {
            background: linear-gradient(135deg, #f44336, #d32f2f);
            color: white;
        }
        
        .stat-card.due-negative {
            background: linear-gradient(135deg, #4CAF50, #388E3C);
            color: white;
        }
        
        .stat-card.due-positive .stat-icon,
        .stat-card.due-negative .stat-icon {
            background: rgba(255,255,255,0.2) !important;
            color: white !important;
        }
        
        .stat-card.due-positive .stat-content h3,
        .stat-card.due-positive .stat-content .stat-value,
        .stat-card.due-positive .stat-content .stat-desc,
        .stat-card.due-negative .stat-content h3,
        .stat-card.due-negative .stat-content .stat-value,
        .stat-card.due-negative .stat-content .stat-desc {
            color: white;
        }
        
        /* ==================== صندوق المستحق البارز ==================== */
        .due-box-positive {
            background: linear-gradient(135deg, #f44336, #d32f2f);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 5px 15px rgba(244,67,54,0.3);
        }
        
        .due-box-negative {
            background: linear-gradient(135deg, #4CAF50, #388E3C);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 5px 15px rgba(76,175,80,0.3);
        }
        
        .due-box-positive h3, .due-box-negative h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            margin-bottom: 5px;
        }
        
        .due-box-positive .amount, .due-box-negative .amount {
            font-size: 28px;
            font-weight: bold;
            background: rgba(255,255,255,0.2);
            padding: 8px 25px;
            border-radius: 50px;
        }
        
        .due-subtitle {
            font-size: 13px;
            opacity: 0.9;
        }
        
        /* ==================== معلومات المورد ==================== */
        .info-section {
            background: var(--bg-color);
            border-radius: var(--radius-lg);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .info-section h3 {
            font-size: 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-primary);
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .info-label {
            font-size: 12px;
            color: var(--text-secondary);
            font-weight: bold;
        }
        
        .info-value {
            font-size: 16px;
            color: var(--text-primary);
            font-weight: 500;
            background: white;
            padding: 12px 15px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
        }
        
        .info-value i {
            margin-left: 8px;
            color: #4CAF50;
        }
        
        /* ==================== جدول المشتريات ==================== */
        .purchases-section {
            background: white;
            border-radius: var(--radius-lg);
            padding: 25px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 30px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .section-header h3 {
            font-size: 18px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .view-all {
            color: #4CAF50;
            text-decoration: none;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .view-all:hover {
            text-decoration: underline;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            text-align: right;
            padding: 15px 10px;
            background: var(--bg-color);
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 13px;
            border-bottom: 2px solid var(--border-color);
        }
        
        td {
            padding: 15px 10px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
            color: var(--text-primary);
        }
        
        tbody tr:hover {
            background: rgba(76, 175, 80, 0.05);
        }
        
        .purchase-number {
            font-weight: bold;
            color: #4CAF50;
        }
        
        .amount {
            font-weight: bold;
            color: #4CAF50;
        }
        
        .paid-amount {
            color: #4CAF50;
            font-weight: bold;
        }
        
        .remaining-amount {
            color: #f44336;
            font-weight: bold;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }
        
        .status-paid {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }
        
        .status-unpaid {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
        }
        
        .status-partial {
            background: rgba(255, 152, 0, 0.1);
            color: #ff9800;
        }
        
        .action-btn {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 6px;
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        
        .action-btn:hover {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }
        
        /* ==================== جدول سندات التسليم ==================== */
        .payments-section {
            background: white;
            border-radius: var(--radius-lg);
            padding: 25px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 30px;
            margin-top: 20px;
        }
        
        .payment-number {
            font-weight: bold;
            color: #9C27B0;
        }
        
        /* ==================== إحصائيات المشتريات ==================== */
        .chart-section {
            background: white;
            border-radius: var(--radius-lg);
            padding: 25px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 30px;
        }
        
        .stats-mini-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-mini-card {
            background: var(--bg-color);
            padding: 20px;
            border-radius: var(--radius-md);
        }
        
        .stat-mini-label {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }
        
        .stat-mini-value {
            font-size: 24px;
            font-weight: bold;
            color: #4CAF50;
        }
        
        .chart-container {
            height: 300px;
            margin-top: 20px;
        }
        
        /* ==================== رسالة عدم وجود بيانات ==================== */
        .no-data {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
            background: var(--bg-color);
            border-radius: var(--radius-md);
        }
        
        .no-data i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.3;
        }
        
        /* ==================== نافذة إضافة سند تسليم ==================== */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal-container {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
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
            background: linear-gradient(135deg, #9C27B0, #7B1FA2);
            color: white;
            padding: 20px 25px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
        }
        
        .modal-close {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            width: 36px;
            height: 36px;
            border-radius: 50%;
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
            padding: 25px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: bold;
            color: #666;
            margin-bottom: 8px;
        }
        
        .form-group label i {
            margin-left: 8px;
            color: #9C27B0;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #9C27B0;
            box-shadow: 0 0 0 3px rgba(156,39,176,0.1);
        }
        
        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        /* ==================== تجاوب مع الشاشات ==================== */
        @media (max-width: 1200px) {
            .stats-grid,
            .info-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid,
            .info-grid,
            .stats-mini-grid {
                grid-template-columns: 1fr;
            }
            
            .supplier-header {
                flex-direction: column;
                text-align: center;
            }
            
            .supplier-status {
                margin-right: 0;
            }
            
            .action-bar {
                flex-direction: column;
            }
            
            .section-header {
                flex-direction: column;
                gap: 10px;
            }
            
            .due-box-positive, .due-box-negative {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
        }
        .layout{
            padding:0px;
            margin-right:-150px;
            width: 108%;
            min-height: 100vh;
        }
    </style>
</head>
<body>
    <div class="layout">
        <!-- ==================== الشريط الجانبي ==================== -->
        <!-- <aside class="sidebar">
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
                <a href="Store_items.php" class="nav-item">
                    <i class="fas fa-boxes"></i>
                    <span>المخزون</span>
                </a>
                <a href="New_sales_invoice.php" class="nav-item">
                    <i class="fas fa-file-invoice"></i>
                    <span>فاتورة مبيعات</span>
                </a>
                <a href="add_item_tostore.php" class="nav-item">
                    <i class="fas fa-truck"></i>
                    <span>فاتورة مشتريات</span>
                </a>
                <a href="invoice_page.php" class="nav-item">
                    <i class="fas fa-list"></i>
                    <span>المبيعات</span>
                </a>
                <a href="purchases_page.php" class="nav-item">
                    <i class="fas fa-truck-loading"></i>
                    <span>المشتريات</span>
                </a>
                <a href="customers.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>العملاء</span>
                </a>
                <a href="suppliers.php" class="nav-item active">
                    <i class="fas fa-handshake"></i>
                    <span>الموردين</span>
                </a>
                <a href="reports.php" class="nav-item">
                    <i class="fas fa-chart-pie"></i>
                    <span>التقارير</span>
                </a>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <i class="fas fa-user-circle"></i>
                    <span><?php echo $_SESSION['username'] ?? 'مدير النظام'; ?></span>
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>تسجيل الخروج</span>
                </a>
            </div>
        </aside> -->
        
        <!-- ==================== المحتوى الرئيسي ==================== -->
        <main class="main-content">
            <!-- ==================== شريط التنقل العلوي ==================== -->
            <header class="top-bar">
                <button class="menu-toggle" id="menuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                
                <div class="page-title">
                    <h1><i class="fas fa-handshake" style="color: #4CAF50; margin-left: 10px;"></i> ملف المورد</h1>
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
            
            <!-- ==================== أزرار الإجراءات ==================== -->
            <div class="action-bar">
                <div class="action-buttons">
                    <a href="#" onclick="goback()" class="btn btn-secondary">
                        <i class="fas fa-arrow-right"></i>
                        العودة 
                    </a>
                    <!-- <a href="edit_supplier.php?id=<?php echo $supplier_id; ?>" class="btn btn-warning">
                        <i class="fas fa-edit"></i>
                        تعديل البيانات
                    </a> -->
                    <a href="add_item_tostore.php?supplier_id=<?php echo $supplier_id; ?>" class="btn btn-success">
                        <i class="fas fa-truck"></i>
                        فاتورة مشتريات
                    </a>
                    <button class="btn btn-payment" onclick="openPaymentModal()">
                        <i class="fas fa-money-bill-wave"></i>
                        سند تسليم
                    </button>
                    <!-- <button class="btn btn-primary" onclick="window.print()">
                        <i class="fas fa-print"></i>
                        طباعة
                    </button> -->
                </div>
            </div>
            
            <!-- ==================== بطاقة المورد ==================== -->
            <div class="supplier-card">
                <div class="supplier-header">
                    <div class="supplier-avatar">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <div class="supplier-header-info">
                        <h2><?php echo $supplier['supplier_name']; ?></h2>
                        <p>مورد منذ <?php echo date('Y-m-d', strtotime($supplier['created_at'])); ?></p>
                    </div>
                    <div class="supplier-status">
                        <i class="fas <?php echo $supplier['total_purchases'] > 0 ? 'fa-check-circle' : 'fa-clock'; ?>"></i>
                        <?php echo $supplier['total_purchases'] > 0 ? 'نشط' : 'غير نشط'; ?>
                    </div>
                </div>
                
                <div class="supplier-body">
                    
                    <!-- ==================== صندوق المستحق البارز ==================== -->
                    <?php if ($net_due > 0): ?>
                    <div class="due-box-positive">
                        <div>
                            <h3>
                                <i class="fas fa-exclamation-triangle"></i>
                                صافي المبلغ المستحق للمورد
                            </h3>
                            <p class="due-subtitle">
                                (متبقي من الفواتير: <?php echo formatNumber($total_remaining); ?> ر.س - مدفوع نقداً: <?php echo formatNumber($total_paid_cash); ?> ر.س)
                            </p>
                        </div>
                        <div class="amount"><?php echo formatNumber($net_due); ?> ر.س</div>
                    </div>
                    <?php elseif ($net_due < 0): ?>
                    <div class="due-box-negative">
                        <div>
                            <h3>
                                <i class="fas fa-check-circle"></i>
                                فائض لصالح الشركة
                            </h3>
                            <p class="due-subtitle">
                                (متبقي من الفواتير: <?php echo formatNumber($total_remaining); ?> ر.س - مدفوع نقداً: <?php echo formatNumber($total_paid_cash); ?> ر.س)
                            </p>
                        </div>
                        <div class="amount"><?php echo formatNumber(abs($net_due)); ?> ر.س</div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- ==================== بطاقات الإحصائيات ==================== -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(76, 175, 80, 0.1); color: #4CAF50;">
                                <i class="fas fa-truck"></i>
                            </div>
                            <div class="stat-content">
                                <h3>إجمالي المشتريات</h3>
                                <div class="stat-value"><?php echo $supplier['total_purchases']; ?></div>
                                <div class="stat-desc">فاتورة</div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(33, 150, 243, 0.1); color: #2196F3;">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="stat-content">
                                <h3>إجمالي الفواتير</h3>
                                <div class="stat-value"><?php echo formatNumber($supplier['total_spent']); ?> ر.س</div>
                                <div class="stat-desc">قيمة المشتريات</div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(76, 175, 80, 0.1); color: #4CAF50;">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div class="stat-content">
                                <h3>مدفوع في الفواتير</h3>
                                <div class="stat-value"><?php echo formatNumber($purchases_stats['total_paid_in_purchases']); ?> ر.س</div>
                                <div class="stat-desc">المسلم للفواتير</div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(156, 39, 176, 0.1); color: #9C27B0;">
                                <i class="fas fa-hand-holding-usd"></i>
                            </div>
                            <div class="stat-content">
                                <h3>سندات التسليم</h3>
                                <div class="stat-value"><?php echo $payments_stats['total_payments']; ?></div>
                                <div class="stat-desc">قيمة: <?php echo formatNumber($payments_stats['total_payments_amount']); ?> ر.س</div>
                            </div>
                        </div>
                        
                        <div class="stat-card <?php echo $net_due > 0 ? 'due-positive' : 'due-negative'; ?>">
                            <div class="stat-icon" style="background: rgba(255,255,255,0.2);">
                                <i class="fas fa-scale-balanced"></i>
                            </div>
                            <div class="stat-content">
                                <h3>صافي المستحق</h3>
                                <div class="stat-value"><?php echo formatNumber(abs($net_due)); ?> ر.س</div>
                                <div class="stat-desc">
                                    <?php echo $net_due > 0 ? 'مستحق للمورد' : 'فائض للشركة'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ==================== معلومات المورد ==================== -->
                    <div class="info-section">
                        <h3><i class="fas fa-info-circle" style="color: #4CAF50;"></i> معلومات الاتصال</h3>
                        
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">رقم الهاتف</span>
                                <div class="info-value">
                                    <i class="fas fa-phone"></i>
                                    <?php echo $supplier['supplier_phone'] ?: 'غير مسجل'; ?>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">البريد الإلكتروني</span>
                                <div class="info-value">
                                    <i class="fas fa-envelope"></i>
                                    <?php echo $supplier['supplier_email'] ?: 'غير مسجل'; ?>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">العنوان</span>
                                <div class="info-value">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo $supplier['supplier_address'] ?: 'غير مسجل'; ?>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">الرقم الضريبي</span>
                                <div class="info-value">
                                    <i class="fas fa-hashtag"></i>
                                    <?php echo $supplier['tax_number'] ?: 'غير مسجل'; ?>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">أول فاتورة</span>
                                <div class="info-value">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo $supplier['first_purchase_date'] ? date('Y-m-d', strtotime($supplier['first_purchase_date'])) : 'لا يوجد'; ?>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">آخر فاتورة</span>
                                <div class="info-value">
                                    <i class="fas fa-calendar-check"></i>
                                    <?php 
                                    if ($last_purchase) {
                                        echo $last_purchase['purchase_date'] . ' - ' . formatNumber($last_purchase['grand_total']) . ' ر.س';
                                        if ($last_purchase['remaining_amount'] > 0) {
                                            echo ' (متبقي: ' . formatNumber($last_purchase['remaining_amount']) . ' ر.س)';
                                        } else {
                                            echo ' (مدفوعة كلياً)';
                                        }
                                    } else {
                                        echo 'لا يوجد';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ==================== آخر فواتير المشتريات ==================== -->
                    <div class="purchases-section">
                        <div class="section-header">
                            <h3>
                                <i class="fas fa-history" style="color: #4CAF50;"></i>
                                كل فواتير المشتريات
                            </h3>
                            
                        </div>
                        
                        <?php if ($has_purchases): ?>
                        <div style="overflow-x: auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>رقم الفاتورة</th>
                                        <th>التاريخ</th>
                                        <th>الإجمالي</th>
                                        <th>المدفوع</th>
                                        <th>المتبقي</th>
                                        <th>طريقة الدفع</th>
                                        <th>الحالة</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($purchase = $purchases_result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="purchase-number"><?php echo $purchase['purchase_number']; ?></td>
                                        <td><?php echo $purchase['purchase_date']; ?></td>
                                        <td class="amount"><?php echo formatNumber($purchase['grand_total']); ?> ر.س</td>
                                        <td class="paid-amount"><?php echo formatNumber($purchase['paid_amount']); ?> ر.س</td>
                                        <td class="remaining-amount"><?php echo formatNumber($purchase['remaining_amount']); ?> ر.س</td>
                                        <td><?php echo $purchase['payment_method']; ?></td>
                                        <td>
                                            <span class="status-badge status-<?php 
                                                echo $purchase['payment_status'] == 'مدفوعة' ? 'paid' : 
                                                     ($purchase['payment_status'] == 'غير مدفوعة' ? 'unpaid' : 'partial'); 
                                            ?>">
                                                <?php echo $purchase['payment_status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="view_purchase.php?id=<?php echo $purchase['purchase_id']; ?>" class="action-btn" title="عرض">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-truck"></i>
                            <p>لا توجد فواتير مشتريات لهذا المورد حتى الآن</p>
                            <a href="add_item_tostore.php?supplier_id=<?php echo $supplier_id; ?>" class="btn btn-success" style="margin-top: 15px; display: inline-flex;">
                                <i class="fas fa-plus"></i>
                                إنشاء فاتورة مشتريات جديدة
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- ==================== سندات التسليم ==================== -->
                    <div class="payments-section">
                        <div class="section-header">
                            <h3>
                                <i class="fas fa-hand-holding-usd" style="color: #9C27B0;"></i>
                                سندات التسليم (<?php echo $total_payments_count; ?>)
                            </h3>
                            
                        </div>
                        
                        <?php if ($has_payments): ?>
                        <div style="overflow-x: auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>رقم السند</th>
                                        <th>التاريخ</th>
                                        <th>المبلغ</th>
                                        <th>طريقة الدفع</th>
                                        <th>المرجع</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $payments_result->data_seek(0);
                                    while ($payment = $payments_result->fetch_assoc()): 
                                    ?>
                                    <tr>
                                        <td style="font-weight: bold; color: #9C27B0;"><?php echo $payment['payment_number']; ?></td>
                                        <td><?php echo $payment['payment_date']; ?></td>
                                        <td class="amount"><?php echo formatNumber($payment['payment_amount']); ?> ر.س</td>
                                        <td><?php echo $payment['payment_method']; ?></td>
                                        <td><?php echo $payment['reference_number'] ?: '---'; ?></td>
                                        <td>
                                            <button class="action-btn" onclick="viewPayment(<?php echo $payment['payment_id']; ?>)" title="عرض" style="color: #9C27B0;">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="action-btn" onclick="printPayment(<?php echo $payment['payment_id']; ?>)" title="طباعة" style="color: #9C27B0;">
                                                <i class="fas fa-print"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-hand-holding-usd"></i>
                            <p>لا توجد سندات تسليم لهذا المورد</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- ==================== إحصائيات المشتريات ==================== -->
                    <?php if ($has_purchases): ?>
                    <div class="chart-section">
                        <h3 style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-chart-bar" style="color: #4CAF50;"></i>
                            إحصائيات المشتريات
                        </h3>
                        
                        <div class="stats-mini-grid">
                            <div class="stat-mini-card">
                                <div class="stat-mini-label">مشتريات هذا العام</div>
                                <div class="stat-mini-value"><?php echo formatNumber($yearly_purchases); ?> ر.س</div>
                            </div>
                            <div class="stat-mini-card">
                                <div class="stat-mini-label">مشتريات هذا الشهر</div>
                                <div class="stat-mini-value"><?php echo formatNumber($monthly_purchases); ?> ر.س</div>
                            </div>
                        </div>
                        
                        <div class="chart-container">
                            <canvas id="supplierChart"></canvas>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- ==================== نافذة إضافة سند تسليم ==================== -->
    <div class="modal-overlay" id="paymentModal">
        <div class="modal-container">
            <div class="modal-header">
                <h3>
                    <i class="fas fa-hand-holding-usd"></i>
                    إضافة سند تسليم
                </h3>
                <button class="modal-close" onclick="closePaymentModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <form method="post" action="add_supplier_payment.php" id="paymentForm">
                    <input type="hidden" name="supplier_id" value="<?php echo $supplier_id; ?>">
                    
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> المورد</label>
                        <input type="text" value="<?php echo $supplier['supplier_name']; ?>" readonly class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-money-bill-wave"></i> المبلغ <span style="color: red;">*</span></label>
                        <input type="number" name="payment_amount" step="0.01" min="1" required class="form-control" placeholder="أدخل المبلغ">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-calendar"></i> تاريخ الدفع <span style="color: red;">*</span></label>
                        <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-credit-card"></i> طريقة الدفع</label>
                        <select name="payment_method" class="form-control">
                            <option value="نقدي">نقدي</option>
                            <option value="بطاقة">بطاقة</option>
                            <option value="تحويل">تحويل بنكي</option>
                            <option value="شيك">شيك</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-hashtag"></i> رقم المرجع</label>
                        <input type="text" name="reference_number" class="form-control" placeholder="رقم الإذن أو رقم الحوالة">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-sticky-note"></i> ملاحظات</label>
                        <textarea name="notes" rows="3" class="form-control" placeholder="ملاحظات إضافية..."></textarea>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closePaymentModal()">
                    <i class="fas fa-times"></i> إلغاء
                </button>
                <button type="submit" form="paymentForm" name="add_payment" class="btn btn-payment">
                    <i class="fas fa-save"></i> حفظ السند
                </button>
            </div>
        </div>
    </div>
    
    <script>
        function goback(){
            window.history.back();
        }
        // ==================== بيانات الرسم البياني ====================
        <?php if ($has_purchases): ?>
        // بيانات آخر 6 أشهر
        const months = [];
        const purchasesData = [];
        
        <?php
        for ($i = 5; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $month_name = date('F', strtotime("-$i months"));
            $month_names = [
                'January' => 'يناير', 'February' => 'فبراير', 'March' => 'مارس',
                'April' => 'أبريل', 'May' => 'مايو', 'June' => 'يونيو',
                'July' => 'يوليو', 'August' => 'أغسطس', 'September' => 'سبتمبر',
                'October' => 'أكتوبر', 'November' => 'نوفمبر', 'December' => 'ديسمبر'
            ];
            
            $start = $month . '-01';
            $end = date('Y-m-t', strtotime($start));
            
            $month_purchases = $conn->query("SELECT COALESCE(SUM(grand_total), 0) as total FROM purchases 
                                            WHERE supplier_id = $supplier_id 
                                            AND purchase_date BETWEEN '$start' AND '$end'")->fetch_assoc()['total'];
            
            echo "months.push('" . $month_names[date('F', strtotime($month))] . "');\n";
            echo "purchasesData.push(" . floatval($month_purchases) . ");\n";
        }
        ?>
        
        // تهيئة الرسم البياني
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('supplierChart').getContext('2d');
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [{
                        label: 'المشتريات (ر.س)',
                        data: purchasesData,
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
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString() + ' ر.س';
                                }
                            }
                        }
                    }
                }
            });
        });
        <?php endif; ?>
        
        // ==================== دوال النافذة المنبثقة ====================
        function openPaymentModal() {
            document.getElementById('paymentModal').classList.add('active');
        }
        
        function closePaymentModal() {
            document.getElementById('paymentModal').classList.remove('active');
        }
        
        function viewPayment(paymentId) {
            window.location.href = 'view_supplier_payment.php?id=' + paymentId;
        }
        
        function printPayment(paymentId) {
            window.open('print_supplier_payment.php?id=' + paymentId, '_blank');
        }
        
        // إغلاق النافذة عند النقر خارجها
        window.onclick = function(event) {
            const modal = document.getElementById('paymentModal');
            if (event.target == modal) {
                modal.classList.remove('active');
            }
        }
        
        // ==================== قائمة جانبية قابلة للطي ====================
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('collapsed');
            document.querySelector('.main-content').classList.toggle('expanded');
        });
        
        // ==================== اختصارات لوحة المفاتيح ====================
        document.addEventListener('keydown', function(e) {
            // ESC للعودة للقائمة
            if (e.key === 'Escape') {
                if (document.getElementById('paymentModal').classList.contains('active')) {
                    closePaymentModal();
                } else {
                    window.location.href = 'suppliers.php';
                }
            }
            
            // Ctrl + P للطباعة
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
            
            // E للتعديل
            if (e.key === 'e' || e.key === 'E') {
                if (!e.ctrlKey && !e.altKey && !document.getElementById('paymentModal').classList.contains('active')) {
                    window.location.href = 'edit_supplier.php?id=<?php echo $supplier_id; ?>';
                }
            }
            
            // P لإضافة سند تسليم
            if (e.key === 'p' || e.key === 'P') {
                if (!e.ctrlKey && !e.altKey) {
                    e.preventDefault();
                    openPaymentModal();
                }
            }
        });
    </script>
</body>
</html>