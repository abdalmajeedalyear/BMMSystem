<?php
session_start();
include "config.php";
// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: logout.php");
    exit();
}


// ==================== منع إعادة إرسال النموذج ====================
if (isset($_SESSION['form_submitted']) && $_SESSION['form_submitted'] === true) {
    // إذا كان النموذج قد أرسل من قبل، لا تعالج مرة أخرى
    unset($_SESSION['form_submitted']);
}

// ==================== البحث عن المنتجات ====================
if (isset($_GET['search_products'])) {
    header('Content-Type: application/json');
    $search = $_GET['search_products'];
    
    if (strlen($search) < 2) {
        echo json_encode([]);
        exit;
    }
    
    $search = "%$search%";
    
    // تعديل: تغيير من store_items إلى products
    $stmt = $conn->prepare("SELECT product_id as item_id, product_code as item_code, product_name as item_name, selling_price, product_unit as unit, current_quantity as quantity 
                            FROM products 
                            WHERE product_name LIKE ? OR product_code LIKE ? 
                            LIMIT 10");
    $stmt->bind_param("ss", $search, $search);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'id' => $row['item_id'],
            'code' => $row['item_code'],
            'name' => $row['item_name'],
            'price' => $row['selling_price'],
            'unit' => $row['unit'],
            'stock' => $row['quantity']
        ];
    }
    
    echo json_encode($products);
    exit;
}

// ==================== معالجة حفظ الفاتورة ====================
$success_message = "";
$error_message = "";

