<?php
session_start();
include "config.php";

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: logout.php");
    exit();
}


// التحقق من وجود معرف العميل
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: customers.php");
    exit();
}

$customer_id = intval($_GET['id']);

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
    $_SESSION['alert'] = [
        'type' => 'error',
        'message' => "❌ العميل غير موجود"
    ];
    header("Location: customers.php");
    exit();
}

$customer = $result->fetch_assoc();

// ==================== إحصائيات الفواتير والمرتجعات والمدفوعات ====================

// 1. إجمالي الفواتير والمبالغ
$invoices_stats = $conn->query("
    SELECT 
        COUNT(*) as total_invoices,
        COALESCE(SUM(grand_total), 0) as total_invoice_amount,
        COALESCE(SUM(paid_amount), 0) as total_paid_in_invoices,
        COALESCE(SUM(remaining_amount), 0) as total_remaining
    FROM invoices 
    WHERE customer_id = $customer_id
")->fetch_assoc();

// 2. إحصائيات المرتجعات
$returns_stats = $conn->query("
    SELECT 
        COUNT(*) as total_returns,
        COALESCE(SUM(total_amount), 0) as total_return_amount
    FROM sales_returns 
    WHERE customer_id = $customer_id
")->fetch_assoc();

// 3. إجمالي سندات القبض (المدفوعات النقدية)
$payments_stats = $conn->query("
    SELECT 
        COUNT(*) as total_payments,
        COALESCE(SUM(payment_amount), 0) as total_payments_amount
    FROM customer_payments 
    WHERE customer_id = $customer_id
")->fetch_assoc();


// 4. إجمالي سندات التسليم (إعطاء فلوس للعميل)
$credit_notes_stats = $conn->query("
    SELECT 
        COUNT(*) as total_credits,
        COALESCE(SUM(amount), 0) as total_credits_amount
    FROM customer_credit_notes 
    WHERE customer_id = $customer_id
")->fetch_assoc();

// 4. حساب صافي المتبقي (بعد خصم المرتجعات وسندات القبض)
// الصيغة: إجمالي المتبقي من الفواتير - إجمالي المرتجعات - إجمالي سندات القبض
$net_remaining = $invoices_stats['total_remaining'] - $returns_stats['total_return_amount'] - $payments_stats['total_payments_amount'];
if ($net_remaining < 0) $net_remaining = 0;

// 5. الفواتير المدفوعة بالكامل
$paid_invoices = $conn->query("
    SELECT COUNT(*) as count 
    FROM invoices 
    WHERE customer_id = $customer_id AND payment_status = 'مدفوعة'
")->fetch_assoc()['count'];

// 6. الفواتير غير المدفوعة (كلياً أو جزئياً)
$unpaid_invoices = $conn->query("
    SELECT COUNT(*) as count 
    FROM invoices 
    WHERE customer_id = $customer_id AND payment_status != 'مدفوعة'
")->fetch_assoc()['count'];

// 7. آخر فاتورة وتاريخها
$last_invoice = $conn->query("
    SELECT invoice_date, grand_total, remaining_amount 
    FROM invoices 
    WHERE customer_id = $customer_id 
    ORDER BY invoice_date DESC 
    LIMIT 1
")->fetch_assoc();

// 8. آخر مرتجع وتاريخه
$last_return = $conn->query("
    SELECT return_date, total_amount 
    FROM sales_returns 
    WHERE customer_id = $customer_id 
    ORDER BY return_date DESC 
    LIMIT 1
")->fetch_assoc();

// 9. آخر سند قبض وتاريخه
$last_payment = $conn->query("
    SELECT payment_date, payment_amount 
    FROM customer_payments 
    WHERE customer_id = $customer_id 
    ORDER BY payment_date DESC 
    LIMIT 1
")->fetch_assoc();

// ==================== إحصائيات إضافية ====================
$year_start = date('Y-01-01');
$year_end = date('Y-12-31');

// مشتريات هذا العام
$yearly_sales = $conn->query("
    SELECT COALESCE(SUM(grand_total), 0) as total 
    FROM invoices 
    WHERE customer_id = $customer_id 
    AND invoice_date BETWEEN '$year_start' AND '$year_end'
")->fetch_assoc()['total'];

// مشتريات هذا الشهر
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
$monthly_sales = $conn->query("
    SELECT COALESCE(SUM(grand_total), 0) as total 
    FROM invoices 
    WHERE customer_id = $customer_id 
    AND invoice_date BETWEEN '$month_start' AND '$month_end'
")->fetch_assoc()['total'];

// الفاتورة الأكبر
$max_invoice = $conn->query("
    SELECT MAX(grand_total) as max 
    FROM invoices 
    WHERE customer_id = $customer_id
")->fetch_assoc()['max'] ?: 0;

// ==================== جميع فواتير العميل (بدون حد) ====================
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
$has_invoices = ($invoices_result && $invoices_result->num_rows > 0);
$total_invoices_count = $has_invoices ? $invoices_result->num_rows : 0;

// ==================== جميع مرتجعات العميل (بدون حد) ====================
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
$has_returns = ($returns_result && $returns_result->num_rows > 0);
$total_returns_count = $has_returns ? $returns_result->num_rows : 0;

// ==================== جميع سندات القبض (بدون حد) ====================
$payments_sql = "SELECT 
                    payment_id,
                    payment_number,
                    payment_date,
                    payment_amount,
                    payment_method,
                    reference_number,
                    notes,
                    created_at,
                    created_by
                FROM customer_payments 
                WHERE customer_id = ?
                ORDER BY payment_date DESC";

$payments_stmt = $conn->prepare($payments_sql);
$payments_stmt->bind_param("i", $customer_id);
$payments_stmt->execute();
$payments_result = $payments_stmt->get_result();
$has_payments = ($payments_result && $payments_result->num_rows > 0);
$total_payments_count = $has_payments ? $payments_result->num_rows : 0;

// ==================== معالجة إضافة سند قبض ====================
$payment_success = "";
$payment_error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_payment'])) {
    
    $payment_amount = floatval($_POST['payment_amount']);
    $payment_date = $conn->real_escape_string($_POST['payment_date']);
    $payment_method = $conn->real_escape_string($_POST['payment_method']);
    $reference_number = $conn->real_escape_string($_POST['reference_number'] ?? '');
    $notes = $conn->real_escape_string($_POST['notes'] ?? '');
    $created_by = $_SESSION['user_id'] ?? 0;

    
    // التحقق من صحة المبلغ
    if ($payment_amount <= 0) {
        $payment_error = "❌ المبلغ يجب أن يكون أكبر من صفر";
    } else {
        // توليد رقم سند قبض فريد
        $year = date('Y');
        $month = date('m');
        $payment_number = "CP-" . $year . $month . "-" . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // إدخال سند القبض
        $insert_sql = "INSERT INTO customer_payments 
                       (payment_number, customer_id, payment_date, payment_amount, payment_method, reference_number, notes, created_by) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("sisdsssi", $payment_number, $customer_id, $payment_date, $payment_amount, $payment_method, $reference_number, $notes, $created_by);
        
        if ($insert_stmt->execute()) {
            $payment_success = "✅ تم إضافة سند قبض رقم <strong>$payment_number</strong> بنجاح";
            
            // إعادة تحميل الصفحة لتحديث البيانات
            echo "<script>setTimeout(() => { window.location.href = 'view_customer.php?id=$customer_id'; }, 2000);</script>";
        } else {
            $payment_error = "❌ حدث خطأ: " . $conn->error;
        }
    }
}


// ==================== معالجة إضافة سند تسليم للعميل ====================
$credit_success = "";
$credit_error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_credit_note'])) {
    
    $credit_amount = floatval($_POST['credit_amount']);
    $credit_date = $conn->real_escape_string($_POST['credit_date']);
    $credit_payment_method = $conn->real_escape_string($_POST['credit_payment_method']);
    $credit_reference_number = $conn->real_escape_string($_POST['credit_reference_number'] ?? '');
    $credit_reason = $conn->real_escape_string($_POST['credit_reason'] ?? '');
    $created_by = $_SESSION['user_id'] ?? 0;
    $customer_id_for_credit = intval($_POST['customer_id']);
    
    // التحقق من صحة المبلغ
    if ($credit_amount <= 0) {
        $credit_error = "❌ المبلغ يجب أن يكون أكبر من صفر";
    } else {
        // توليد رقم سند تسليم فريد
        $year = date('Y');
        $month = date('m');
        $credit_number = "CN-" . $year . $month . "-" . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // إدخال سند التسليم
        $insert_sql = "INSERT INTO customer_credit_notes 
                       (credit_note_number, customer_id, credit_note_date, amount, payment_method, reference_number, reason, notes, created_by) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("sisdssssi", $credit_number, $customer_id_for_credit, $credit_date, $credit_amount, 
                                 $credit_payment_method, $credit_reference_number, $credit_reason, $credit_reason, $created_by);
        
        if ($insert_stmt->execute()) {
            $credit_success = "✅ تم إضافة سند تسليم رقم <strong>$credit_number</strong> بقيمة " . number_format($credit_amount, 2) . " ر.ي بنجاح";
            
            // إعادة تحميل الصفحة لتحديث البيانات
            echo "<script>setTimeout(() => { window.location.href = 'view_customer.php?id=$customer_id_for_credit'; }, 2000);</script>";
        } else {
            $credit_error = "❌ حدث خطأ: " . $conn->error;
        }
    }
}







// تنسيق الأرقام
function formatNumber($number) {
    return number_format($number, 2);
}

// $created_by_name = $invoice['created_by_name'] ? $invoice['created_by_name'] : '---';

// تحديد لون وأيقونة نوع العميل
$type_icon = '';
$type_color = '';
$type_bg = '';

switch($customer['customer_type']) {
    case 'شركة':
        $type_icon = 'fa-building';
        $type_color = '#4CAF50';
        $type_bg = '#e8f5e9';
        break;
    case 'جهة حكومية':
        $type_icon = 'fa-landmark';
        $type_color = '#FF9800';
        $type_bg = '#fff3e0';
        break;
    default:
        $type_icon = 'fa-user';
        $type_color = '#2196F3';
        $type_bg = '#e3f2fd';
}


// ==================== معالجة حذف سند القبض ====================
if (isset($_GET['delete_payment']) && isset($_GET['payment_id']) && isset($_GET['customer_id'])) {
    $payment_id = intval($_GET['payment_id']);
    $customer_id_for_delete = intval($_GET['customer_id']);
    
    // التحقق من وجود السند
    $check = $conn->query("SELECT payment_number FROM customer_payments WHERE payment_id = $payment_id");
    if ($check && $check->num_rows > 0) {
        $payment = $check->fetch_assoc();
        $payment_number = $payment['payment_number'];
        
        // حذف السند
        $delete = $conn->query("DELETE FROM customer_payments WHERE payment_id = $payment_id");
        
        if ($delete) {
            $_SESSION['alert'] = [
                'type' => 'success',
                'message' => "✅ تم حذف سند القبض رقم <strong>$payment_number</strong> بنجاح"
            ];
        } else {
            $_SESSION['alert'] = [
                'type' => 'error',
                'message' => "❌ حدث خطأ أثناء حذف السند: " . $conn->error
            ];
        }
    } else {
        $_SESSION['alert'] = [
            'type' => 'error',
            'message' => "❌ سند القبض غير موجود"
        ];
    }
    
    // العودة إلى صفحة العميل
    if ($customer_id_for_delete > 0) {
        header("Location: view_customer.php?id=" . $customer_id_for_delete);
    } else {
        header("Location: customers.php");
    }
    exit();
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>عرض العميل - <?php echo $customer['customer_name']; ?></title>
    
    <!-- Font Awesome محلي -->
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">
    
    <!-- Chart.js محلي -->
    <script src="assets/chart.js/chart.min.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Tahoma', Arial, sans-serif;
            background: #f0f2f5;
            padding: 20px;
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
        
        /* ==================== أزرار الإجراءات ==================== */
        .action-bar {
            background: white;
            border-radius: 15px;
            padding: 15px 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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
        
        .btn-info {
            background: #00BCD4;
            color: white;
        }
        
        .btn-info:hover {
            background: #00ACC1;
            transform: translateY(-2px);
        }
        
        .btn-payment {
            background: #9C27B0;
            color: white;
        }
        
        .btn-payment:hover {
            background: #7B1FA2;
            transform: translateY(-2px);
        }
        
        /* ==================== بطاقة العميل ==================== */
        .customer-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .customer-header {
            background: linear-gradient(135deg, #2196F3, #1976D2);
            color: white;
            padding: 30px;
            display: flex;
            align-items: center;
            gap: 25px;
            flex-wrap: wrap;
        }
        
        .customer-avatar {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .customer-avatar i {
            font-size: 40px;
            color: #2196F3;
        }
        
        .customer-header-info h2 {
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .customer-header-info p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .customer-type-badge {
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
        
        .customer-body {
            padding: 30px;
        }
        
        /* ==================== بطاقات الإحصائيات ==================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s;
            border: 1px solid #eee;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }
        
        .stat-content h3 {
            font-size: 13px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .stat-value {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin-bottom: 3px;
        }
        
        .stat-desc {
            font-size: 11px;
            color: #999;
        }
        
        /* تنسيق خاص لبطاقة المتبقي */
        .remaining-card {
            background: linear-gradient(135deg, #f44336, #d32f2f);
            color: white;
        }
        
        .remaining-card .stat-icon {
            background: rgba(255,255,255,0.2) !important;
            color: white !important;
        }
        
        .remaining-card .stat-content h3,
        .remaining-card .stat-content .stat-value,
        .remaining-card .stat-content .stat-desc {
            color: white;
        }
        
        /* تنسيق خاص لبطاقة سندات القبض */
        .payment-card {
            background: linear-gradient(135deg, #9C27B0, #7B1FA2);
            color: white;
        }
        
        .payment-card .stat-icon {
            background: rgba(255,255,255,0.2) !important;
            color: white !important;
        }
        
        .payment-card .stat-content h3,
        .payment-card .stat-content .stat-value,
        .payment-card .stat-content .stat-desc {
            color: white;
        }
        
        /* ==================== صافي المتبقي البارز ==================== */
        .net-remaining-box {
            background: linear-gradient(135deg, #f44336, #d32f2f);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 5px 15px rgba(244,67,54,0.3);
        }
        
        .net-remaining-box h2 {
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .net-remaining-box .amount {
            font-size: 36px;
            font-weight: bold;
            background: rgba(255,255,255,0.2);
            padding: 10px 25px;
            border-radius: 50px;
        }
        
        /* ==================== معلومات العميل ==================== */
        .info-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .info-section h3 {
            font-size: 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #333;
            border-bottom: 2px solid #2196F3;
            padding-bottom: 10px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .info-label {
            font-size: 12px;
            color: #666;
            font-weight: bold;
        }
        
        .info-value {
            font-size: 16px;
            color: #333;
            font-weight: 500;
            background: white;
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid #eee;
        }
        
        .info-value i {
            margin-left: 8px;
            color: #2196F3;
        }
        
        /* ==================== جداول البيانات ==================== */
        .tables-grid {
            
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .table-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .table-section h3 {
            font-size: 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid #2196F3;
        }
        
        .table-section .return-header {
            border-bottom-color: #FF9800;
        }
        
        .table-section .return-header i {
            color: #FF9800;
        }
        
        .table-section .payment-header {
            border-bottom-color: #9C27B0;
        }
        
        .table-section .payment-header i {
            color: #9C27B0;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .section-header h3 {
            margin-bottom: 0;
            border-bottom: none;
            padding-bottom: 0;
        }
        
        .view-all {
            color: #2196F3;
            text-decoration: none;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .view-all:hover {
            text-decoration: underline;
        }
        
        .table-wrapper {
            overflow-x: auto;
            max-height: 400px;
            overflow-y: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }
        
        th {
            text-align: right;
            padding: 12px 8px;
            background: #f8f9fa;
            color: #666;
            font-weight: 600;
            font-size: 12px;
            border-bottom: 2px solid #ddd;
            position: sticky;
            top: 0;
            background: #f8f9fa;
            z-index: 10;
        }
        
        td {
            padding: 10px 8px;
            border-bottom: 1px solid #eee;
            font-size: 13px;
            color: #333;
        }
        
        tbody tr:hover {
            background: #f5f5f5;
        }
        
        .invoice-number {
            font-weight: bold;
            color: #2196F3;
        }
        
        .return-number {
            font-weight: bold;
            color: #FF9800;
        }
        
        .payment-number {
            font-weight: bold;
            color: #9C27B0;
        }
        
        .amount {
            font-weight: bold;
            color: #4CAF50;
        }
        
        .remaining-amount {
            font-weight: bold;
            color: #f44336;
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
        
        .status-return {
            background: #fff3e0;
            color: #FF9800;
        }
        
        .status-payment {
            background: #f3e5f5;
            color: #9C27B0;
        }
        
        .action-btn {
            width: 30px;
            height: 30px;
            border: none;
            border-radius: 6px;
            background: transparent;
            color: #666;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        
        .action-btn:hover {
            background: #e3f2fd;
            color: #2196F3;
        }
        
        .action-btn.return:hover {
            background: #fff3e0;
            color: #FF9800;
        }
        
        .action-btn.payment:hover {
            background: #f3e5f5;
            color: #9C27B0;
        }
        
        /* ==================== الرسم البياني ==================== */
        .chart-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .chart-container {
            height: 250px;
            margin-top: 20px;
        }
        
        /* ==================== النافذة المنبثقة ==================== */
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
            border-radius: 20px;
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
            border-radius: 20px 20px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
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
            box-shadow: 0 0 0 3px rgba(156, 39, 176, 0.1);
        }
        
        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .alert-message {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 8px;
            color: white;
            font-weight: bold;
            z-index: 10000;
            animation: slideIn 0.3s ease;
        }
        
        .alert-success {
            background: #4CAF50;
        }
        
        .alert-error {
            background: #f44336;
        }
        
        /* ==================== رسالة عدم وجود بيانات ==================== */
        .no-data {
            text-align: center;
            padding: 25px;
            color: #999;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .no-data i {
            font-size: 40px;
            margin-bottom: 10px;
            opacity: 0.3;
        }
        
        /* ==================== تجاوب مع الشاشات ==================== */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .info-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .tables-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .customer-header {
                flex-direction: column;
                text-align: center;
            }
            
            .customer-type-badge {
                margin-right: 0;
            }
            
            .action-bar {
                flex-direction: column;
            }
            
            .net-remaining-box {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .net-remaining-box .amount {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>

<!-- رسائل التنبيه من SESSION -->
<?php if (isset($_SESSION['alert'])): ?>
    <div class="alert-message alert-<?php echo $_SESSION['alert']['type']; ?>" id="sessionAlert">
        <i class="fas <?php echo $_SESSION['alert']['type'] == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
        <?php echo $_SESSION['alert']['message']; ?>
    </div>
    <script>
        setTimeout(() => {
            const alert = document.getElementById('sessionAlert');
            if (alert) alert.remove();
        }, 3000);
    </script>
    <?php unset($_SESSION['alert']); ?>
<?php endif; ?>


<!-- رسائل التنبيه -->
<?php if ($payment_success): ?>
    <div class="alert-message alert-success" id="successAlert">
        <i class="fas fa-check-circle"></i> <?php echo $payment_success; ?>
    </div>
    <script>
        setTimeout(() => {
            document.getElementById('successAlert')?.remove();
        }, 3000);
    </script>
<?php endif; ?>

<?php if ($payment_error): ?>
    <div class="alert-message alert-error" id="errorAlert">
        <i class="fas fa-exclamation-circle"></i> <?php echo $payment_error; ?>
    </div>
    <script>
        setTimeout(() => {
            document.getElementById('errorAlert')?.remove();
        }, 3000);
    </script>
<?php endif; ?>

<?php if ($credit_success): ?>
    <div class="alert-message alert-success" id="creditSuccessAlert">
        <i class="fas fa-check-circle"></i> <?php echo $credit_success; ?>
    </div>
    <script>
        setTimeout(() => {
            document.getElementById('creditSuccessAlert')?.remove();
        }, 3000);
    </script>
<?php endif; ?>

<?php if ($credit_error): ?>
    <div class="alert-message alert-error" id="creditErrorAlert">
        <i class="fas fa-exclamation-circle"></i> <?php echo $credit_error; ?>
    </div>
    <script>
        setTimeout(() => {
            document.getElementById('creditErrorAlert')?.remove();
        }, 3000);
    </script>
<?php endif; ?>

<div class="container">
    <!-- ==================== شريط التنقل العلوي ==================== -->
    <div class="top-bar">
        <div class="page-title">
            <i class="fas fa-user-circle"></i>
            <h1>ملف العميل</h1>
        </div>
        <div class="date-display">
            <i class="far fa-calendar-alt"></i>
            <?php echo date('Y-m-d'); ?>
        </div>
    </div>
    
    <!-- ==================== أزرار الإجراءات ==================== -->
    <div class="action-bar">
        <div class="action-buttons">
            
            <a href="#" onclick="goback()" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i>
                العودة
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-home"></i>
                الواجهة الرئيسية
            </a>
            <!-- <a href="edit_customer.php?id=<?php echo $customer_id; ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i>
                تعديل البيانات
            </a> -->
            <a href="New_sales_invoice.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-success">
                <i class="fas fa-file-invoice"></i>
                فاتورة جديدة
            </a>
            <button class="btn btn-payment" onclick="openPaymentModal()">
                <i class="fas fa-money-bill-wave"></i>
                سند قبض
            </button>
            <button class="btn btn-warning" onclick="openCreditNoteModal()">
                <i class="fas fa-hand-holding-heart"></i>
                سند تسليم
            </button>
            <!-- زر التقرير الجديد -->
            <!-- <a href="customer_report.php?id=<?php echo $customer_id; ?>"  class="btn btn-info">
                <i class="fas fa-file-pdf"></i>
                تقرير كامل
            </a> -->
            <!-- <button class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print"></i>
                طباعة
            </button> -->
            <!-- في قسم action-buttons أضف هذا الزر -->
            <a href="customer_statement.php?id=<?php echo $customer_id; ?>" class="btn btn-info">
                <i class="fas fa-file-pdf"></i>
                تقرير كشف حساب
            </a>
        </div>
    </div>
    
    <!-- ==================== بطاقة العميل ==================== -->
    <div class="customer-card">
        <div class="customer-header">
            <div class="customer-avatar">
                <i class="fas <?php echo $type_icon; ?>"></i>
            </div>
            <div class="customer-header-info">
                <h2><?php echo $customer['customer_name']; ?></h2>
                <p>عميل منذ <?php echo date('Y-m-d', strtotime($customer['created_at'])); ?></p>
            </div>
            <div class="customer-type-badge" style="background: <?php echo $type_bg; ?>; color: <?php echo $type_color; ?>;">
                <i class="fas <?php echo $type_icon; ?>"></i>
                <?php echo $customer['customer_type'] ?: 'فرد'; ?>
            </div>
        </div>
        
        <div class="customer-body">
            <!-- ==================== صافي المتبقي (بعد خصم المرتجعات وسندات القبض) ==================== -->
            <?php if ($net_remaining > 0): ?>
            <div class="net-remaining-box">
                <h2>
                    <i class="fas fa-exclamation-triangle"></i>
                    صافي المبلغ المتبقي على العميل
                </h2>
                <div class="amount"><?php echo formatNumber($net_remaining); ?> ر.ي</div>
            </div>
            <?php endif; ?>
            
            <!-- ==================== بطاقات الإحصائيات ==================== -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #e3f2fd; color: #2196F3;">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div class="stat-content">
                        <h3>إجمالي الفواتير</h3>
                        <div class="stat-value"><?php echo $invoices_stats['total_invoices']; ?></div>
                        <div class="stat-desc">فاتورة</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: #e8f5e9; color: #4CAF50;">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-content">
                        <h3>إجمالي المشتريات</h3>
                        <div class="stat-value"><?php echo formatNumber($invoices_stats['total_invoice_amount']); ?> ر.ي</div>
                        <div class="stat-desc">قيمة الفواتير</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: #fff3e0; color: #FF9800;">
                        <i class="fas fa-undo-alt"></i>
                    </div>
                    <div class="stat-content">
                        <h3>إجمالي المرتجعات</h3>
                        <div class="stat-value"><?php echo $returns_stats['total_returns']; ?></div>
                        <div class="stat-desc">قيمة: <?php echo formatNumber($returns_stats['total_return_amount']); ?> ر.ي</div>
                    </div>
                </div>
                
                <div class="stat-card payment-card">
                    <div class="stat-icon" style="background: rgba(156,39,176,0.1);">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <div class="stat-content">
                        <h3>سندات القبض</h3>
                        <div class="stat-value"><?php echo $payments_stats['total_payments']; ?></div>
                        <div class="stat-desc">قيمة: <?php echo formatNumber($payments_stats['total_payments_amount']); ?> ر.ي</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: #e8f5e9; color: #4CAF50;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3>المدفوع / المتبقي</h3>
                        <div class="stat-value"><?php echo $paid_invoices; ?> / <?php echo $unpaid_invoices; ?></div>
                        <div class="stat-desc">مدفوع / غير مدفوع</div>
                    </div>
                </div>
                
                <div class="stat-card remaining-card">
                    <div class="stat-icon" style="background: rgba(244,67,54,0.1);">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="stat-content">
                        <h3>المتبقي بعد الكل</h3>
                        <div class="stat-value"><?php echo formatNumber($net_remaining); ?> ر.ي</div>
                        <div class="stat-desc">صافي المتبقي</div>
                    </div>
                </div>
            </div>
            
            <!-- ==================== معلومات العميل ==================== -->
            <div class="info-section">
                <h3><i class="fas fa-info-circle" style="color: #2196F3;"></i> معلومات الاتصال</h3>
                
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">رقم الهاتف</span>
                        <div class="info-value">
                            <i class="fas fa-phone"></i>
                            <?php echo $customer['customer_phone'] ?: 'غير مسجل'; ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">البريد الإلكتروني</span>
                        <div class="info-value">
                            <i class="fas fa-envelope"></i>
                            <?php echo $customer['customer_email'] ?: 'غير مسجل'; ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">العنوان</span>
                        <div class="info-value">
                            <i class="fas fa-map-marker-alt"></i>
                            <?php echo $customer['customer_address'] ?: 'غير مسجل'; ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">الرقم الضريبي</span>
                        <div class="info-value">
                            <i class="fas fa-hashtag"></i>
                            <?php echo $customer['tax_number'] ?: 'غير مسجل'; ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">آخر فاتورة</span>
                        <div class="info-value">
                            <i class="fas fa-calendar"></i>
                            <?php 
                            if ($last_invoice) {
                                echo $last_invoice['invoice_date'] . ' - ' . formatNumber($last_invoice['grand_total']) . ' ر.ي';
                                if ($last_invoice['remaining_amount'] > 0) {
                                    echo ' (متبقي: ' . formatNumber($last_invoice['remaining_amount']) . ' ر.ي)';
                                }
                            } else {
                                echo 'لا يوجد';
                            }
                            ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">آخر مرتجع</span>
                        <div class="info-value">
                            <i class="fas fa-undo-alt"></i>
                            <?php echo $last_return ? $last_return['return_date'] . ' - ' . formatNumber($last_return['total_amount']) . ' ر.ي' : 'لا يوجد'; ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">آخر سند قبض</span>
                        <div class="info-value">
                            <i class="fas fa-hand-holding-usd"></i>
                            <?php echo $last_payment ? $last_payment['payment_date'] . ' - ' . formatNumber($last_payment['payment_amount']) . 'ر.ي' : 'لا يوجد'; ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">إجمالي سندات القبض</span>
                        <div class="info-value">
                            <i class="fas fa-coins"></i>
                            <?php echo formatNumber($payments_stats['total_payments_amount']); ?> ر.ي
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ==================== جداول البيانات ==================== -->
            <div class="tables-grid" style="grid-template-columns: 1fr 1fr 1fr;">
                <!-- جميع الفواتير -->
                <div class="table-section">
                    <h3>
                        <i class="fas fa-file-invoice" style="color: #2196F3;"></i>
                        جميع الفواتير (<?php echo $total_invoices_count; ?>)
                    </h3>
                    
                    <?php if ($has_invoices): ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>رقم الفاتورة</th>
                                    <th>التاريخ</th>
                                    <th>الإجمالي</th>
                                    <th>المتبقي</th>
                                    <th>بواسطة</th>
                                    <th>الاجرائات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $invoices_result->data_seek(0);
                                while ($invoice = $invoices_result->fetch_assoc()): 
                                ?>
                                <tr>
                                    <td class="invoice-number"><?php echo $invoice['invoice_number']; ?></td>
                                    <td><?php echo $invoice['invoice_date']; ?></td>
                                    <td class="amount"><?php echo formatNumber($invoice['grand_total']); ?></td>
                                    <td class="remaining-amount"><?php echo formatNumber($invoice['remaining_amount']); ?></td>
                                    <td>
                                        <i class="fas fa-user-circle" style="color: #2196F3;"></i>
                                        <?php 
                                        // جلب اسم المستخدم الذي أنشأ الفاتورة
                                        $created_by = $conn->query("SELECT full_name FROM users WHERE user_id = (SELECT created_by FROM invoices WHERE invoice_id = {$invoice['invoice_id']})")->fetch_assoc();
                                        echo $created_by ? $created_by['full_name'] : 'غير محدد';
                                        ?></td>
                                    <td>
                                        <div class="action-buttons">
                                                <button class="action-btn" onclick="viewInvoice(<?php echo $invoice['invoice_id']; ?>)" title="عرض">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="action-btn" onclick="printInvoice(<?php echo $invoice['invoice_id']; ?>)" title="طباعة">
                                                    <i class="fas fa-print"></i>
                                                </button>
                                                <button class="action-btn" style="color: #FF9800;" onclick="returnInvoice(<?php echo $invoice['invoice_id']; ?>)" title="مرتجع">
                                                    <i class="fas fa-undo-alt"></i>
                                                </button>
                                                <?php if ($_SESSION['full_name'] == 'مدير النظام'): ?>
                                                    <button class="action-btn" onclick="deleteInvoice(<?php echo $invoice['invoice_id']; ?>, '<?php echo $invoice['invoice_number']; ?>')" title="حذف">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button class="action-btn" style="display: none;" onclick="deleteInvoice(<?php echo $invoice['invoice_id']; ?>, '<?php echo $invoice['invoice_number']; ?>')" title="حذف">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <!--  -->
                                            </div>
                                    </td>
                                    
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-file-invoice"></i>
                        <p>لا توجد فواتير</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- جميع المرتجعات -->
                <div class="table-section">
                    <h3 class="return-header">
                        <i class="fas fa-undo-alt" style="color: #FF9800;"></i>
                        جميع المرتجعات (<?php echo $total_returns_count; ?>)
                    </h3>
                    
                    <?php if ($has_returns): ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>رقم المرتجع</th>
                                    <th>التاريخ</th>
                                    <th>المبلغ</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $returns_result->data_seek(0);
                                while ($return = $returns_result->fetch_assoc()): 
                                ?>
                                <tr>
                                    <td class="return-number"><?php echo $return['return_number']; ?></td>
                                    <td><?php echo $return['return_date']; ?></td>
                                    <td class="amount"><?php echo formatNumber($return['total_amount']); ?> ر.ي</td>
                                    <td>
                                        <a href="view_sales_return.php?id=<?php echo $return['return_id']; ?>" class="action-btn return" title="عرض">
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
                        <i class="fas fa-undo-alt"></i>
                        <p>لا توجد مرتجعات</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- جميع سندات القبض -->
                <div class="table-section">
                    <h3 class="payment-header">
                        <i class="fas fa-hand-holding-usd" style="color: #9C27B0;"></i>
                        سندات القبض (<?php echo $total_payments_count; ?>)
                    </h3>
                    
                    <?php if ($has_payments): ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>رقم السند</th>
                                    <th>التاريخ</th>
                                    <th>المبلغ</th>
                                    <th>طريقة الدفع</th>
                                    <th>بواسطة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $payments_result->data_seek(0);
                                while ($payment = $payments_result->fetch_assoc()): 
                                // جلب اسم المستخدم الذي أنشأ السند
                                    $created_by_name = 'غير محدد';
                                    if (!empty($payment['created_by'])) {
                                        $user_sql = "SELECT full_name FROM users WHERE user_id = " . $payment['created_by'];
                                        $user_result = $conn->query($user_sql);
                                        if ($user_result && $user_result->num_rows > 0) {
                                            $user_data = $user_result->fetch_assoc();
                                            $created_by_name = $user_data['full_name'];
                                        }
                                    }
                                ?>
                                <tr>
                                    <td class="payment-number"><?php echo $payment['payment_number']; ?></td>
                                    <td><?php echo $payment['payment_date']; ?></td>
                                    <td class="amount"><?php echo formatNumber($payment['payment_amount']); ?> ر.ي</td>
                                    <td><?php echo $payment['payment_method']; ?></td>
                                    <td><i class="fas fa-user-circle" style="color: #2196F3;"></i> <?php echo $created_by_name; ?></td>
                                    
                                    <td>
                                        <div style="display: flex; gap: 5px;">
                                            <button class="action-btn payment" onclick="viewPayment(<?php echo $payment['payment_id']; ?>)" title="عرض">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="action-btn payment" onclick="printPayment(<?php echo $payment['payment_id']; ?>)" title="طباعة">
                                                <i class="fas fa-print"></i>
                                            </button>
                                            <button class="action-btn payment" onclick="deletePayment(<?php echo $payment['payment_id']; ?>, '<?php echo $payment['payment_number']; ?>', <?php echo $customer_id; ?>)" title="حذف" style="color: #f44336;">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-hand-holding-usd"></i>
                        <p>لا توجد سندات قبض</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- ==================== إحصائيات المشتريات ==================== -->
            <?php if ($has_invoices): ?>
            <div class="chart-section">
                <h3 style="margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-chart-bar" style="color: #2196F3;"></i>
                    إحصائيات المشتريات (آخر 6 أشهر)
                </h3>
                
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 20px;">
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 10px;">
                        <div style="font-size: 13px; color: #666; margin-bottom: 5px;">مشتريات هذا العام</div>
                        <div style="font-size: 20px; font-weight: bold; color: #2196F3;"><?php echo formatNumber($yearly_sales); ?> ر.ي</div>
                    </div>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 10px;">
                        <div style="font-size: 13px; color: #666; margin-bottom: 5px;">مشتريات هذا الشهر</div>
                        <div style="font-size: 20px; font-weight: bold; color: #4CAF50;"><?php echo formatNumber($monthly_sales); ?> ر.ي</div>
                    </div>
                </div>
                
                <div class="chart-container">
                    <canvas id="customerChart"></canvas>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ==================== نافذة إضافة سند قبض ==================== -->
<div class="modal-overlay" id="paymentModal">
    <div class="modal-container">
        <div class="modal-header">
            <h2>
                <i class="fas fa-hand-holding-usd"></i>
                إضافة سند قبض
            </h2>
            <button class="modal-close" onclick="closePaymentModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-body">
            <form method="post" id="paymentForm">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> العميل</label>
                    <input type="text" class="form-control" value="<?php echo $customer['customer_name']; ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-money-bill-wave"></i> المبلغ <span style="color: red;">*</span></label>
                    <input type="number" name="payment_amount" class="form-control" min="1" step="0.01" required placeholder="أدخل المبلغ">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> تاريخ الدفع <span style="color: red;">*</span></label>
                    <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
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
                    <input type="text" name="reference_number" class="form-control" placeholder="رقم الإذن أو رقم الحوالة (اختياري)">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-sticky-note"></i> ملاحظات</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="ملاحظات إضافية..."></textarea>
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



<!-- ==================== نافذة سند تسليم للعميل ==================== -->
<div class="modal-overlay" id="creditNoteModal">
    <div class="modal-container">
        <div class="modal-header" style="background: linear-gradient(135deg, #FF9800, #F57C00);">
            <h2>
                <i class="fas fa-money-bill-wave"></i>
                سند تسليم للعميل
            </h2>
            <button class="modal-close" onclick="closeCreditNoteModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-body">
            <form method="post" id="creditNoteForm">
                <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                
                <div class="form-group">
                    <label><i class="fas fa-user"></i> العميل</label>
                    <input type="text" class="form-control" value="<?php echo $customer['customer_name']; ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-info-circle"></i> الرصيد الحالي</label>
                    <input type="text" class="form-control" value="<?php echo formatNumber($net_remaining); ?> ر.ي" readonly style="background: #f0f0f0;">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-money-bill-wave"></i> مبلغ التسليم <span style="color: red;">*</span></label>
                    <input type="number" name="credit_amount" id="credit_amount" class="form-control" min="1" step="0.01" required placeholder="أدخل المبلغ" oninput="calculateRemainingForCredit()">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> تاريخ التسليم <span style="color: red;">*</span></label>
                    <input type="date" name="credit_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-credit-card"></i> طريقة الدفع</label>
                    <select name="credit_payment_method" class="form-control">
                        <option value="نقدي">نقدي</option>
                        <option value="بطاقة">بطاقة</option>
                        <option value="تحويل">تحويل بنكي</option>
                        <option value="شيك">شيك</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-hashtag"></i> رقم المرجع</label>
                    <input type="text" name="credit_reference_number" class="form-control" placeholder="رقم الإذن أو رقم الحوالة (اختياري)">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-sticky-note"></i> السبب / الملاحظات</label>
                    <textarea name="credit_reason" class="form-control" rows="3" placeholder="سبب التسليم..."></textarea>
                </div>
                
                <div class="form-group" id="credit_remaining_display" style="background: #e3f2fd; padding: 10px; border-radius: 8px; text-align: center;">
                    <i class="fas fa-info-circle"></i> أدخل المبلغ لحساب الرصيد المتبقي
                </div>
            </form>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeCreditNoteModal()">
                <i class="fas fa-times"></i> إلغاء
            </button>
            <button type="submit" form="creditNoteForm" name="add_credit_note" class="btn" style="background: #FF9800; color: white;">
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
    <?php if ($has_invoices): ?>
    // بيانات آخر 6 أشهر
    const months = [];
    const salesData = [];
    
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
        
        $month_sales = $conn->query("SELECT COALESCE(SUM(grand_total), 0) as total FROM invoices 
                                    WHERE customer_id = $customer_id 
                                    AND invoice_date BETWEEN '$start' AND '$end'")->fetch_assoc()['total'];
        
        echo "months.push('" . $month_names[date('F', strtotime($month))] . "');\n";
        echo "salesData.push(" . floatval($month_sales) . ");\n";
    }
    ?>
    
    // تهيئة الرسم البياني
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('customerChart').getContext('2d');
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'المشتريات (ر.ي)',
                    data: salesData,
                    borderColor: '#2196F3',
                    backgroundColor: 'rgba(33, 150, 243, 0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: '#2196F3',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
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
                                return value.toLocaleString() + ' ر.ي';
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
        window.location.href = 'view_payment.php?id=' + paymentId;
    }
    
    // ==================== اختصارات لوحة المفاتيح ====================
    document.addEventListener('keydown', function(e) {
        // ESC للعودة للقائمة
        if (e.key === 'Escape') {
            if (document.getElementById('paymentModal').classList.contains('active')) {
                closePaymentModal();
            } else {
                window.location.href = 'customers.php';
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
                window.location.href = 'edit_customer.php?id=<?php echo $customer_id; ?>';
            }
        }
        
        // P لإضافة سند قبض
        if (e.key === 'p' || e.key === 'P') {
            if (!e.ctrlKey && !e.altKey) {
                e.preventDefault();
                openPaymentModal();
            }
        }
    });


    function printPayment(paymentId) {
        window.location.href = 'print_payment.php?id=' + paymentId + '&customer_id=<?php echo $customer_id; ?>';
    }


    // ==================== حذف سند القبض ====================
    function deletePayment(paymentId, paymentNumber, customerId) {
        if (confirm('هل أنت متأكد من حذف سند القبض رقم: ' + paymentNumber + '؟')) {
            window.location.href = 'delete_payment.php?id=' + paymentId + '&customer_id=' + customerId;
        }
    }


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
    function returnInvoice(invoiceId) {
                window.location.href = 'sales_return.php?invoice_id=' + invoiceId;
        }


    // ==================== سند تسليم للعميل ====================
    function openCreditNoteModal() {
        document.getElementById('creditNoteModal').classList.add('active');
    }

    function closeCreditNoteModal() {
        document.getElementById('creditNoteModal').classList.remove('active');
    }

    function calculateRemainingForCredit() {
        const netRemaining = <?php echo $net_remaining; ?>;
        const amount = parseFloat(document.getElementById('credit_amount').value) || 0;
        const remainingAfter = netRemaining - amount;
        
        document.getElementById('credit_remaining_display').innerHTML = 
            '<i class="fas fa-info-circle"></i> الرصيد بعد التسليم: ' + 
            (remainingAfter > 0 ? remainingAfter.toFixed(2) + ' ر.ي (متبقي على العميل)' : 
            remainingAfter < 0 ? Math.abs(remainingAfter).toFixed(2) + ' ر.ي (يستحق للعميل)' : 
            '0.00 ر.ي (متساوي)');
    }
</script>

</body>
</html>