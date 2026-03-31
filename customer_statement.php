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


// احصائيات فواتير المبعات  للعميل معين
$sql = "SELECT 
            c.customer_id,
            c.customer_name,
            COALESCE(SUM(i.paid_amount), 0) as total_paid,
            COALESCE(SUM(i.grand_total), 0) as total_invoices,
            COALESCE(SUM(i.remaining_amount), 0) as total_remaining,
            COUNT(i.invoice_id) as invoices_count
        FROM customers c
        LEFT JOIN invoices i ON c.customer_id = i.customer_id
        WHERE c.customer_id = ?
        GROUP BY c.customer_id, c.customer_name";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

// عرض النتائج
// echo "العميل: " . $data['customer_name'] . "<br>";
// echo "إجمالي المبالغ المسلمة: " . number_format($data['total_paid'], 2) . " ر.ي<br>";
$total_paidd=$data['total_paid'];
// echo "إجمالي الفواتير: " . number_format($data['total_invoices'], 2) . " ر.ي<br>";
// echo "إجمالي المتبقي: " . number_format($data['total_remaining'], 2) . " ر.ي<br>";
// echo "عدد الفواتير: " . $data['invoices_count'];



// تواريخ التقرير
$date_to = date('Y-m-d');
$date_from = date('Y-m-d', strtotime('-1 year'));

if (isset($_GET['from']) && isset($_GET['to'])) {
    $date_from = $_GET['from'];
    $date_to = $_GET['to'];
}

// ==================== بيانات العميل الأساسية ====================
$customer_sql = "SELECT * FROM customers WHERE customer_id = ?";
$customer_stmt = $conn->prepare($customer_sql);
$customer_stmt->bind_param("i", $customer_id);
$customer_stmt->execute();
$customer_result = $customer_stmt->get_result();

if ($customer_result->num_rows === 0) {
    header("Location: customers.php");
    exit();
}

$customer = $customer_result->fetch_assoc();

// ==================== استعلام UNION مباشر من قاعدة البيانات ====================
$union_sql = "
    (SELECT 
        'فاتورة مبيعات' as doc_type,
        invoice_number as doc_number,
        invoice_date as trans_date,
        CONCAT(invoice_date, ' ', TIME(created_at)) as trans_datetime,
        CONCAT('فاتورة مبيعات رقم ', invoice_number) as description,
        invoice_id as reference,
        grand_total as amount,
        paid_amount as paid,
        'debit' as trans_type
    FROM invoices 
    WHERE customer_id = $customer_id AND invoice_date BETWEEN '$date_from' AND '$date_to')
    
    UNION ALL
    
    (SELECT 
        'مرتجع مبيعات' as doc_type,
        return_number as doc_number,
        return_date as trans_date,
        CONCAT(return_date, ' ', TIME(created_at)) as trans_datetime,
        CONCAT('مرتجع مبيعات رقم ', return_number) as description,
        return_id as reference,
        total_amount as amount,
        total_amount as paid,
        'credit' as trans_type
    FROM sales_returns 
    WHERE customer_id = $customer_id AND return_date BETWEEN '$date_from' AND '$date_to')
    
    UNION ALL
    
    (SELECT 
        'سند قبض' as doc_type,
        payment_number as doc_number,
        payment_date as trans_date,
        CONCAT(payment_date, ' ', TIME(created_at)) as trans_datetime,
        CONCAT('سند قبض رقم ', payment_number) as description,
        payment_id as reference,
        payment_amount as amount,
        payment_amount as paid,
        'credit' as trans_type
    FROM customer_payments 
    WHERE customer_id = $customer_id AND payment_date BETWEEN '$date_from' AND '$date_to')

    UNION ALL

    (SELECT 
        'سند تسليم' as doc_type,
        credit_note_number as doc_number,
        credit_note_date as trans_date,
        CONCAT(credit_note_date, ' ', TIME(created_at)) as trans_datetime,
        CONCAT('سند تسليم رقم ', credit_note_number) as description,
        credit_note_id as reference,
        amount as amount,
        amount as paid,
        'debitt' as trans_type
    FROM customer_credit_notes 
    WHERE customer_id = $customer_id AND credit_note_date BETWEEN '$date_from' AND '$date_to')
    
    ORDER BY trans_datetime ASC
";

$union_result = $conn->query($union_sql);

if (!$union_result) {
    die("خطأ في استعلام UNION: " . $conn->error);
}

