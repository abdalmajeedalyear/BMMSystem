<?php
session_start();
include "config.php";

$customer_name = $_GET['customer'] ?? '';
$customer = null;
$sales_invoices = [];
$payments = [];
$total_sales = 0;
$total_paid = 0;
$total_returns = 0;

if (!empty($customer_name)) {
    // البحث عن العميل
    $stmt = $conn->prepare("SELECT * FROM customers WHERE customer_name LIKE ?");
    $search_term = "%" . $customer_name . "%";
    $stmt->bind_param("s", $search_term);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $customer = $result->fetch_assoc();
        
        // جلب فواتير المبيعات للعميل
        $stmt_invoices = $conn->prepare("
            SELECT invoice_id, invoice_number, invoice_date, grand_total, payment_status, payment_method 
            FROM invoices 
            WHERE customer_id = ? 
            ORDER BY invoice_date DESC
        ");
        $stmt_invoices->bind_param("i", $customer['customer_id']);
        $stmt_invoices->execute();
        $invoices_result = $stmt_invoices->get_result();
        
        while ($row = $invoices_result->fetch_assoc()) {
            $sales_invoices[] = $row;
            $total_sales += $row['grand_total'];
        }
        
        // جلب المدفوعات
        $stmt_payments = $conn->prepare("
            SELECT p.*, i.invoice_number 
            FROM payments p 
            LEFT JOIN invoices i ON p.invoice_id = i.invoice_id 
            WHERE i.customer_id = ?
            ORDER BY p.payment_date DESC
        ");
        $stmt_payments->bind_param("i", $customer['customer_id']);
        $stmt_payments->execute();
        $payments_result = $stmt_payments->get_result();
        
        while ($row = $payments_result->fetch_assoc()) {
            $payments[] = $row;
            $total_paid += $row['payment_amount'];
        }
    }
}

$balance = $total_sales - $total_paid;
?>
<!DOCTYPE html>
<html class="light" dir="rtl" lang="ar">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>كشف حساب عميل</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&family=Noto+Sans+Arabic:wght@400;500;700;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#137fec",
                        "background-light": "#f6f7f8",
                        "background-dark": "#101922",
                    },
                    fontFamily: {
                        "display": ["Inter", "Noto Sans Arabic", "sans-serif"],
                    },
                },
            },
        }
    </script>
    <style>
        body { font-family: 'Noto Sans Arabic', 'Inter', sans-serif; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    </style>
</head>
<body class="bg-background-light min-h-screen">
    <header class="flex items-center justify-between bg-white px-10 py-3 border-b sticky top-0">
        <div class="flex items-center gap-4">
            <span class="material-symbols-outlined text-primary text-2xl">account_balance</span>
            <h2 class="text-lg font-bold">نظام إدارة مخازن مواد البناء</h2>
        </div>
        <div class="flex items-center gap-4">
            <a href="Control_Panel_main.php" class="text-sm font-medium hover:text-primary">الرئيسية</a>
            <a href="Store_items.php" class="text-sm font-medium hover:text-primary">المخازن</a>
        </div>
    </header>

    <main class="p-6 max-w-6xl mx-auto">
        <h1 class="text-3xl font-black mb-6">كشف حساب عميل</h1>
        
        <!-- نموذج البحث -->
        <form method="get" class="bg-white p-6 rounded-xl border shadow-sm mb-6">
            <div class="flex gap-4 items-end">
                <div class="flex-1">
                    <label class="block text-sm font-semibold mb-2">اسم العميل</label>
                    <input type="text" name="customer" value="<?php echo htmlspecialchars($customer_name); ?>" 
                           class="w-full border rounded-lg p-3" placeholder="ابحث باسم العميل..." required>
                </div>
                <button type="submit" class="px-6 py-3 bg-primary text-white rounded-lg font-bold hover:bg-primary/90">
                    بحث
                </button>
            </div>
        </form>

        <?php if ($customer): ?>
        <!-- بيانات العميل -->
        <div class="bg-white p-6 rounded-xl border shadow-sm mb-6">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-xl font-bold"><?php echo htmlspecialchars($customer['customer_name']); ?></h2>
                    <p class="text-gray-500 mt-1">رقم الهاتف: <?php echo htmlspecialchars($customer['customer_phone'] ?? 'غير محدد'); ?></p>
                    <p class="text-gray-500">العنوان: <?php echo htmlspecialchars($customer['customer_address'] ?? 'غير محدد'); ?></p>
                    <p class="text-gray-500">تاريخ التسجيل: <?php echo date('Y-m-d', strtotime($customer['created_at'])); ?></p>
                </div>
                <div class="text-left">
                    <div class="text-sm text-gray-500">الرصيد الحالي</div>
                    <div class="text-3xl font-black <?php echo $balance > 0 ? 'text-red-500' : 'text-green-600'; ?>">
                        <?php echo number_format($balance, 2); ?> ر.س
                    </div>
                </div>
            </div>
        </div>

        <!-- ملخص الحساب -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white p-4 rounded-xl border shadow-sm">
                <div class="text-sm text-gray-500">إجمالي المبيعات</div>
                <div class="text-xl font-bold text-primary"><?php echo number_format($total_sales, 2); ?> ر.س</div>
            </div>
            <div class="bg-white p-4 rounded-xl border shadow-sm">
                <div class="text-sm text-gray-500">المسدد</div>
                <div class="text-xl font-bold text-green-600"><?php echo number_format($total_paid, 2); ?> ر.س</div>
            </div>
            <div class="bg-white p-4 rounded-xl border shadow-sm">
                <div class="text-sm text-gray-500">المرتجعات</div>
                <div class="text-xl font-bold text-orange-500"><?php echo number_format($total_returns, 2); ?> ر.س</div>
            </div>
            <div class="bg-white p-4 rounded-xl border shadow-sm">
                <div class="text-sm text-gray-500">المتبقي</div>
                <div class="text-xl font-bold <?php echo $balance > 0 ? 'text-red-500' : 'text-green-600'; ?>">
                    <?php echo number_format($balance, 2); ?> ر.س
                </div>
            </div>
        </div>

        <!-- فواتير المبيعات -->
        <div class="bg-white rounded-xl border shadow-sm overflow-hidden mb-6">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h3 class="font-bold">فواتير المبيعات (<?php echo count($sales_invoices); ?>)</h3>
            </div>
            <?php if (count($sales_invoices) > 0): ?>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="px-6 py-3 text-right">رقم الفاتورة</th>
                        <th class="px-6 py-3 text-right">التاريخ</th>
                        <th class="px-6 py-3 text-right">المبلغ</th>
                        <th class="px-6 py-3 text-right">طريقة الدفع</th>
                        <th class="px-6 py-3 text-right">الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sales_invoices as $invoice): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-6 py-3 font-mono"><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                        <td class="px-6 py-3"><?php echo $invoice['invoice_date']; ?></td>
                        <td class="px-6 py-3 font-bold"><?php echo number_format($invoice['grand_total'], 2); ?> ر.س</td>
                        <td class="px-6 py-3"><?php echo htmlspecialchars($invoice['payment_method']); ?></td>
                        <td class="px-6 py-3">
                            <?php if ($invoice['payment_status'] == 'مدفوعة'): ?>
                                <span class="px-2 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700">مدفوعة</span>
                            <?php elseif ($invoice['payment_status'] == 'جزئي'): ?>
                                <span class="px-2 py-1 rounded-full text-xs font-bold bg-orange-100 text-orange-700">مدفوعة جزئياً</span>
                            <?php else: ?>
                                <span class="px-2 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700">غير مدفوعة</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="p-6 text-center text-gray-500">لا توجد فواتير لهذا العميل</div>
            <?php endif; ?>
        </div>

        <!-- المدفوعات -->
        <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h3 class="font-bold">سجل المدفوعات (<?php echo count($payments); ?>)</h3>
            </div>
            <?php if (count($payments) > 0): ?>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="px-6 py-3 text-right">التاريخ</th>
                        <th class="px-6 py-3 text-right">المبلغ</th>
                        <th class="px-6 py-3 text-right">طريقة الدفع</th>
                        <th class="px-6 py-3 text-right">الفاتورة</th>
                        <th class="px-6 py-3 text-right">ملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-6 py-3"><?php echo $payment['payment_date']; ?></td>
                        <td class="px-6 py-3 font-bold text-green-600">+<?php echo number_format($payment['payment_amount'], 2); ?> ر.س</td>
                        <td class="px-6 py-3"><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                        <td class="px-6 py-3 font-mono"><?php echo htmlspecialchars($payment['invoice_number'] ?? 'عام'); ?></td>
                        <td class="px-6 py-3 text-gray-500"><?php echo htmlspecialchars($payment['notes'] ?? ''); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="p-6 text-center text-gray-500">لا توجد مدفوعات لهذا العميل</div>
            <?php endif; ?>
        </div>
        
        <?php elseif (!empty($customer_name)): ?>
        <div class="bg-red-50 border border-red-200 p-6 rounded-xl text-red-700">
            لم يتم العثور على عميل بهذا الاسم
        </div>
        <?php endif; ?>
    </main>
</body>
</html>