// ==================== معالجة حفظ الفاتورة ====================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_invoice'])) {
    
    // التحقق من وجود منتجات حقيقية
    $has_products = false;
    $products_to_save = []; // مصفوفة لتخزين المنتجات الصالحة
    $out_of_stock = []; // مصفوفة للمنتجات الغير متوفرة
    
    if (isset($_POST['products']) && is_array($_POST['products'])) {
        foreach ($_POST['products'] as $product) {
            if (!empty($product['product_name']) && !empty($product['product_quantity']) && $product['product_quantity'] > 0) {
                $has_products = true;
                
                // تنظيف البيانات
                $name = $conn->real_escape_string($product['product_name']);
                $unit = $conn->real_escape_string($product['product_unit'] ?? 'قطعة');
                $qty = floatval($product['product_quantity']);
                $price = floatval($product['product_price']);
                $discount = floatval($product['product_discount'] ?? 0);
                $total = floatval($product['product_total'] ?? 0);
                
                // التحقق من توفر الكمية في المخزون - تعديل من store_items إلى products
                $stock_check = $conn->query("SELECT current_quantity as quantity FROM products WHERE product_name = '$name'");
                if ($stock_check->num_rows > 0) {
                    $stock = $stock_check->fetch_assoc();
                    if ($stock['quantity'] < $qty) {
                        $out_of_stock[] = "$name (المتوفر: {$stock['quantity']}، المطلوب: $qty)";
                    } else {
                        $products_to_save[] = [
                            'name' => $name,
                            'unit' => $unit,
                            'qty' => $qty,
                            'price' => $price,
                            'discount' => $discount,
                            'total' => $total
                        ];
                    }
                } else {
                    $out_of_stock[] = "$name (غير موجود في المخزون)";
                }
            }
        }
    }
    
    if (!$has_products) {
        $_SESSION['alert'] = [
            'type' => 'error',
            'message' => "❌ يجب إضافة منتج واحد على الأقل"
        ];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    if (!empty($out_of_stock)) {
        $error_msg = "❌ لا يمكن حفظ الفاتورة لعدم توفر الكميات التالية:\n- " . implode("\n- ", $out_of_stock);
        $_SESSION['alert'] = [
            'type' => 'error',
            'message' => nl2br($error_msg)
        ];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    // بدء المعاملة
    $conn->begin_transaction();
    
    try {
        // بيانات الفاتورة
        $invoice_number = generateInvoiceNumber($conn);
        $invoice_date = $conn->real_escape_string($_POST['invoice_date']);
        $customer_name = $conn->real_escape_string($_POST['customer_name']);
        $created_by = $_SESSION['user_id'] ?? 0;
        $customer_phone = $conn->real_escape_string($_POST['customer_phone'] ?? '');
        $notes = $conn->real_escape_string($_POST['notes'] ?? '');
        $payment_method = $conn->real_escape_string($_POST['payment_method']);
        
        // الحصول على المبلغ المسلم والمتبقي
        $paid_amount = floatval($_POST['paid_amount'] ?? 0);
        $remaining_amount = floatval($_POST['remaining_amount'] ?? 0);
        
        // إدخال العميل
        $customer_id = 0;
        if (!empty($customer_name)) {
            $check = $conn->query("SELECT customer_id FROM customers WHERE customer_name = '$customer_name'");
            if ($check->num_rows > 0) {
                $customer_id = $check->fetch_assoc()['customer_id'];
            } else {
                $conn->query("INSERT INTO customers (customer_name, customer_phone) VALUES ('$customer_name', '$customer_phone')");
                $customer_id = $conn->insert_id;
            }
        }
        
        // حساب الإجماليات من النموذج
        $subtotal = floatval($_POST['subtotal'] ?? 0);
        $total_discount = floatval($_POST['total_discount'] ?? 0);
        $total_tax = floatval($_POST['total_tax'] ?? 0);
        $grand_total = floatval($_POST['grand_total'] ?? 0);
        
        // تحديث حالة الدفع بناءً على المبلغ المدفوع
        if ($paid_amount >= $grand_total && $grand_total > 0) {
            $payment_status = 'مدفوعة';
            $remaining_amount = 0;
        } elseif ($paid_amount > 0) {
            $payment_status = 'جزئي';
        } else {
            $payment_status = 'غير مدفوعة';
            $remaining_amount = $grand_total;
        }
        
        // ثم في استعلام INSERT
        $sql = "INSERT INTO invoices (invoice_number, invoice_date, customer_id, subtotal, total_discount, total_tax, grand_total, paid_amount, remaining_amount, payment_method, payment_status, notes, created_by) 
        VALUES ('$invoice_number', '$invoice_date', $customer_id, $subtotal, $total_discount, $total_tax, $grand_total, $paid_amount, $remaining_amount, '$payment_method', '$payment_status', '$notes', $created_by)";
        
        if (!$conn->query($sql)) {
            throw new Exception("خطأ في حفظ الفاتورة: " . $conn->error);
        }
        
        $invoice_id = $conn->insert_id;
        
        // إدخال المنتجات وتحديث المخزون
        foreach ($products_to_save as $product) {
            // إدخال عنصر الفاتورة
            $sql_item = "INSERT INTO invoice_items (invoice_id, product_name, product_unit, product_quantity, product_price, product_discount, product_total) 
                        VALUES ($invoice_id, '{$product['name']}', '{$product['unit']}', {$product['qty']}, {$product['price']}, {$product['discount']}, {$product['total']})";
            
            if (!$conn->query($sql_item)) {
                throw new Exception("خطأ في حفظ المنتجات: " . $conn->error);
            }
            
            // تحديث المخزون (إنقاص الكمية) - تعديل من store_items إلى products
            $update_stock = "UPDATE products SET current_quantity = current_quantity - {$product['qty']} WHERE product_name = '{$product['name']}'";
            if (!$conn->query($update_stock)) {
                throw new Exception("خطأ في تحديث المخزون: " . $conn->error);
            }
        }
        
        // تأكيد المعاملة
        $conn->commit();
        
        $_SESSION['alert'] = [
            'type' => 'success',
            'message' => "✅ تم حفظ الفاتورة رقم $invoice_number بنجاح! المبلغ المدفوع: " . number_format($paid_amount, 2) . " ر.ي، المتبقي: " . number_format($remaining_amount, 2) . " ر.ي"
        ];
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['alert'] = [
            'type' => 'error',
            'message' => "❌ " . $e->getMessage()
        ];
    }
    
    // منع إعادة الإرسال
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// دالة توليد رقم الفاتورة
function generateInvoiceNumber($conn) {
    $year = date('Y');
    $month = date('m');
    
    $sql = "SELECT MAX(invoice_id) as max_id FROM invoices";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    $next_id = ($row['max_id'] ? $row['max_id'] : 0) + 1;
    
    return "INV-" . $year . $month . "-" . str_pad($next_id, 4, '0', STR_PAD_LEFT);
}

// استرجاع رسائل الجلسة
if (isset($_SESSION['alert'])) {
    if ($_SESSION['alert']['type'] == 'success') {
        $success_message = $_SESSION['alert']['message'];
    } else {
        $error_message = $_SESSION['alert']['message'];
    }
    unset($_SESSION['alert']);
}

$next_invoice_number = generateInvoiceNumber($conn);
$today_date = date('Y-m-d');
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فاتورة مبيعات جديدة - نظام المخازن</title>
    
    <!-- Font Awesome محلي (نفس مسار ملف المشتريات) -->
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">
    
    <style>
        /* نفس تنسيقات ملف المشتريات بالكامل */
        body { 
            font-family: 'Tahoma', sans-serif; 
            background: #f5f5f5; 
            margin: 0; 
            padding: 20px; 
        }
        
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            background: white; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }
        
        h1 { 
            color: #333; 
            margin-bottom: 20px; 
        }
        
        /* تنسيق الجدول */
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 15px 0; 
        }
        
        th { 
            background: #f0f0f0; 
            padding: 12px; 
            text-align: center; 
            border: 1px solid #ddd; 
        }
        
        td { 
            border: 1px solid #ddd; 
            padding: 8px; 
        }
        td:nth-child(2) {
            width: 30%;
            border: 1px solid #ddd; 
            padding: 8px; 
        }
        input, select { 
            width: 100%; 
            padding: 8px; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
            box-sizing: border-box; 
        }
        
        input:focus, select:focus { 
            outline: 2px solid #2196F3; 
        }
        
        /* الأزرار */
        .btn { 
            padding: 10px 20px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            font-weight: bold; 
        }
        
        .btn-primary { 
            background: #2196F3; 
            color: white; 
        }
        
        .btn-success { 
            background: #4CAF50; 
            color: white; 
        }
        
        .btn-danger { 
            background: #f44336; 
            color: white; 
        }
        
        .btn-warning { 
            background: #ff9800; 
            color: white; 
        }
        
        /* رأس الفاتورة */
        .invoice-header {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }
        
        .header-item {
            display: flex;
            flex-direction: column;
        }
        
        .header-item label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .header-item input, .header-item select {
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            font-size: 14px;
        }
        
        .header-item input[readonly] {
            background: #f5f5f5;
            color: #666;
        }
        
        .col-span-2 {
            grid-column: span 2;
        }
        
        /* شريط الأدوات */
        .toolbar {
            background: #f9f9f9;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-bottom: none;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .shortcuts span {
            background: #eee;
            padding: 3px 6px;
            border-radius: 4px;
            margin: 0 5px;
            font-size: 12px;
        }
        
        /* الملخص */
        .summary-section {
            margin-top: 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .totals {
            background: #f0f8ff;
            padding: 20px;
            border: 1px solid #2196F3;
            border-radius: 8px;
        }
        
        /* زر الحذف */
        .delete-btn {
            background: none;
            border: none;
            color: #f44336;
            cursor: pointer;
            font-size: 16px;
        }
        
        /* الاقتراحات */
        .autocomplete-wrapper { 
            position: relative;
             
        }
        
        .autocomplete-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 4px 4px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            display: none;
        }
        
        .autocomplete-dropdown.show { 
            display: block; 
        }
        
        .autocomplete-item {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .autocomplete-item:hover { 
            background: #f0f0f0; 
        }
        
        .autocomplete-item .item-name {
            font-weight: bold;
            color: #333;
        }
        
        .autocomplete-item .item-code {
            font-size: 11px;
            color: #666;
        }
        
        .autocomplete-item .item-details {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            margin-top: 5px;
        }
        
        .autocomplete-item .item-price {
            color: #4CAF50;
        }
        
        .autocomplete-item .item-stock {
            color: #ff9800;
        }
        
        /* أزرار التحكم السفلية */
        .footer-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        
        /* رسائل التنبيه */
        .alert {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 4px;
            color: white;
            font-weight: bold;
            z-index: 9999;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .alert-success { background: #4CAF50; }
        .alert-error { background: #f44336; }
        
        /* تنسيق شريط العنوان */
        .title-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
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
        }
        
        .back-link:hover {
            background: #f5f5f5;
        }
        
        .date-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: bold;
            margin-right: auto;
            margin-left: 120px;
            border: 3px solid #3a94dd;
        }
        
        .head_link {
            display: flex;
            align-items: center;
            width: 100%;
        }
        
        /* تنسيق خاص لحقول الدفع */
        .payment-highlight {
            font-weight: bold;
        }
        
        .paid-field {
            border: 2px solid #4CAF50 !important;
        }
        
        .remaining-field {
            border: 2px solid #f44336 !important;
            font-weight: bold;
        }
    </style>
</head>
<body class="bg-gray-100">

<!-- رسائل التنبيه -->
<?php if ($success_message): ?>
    <div class="alert alert-success" id="successAlert">
        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
    </div>
    <script>setTimeout(() => document.getElementById('successAlert')?.remove(), 5000);</script>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-error" id="errorAlert">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
    </div>
    <script>setTimeout(() => document.getElementById('errorAlert')?.remove(), 5000);</script>
<?php endif; ?>

<!-- ==================== شريط العنوان وزر الرجوع ==================== -->
<div class="head_link">
    <a href="#" onclick="goback()"  class="back-link">
        <i class="fas fa-arrow-right"></i>
        <span>العودة</span>
    </a>
    <div class="date-badge">
        <i class="far fa-calendar-alt ml-1"></i> <?php echo $today_date; ?>
    </div>
</div>

<div class="container">
    
    <h1><i class="fas fa-shopping-cart" style="color: #2196F3; margin-left: 10px;"></i> فاتورة مبيعات جديدة</h1>
    
    <form method="post" id="salesForm">
        
        <!-- ==================== رأس الفاتورة ==================== -->
        <div class="invoice-header">
            <!-- رقم الفاتورة -->
            <div class="header-item">
                <label>رقم الفاتورة</label>
                <input type="text" name="invoice_number" value="<?php echo $next_invoice_number; ?>" readonly>
            </div>
            <div class="header-item">
                <label>user_id</label>
                
                <input type="text" name="user_id" value="" readonly>
            </div>
            
            <!-- التاريخ -->
            <div class="header-item">
                <label>تاريخ الفاتورة</label>
                <input type="date" name="invoice_date" value="<?php echo $today_date; ?>" required>
            </div>
            
            <!-- اسم العميل (يمتد على عمودين) -->
            <div class="header-item col-span-2">
                <label>اسم العميل</label>
                <div style="position: relative;">
                    <input type="text" name="customer_name" placeholder="أدخل اسم العميل" value="عميل نقدي" required>
                    <i class="fas fa-user" style="position: absolute; left: 10px; top: 12px; color: #999;"></i>
                </div>
            </div>
            
            <!-- رقم هاتف العميل (يمتد على عمودين) -->
            <div class="header-item col-span-2">
                <label>رقم هاتف العميل</label>
                <div style="position: relative;">
                    <input type="text" name="customer_phone" placeholder="أدخل رقم هاتف العميل">
                    <i class="fas fa-phone" style="position: absolute; left: 10px; top: 12px; color: #999;"></i>
                </div>
            </div>
            
            <!-- طريقة الدفع -->
            <div class="header-item">
                <label>طريقة الدفع</label>
                <select name="payment_method">
                    <option value="نقدي" selected>نقدي</option>
                    <option value="بطاقة">بطاقة</option>
                    <option value="تحويل">تحويل بنكي</option>
                    <option value="شيك">شيك</option>
                </select>
            </div>
            
            <!-- حالة الدفع (ستتغير تلقائياً) -->
            <div class="header-item">
                <label>حالة الدفع</label>
                <input type="text" id="payment_status_display" value="غير مدفوعة" readonly style="background:#f0f0f0; font-weight:bold;">
                <input type="hidden" name="payment_status" id="payment_status" value="غير مدفوعة">
            </div>
            
            <!-- ===== الحقول الجديدة ===== -->
            <!-- المبلغ المسلم -->
            <div class="header-item">
                <label>المبلغ المسلم</label>
                <input type="number" name="paid_amount" id="paid_amount" 
                value="0"
                min="0" max="<?php echo $grand_total; ?>" step="1" class="paid-field"  
                oninput="if(this.value > parseFloat(document.getElementById('grand_total').value)){this.value = parseFloat(document.getElementById('grand_total').value) 
                alert('⚠️ المبلغ المسلم لا يمكن أن يكون أكبر من إجمالي الفاتورة');
                } updatePaymentInfo()">
            </div>
            
            <!-- المبلغ المتبقي -->
            <div class="header-item">
                <label>المبلغ المتبقي</label>
                <input type="number" name="remaining_amount" id="remaining_amount" value="0" min="0" step="0.01" class="remaining-field" readonly>
            </div>
        </div>
        
        <!-- ==================== شريط الأدوات ==================== -->
        <div class="toolbar">
            <div>
                <button type="button" onclick="addRow()" class="btn btn-primary" style="padding: 5px 10px;">
                    <i class="fas fa-plus"></i> إضافة منتج
                </button>
            </div>
            <div class="shortcuts">
                <span>F2</span> إضافة
                <span>F9</span> حفظ
                <span>Del</span> حذف
            </div>
        </div>
        
        <!-- ==================== جدول المنتجات ==================== -->
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>اسم المنتج</th>
                    <th>الوحدة</th>
                    <th>الكمية</th>
                    <th>السعر</th>
                    <th>الخصم</th>
                    <th>الإجمالي</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="productsTableBody">
                <!-- الصف الأول -->
                <tr id="row_0">
                    <td class="text-center">1</td>
                    <td>
                        <div class="autocomplete-wrapper">
                            <input type="text" 
                                   name="products[0][product_name]" 
                                   id="product_name_0" 
                                   placeholder="اكتب اسم المنتج للبحث..." 
                                   autocomplete="off"
                                   oninput="searchProducts(this.value, 0)"
                                   onfocus="if(this.value.length>=2) searchProducts(this.value, 0)">
                            <div class="autocomplete-dropdown" id="dropdown_0"></div>
                            <input type="hidden" name="products[0][item_id]" id="item_id_0" value="">
                        </div>
                    </td>
                    <td>
                        <select name="products[0][product_unit]" id="product_unit_0">
                            <option value="كيس">كيس</option>
                            <option value="طن">طن</option>
                            <option value="لتر">لتر</option>
                            <option value="قطعة" selected>قطعة</option>
                            <option value="متر">متر</option>
                            <option value="كيلو">كيلو</option>
                        </select>
                    </td>
                    <td>
                        <input type="number" name="products[0][product_quantity]" id="product_quantity_0" value="1" min="0.1" step="0.1" oninput="calculateRow(0)">
                    </td>
                    <td>
                        <input type="number" name="products[0][product_price]" id="product_price_0" value="0" min="0" step="0.01" oninput="calculateRow(0)">
                    </td>
                    <td>
                        <input type="number" name="products[0][product_discount]" id="product_discount_0" value="0" min="0" step="0.01" oninput="calculateRow(0)">
                    </td>
                    <td>
                        <input type="text" name="products[0][product_total]" id="product_total_0" readonly style="background:#f5f5f5;">
                    </td>
                    <td class="text-center">
                        <button type="button" onclick="removeRow(0)" class="delete-btn">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <!-- ==================== الملخص ==================== -->
        <div class="summary-section">
            <div>
                <label>ملاحظات الفاتورة</label>
                <textarea name="notes" rows="4" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:4px;" placeholder="أدخل أي ملاحظات إضافية..."></textarea>
            </div>
            
            <div class="totals">
                <h3 style="margin-top:0;">ملخص الفاتورة</h3>
                <div style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #ddd;">
                    <span>الإجمالي الفرعي:</span>
                    <span id="display_subtotal">0.00</span>
                </div>
                <div style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #ddd;">
                    <span>إجمالي الخصم:</span>
                    <span id="display_discount" style="color:#f44336;">0.00</span>
                </div>
                <div style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #ddd;">
                    <span>الضريبة (15%):</span>
                    <span id="display_tax" style="color:#2196F3;">0.00</span>
                </div>
                <div style="display:flex; justify-content:space-between; padding:10px 0; font-size:18px; font-weight:bold;">
                    <span>الإجمالي النهائي:</span>
                    <span id="display_grand_total" style="color:#4CAF50;">0.00</span>
                </div>
                
                <input type="hidden" name="subtotal" id="subtotal" value="0">
                <input type="hidden" name="total_discount" id="total_discount" value="0">
                <input type="hidden" name="total_tax" id="total_tax" value="0">
                <input type="hidden" name="grand_total" id="grand_total" value="0">
                
                <div style="margin-top:15px; padding:10px; background:#e8f5e9; border-radius:4px; font-size:13px; color:#2e7d32;">
                    <i class="fas fa-info-circle"></i> سيتم خصم المنتجات من المخزون تلقائياً
                </div>
            </div>
        </div>
        
        <!-- ==================== أزرار التحكم ==================== -->
        <div class="footer-actions">
            <button type="button" onclick="resetForm()" class="btn btn-danger">
                <i class="fas fa-redo-alt"></i> إعادة تعيين
            </button>
            <div>
                <button type="button" onclick="window.location.href='invoice_page.php'" class="btn btn-warning" style="margin-left:10px;">
                    <i class="fas fa-times"></i> إلغاء
                </button>
                <button type="submit" name="save_invoice" class="btn btn-success">
                    <i class="fas fa-save"></i> حفظ الفاتورة
                </button>
            </div>
        </div>
        
    </form>
</div>

<script>
    function goback(){
        window.history.back();
    }
    let rowCounter = 1;
    let searchTimeout = null;
    
    // ==================== البحث عن المنتجات ====================
    function searchProducts(query, rowId) {
        if (query.length < 2) {
            hideDropdown(rowId);
            return;
        }
        
        showLoading(rowId);
        
        if (searchTimeout) clearTimeout(searchTimeout);
        
        searchTimeout = setTimeout(() => {
            fetch(`?search_products=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(products => {
                    showSuggestions(products, rowId);
                })
                .catch(error => {
                    console.error('خطأ في البحث:', error);
                    hideDropdown(rowId);
                });
        }, 300);
    }
    
    function showSuggestions(products, rowId) {
        const dropdown = document.getElementById(`dropdown_${rowId}`);
        if (!dropdown) return;
        
        if (products.length === 0) {
            dropdown.innerHTML = '<div class="autocomplete-item" style="text-align:center;">لا توجد نتائج</div>';
            dropdown.classList.add('show');
            return;
        }
        
        let html = '';
        products.forEach(product => {
            html += `
                <div class="autocomplete-item" onclick="selectProduct(${rowId}, ${product.id}, '${product.name.replace(/'/g, "\\'")}', '${product.unit}', ${product.price}, ${product.stock})">
                    <div class="item-name">${product.name}</div>
                    <div class="item-code">كود: ${product.code}</div>
                    <div class="item-details">
                        <span class="item-price">💰 السعر: ${product.price} ر.ي</span>
                        <span class="item-stock">📦 المخزون: ${product.stock} ${product.unit}</span>
                    </div>
                </div>
            `;
        });
        
        dropdown.innerHTML = html;
        dropdown.classList.add('show');
    }
    
    function showLoading(rowId) {
        const dropdown = document.getElementById(`dropdown_${rowId}`);
        if (dropdown) {
            dropdown.innerHTML = '<div class="autocomplete-item" style="text-align:center;">جاري البحث...</div>';
            dropdown.classList.add('show');
        }
    }
    
    function hideDropdown(rowId) {
        const dropdown = document.getElementById(`dropdown_${rowId}`);
        if (dropdown) {
            dropdown.classList.remove('show');
        }
    }
    
    function selectProduct(rowId, productId, productName, productUnit, productPrice, productStock) {
        // تعيين اسم المنتج والمعرف والسعر
        document.getElementById(`product_name_${rowId}`).value = productName;
        document.getElementById(`item_id_${rowId}`).value = productId;
        document.getElementById(`product_price_${rowId}`).value = productPrice;
        
        // تعيين الوحدة
        const unitSelect = document.getElementById(`product_unit_${rowId}`);
        for (let i = 0; i < unitSelect.options.length; i++) {
            if (unitSelect.options[i].value === productUnit) {
                unitSelect.selectedIndex = i;
                break;
            }
        }
        
        // ========== التعديل الجديد: تعيين الحد الأقصى للكمية ==========
        const qtyInput = document.getElementById(`product_quantity_${rowId}`);
        // تعيين الحد الأقصى للكمية من المخزون
        qtyInput.max = productStock;
        // تعيين القيمة الافتراضية إلى 1 إذا كان المخزون >= 1، وإلا 0
        qtyInput.value = productStock >= 1 ? 1 : 0;
        // إضافة رسالة توضيحية للحد الأقصى (اختياري)
        qtyInput.title = `الكمية المتاحة: ${productStock} ${productUnit}`;
        // إضافة خاصية placeholder توضح المخزون
        qtyInput.placeholder = `المتاح: ${productStock}`;
        // =======================================================
        
        hideDropdown(rowId);
        calculateRow(rowId);
    }
    
    function calculateRow(rowId) {
        let qtyInput = document.getElementById(`product_quantity_${rowId}`);
        let qty = parseFloat(qtyInput.value) || 0;
        
        // الحصول على الحد الأقصى من خاصية max في حقل الإدخال
        let maxQty = parseFloat(qtyInput.max);
        
        // إذا كان هناك حد أقصى محدد (أي تم اختيار منتج) وكانت الكمية أكبر من الحد الأقصى
        if (!isNaN(maxQty) && maxQty > 0 && qty > maxQty) {
            alert(`⚠️ الكمية المطلوبة (${qty}) تتجاوز الكمية المتوفرة في المخزون (${maxQty}). سيتم تعديل الكمية إلى الحد الأقصى المتاح.`);
            qtyInput.value = maxQty;
            qty = maxQty;
        }
        
        const price = parseFloat(document.getElementById(`product_price_${rowId}`).value) || 0;
        const discount = parseFloat(document.getElementById(`product_discount_${rowId}`).value) || 0;
        
        const subtotal = qty * price;
        const total = Math.max(0, subtotal - discount);
        
        document.getElementById(`product_total_${rowId}`).value = total.toFixed(2);
        
        calculateTotals();
    }
    
    function calculateTotals() {
        let subtotal = 0;
        let totalDiscount = 0;
        let grandTotal = 0;
        
        const rows = document.querySelectorAll('#productsTableBody tr');
        
        rows.forEach(row => {
            const qty = parseFloat(row.querySelector('input[name*="[product_quantity]"]').value) || 0;
            const price = parseFloat(row.querySelector('input[name*="[product_price]"]').value) || 0;
            const discount = parseFloat(row.querySelector('input[name*="[product_discount]"]').value) || 0;
            
            const rowSubtotal = qty * price;
            const rowTotal = Math.max(0, rowSubtotal - discount);
            
            subtotal += rowSubtotal;
            totalDiscount += discount;
            grandTotal += rowTotal;
        });
        
        const taxRate = 1;
        const taxAmount = grandTotal * taxRate;
        const finalTotal = taxAmount;
        const va=0;
        
        document.getElementById('display_subtotal').textContent = subtotal.toFixed(2);
        document.getElementById('display_discount').textContent = totalDiscount.toFixed(2);
        document.getElementById('display_tax').textContent = va.toFixed(2);
        document.getElementById('display_grand_total').textContent = finalTotal.toFixed(2);
        document.getElementById('paid_amount').value = finalTotal.toFixed(2);
        
        document.getElementById('subtotal').value = subtotal.toFixed(2);
        document.getElementById('total_discount').value = totalDiscount.toFixed(2);
        document.getElementById('total_tax').value = va.toFixed(2);
        document.getElementById('grand_total').value = finalTotal.toFixed(2);
        
        // تحديث معلومات الدفع بعد حساب الإجماليات
        updatePaymentInfo();
    }
    
    // ==================== تحديث معلومات الدفع ====================
    function updatePaymentInfo() {
        const grandTotal = parseFloat(document.getElementById('display_grand_total').textContent) || 0;
        const paidAmount = parseFloat(document.getElementById('paid_amount').value) || 0;
        const remaining = Math.max(0, grandTotal - paidAmount);
        
        // تحديث المبلغ المتبقي
        document.getElementById('remaining_amount').value = remaining.toFixed(2);
        
        // تحديث حالة الدفع
        const paymentStatusField = document.getElementById('payment_status');
        const paymentStatusDisplay = document.getElementById('payment_status_display');
        
        if (paidAmount >= grandTotal && grandTotal > 0) {
            paymentStatusField.value = 'مدفوعة';
            paymentStatusDisplay.value = 'مدفوعة';
            paymentStatusDisplay.style.color = '#4CAF50';
            paymentStatusDisplay.style.fontWeight = 'bold';
        } else if (paidAmount > 0) {
            paymentStatusField.value = 'جزئي';
            paymentStatusDisplay.value = 'مدفوعة جزئياً';
            paymentStatusDisplay.style.color = '#FF9800';
            paymentStatusDisplay.style.fontWeight = 'bold';
        } else {
            paymentStatusField.value = 'غير مدفوعة';
            paymentStatusDisplay.value = 'غير مدفوعة';
            paymentStatusDisplay.style.color = '#f44336';
            paymentStatusDisplay.style.fontWeight = 'bold';
        }
        
        // تغيير لون حقل المتبقي حسب القيمة
        const remainingField = document.getElementById('remaining_amount');
        if (remaining === 0) {
            remainingField.style.color = '#4CAF50';
        } else {
            remainingField.style.color = '#f44336';
        }
    }
    
    function addRow() {
        const tbody = document.getElementById('productsTableBody');
        const newRowId = rowCounter++;
        
        const newRow = document.createElement('tr');
        newRow.id = `row_${newRowId}`;
        newRow.innerHTML = `
            <td class="text-center">${newRowId + 1}</td>
            <td>
                <div class="autocomplete-wrapper">
                    <input type="text" 
                           name="products[${newRowId}][product_name]" 
                           id="product_name_${newRowId}" 
                           placeholder="اكتب اسم المنتج للبحث..." 
                           autocomplete="off"
                           oninput="searchProducts(this.value, ${newRowId})"
                           onfocus="if(this.value.length>=2) searchProducts(this.value, ${newRowId})">
                    <div class="autocomplete-dropdown" id="dropdown_${newRowId}"></div>
                    <input type="hidden" name="products[${newRowId}][item_id]" id="item_id_${newRowId}" value="">
                </div>
            </td>
            <td>
                <select name="products[${newRowId}][product_unit]" id="product_unit_${newRowId}">
                    <option value="كيس">كيس</option>
                    <option value="طن">طن</option>
                    <option value="لتر">لتر</option>
                    <option value="قطعة" selected>قطعة</option>
                    <option value="متر">متر</option>
                    <option value="كيلو">كيلو</option>
                </select>
            </td>
            <td>
                <input type="number" name="products[${newRowId}][product_quantity]" id="product_quantity_${newRowId}" value="1" min="0.1" step="0.1" oninput="calculateRow(${newRowId})">
            </td>
            <td>
                <input type="number" name="products[${newRowId}][product_price]" id="product_price_${newRowId}" value="0" min="0" step="0.01" oninput="calculateRow(${newRowId})">
            </td>
            <td>
                <input type="number" name="products[${newRowId}][product_discount]" id="product_discount_${newRowId}" value="0" min="0" step="0.01" oninput="calculateRow(${newRowId})">
            </td>
            <td>
                <input type="text" name="products[${newRowId}][product_total]" id="product_total_${newRowId}" readonly style="background:#f5f5f5;">
            </td>
            <td class="text-center">
                <button type="button" onclick="removeRow(${newRowId})" class="delete-btn">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        
        tbody.appendChild(newRow);
        updateRowNumbers();
        calculateTotals();
    }
    
    function removeRow(rowId) {
        if (document.querySelectorAll('#productsTableBody tr').length <= 1) {
            alert('يجب أن يحتوي الجدول على صف واحد على الأقل');
            return;
        }
        
        if (confirm('هل أنت متأكد من حذف هذا المنتج؟')) {
            const row = document.getElementById(`row_${rowId}`);
            if (row) {
                row.remove();
                updateRowNumbers();
                calculateTotals();
            }
        }
    }
    
    function updateRowNumbers() {
        const rows = document.querySelectorAll('#productsTableBody tr');
        rows.forEach((row, index) => {
            row.querySelector('td:first-child').textContent = index + 1;
            row.id = `row_${index}`;
            
            const deleteBtn = row.querySelector('button');
            if (deleteBtn) {
                deleteBtn.setAttribute('onclick', `removeRow(${index})`);
            }
        });
    }
    
    function resetForm() {
        if (confirm('هل أنت متأكد من إعادة تعيين النموذج؟')) {
            location.reload();
        }
    }
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'F2' || e.keyCode === 113) {
            e.preventDefault();
            addRow();
        }
        if (e.key === 'F9' || e.keyCode === 120) {
            e.preventDefault();
            if (document.querySelectorAll('#productsTableBody tr').length > 0) {
                document.querySelector('form').submit();
            } else {
                alert('يجب إضافة منتج واحد على الأقل');
            }
        }
    });
    
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.autocomplete-wrapper')) {
            document.querySelectorAll('.autocomplete-dropdown').forEach(dropdown => {
                dropdown.classList.remove('show');
            });
        }
    });
    
    document.addEventListener('DOMContentLoaded', function() {
        calculateTotals();
    });

    
</script>

<!-- معالجة طلب get_all_items -->
<?php
if (isset($_GET['get_all_items'])) {
    header('Content-Type: application/json');
    $sql = "SELECT 
                product_id as id, 
                product_code as code, 
                product_name as name, 
                product_unit as unit, 
                selling_price as price, 
                current_quantity as quantity 
            FROM products 
            ORDER BY product_name";
    $result = $conn->query($sql);
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    echo json_encode($items);
    exit;
}



?>

</body>
</html>