<?php
session_start();
include "config.php";

// ==================== جلب قائمة الموردين ====================
$suppliers_list = $conn->query("SELECT supplier_id, supplier_name FROM suppliers ORDER BY supplier_name ASC");

// ==================== البحث عن المنتجات للاقتراحات ====================
if (isset($_GET['search_products'])) {
    header('Content-Type: application/json');
    $search = $_GET['search_products'];
    
    if (strlen($search) < 2) {
        echo json_encode([]);
        exit;
    }
    
    $search_term = "%$search%";
    
    $stmt = $conn->prepare("SELECT 
                                product_id, 
                                product_code, 
                                product_name, 
                                product_unit, 
                                purchase_price, 
                                selling_price, 
                                current_quantity 
                            FROM products 
                            WHERE product_name LIKE ? OR product_code LIKE ? 
                            LIMIT 10");
    $stmt->bind_param("ss", $search_term, $search_term);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'id' => $row['product_id'],
            'code' => $row['product_code'],
            'name' => $row['product_name'],
            'unit' => $row['product_unit'],
            'price' => $row['purchase_price'],
            'selling_price' => $row['selling_price'],
            'stock' => $row['current_quantity']
        ];
    }
    
    echo json_encode($products);
    exit;
}

// ==================== معالجة إضافة الأصناف مباشرة ====================
$success_message = "";
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_direct_items'])) {
    
    // التحقق من اختيار المورد
    $supplier_id = intval($_POST['supplier_id'] ?? 0);
    $new_supplier_name = $conn->real_escape_string($_POST['new_supplier_name'] ?? '');
    
    // إذا تم اختيار "إضافة مورد جديد"
    if ($supplier_id == -1 && !empty($new_supplier_name)) {
        // إضافة المورد الجديد
        $insert_supplier = "INSERT INTO suppliers (supplier_name, created_at) VALUES ('$new_supplier_name', NOW())";
        if ($conn->query($insert_supplier)) {
            $supplier_id = $conn->insert_id;
        } else {
            $_SESSION['alert'] = [
                'type' => 'error',
                'message' => "❌ خطأ في إضافة المورد الجديد: " . $conn->error
            ];
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    } elseif ($supplier_id <= 0) {
        $_SESSION['alert'] = [
            'type' => 'error',
            'message' => "❌ يجب اختيار مورد"
        ];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    // الحصول على اسم المورد
    $supplier_info = $conn->query("SELECT supplier_name FROM suppliers WHERE supplier_id = $supplier_id")->fetch_assoc();
    $supplier_name = $supplier_info['supplier_name'];
    
    // التحقق من وجود منتجات
    $has_products = false;
    $products_to_add = [];
    
    if (isset($_POST['products']) && is_array($_POST['products'])) {
        foreach ($_POST['products'] as $product) {
            if (!empty($product['product_name']) && !empty($product['product_quantity']) && $product['product_quantity'] > 0) {
                $has_products = true;
                
                $products_to_add[] = [
                    'name' => $conn->real_escape_string($product['product_name']),
                    'unit' => $conn->real_escape_string($product['product_unit'] ?? 'قطعة'),
                    'qty' => floatval($product['product_quantity']),
                    'price' => floatval($product['purchase_price']),
                    'selling_price' => floatval($product['selling_price']),
                    'category' => $conn->real_escape_string($product['category'] ?? 'مواد متنوعة')
                ];
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
    
    // بدء المعاملة
    $conn->begin_transaction();
    
    try {
        $added_count = 0;
        $updated_count = 0;
        
        foreach ($products_to_add as $product) {
            
            // التحقق من وجود المنتج بالاسم
            $check = $conn->query("SELECT product_id, current_quantity FROM products WHERE product_name = '{$product['name']}' AND product_unit = '{$product['unit']}'");
            
            // التحقق من وجود المنتج بنفس الاسم والوحدة
$check = $conn->query("SELECT product_id, current_quantity FROM products WHERE product_name = '{$product['name']}' AND product_unit = '{$product['unit']}'");

            if ($check && $check->num_rows > 0) {
                // المنتج موجود بنفس الاسم والوحدة - تحديث الكمية
                $row = $check->fetch_assoc();
                $existing_id = $row['product_id'];
                $old_quantity = $row['current_quantity'];
                $new_quantity = $old_quantity + $product['qty'];
                
                $update = "UPDATE products SET 
                        current_quantity = $new_quantity,
                        updated_at = NOW() 
                        WHERE product_id = $existing_id";
                
                if (!$conn->query($update)) {
                    throw new Exception("خطأ في تحديث المخزون");
                }
                $updated_count++;
                
            } else {
                // التحقق من وجود المنتج بالاسم فقط (لإظهار رسالة مناسبة)
                $check_name_only = $conn->query("SELECT product_id, product_unit FROM products WHERE product_name = '{$product['name']}'");
                
                if ($check_name_only && $check_name_only->num_rows > 0) {
                    // المنتج موجود ولكن بوحدة مختلفة
                    $existing_product = $check_name_only->fetch_assoc();
                    $existing_unit = $existing_product['product_unit'];
                    
                    // رسالة تنبيه سيتم تخزينها في سجل الأخطاء
                    error_log("منتج موجود بوحدة مختلفة: {$product['name']} - الوحدة الحالية: {$existing_unit}، الوحدة الجديدة: {$product['unit']} - سيتم إنشاء منتج جديد");
                }
                
                // إنشاء منتج جديد (سواء كان غير موجود أو موجود بوحدة مختلفة)
                $unique_code = "DIR-" . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // التحقق من عدم تكرار الكود
                $counter = 1;
                while (true) {
                    $check_code = $conn->query("SELECT COUNT(*) as count FROM products WHERE product_code = '$unique_code'");
                    $code_row = $check_code->fetch_assoc();
                    
                    if ($code_row['count'] == 0) break;
                    
                    $unique_code = "DIR-" . date('Y') . str_pad(rand(1, 9999) + $counter, 4, '0', STR_PAD_LEFT);
                    $counter++;
                }
                
                $insert = "INSERT INTO products 
                        (product_code, product_name, product_category, current_quantity, product_unit, purchase_price, selling_price, min_quantity, created_at) 
                        VALUES ('$unique_code', '{$product['name']}', '{$product['category']}', {$product['qty']}, '{$product['unit']}', {$product['price']}, {$product['selling_price']}, 10, NOW())";
                
                if (!$conn->query($insert)) {
                    throw new Exception("خطأ في إضافة منتج جديد: " . $conn->error);
                }
                $added_count++;
            }
        }
        
        // تأكيد المعاملة
        $conn->commit();
        
        $message = "✅ تم إضافة المنتجات إلى المخزون بنجاح! ";
        $message .= "(المورد: $supplier_name) ";
        if ($added_count > 0) $message .= "تم إضافة $added_count منتج جديد. ";
        if ($updated_count > 0) $message .= "تم تحديث كمية $updated_count منتج موجود.";
        
        $_SESSION['alert'] = [
            'type' => 'success',
            'message' => $message
        ];
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['alert'] = [
            'type' => 'error',
            'message' => "❌ حدث خطأ: " . $e->getMessage()
        ];
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
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

$today_date = date('Y-m-d');
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة أصناف مباشرة - نظام المخازن</title>
    
    <!-- Font Awesome محلي -->
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">
    
    <!-- ملف CSS الرئيسي -->
    <link rel="stylesheet" href="Style/reports.css">
    
    <style>
        /* تنسيقات إضافية */
        body { 
            font-family: 'Tahoma', sans-serif; 
            background: #f5f5f5; 
            margin: 0; 
            padding: 20px; 
           
        }
        
        .layout {   
            margin-right:-150px;
            width: 100%;
            min-height: 100vh;
        }
        
        .container { 
            max-width: 1200px;
            margin-top: 25px;
            margin-right: 90px;
            background: white; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }
        
        h1 { 
            color: #333; 
            margin-bottom: 20px; 
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
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
        
        .btn { 
            padding: 10px 20px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            font-weight: bold; 
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-primary { 
            background: #2196F3; 
            color: white; 
        }
        
        .btn-primary:hover { 
            background: #1976D2; 
        }
        
        .btn-success { 
            background: #4CAF50; 
            color: white; 
        }
        
        .btn-success:hover { 
            background: #388E3C; 
        }
        
        .btn-danger { 
            background: #f44336; 
            color: white; 
        }
        
        .btn-danger:hover { 
            background: #d32f2f; 
        }
        
        .btn-warning { 
            background: #ff9800; 
            color: white; 
        }
        
        .btn-warning:hover { 
            background: #f57c00; 
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
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
        
        .delete-btn { 
            background: none; 
            border: none; 
            color: #f44336; 
            cursor: pointer; 
            font-size: 16px; 
        }
        
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
        
        .footer-actions { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-top: 20px; 
            padding-top: 20px; 
            border-top: 1px solid #ddd; 
        }
        
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
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: white;
            padding: 10px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-decoration: none;
            color: #333;
            margin-right: 90px;
            margin-bottom: -20px;
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
            margin-left: 150px;
            margin-bottom: -20px;
            border: 3px solid #3a94dd;
        }
        
        .head_link {
            display: flex;
            align-items: center;
            width: 100%;
        }
        
        /* تنسيق خاص لحقول الأسعار */
        .price-purchase {
            border: 1px solid #2196F3 !important;
        }
        
        .price-selling {
            border: 2px solid #4CAF50 !important;
            font-weight: bold;
        }
        
        .info-note {
            background: #fff3cd;
            border-right: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            color: #856404;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-note i {
            font-size: 20px;
        }
        
        /* تنسيق قسم المورد */
        .supplier-section {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .supplier-section h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .supplier-section h3 i {
            color: #4CAF50;
        }
        
        .supplier-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 15px;
        }
        
        .supplier-select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .new-supplier-input {
            display: none;
            margin-top: 10px;
        }
        
        .new-supplier-input.active {
            display: block;
        }
        
        .new-supplier-input input {
            width: 100%;
            padding: 12px;
            border: 2px solid #4CAF50;
            border-radius: 4px;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .container {
                margin-right: 10px;
                margin-left: 10px;
            }
            
            .head_link {
                flex-direction: column;
                gap: 10px;
            }
            
            .date-badge {
                margin-left: 0;
                margin-right: 0;
            }
            
            .summary-section,
            .supplier-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body >
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
                <a href="add_direct_items.php" class="nav-item active">
                    <i class="fas fa-plus-circle"></i>
                    <span>إضافة مباشرة</span>
                </a>
                <a href="invoice_page.php" class="nav-item">
                    <i class="fas fa-list"></i>
                    <span>المبيعات</span>
                </a>
                <a href="purchases_page.php" class="nav-item">
                    <i class="fas fa-truck-loading"></i>
                    <span>المشتريات</span>
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
                    <i class="fas fa-handshake"></i>
                    <span>الموردين</span>
                </a>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <i class="fas fa-user-circle"></i>
                    <span><?php echo $_SESSION['username'] ?? 'مدير المخزون'; ?></span>
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>تسجيل الخروج</span>
                </a>
            </div>
        </aside> -->
        
        <!-- ==================== المحتوى الرئيسي ==================== -->
        <main class="main-content " >
           
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
            
            <div class="head_link">
                <a href="Store_items.php" class="back-link">
                    <i class="fas fa-arrow-right"></i>
                    <span>العودة للمخزون</span>
                </a>
                <div class="date-badge">
                    <i class="far fa-calendar-alt ml-1"></i> <?php echo $today_date; ?>
                </div>
            </div>
            
            <div class="container">
                
                <h1>
                    <i class="fas fa-boxes" style="color: #4CAF50;"></i>
                    إضافة أصناف مباشرة إلى المخزون
                </h1>
                
                <div class="info-note">
                    <i class="fas fa-info-circle"></i>
                    <span>هذه الصفحة لإضافة الأصناف الموجودة مسبقاً مباشرة إلى المخزون مع تحديد المورد.</span>
                </div>
                
                <form method="post" id="directItemsForm">
                    
                    <!-- ==================== قسم اختيار المورد ==================== -->
                    <div class="supplier-section">
                        <h3>
                            <i class="fas fa-truck"></i>
                            معلومات المورد
                        </h3>
                        <div class="supplier-grid">
                            <div>
                                <label style="font-weight: bold; display: block; margin-bottom: 8px;">اختر المورد:</label>
                                <select name="supplier_id" id="supplierSelect" class="supplier-select" onchange="toggleNewSupplier()" required>
                                    <option value="">-- اختر المورد --</option>
                                    <?php while($supplier = $suppliers_list->fetch_assoc()): ?>
                                        <option value="<?php echo $supplier['supplier_id']; ?>">
                                            <?php echo $supplier['supplier_name']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                    <option value="-1">➕ إضافة مورد جديد...</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-weight: bold; display: block; margin-bottom: 8px;">&nbsp;</label>
                                <div class="new-supplier-input" id="newSupplierInput">
                                    <input type="text" name="new_supplier_name" placeholder="أدخل اسم المورد الجديد">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ==================== شريط الأدوات ==================== -->
                    <div class="toolbar">
                        <div>
                            <button type="button" onclick="addNewRow()" class="btn btn-primary" style="padding: 5px 10px;">
                                <i class="fas fa-plus"></i> إضافة صنف
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
                                <th>اسم الصنف</th>
                                <th>الفئة</th>
                                <th>الوحدة</th>
                                <th>الكمية</th>
                                <th>سعر الشراء</th>
                                <th>سعر البيع</th>
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
                                               placeholder="اكتب اسم المنتج للبحث (للتأكد من عدم التكرار)..." 
                                               autocomplete="off"
                                               oninput="searchProducts(this.value, 0)">
                                        <div class="autocomplete-dropdown" id="dropdown_0"></div>
                                    </div>
                                </td>
                                <td>
                                    <select name="products[0][category]" id="category_0">
                                        
                                        <option value="سباكة">سباكة</option>
                                        <option value="مواد متنوعة">مواد متنوعة</option>
                                        <option value="أسمنت">أسمنت</option>
                                        <option value="حديد">حديد</option>
                                        <option value="خشب">خشب</option>
                                        <option value="دهانات">دهانات</option>
                                        <option value="أدوات صحية">أدوات صحية</option>
                                        <option value="كهرباء">كهرباء</option>
                                        <option value="عدد يدوية">عدد يدوية</option>
                                    </select>
                                </td>
                                <td>
                                    <select name="products[0][product_unit]" id="product_unit_0">
                                        <option value="حبة">حبة</option>
                                        <option value="كيس">كيس</option>
                                        <option value="قطعة">قطعة</option>
                                        <option value="طن">طن</option>
                                        <option value="لتر">لتر</option>
                                        <option value="قطعة" selected>قطعة</option>
                                        <option value="متر">متر</option>
                                        <option value="كيلو">كيلو</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="products[0][product_quantity]" id="product_quantity_0" value="1" min="0.1" step="0.1" oninput="calculateTotals()">
                                </td>
                                <td>
                                    <input type="number" name="products[0][purchase_price]" id="purchase_price_0" class="price-purchase" value="0" min="0" step="0.01" oninput="calculateTotals()">
                                </td>
                                <td>
                                    <input type="number" name="products[0][selling_price]" id="selling_price_0" class="price-selling" value="0" min="0" step="0.01" required>
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
                            <label style="font-weight: bold; display: block; margin-bottom: 10px;">
                                <i class="fas fa-sticky-note" style="color: #2196F3;"></i>
                                ملاحظات
                            </label>
                            <textarea name="notes" rows="4" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:4px;" placeholder="أدخل أي ملاحظات إضافية..."></textarea>
                        </div>
                        
                        <div class="totals">
                            <h3 style="margin-top:0; color: #2196F3;">
                                <i class="fas fa-calculator"></i>
                                ملخص الإضافة
                            </h3>
                            <div style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #ddd;">
                                <span>عدد الأصناف:</span>
                                <span id="display_items_count">1</span>
                            </div>
                            <div style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #ddd;">
                                <span>إجمالي الكميات:</span>
                                <span id="display_total_quantity">1</span>
                            </div>
                            <div style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #ddd;">
                                <span>إجمالي التكلفة:</span>
                                <span id="display_total_cost">0.00 ر.ي</span>
                            </div>
                            
                            <div style="margin-top:15px; padding:10px; background:#e8f5e9; border-radius:4px; font-size:13px; color:#2e7d32;">
                                <i class="fas fa-info-circle"></i>
                                سيتم إضافة المنتجات إلى المخزون باسم المورد المحدد
                            </div>
                        </div>
                    </div>
                    
                    <!-- ==================== أزرار التحكم ==================== -->
                    <div class="footer-actions">
                        <button type="button" onclick="resetForm()" class="btn btn-danger">
                            <i class="fas fa-redo-alt"></i> إعادة تعيين
                        </button>
                        <div>
                            <button type="button" onclick="window.location.href='Store_items.php'" class="btn btn-secondary" style="margin-left:10px;">
                                <i class="fas fa-times"></i> إلغاء
                            </button>
                            <button type="submit" name="add_direct_items" class="btn btn-success">
                                <i class="fas fa-save"></i> إضافة للمخزون
                            </button>
                        </div>
                    </div>
                    
                </form>
            </div>
        </main>
    </div>
    
    
    <script>
        let rowCounter = 1;
        let searchTimeout = null;
        
        // ==================== إظهار/إخفاء حقل المورد الجديد ====================
        function toggleNewSupplier() {
            const select = document.getElementById('supplierSelect');
            const newSupplierInput = document.getElementById('newSupplierInput');
            
            if (select.value === '-1') {
                newSupplierInput.classList.add('active');
            } else {
                newSupplierInput.classList.remove('active');
            }
        }
        
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
                dropdown.innerHTML = '<div class="autocomplete-item" style="text-align:center;">لا توجد نتائج - يمكنك إضافة صنف جديد</div>';
                dropdown.classList.add('show');
                return;
            }
            
            let html = '';
            products.forEach(product => {
                html += `
                    <div class="autocomplete-item" onclick="selectProduct(${rowId}, '${product.name.replace(/'/g, "\\'")}', ${product.price}, '${product.unit}')">
                        <div class="item-name">${product.name}</div>
                        <div class="item-code">كود: ${product.code} | الوحدة: ${product.unit}</div>
                        <div class="item-details">
                            <span class="item-price">💰 سعر الشراء: ${product.price} ر.ي</span>
                            <span class="item-stock">📦 المخزون: ${product.stock} ${product.unit}</span>
                        </div>
                        <div style="font-size: 11px; color: #999; margin-top: 5px;">
                            <i class="fas fa-exclamation-triangle" style="color: #ff9800;"></i>
                            هذا المنتج موجود بالفعل بنفس الوحدة، سيتم إضافة الكمية للمخزون الحالي
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
        
        function selectProduct(rowId, productName, purchasePrice, productUnit) {
            document.getElementById(`product_name_${rowId}`).value = productName;
            
            // اقتراح سعر الشراء
            const priceInput = document.getElementById(`purchase_price_${rowId}`);
            if (priceInput && purchasePrice > 0) {
                priceInput.value = purchasePrice;
            }
            
            // تعيين الوحدة إذا كانت موجودة في البيانات
            if (productUnit) {
                const unitSelect = document.getElementById(`product_unit_${rowId}`);
                for (let i = 0; i < unitSelect.options.length; i++) {
                    if (unitSelect.options[i].value === productUnit) {
                        unitSelect.selectedIndex = i;
                        break;
                    }
                }
            }
            
            hideDropdown(rowId);
            calculateTotals();
        }
        
        function calculateTotals() {
            let itemsCount = 0;
            let totalQuantity = 0;
            let totalCost = 0;
            
            const rows = document.querySelectorAll('#productsTableBody tr');
            
            rows.forEach(row => {
                const qty = parseFloat(row.querySelector('input[name*="[product_quantity]"]').value) || 0;
                const price = parseFloat(row.querySelector('input[name*="[purchase_price]"]').value) || 0;
                
                if (qty > 0) {
                    itemsCount++;
                    totalQuantity += qty;
                    totalCost += qty * price;
                }
            });
            
            document.getElementById('display_items_count').textContent = itemsCount;
            document.getElementById('display_total_quantity').textContent = totalQuantity;
            document.getElementById('display_total_cost').textContent = totalCost.toFixed(2) + ' ر.ي';
        }
        
        function addNewRow() {
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
                               oninput="searchProducts(this.value, ${newRowId})">
                        <div class="autocomplete-dropdown" id="dropdown_${newRowId}"></div>
                    </div>
                </td>
                <td>
                    <select name="products[${newRowId}][category]" id="category_${newRowId}">
                        <option value="مواد متنوعة">مواد متنوعة</option>
                        <option value="أسمنت">أسمنت</option>
                        <option value="حديد">حديد</option>
                        <option value="خشب">خشب</option>
                        <option value="دهانات">دهانات</option>
                        <option value="أدوات صحية">أدوات صحية</option>
                        <option value="كهرباء">كهرباء</option>
                        <option value="عدد يدوية">عدد يدوية</option>
                    </select>
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
                    <input type="number" name="products[${newRowId}][product_quantity]" id="product_quantity_${newRowId}" value="1" min="1.1" step="0.1"oninput="calculateTotals()">
                </td>
                <td>
                    <input type="number" name="products[${newRowId}][purchase_price]" id="purchase_price_${newRowId}" class="price-purchase" value="0" min="0" step="0.01" oninput="calculateTotals()">
                </td>
                <td>
                    <input type="number" name="products[${newRowId}][selling_price]" id="selling_price_${newRowId}" class="price-selling" value="0" min="0" step="0.01" required>
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
            
            if (confirm('هل أنت متأكد من حذف هذا الصنف؟')) {
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
                addNewRow();
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
        
        // ==================== قائمة جانبية قابلة للطي ====================
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('collapsed');
            document.querySelector('.main-content').classList.toggle('expanded');
        });
    </script>
</body>
</html>