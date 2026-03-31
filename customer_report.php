<?php
session_start();
include "config.php";

// التحقق من وجود معرف العميل
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: customers.php");
    exit();
}

$customer_id = intval($_GET['id']);
$report_date = date('Y-m-d H:i:s');

// ==================== استعلام بيانات العميل الأساسية ====================
$sql = "SELECT 
            c.customer_id,
            c.customer_name,
            c.customer_phone,
            c.customer_email,
            c.customer_address,
            c.customer_type,
            c.tax_number,
            c.created_at
        FROM customers c
        WHERE c.customer_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: customers.php");
    exit();
}

$customer = $result->fetch_assoc();

// ==================== إحصائيات الفواتير ====================
$invoices_stats = $conn->query("
    SELECT 
        COUNT(*) as total_invoices,
        COALESCE(SUM(grand_total), 0) as total_invoice_amount,
        COALESCE(SUM(paid_amount), 0) as total_paid_in_invoices,
        COALESCE(SUM(remaining_amount), 0) as total_remaining
    FROM invoices 
    WHERE customer_id = $customer_id
")->fetch_assoc();

// ==================== إحصائيات المرتجعات ====================
$returns_stats = $conn->query("
    SELECT 
        COUNT(*) as total_returns,
        COALESCE(SUM(total_amount), 0) as total_return_amount
    FROM sales_returns 
    WHERE customer_id = $customer_id
")->fetch_assoc();

// ==================== إحصائيات سندات القبض ====================
$payments_stats = $conn->query("
    SELECT 
        COUNT(*) as total_payments,
        COALESCE(SUM(payment_amount), 0) as total_payments_amount
    FROM customer_payments 
    WHERE customer_id = $customer_id
")->fetch_assoc();

// ==================== حساب صافي المتبقي ====================
$net_remaining = $invoices_stats['total_remaining'] - $returns_stats['total_return_amount'] - $payments_stats['total_payments_amount'];
if ($net_remaining < 0) $net_remaining = 0;

// ==================== جميع الفواتير ====================
$invoices_sql = "SELECT 
                    invoice_id,
                    invoice_number,
                    invoice_date,
                    grand_total,
                    paid_amount,
                    remaining_amount,
                    payment_status,
                    payment_method,
                    created_at
                FROM invoices 
                WHERE customer_id = ?
                ORDER BY invoice_date DESC";

$invoices_stmt = $conn->prepare($invoices_sql);
$invoices_stmt->bind_param("i", $customer_id);
$invoices_stmt->execute();
$invoices_result = $invoices_stmt->get_result();

// ==================== جميع المرتجعات ====================
$returns_sql = "SELECT 
                    return_id,
                    return_number,
                    return_date,
                    total_amount,
                    reason
                FROM sales_returns 
                WHERE customer_id = ?
                ORDER BY return_date DESC";

$returns_stmt = $conn->prepare($returns_sql);
$returns_stmt->bind_param("i", $customer_id);
$returns_stmt->execute();
$returns_result = $returns_stmt->get_result();

// ==================== جميع سندات القبض ====================
$payments_sql = "SELECT 
                    payment_id,
                    payment_number,
                    payment_date,
                    payment_amount,
                    payment_method,
                    reference_number,
                    notes,
                    created_at
                FROM customer_payments 
                WHERE customer_id = ?
                ORDER BY payment_date DESC";

$payments_stmt = $conn->prepare($payments_sql);
$payments_stmt->bind_param("i", $customer_id);
$payments_stmt->execute();
$payments_result = $payments_stmt->get_result();

// ==================== ملخص الحساب ====================
$summary = [
    'total_invoices' => $invoices_stats['total_invoice_amount'],
    'total_returns' => $returns_stats['total_return_amount'],
    'total_payments' => $payments_stats['total_payments_amount'],
    'net_balance' => $invoices_stats['total_invoice_amount'] - $returns_stats['total_return_amount'] - $payments_stats['total_payments_amount']
];

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
    <!-- Font Awesome محلي -->
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">
    
    <!-- Chart.js محلي -->
    <script src="assets/chart.js/chart.min.js"></script>
    <title class='titel'>
        
        تقرير العميل - <?php echo $customer['customer_name']; ?>
        
    </title>
    <style>
        titel.{
            font-size: 32px;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Tahoma', Arial, sans-serif;
        }
        
        body {
            background: #f0f2f5;
            padding: 30px;
        }
        
        .report-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        /* ==================== ترويسة التقرير ==================== */
        .report-header {
            background: linear-gradient(135deg, #2196F3, #1976D2);
            color: white;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .report-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transform: rotate(45deg);
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            position: relative;
            z-index: 1;
        }
        
        .company-info h1 {
            font-size: 32px;
            margin-bottom: 5px;
        }
        
        .company-info p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .report-title {
            background: rgba(255,255,255,0.2);
            padding: 15px 30px;
            border-radius: 50px;
            font-size: 18px;
            font-weight: bold;
            backdrop-filter: blur(10px);
        }
        
        .customer-badge {
            background: rgba(255,255,255,0.15);
            border-radius: 20px;
            padding: 25px;
            display: flex;
            gap: 30px;
            align-items: center;
            position: relative;
            z-index: 1;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255,255,255,0.3);
        }
        .customer-badgee{
            background: rgba(255,255,255,0.15);
            border-radius: 20px;
            padding: 25px;
            display: flex;
            gap: 30px;
            align-items: center;
            position: relative;
            
            
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .customer-icon {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: #2196F3;
        }
        
        .customer-details h2 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .customer-meta {
            display: flex;
            gap: 20px;
            font-size: 14px;
            opacity: 0.9;
        }
        
        /* ==================== بطاقات الملخص ==================== */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            padding: 30px;
            background: #f8f9fa;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .card-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .card-content h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .card-content .value {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }
        
        .card-content .small {
            font-size: 12px;
            color: #999;
        }
        
        .total-card {
            background: linear-gradient(135deg, #2196F3, #1976D2);
            color: white;
        }
        
        .total-card .card-content h3,
        .total-card .card-content .value,
        .total-card .card-content .small {
            color: white;
        }
        
        .total-card .card-icon {
            background: rgba(255,255,255,0.2) !important;
            color: white !important;
        }
        
        /* ==================== صافي الرصيد ==================== */
        .balance-box {
            background: <?php echo $net_remaining > 0 ? 'linear-gradient(135deg, #f44336, #d32f2f)' : 'linear-gradient(135deg, #4CAF50, #388E3C)'; ?>;
            color: white;
            margin: 0 30px 30px;
            border-radius: 15px;
            padding: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 5px 20px <?php echo $net_remaining > 0 ? 'rgba(244,67,54,0.3)' : 'rgba(76,175,80,0.3)'; ?>;
        }
        
        .balance-box h2 {
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .balance-box .amount {
            font-size: 36px;
            font-weight: bold;
            background: rgba(255,255,255,0.2);
            padding: 10px 30px;
            border-radius: 50px;
        }
        
        /* ==================== معلومات الاتصال ==================== */
        .contact-info {
            padding: 0 30px 30px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }
        
        .contact-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
        }
        
        .contact-item .label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .contact-item .value {
            font-size: 14px;
            font-weight: bold;
            color: #333;
        }
        
        /* ==================== الجداول ==================== */
        .tables-section {
            padding: 0 30px 30px;
        }
        
        .table-container {
            margin-bottom: 30px;
        }
        
        .table-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid;
        }
        
        .invoices-title {
            color: #2196F3;
            border-bottom-color: #2196F3;
        }
        
        .returns-title {
            color: #FF9800;
            border-bottom-color: #FF9800;
        }
        
        .payments-title {
            color: #9C27B0;
            border-bottom-color: #9C27B0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
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
            padding: 12px 10px;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }
        
        tbody tr:hover {
            background: #f5f5f5;
        }
        
        .invoice-number {
            color: #2196F3;
            font-weight: bold;
        }
        
        .return-number {
            color: #FF9800;
            font-weight: bold;
        }
        
        .payment-number {
            color: #9C27B0;
            font-weight: bold;
        }
        
        .amount {
            color: #4CAF50;
            font-weight: bold;
        }
        
        .remaining {
            color: #f44336;
            font-weight: bold;
        }
        
        .status-badge {
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            display: inline-block;
        }
        
        .status-paid {
            background: #d4edda;
            color: #155724;
        }
        
        .status-unpaid {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-partial {
            background: #fff3cd;
            color: #856404;
        }
        
        /* ==================== ملخص الحساب النهائي ==================== */
        .final-summary {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-top: 30px;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 15px;
            background: white;
            border-radius: 10px;
        }
        
        .summary-item.total {
            background: <?php echo $net_remaining > 0 ? '#ffebee' : '#e8f5e9'; ?>;
            border: 2px solid <?php echo $net_remaining > 0 ? '#f44336' : '#4CAF50'; ?>;
        }
        
        .summary-label {
            font-weight: bold;
            color: #666;
        }
        
        .summary-value {
            font-weight: bold;
            color: #333;
        }
        
        .summary-value.total {
            color: <?php echo $net_remaining > 0 ? '#f44336' : '#4CAF50'; ?>;
            font-size: 18px;
        }
        
        /* ==================== التوقيعات ==================== */
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
            padding: 30px;
            border-top: 2px dashed #ccc;
        }
        
        .signature-box {
            text-align: center;
            width: 200px;
        }
        
        .signature-line {
            border-top: 2px dashed #333;
            padding-top: 10px;
            margin-top: 10px;
            font-size: 14px;
            color: #666;
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            color: #666;
            font-size: 12px;
            border-top: 1px solid #ddd;
        }
        
        .print-button {
            text-align: center;
            margin: 20px 0;
        }
        
        .print-button button {
            padding: 15px 40px;
            background: #2196F3;
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .print-button button:hover {
            background: #1976D2;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(33,150,243,0.3);
        }
        .head_link {
            display: flex;
            align-items: center;
            width: 100%;
        }
        .back-link {
            width: 10%;
            align-items: center;
            gap: 10px;
            background: white;
            padding: 10px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-decoration: none;
            color: #333;
            margin-right: 118px;
            margin-bottom: 13px;
        }
        
        .back-link:hover {
            background: #f5f5f5;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .report-container {
                box-shadow: none;
            }
            
            .print-button {
                display: none;
            }
            
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- ==================== شريط العنوان وزر الرجوع ==================== -->
    <div class="head_link">
        <a href="view_customer.php?id=<?php echo $customer['customer_id']; ?>" class="back-link no-print" title="عودة">
            <i class="fas fa-arrow-right"></i>
            <span>العودة</span>
        </a>
    </div>
    <div class="report-container">
        <!-- ==================== ترويسة التقرير ==================== -->
        <div class="report-header">
            <div class="header-top">
                <div class="company-info">
                    <h1>نظام إدارة المخازن</h1>
                    <p>تقرير حساب عميل شامل</p>
                </div>
                <div class="report-title">
                    <i class="fas fa-file-pdf"></i> تقرير حساب
                </div>
            </div>
            
            <div class="customer-badgee">
                <div class="customer-icon">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="customer-details">
                    <h2><?php echo $customer['customer_name']; ?></h2>
                    <div class="customer-meta">
                        <span>نوع العميل: <?php echo $customer['customer_type'] ?: 'فرد'; ?></span>
                        <span>رقم ضريبي: <?php echo $customer['tax_number'] ?: '---'; ?></span>
                        <span>تاريخ التسجيل: <?php echo date('Y-m-d', strtotime($customer['created_at'])); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ==================== بطاقات الملخص ==================== -->
        <div class="summary-cards">
            <div class="card">
                <div class="card-icon" style="background: #e3f2fd; color: #2196F3;">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div class="card-content">
                    <h3>إجمالي الفواتير</h3>
                    <div class="value"><?php echo $invoices_stats['total_invoices']; ?></div>
                    <div class="small">قيمة: <?php echo formatNumber($invoices_stats['total_invoice_amount']); ?> ر.ي</div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-icon" style="background: #fff3e0; color: #FF9800;">
                    <i class="fas fa-undo-alt"></i>
                </div>
                <div class="card-content">
                    <h3>إجمالي المرتجعات</h3>
                    <div class="value"><?php echo $returns_stats['total_returns']; ?></div>
                    <div class="small">قيمة: <?php echo formatNumber($returns_stats['total_return_amount']); ?> ر.ي</div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-icon" style="background: #f3e5f5; color: #9C27B0;">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
                <div class="card-content">
                    <h3>سندات القبض</h3>
                    <div class="value"><?php echo $payments_stats['total_payments']; ?></div>
                    <div class="small">قيمة: <?php echo formatNumber($payments_stats['total_payments_amount']); ?> ر.ي</div>
                </div>
            </div>
            
            <div class="card total-card">
                <div class="card-icon" style="background: rgba(255,255,255,0.2);">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="card-content">
                    <h3>صافي الرصيد</h3>
                    <div class="value"><?php echo formatNumber($summary['net_balance']); ?> ر.ي</div>
                    <div class="small"><?php echo $summary['net_balance'] > 0 ? 'عليه' : 'له'; ?></div>
                </div>
            </div>
        </div>
        
        <!-- ==================== صافي المتبقي البارز ==================== -->
        <div class="balance-box">
            <h2>
                <i class="fas <?php echo $net_remaining > 0 ? 'fa-exclamation-triangle' : 'fa-check-circle'; ?>"></i>
                <?php echo $net_remaining > 0 ? 'المبلغ المتبقي على العميل' : 'لا يوجد مبالغ متبقية'; ?>
            </h2>
            <div class="amount"><?php echo formatNumber($net_remaining); ?> ر.ي</div>
        </div>
        
        <!-- ==================== معلومات الاتصال ==================== -->
        <div class="contact-info">
            <div class="contact-item">
                <div class="label">رقم الهاتف</div>
                <div class="value"><i class="fas fa-phone"></i> <?php echo $customer['customer_phone'] ?: 'غير مسجل'; ?></div>
            </div>
            <div class="contact-item">
                <div class="label">البريد الإلكتروني</div>
                <div class="value"><i class="fas fa-envelope"></i> <?php echo $customer['customer_email'] ?: 'غير مسجل'; ?></div>
            </div>
            <div class="contact-item">
                <div class="label">العنوان</div>
                <div class="value"><i class="fas fa-map-marker-alt"></i> <?php echo $customer['customer_address'] ?: 'غير مسجل'; ?></div>
            </div>
            <div class="contact-item">
                <div class="label">الرقم الضريبي</div>
                <div class="value"><i class="fas fa-hashtag"></i> <?php echo $customer['tax_number'] ?: 'غير مسجل'; ?></div>
            </div>
        </div>
        
        <!-- ==================== جداول البيانات ==================== -->
        <div class="tables-section">
            <!-- جدول الفواتير -->
            <div class="table-container">
                <h3 class="table-title invoices-title">
                    <i class="fas fa-file-invoice"></i>
                    جميع الفواتير (<?php echo $invoices_result->num_rows; ?>)
                </h3>
                
                <?php if ($invoices_result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>رقم الفاتورة</th>
                            <th>التاريخ</th>
                            <th>الإجمالي</th>
                            <th>المدفوع</th>
                            <th>المتبقي</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($invoice = $invoices_result->fetch_assoc()): ?>
                        <tr>
                            <td class="invoice-number"><?php echo $invoice['invoice_number']; ?></td>
                            <td><?php echo $invoice['invoice_date']; ?></td>
                            <td class="amount"><?php echo formatNumber($invoice['grand_total']); ?> ر.ي</td>
                            <td><?php echo formatNumber($invoice['paid_amount']); ?> ر.ي</td>
                            <td class="remaining"><?php echo formatNumber($invoice['remaining_amount']); ?> ر.ي</td>
                            <td>
                                <span class="status-badge status-<?php 
                                    echo $invoice['payment_status'] == 'مدفوعة' ? 'paid' : 
                                         ($invoice['payment_status'] == 'غير مدفوعة' ? 'unpaid' : 'partial'); 
                                ?>">
                                    <?php echo $invoice['payment_status']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="text-align: center; padding: 30px; color: #999;">لا توجد فواتير</p>
                <?php endif; ?>
            </div>
            
            <!-- جدول المرتجعات -->
            <div class="table-container">
                <h3 class="table-title returns-title">
                    <i class="fas fa-undo-alt"></i>
                    جميع المرتجعات (<?php echo $returns_result->num_rows; ?>)
                </h3>
                
                <?php if ($returns_result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>رقم المرتجع</th>
                            <th>التاريخ</th>
                            <th>المبلغ</th>
                            <th>السبب</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($return = $returns_result->fetch_assoc()): ?>
                        <tr>
                            <td class="return-number"><?php echo $return['return_number']; ?></td>
                            <td><?php echo $return['return_date']; ?></td>
                            <td class="amount"><?php echo formatNumber($return['total_amount']); ?> ر.ي</td>
                            <td><?php echo $return['reason'] ?: '---'; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="text-align: center; padding: 30px; color: #999;">لا توجد مرتجعات</p>
                <?php endif; ?>
            </div>
            
            <!-- جدول سندات القبض -->
            <div class="table-container">
                <h3 class="table-title payments-title">
                    <i class="fas fa-hand-holding-usd"></i>
                    سندات القبض (<?php echo $payments_result->num_rows; ?>)
                </h3>
                
                <?php if ($payments_result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>رقم السند</th>
                            <th>التاريخ</th>
                            <th>المبلغ</th>
                            <th>طريقة الدفع</th>
                            <th>المرجع</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($payment = $payments_result->fetch_assoc()): ?>
                        <tr>
                            <td class="payment-number"><?php echo $payment['payment_number']; ?></td>
                            <td><?php echo $payment['payment_date']; ?></td>
                            <td class="amount"><?php echo formatNumber($payment['payment_amount']); ?> ر.ي</td>
                            <td><?php echo $payment['payment_method']; ?></td>
                            <td><?php echo $payment['reference_number'] ?: '---'; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="text-align: center; padding: 30px; color: #999;">لا توجد سندات قبض</p>
                <?php endif; ?>
            </div>
            
            <!-- ==================== ملخص الحساب النهائي ==================== -->
            <div class="final-summary">
                <h3 style="margin-bottom: 20px; color: #333;">ملخص الحساب النهائي</h3>
                <div class="summary-grid">
                    <div class="summary-item">
                        <span class="summary-label">إجمالي الفواتير:</span>
                        <span class="summary-value"><?php echo formatNumber($summary['total_invoices']); ?> ر.ي</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">إجمالي المرتجعات:</span>
                        <span class="summary-value">- <?php echo formatNumber($summary['total_returns']); ?> ر.ي</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">إجمالي المدفوعات (سندات قبض):</span>
                        <span class="summary-value">- <?php echo formatNumber($summary['total_payments']); ?> ر.ي</span>
                    </div>
                    <div class="summary-item total">
                        <span class="summary-label">صافي الرصيد:</span>
                        <span class="summary-value total"><?php echo formatNumber($summary['net_balance']); ?> ر.ي</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ==================== التوقيعات ==================== -->
        <div class="signatures">
            <div class="signature-box">
                <div>توقيع العميل</div>
                <div class="signature-line"></div>
            </div>
            <div class="signature-box">
                <div>المسؤول المالي</div>
                <div class="signature-line"></div>
            </div>
            <div class="signature-box">
                <div>ختم الشركة</div>
                <div class="signature-line"></div>
            </div>
        </div>
        
        <!-- ==================== تذييل التقرير ==================== -->
        <div class="footer">
            <p>تم إنشاء هذا التقرير في: <?php echo $report_date; ?></p>
            <p>جميع المبالغ بالريال اليمني</p>
        </div>
    </div>
    
    <!-- ==================== زر الطباعة ==================== -->
    <div class="print-button no-print">
        <button onclick="window.print()">
            <i class="fas fa-print"></i> طباعة التقرير
        </button>
    </div>
    
    <script>
        // طباعة تلقائية (اختياري)
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>