// ==================== حساب الرصيد التراكمي ====================
$running_balance = 0;
$transactions_for_balance = [];
$transactions_for_balance_result = $conn->query($union_sql);
while ($row = $transactions_for_balance_result->fetch_assoc()) {
    
    if ($row['trans_type'] == 'debit') {
        $running_balance += $row['amount']-$row['paid'];
    } elseif ($row['trans_type'] == 'debitt'){
        $running_balance += $row['amount'];
    } else {
        $running_balance -= $row['amount'];
    }
    $row['balance'] = $running_balance;
    $transactions_for_balance[] = $row;
}

// إحصائيات
$invoice_count = 0;
$return_count = 0;
$payment_count = 0;
$delivery_count=0;
$total_debit = 0;
$total_credit = 0;
$total_delivery=0;

$stats_result = $conn->query($union_sql);
while ($row = $stats_result->fetch_assoc()) {
    if ($row['doc_type'] == 'فاتورة مبيعات') {
        $invoice_count++;
        $total_debit += $row['amount'];
    } elseif ($row['doc_type'] == 'مرتجع مبيعات') {
        $return_count++;
        $total_credit += $row['amount'];
    } elseif ($row['doc_type'] == 'سند قبض') {
        $payment_count++;
        $total_credit += $row['amount'];
    } elseif ($row['doc_type'] == 'سند تسليم') {
        $delivery_count++;
        $total_delivery += $row['amount'];
    }
}

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
    <title>كشف حساب - <?php echo $customer['customer_name']; ?></title>
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Tahoma', 'Arial', sans-serif;
        }

        body {
            background: #f5f5f5;
            padding: 20px;
        }

        .statement-container {
            max-width: 1300px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }

        /* الهيدر */
        .statement-header {
            margin-top: 20px;
            color: white;
            padding: 20px;
            position: relative;
            border: 2px solid #1a160f;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .company-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .company-logo {
            width: 60px;
            height: 60px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #17120a;
        }

        .company-logo i {
            font-size: 30px;
            color: #17120a;
        }

        .company-details h1 {
            font-size: 20px;
            margin-bottom: 3px;
            color: #17120a;
            font-weight: bold;
        }

        .company-details p {
            font-size: 11px;
            opacity: 0.8;
            line-height: 1.4;
            color: #17120a;
            font-weight: bold;
        }

        .company-contacts {
            border: 2px solid #17120a;
            background: rgba(255,255,255,0.1);
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 14px;
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .company-contacts i {
            color: #17120a;
            width: 18px;
        }
        .company-contacts span {
            color: #17120a;
            
        }

        .report-title {
            padding: 10px 20px;
            margin-right:280px;
            border: 2px solid #1a160f;
            background: rgba(255,255,255,0.1);
            text-align: center;
            border-radius: 8px; 
            width: 400px;
        }

        .report-title h2 {
            font-size: 22px;
            
            color: #17120a;
        }

        .date-range {
            margin-top: 10px;
            margin-right:330px;
            width: 300px;
            border-radius: 8px;
            border: 2px solid #17120a;
            background: rgba(255,255,255,0.1);
            display: flex;
            gap: 10px;
            justify-content: center;
            font-size: 13px;
            color: #17120a;
        }

        .date-range span {
            background: rgba(255,255,255,0.1);
            padding: 4px 12px;
            border-radius: 20px;
        }

        /* معلومات العميل */
        .customer-info-bar {
           
            background: #f8f9fa;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            border-bottom: 2px solid #1a3e6f;
            border-right: 2px solid #1a3e6f;
            border-left: 2px solid #1a3e6f;
            font-size: 14px;
        }

        .customer-id {
            background: #1a3e6f;
            color: white;
            padding: 5px 15px;
            border-radius: 6px;
            font-weight: bold;
        }

        .customer-id i {
            color: #f5b042;
            margin-left: 5px;
        }

        .customer-name {
            border: 2px solid #1a3e6f;
            padding: 5px 20px;
            border-radius: 12px;
            font-weight: bold;
            color: #1a3e6f;
        }

        .customer-currency {
            background: #f5b042;
            color: #1a3e6f;
            padding: 5px 15px;
            border-radius: 6px;
            font-weight: bold;
            font-size: 13px;
        }

        /* ملخص سريع */
        .quick-summary {
            background: #f0f4f8;
            padding: 10px 20px;
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            border: 2px solid #17120a;
            font-size: 13px;
        }

        .summary-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .summary-item i {
            font-size: 16px;
        }

        .summary-item .count {
            font-weight: bold;
            font-size: 18px;
            margin-right: 5px;
        }

        /* الفلتر */
        .filter-section {
            background: #f8f9fa;
            padding: 10px 20px;
            border-bottom: 1px solid #dee2e6;
        }

        .filter-form {
            display: flex;
            gap: 10px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .filter-group label {
            font-size: 11px;
            color: #666;
            font-weight: bold;
        }

        .filter-group input {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            width: 140px;
        }

        .filter-group input:focus {
            outline: none;
            border-color: #1a3e6f;
        }

        .filter-btn, .reset-btn {
            padding: 6px 15px;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
        }

        .filter-btn {
            background: #1a3e6f;
            color: white;
        }

        .reset-btn {
            background: #6c757d;
            color: white;
        }

        /* الجدول */
        .table-wrapper {
            border-right: 2px solid #1a3e6f;
            border-left: 2px solid #1a3e6f;
            padding: 15px 20px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
            font-size: 13px;
        }

        th {
            background: #1a3e6f;
            color: white;
            padding: 10px 5px;
            font-weight: 600;
            text-align: center;
            font-size: 12px;
            white-space: nowrap;
        }

        td {
            padding: 8px 5px;
            text-align: center;
            border-bottom: 1px solid #dee2e6;
            color: #333;
            font-size: 12px;
            white-space: nowrap;
        }

        td:nth-child(4) {
            text-align: right;
            max-width: 250px;
            white-space: normal;
            word-wrap: break-word;
        }

        tbody tr:hover {
            background: #f8f9fa;
        }

        .doc-invoice { 
            background: #e3f2fd;
            color: #1a3e6f;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
            display: inline-block;
        }
        
        .doc-payment { 
            background: #e8f5e9;
            color: #28a745;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
            display: inline-block;
        }
        
        .doc-return { 
            background: #ffebee;
            color: #dc3545;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
            display: inline-block;
        }

        .amount-debit {
            color: #1a3e6f;
            font-weight: 600;
        }

        .amount-credit {
            color: #28a745;
            font-weight: 600;
        }

        .amount-balance {
            font-weight: 700;
        }

        .balance-positive {
            color: #1a3e6f;
        }

        .balance-negative {
            color: #dc3545;
        }

        /* التذييل */
        .table-footer {
            background: #f8f9fa;
            padding: 12px 20px;
            border-top: 2px solid #1a3e6f;
            border-bottom: 2px solid #1a3e6f;
            border-left: 2px solid #1a3e6f;
            border-right: 2px solid #1a3e6f;
            margin-top: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 13px;
        }

        .totals {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .total-item {
            padding:5px;
            display: flex;
            align-items: center;
            gap: 5px;
            border: 2px solid #16473e;
            border-radius: 4px;
        }

        .total-label {
            color: #666;
        }

        .total-value {
            font-weight: bold;
        }

        .final-balance {
            background: #1a3e6f;
            color: white;
            padding: 8px 20px;
            border-radius: 6px;
            font-weight: bold;
        }

        /* التوقيعات */
        .signatures {
            padding: 15px 20px;
            display: flex;
            justify-content: space-around;
            border-top: 2px dashed #dee2e6;
            border-right: 2px solid #1a3e6f;
            border-left: 2px solid #1a3e6f;

            margin-top: 20px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .signature-box {
            text-align: center;
            min-width: 120px;
        }

        .signature-title {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }

        .signature-line {
            width: 120px;
            height: 1px;
            background: #1a3e6f;
            margin: 10px 0 3px;
        }

        .signature-name {
            font-size: 13px;
            font-weight: bold;
            color: #1a3e6f;
        }

        .signature-date {
            font-size: 10px;
            color: #999;
        }

        /* الفوتر */
        .statement-footer {
            background: #f8f9fa;
            padding: 10px 20px;
            border-top: 2px solid #1a3e6f;
            border-right: 2px solid #1a3e6f;
            border-left: 2px solid #1a3e6f;
            border-bottom: 2px solid #1a3e6f;
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: #666;
            flex-wrap: wrap;
            gap: 10px;
        }

        /* أزرار التحكم */
        .action-buttons {
            padding: 10px 20px;
            background: #f8f9fa;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            border-top: 1px solid #dee2e6;
        }

        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #1a3e6f;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        /* رسالة لا توجد بيانات */
        .no-data {
            text-align: center;
            padding: 30px;
            color: #999;
        }

        .no-data i {
            font-size: 40px;
            margin-bottom: 10px;
            opacity: 0.3;
        }

        /* تنسيقات الطباعة */
        @media print {
            body {
                background: white;
                padding: 0;
            }

            .statement-container {
                box-shadow: none;
            }

            .filter-section,
            .action-buttons,
            .no-print {
                display: none !important;
            }

            th {
                background: #1a3e6f !important;
                color: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .customer-id, .final-balance {
                background: #1a3e6f !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .customer-currency {
                background: #bd7f1d !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="statement-container">
        <!-- الهيدر -->
        <div class="statement-header">
            <div class="header-top">
                <div class="company-info">
                    <div class="company-logo">
                        <i class="fas fa-warehouse"></i>
                    </div>
                    <div class="company-details">
                        <h1>مكتب اليعري للمقاولات</h1>
                        <p>مواد البناء والكهرباء والسباكة - مواد صحية وأدوات نجارة</p>
                    </div>
                </div>
                <div class="company-contacts">
                    <span><i class="fas fa-phone"></i> 774608082 - 777859275</span>
                    <span><i class="fas fa-map-marker-alt"></i> صنعاء -بني مطر-متنة-مفرق بيت ردم</span>
                </div>
            </div>

            <div class="report-title">
                <h2>كشف حساب تحليلي</h2>
                
            </div>
            <div class="date-range">
                <span><i class="far fa-calendar-alt"></i> من: <?php echo date('Y-m-d', strtotime($date_from)); ?></span>
                <span><i class="far fa-calendar-alt"></i> إلى: <?php echo date('Y-m-d', strtotime($date_to)); ?></span>
            </div>
        </div>

        <!-- معلومات العميل -->
        <div class="customer-info-bar">
            <div class="customer-id">
                <i class="fas fa-id-card"></i> رقم: <?php echo str_pad($customer_id, 4, '0', STR_PAD_LEFT); ?>
            </div>
            <div class="customer-name">
                <i class="fas fa-user"></i> <?php echo $customer['customer_name']; ?>
            </div>
            <div class="customer-currency">
                <i class="fas fa-money-bill"></i> YER ريال يمني
            </div>
        </div>

        <!-- ملخص سريع -->
        <div class="quick-summary">
            <div class="summary-item">
                <i class="fas fa-file-invoice" style="color: #1a3e6f;"></i>
                <span>فواتير: <span class="count"><?php echo $invoice_count; ?></span></span>
            </div>
            <div class="summary-item">
                <i class="fas fa-undo-alt" style="color: #dc3545;"></i>
                <span>مرتجعات: <span class="count"><?php echo $return_count; ?></span></span>
            </div>
            <div class="summary-item">
                <i class="fas fa-hand-holding-usd" style="color: #28a745;"></i>
                <span>سندات: <span class="count"><?php echo $payment_count+$delivery_count; ?></span></span>
            </div>

            <div class="summary-item">
                <i class="fas fa-calculator" style="color: #1a3e6f;"></i>
                <span>إجمالي: <span class="count"><?php echo count($transactions_for_balance); ?></span></span>
            </div>
        </div>

        <!-- فلتر التاريخ -->
        <div class="filter-section no-print">
            <form method="GET" class="filter-form">
                <input type="hidden" name="id" value="<?php echo $customer_id; ?>">
                <div class="filter-group">
                    <label><i class="far fa-calendar-alt"></i> من تاريخ</label>
                    <input type="date" name="from" value="<?php echo $date_from; ?>">
                </div>
                <div class="filter-group">
                    <label><i class="far fa-calendar-alt"></i> إلى تاريخ</label>
                    <input type="date" name="to" value="<?php echo $date_to; ?>">
                </div>
                <button type="submit" class="filter-btn">
                    <i class="fas fa-search"></i> عرض
                </button>
                <a href="?id=<?php echo $customer_id; ?>" class="reset-btn">
                    <i class="fas fa-redo-alt"></i> الكل
                </a>
            </form>
        </div>

        <!-- الجدول الرئيسي -->
        <div class="table-wrapper">
            <?php if (count($transactions_for_balance) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>التاريخ</th>
                        <th>نوع المستند</th>
                        <th>رقم المستند</th>
                        <th>البيان</th>
                        <th>رقم المرجع</th>
                        <th>مدين</th>
                        <th>دائن</th>
                        <th>الرصيد</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $row_count = 0;
                    foreach ($transactions_for_balance as $trans): 
                        $row_count++;
                        $balance_class = $trans['balance'] >= 0 ? 'balance-positive' : 'balance-negative';
                        
                        // تحديد الكلاس المناسب لكل نوع
                        if ($trans['doc_type'] == 'فاتورة مبيعات') {
                            $doc_class = 'doc-invoice';
                            $doc_icon = 'fa-file-invoice';
                        } elseif ($trans['doc_type'] == 'مرتجع مبيعات') {
                            $doc_class = 'doc-return';
                            $doc_icon = 'fa-undo-alt';
                        } elseif ($trans['doc_type'] == 'سند قبض') {
                            $doc_class = 'doc-payment';
                            $doc_icon = 'fa-hand-holding-usd';
                        }
                    ?>
                    <tr>
                        <td><?php echo $row_count; ?></td>
                        <td><?php echo date('Y-m-d', strtotime($trans['trans_date'])); ?></td>
                        <td>
                            <span class="<?php echo $doc_class; ?>">
                                <i class="fas <?php echo $doc_icon; ?>"></i> <?php echo $trans['doc_type']; ?>
                            </span>
                        </td>
                        <td><?php echo $trans['doc_number']; ?></td>
                        <td style="text-align: right;"><?php echo $trans['description']; ?></td>
                        <td><?php echo $trans['reference']; ?></td>
                        <td class="amount-debit"><?php echo $trans['trans_type'] == 'debit' ? formatNumber($trans['amount']) : '-';       echo $trans['trans_type'] == 'debitt' ? formatNumber($trans['amount']) : '-';  ?></td>
                        <td class="amount-credit"><?php echo $trans['trans_type'] == 'credit' ? formatNumber($trans['amount']) : '-';  if($trans['trans_type'] == 'debit' && !empty($trans['paid']) && $trans['paid'] > 0){ echo formatNumber($trans['paid']); } else { echo '-'; } ?></td>
                        <td class="amount-balance <?php echo $balance_class; ?>"><?php echo formatNumber($trans['balance']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">
                <i class="fas fa-file-invoice"></i>
                <p>لا توجد معاملات في هذه الفترة</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- ملخص الجدول -->
        <?php if (count($transactions_for_balance) > 0): ?>
        <div class="table-footer">
            <div class="totals">
                <div class="total-item">
                    <span class="total-label">إجمالي المبيعات (مدين):</span>
                    <span class="total-value amount-debit"><?php echo formatNumber($total_debit); ?> ر.ي</span>
                </div>
                <div class="total-item">
                    <span class="total-label">إجمالي سندات القبض والمرتجعات (دائن):</span>
                    <span class="total-value amount-credit"><?php echo formatNumber($total_credit+$total_paidd); ?> ر.ي</span>
                </div>
                <div class="total-item">
                    <span class="total-label">إجمالي سندات التسليم (مدين):</span>
                    <span class="total-value amount-credit"><?php echo formatNumber($total_delivery); ?> ر.ي</span>
                </div>
            </div>
            <div class="final-balance">
                <i class="fas fa-balance-scale"></i>
                الرصيد النهائي: <?php 
                if($running_balance > 0) {
                    echo formatNumber($running_balance) ."ر.ي"." : مبلغ مستحق علية";
                } else {
                    echo "".formatNumber(abs($running_balance)) ."ر.ي"." : مبلغ مستحق له";
                }
                ?> 
            </div>
        </div>

        <!-- التوقيعات -->
        <div class="signatures">
            <div class="signature-box">
                <div class="signature-title">المحاسب</div>
                <div class="signature-line"></div>
                <div class="signature-name">_____________</div>
                <div class="signature-date"><?php echo date('Y-m-d'); ?></div>
            </div>
            <div class="signature-box">
                <div class="signature-title">المدير المالي</div>
                <div class="signature-line"></div>
                <div class="signature-name">_____________</div>
                <div class="signature-date"><?php echo date('Y-m-d'); ?></div>
            </div>
            <div class="signature-box">
                <div class="signature-title">ختم المحل</div>
                <div class="signature-line"></div>
                <div class="signature-name">مكتب اليعري</div>
                <div class="signature-date"><?php echo date('Y-m-d'); ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- الفوتر -->
        <div class="statement-footer">
            <div><i class="far fa-clock"></i> <?php echo date('Y-m-d h:i A'); ?></div>
            <div><i class="fas fa-user"></i> <?php echo $_SESSION['full_name'] ?? 'مدير النظام'; ?></div>
            <div><i class="far fa-copyright"></i> مكتب اليعري للمقاولات</div>
        </div>

        <!-- أزرار التحكم -->
        <div class="action-buttons no-print">
            <a href="view_customer.php?id=<?php echo $customer_id; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i> عودة
            </a>
            <button class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print"></i> طباعة
            </button>
        </div>
    </div>
</body>
</html>