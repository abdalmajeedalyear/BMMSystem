
<?php
session_start();
include "config.php";

// توليد رقم المشتريات
function generatePurchaseNumber($conn) {
    $year = date('Y');
    $month = date('m');
    
    $sql = "SELECT MAX(purchase_id) as max_id FROM purchases";
    $result = $conn->query($sql);
    $next_id = 1;
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $next_id = ($row['max_id'] ? $row['max_id'] : 0) + 1;
    }

    return "PUR-" . $year . $month . "-" . str_pad($next_id, 4, '0', STR_PAD_LEFT);
}

$success_message = "";
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_purchase'])) {
    $conn->begin_transaction();
    
    try {
        $purchase_number = generatePurchaseNumber($conn);
        $purchase_date = $_POST['purchase_date'] ?? date('Y-m-d');
        $supplier_name = $_POST['supplier_name'] ?? 'مورد نقدي';
        $supplier_phone = $_POST['supplier_phone'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $payment_method = $_POST['payment_method'] ?? 'نقدي';
        $payment_status = $_POST['payment_status'] ?? 'غير مدفوعة';
        
        $subtotal = 0;
        $total_discount = 0;
        $grand_total = 0;
        
        $products_data = [];
        
        if (isset($_POST['products']) && is_array($_POST['products'])) {
            foreach ($_POST['products'] as $index => $product) {
                if (!empty($product['product_name'])) {
                    $quantity = floatval($product['product_quantity'] ?? 0);
                    $price = floatval($product['product_price'] ?? 0);
                    $discount = floatval($product['product_discount'] ?? 0);
                    
                    $product_subtotal = $quantity * $price;
                    $after_discount = max(0, $product_subtotal - $discount);
                    $product_total = $after_discount;
                    
                    $subtotal += $product_subtotal;
                    $total_discount += $discount;
                    $grand_total += $product_total;
                    
                    $products_data[] = [
                        'name' => $product['product_name'],
                        'unit' => $product['product_unit'] ?? 'قطعة',
                        'quantity' => $quantity,
                        'price' => $price,
                        'discount' => $discount,
                        'total' => $product_total
                    ];
                }
            }
        }
        
        $stmt_purchase = $conn->prepare("INSERT INTO purchases 
            (purchase_number, purchase_date, supplier_name, supplier_phone, subtotal, total_discount, grand_total, payment_method, payment_status, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt_purchase->bind_param(
            "sssddddsss",
            $purchase_number,
            $purchase_date,
            $supplier_name,
            $supplier_phone,
            $subtotal,
            $total_discount,
            $grand_total,
            $payment_method,
            $payment_status,
            $notes
        );
        
        if (!$stmt_purchase->execute()) {
            throw new Exception("خطأ في حفظ المشتريات: " . $stmt_purchase->error);
        }
        
        $purchase_id = $conn->insert_id;
        
        $stmt_items = $conn->prepare("INSERT INTO purchase_items 
            (purchase_id, product_name, product_unit, product_quantity, product_price, product_discount, product_total) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($products_data as $index => $product) {
            $stmt_items->bind_param(
                "issdddd",
                $purchase_id,
                $product['name'],
                $product['unit'],
                $product['quantity'],
                $product['price'],
                $product['discount'],
                $product['total']
            );
            
            if (!$stmt_items->execute()) {
                throw new Exception("خطأ في حفظ أصناف المشتريات: " . $stmt_items->error);
            }
            
            // تحديث المخزون - إضافة الكميات
            $stmt_check = $conn->prepare("SELECT item_id FROM store_items WHERE item_name = ?");
            $stmt_check->bind_param("s", $product['name']);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            
            if ($result_check && $result_check->num_rows > 0) {
                $row = $result_check->fetch_assoc();
                $item_id = $row['item_id'];
                
                $stmt_update = $conn->prepare("UPDATE store_items SET quantity = quantity + ?, cost_price = ?, updated_at = NOW() WHERE item_id = ?");
                $stmt_update->bind_param("ddi", $product['quantity'], $product['price'], $item_id);
                $stmt_update->execute();
            } else {
                // إنشاء كود فريد لكل صنف
                $unique_code = "BM-" . str_pad($purchase_id + 1000 + $index, 4, '0', STR_PAD_LEFT);
                
                // التحقق من عدم تكرار الكود
                $counter = 1;
                while (true) {
                    $check_code = $conn->prepare("SELECT COUNT(*) as count FROM store_items WHERE item_code = ?");
                    $check_code->bind_param("s", $unique_code);
                    $check_code->execute();
                    $code_result = $check_code->get_result();
                    $code_row = $code_result->fetch_assoc();
                    
                    if ($code_row['count'] == 0) {
                        break;
                    }
                    
                    $unique_code = "BM-" . str_pad($purchase_id + 1000 + $index + $counter, 4, '0', STR_PAD_LEFT);
                    $counter++;
                }
                
                $stmt_insert_item = $conn->prepare("INSERT INTO store_items (item_code, item_name, category, quantity, unit, cost_price, selling_price, min_stock_level) VALUES (?, ?, 'مواد متنوعة', ?, ?, ?, ?, 10)");
                $selling_price = $product['price'] * 1.25;
                $stmt_insert_item->bind_param("ssdsdd", $unique_code, $product['name'], $product['quantity'], $product['unit'], $product['price'], $selling_price);
                $stmt_insert_item->execute();
            }
        }
        
        if ($payment_status == 'مدفوعة') {
            $stmt_payment = $conn->prepare("INSERT INTO payments 
                (purchase_id, payment_date, payment_amount, payment_method, notes) 
                VALUES (?, ?, ?, ?, ?)");
            
            $payment_notes = "دفعة مقابل مشتريات رقم: " . $purchase_number;
            $stmt_payment->bind_param(
                "issss",
                $purchase_id,
                $purchase_date,
                $grand_total,
                $payment_method,
                $payment_notes
            );
            
            $stmt_payment->execute();
        }
        
        $conn->commit();
        
        $_SESSION['alert'] = [
            'type' => 'success',
            'message' => "✅ تم حفظ المشتريات رقم <strong>$purchase_number</strong> بنجاح!",
            'purchase_number' => $purchase_number
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

if (isset($_SESSION['alert'])) {
    if ($_SESSION['alert']['type'] == 'success') {
        $success_message = $_SESSION['alert']['message'];
    } else {
        $error_message = $_SESSION['alert']['message'];
    }
    unset($_SESSION['alert']);
}

$next_purchase_number = generatePurchaseNumber($conn);
$today_date = date('Y-m-d');
?>
<!DOCTYPE html>
<html class="light" dir="rtl" lang="ar"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css"/>
<title>إضافة مشتريات جديد</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
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
                        "display": ["Inter", "sans-serif"]
                    },
                    borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px"},
                },
            },
        }
    </script>



<style>
        body {
            font-family: 'Inter', "Noto Sans", sans-serif;
        }
        .excel-grid input {
            border: none;
            width: 100%;
            height: 100%;
            padding: 0.5rem;
            background: transparent;
        }
        .excel-grid input:focus {
            outline: 2px solid #137fec;
            background: white;
        }
        .excel-table th {
            background-color: #f0f2f4;
            border: 1px solid #dbe0e6;
            font-weight: 600;
            padding: 8px;
            text-align: right;
        }
        .excel-table td {
            border: 1px solid #dbe0e6;
            padding: 0;
        }






    /* رسائل التنبيه المتحركة */
    .alert-message {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        max-width: 500px;
        padding: 1rem 1.5rem;
        border-radius: 0.5rem;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        display: flex;
        align-items: center;
        gap: 0.75rem;
        transform: translateX(400px);
        opacity: 0;
        transition: all 0.3s ease;
    }
    
    .alert-message.show {
        transform: translateX(0);
        opacity: 1;
    }
    
    .alert-success {
        background-color: #f0fdf4;
        border: 1px solid #bbf7d0;
        color: #166534;
    }
    
    .alert-error {
        background-color: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
    }
    
    .alert-info {
        background-color: #eff6ff;
        border: 1px solid #bfdbfe;
        color: #1e40af;
    }
    
    .alert-icon {
        font-size: 1.25rem;
        flex-shrink: 0;
    }
    
    .alert-content {
        flex-grow: 1;
    }
    
    .alert-close {
        background: none;
        border: none;
        color: inherit;
        cursor: pointer;
        font-size: 1.25rem;
        padding: 0;
        margin-left: 0.5rem;
    }
    
    /* مؤشر التقدم */
    .alert-progress {
        position: absolute;
        bottom: 0;
        left: 0;
        height: 3px;
        background-color: currentColor;
        opacity: 0.3;
        border-radius: 0 0 0 0.5rem;
        width: 100%;
        animation: progressBar 5s linear forwards;
    }
    
    @keyframes progressBar {
        from { width: 100%; }
        to { width: 0%; }
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateX(400px); }
        to { opacity: 1; transform: translateX(0); }
    }
    
    @keyframes fadeOut {
        from { opacity: 1; transform: translateX(0); }
        to { opacity: 0; transform: translateX(400px); }
    }

    </style>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-[#111418] min-h-screen">
    

<!-- رسائل التنبيه -->
    <div id="alertContainer" class="fixed top-4 right-4 z-[9999] space-y-3">
        <?php if (!empty($success_message)): ?>
            <div id="successAlert" class="alert-message alert-success show">
                <div class="alert-icon">✅</div>
                <div class="alert-content"><?php echo $success_message; ?></div>
                <button onclick="closeAlert('successAlert')" class="alert-close">×</button>
                <div class="alert-progress"></div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div id="errorAlert" class="alert-message alert-error show">
                <div class="alert-icon">❌</div>
                <div class="alert-content"><?php echo $error_message; ?></div>
                <button onclick="closeAlert('errorAlert')" class="alert-close">×</button>
                <div class="alert-progress"></div>
            </div>
        <?php endif; ?>
    </div>

    <form action="" method="post">

<div class="flex flex-col h-full w-full">
<!-- Top Navigation -->
<header class="flex items-center justify-between whitespace-nowrap border-b border-solid border-b-[#f0f2f4] dark:border-b-gray-700 bg-white dark:bg-background-dark px-10 py-3 sticky top-0 z-50">
<div class="flex items-center gap-8">
<div class="flex items-center gap-4 text-[#111418] dark:text-white">
<div class="size-6 text-primary">
<span class="material-symbols-outlined">architecture</span>
</div>
<h2 class="text-lg font-bold leading-tight tracking-[-0.015em]">نظام إدارة مخازن مواد البناء</h2>
</div>
<label class="flex flex-col min-w-40 !h-10 max-w-64">
<div class="flex w-full flex-1 items-stretch rounded-lg h-full">
<div class="text-[#617589] flex border-none bg-[#f0f2f4] dark:bg-gray-800 items-center justify-center pr-4 rounded-r-lg" data-icon="search">
<span class="material-symbols-ou+tlined">search</span>
</div>
<input class="form-input flex w-full min-w-0 flex-1 resize-none overflow-hidden rounded-l-lg text-[#111418] dark:text-white focus:outline-0 focus:ring-0 border-none bg-[#f0f2f4] dark:bg-gray-800 focus:border-none h-full placeholder:text-[#617589] px-4 text-base font-normal leading-normal" placeholder="بحث سريع..." value=""/>
</div>
</label>
</div>
<div class="flex flex-1 justify-end gap-8">
<div class="flex items-center gap-9 dark:text-white">
<a class="text-sm font-medium leading-normal hover:text-primary transition-colors" href="#">الرئيسية</a>
<a class="text-sm font-medium leading-normal hover:text-primary transition-colors" href="#">المخازن</a>
<a class="text-sm font-medium leading-normal hover:text-primary transition-colors" href="#">التقارير</a>
<a class="text-sm font-medium leading-normal hover:text-primary transition-colors" href="#">الإعدادات</a>
</div>
<button class="flex min-w-[84px] max-w-[480px] cursor-pointer items-center justify-center overflow-hidden rounded-lg h-10 px-4 bg-primary text-white text-sm font-bold leading-normal tracking-[0.015em]">
<span class="truncate">تسجيل الخروج</span>
</button>
<div class="bg-center bg-no-repeat aspect-square bg-cover rounded-full size-10 border border-gray-200" data-alt="User profile avatar" style='background-image: url("https://lh3.googleusercontent.com/aida-public/AB6AXuCJ4mRCy15oTHE5ng08gVHay6Hl0rW5T8cZ-9ztMYHm7xSxTmVwfxaq-B7oBwH9cuqS6d7BsTOMBfpb-8rJ4tWAMAsGR456iKxJTGuYequf8dVHxNjlhvrwi8ZFQLgBQoCzFH0KrnInDABlkuvEI1t6InmRsy35WJX9JjCs1b70-51plBd8H1P8v1hcIZGzANBSJkYrvEbKN9cuqe-TweNwUcygeBpyDP_RdfBp4YBItKoAvfYfs-Jq-JSyxc88pPS3BR_OYBCvkg");'>

</div>
</div>
</header>
<div class="flex flex-1">
<!-- Sidebar Navigation -->
<aside class="w-64 bg-white dark:bg-background-dark border-l border-gray-200 dark:border-gray-700 flex flex-col justify-between p-4 sticky top-[65px] h-[calc(100vh-65px)]">
<div class="flex flex-col gap-6">
<div class="flex flex-col px-3">
<h1 class="text-[#111418] dark:text-white text-base font-bold leading-normal">فرع التجزئة الرئيسي</h1>
<p class="text-[#617589] text-xs font-normal leading-normal">لوحة تحكم المبيعات</p>
</div>
<nav class="flex flex-col gap-1">
<a class="flex items-center gap-3 px-3 py-2 rounded-lg text-[#111418] dark:text-white hover:bg-[#f0f2f4] dark:hover:bg-gray-800 transition-colors" href="index.php">
<span class="material-symbols-outlined">dashboard</span>
<span class="text-sm font-medium">لوحة التحكم</span>
</a>
<a class="flex items-center gap-3 px-3 py-2 rounded-lg bg-primary/10 text-primary" href="">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1">receipt_long</span>
<span class="text-sm font-medium">فواتير المبيعات</span>
</a>
<a class="flex items-center gap-3 px-3 py-2 rounded-lg text-[#111418] dark:text-white hover:bg-[#f0f2f4] dark:hover:bg-gray-800 transition-colors" href="#">
<span class="material-symbols-outlined">shopping_cart</span>
<span class="text-sm font-medium">المشتريات</span>
</a>
<a class="flex items-center gap-3 px-3 py-2 rounded-lg text-[#111418] dark:text-white hover:bg-[#f0f2f4] dark:hover:bg-gray-800 transition-colors" href="#">
<span class="material-symbols-outlined">inventory_2</span>
<span class="text-sm font-medium">الأصناف والمخزون</span>
</a>
<a class="flex items-center gap-3 px-3 py-2 rounded-lg text-[#111418] dark:text-white hover:bg-[#f0f2f4] dark:hover:bg-gray-800 transition-colors" href="#">
<span class="material-symbols-outlined">group</span>
<span class="text-sm font-medium">العملاء والموردين</span>
</a>
</nav>
</div>
<div class="p-3 bg-[#f0f2f4] dark:bg-gray-800 rounded-lg">
<p class="text-xs text-[#617589] mb-1">حالة النظام</p>
<div class="flex items-center gap-2">
<div class="size-2 rounded-full bg-green-500"></div>
<span class="text-xs font-medium dark:text-white">متصل - مزامنة سحابية</span>
</div>
</div>
</aside>


<!-- Main Content Area -->
<main class="flex-1 p-6 overflow-y-auto">
<div class="max-w-6xl mx-auto space-y-6">
<!-- Page Heading and Primary Actions -->
<div class="flex flex-wrap items-center justify-between gap-4">
<div class="flex items-center gap-3">
<span class="material-symbols-outlined text-4xl text-primary">add_shopping_cart</span>
<h1 class="text-[#111418] dark:text-white text-3xl font-black leading-tight tracking-[-0.033em]">فاتورة مبيعات جديدة</h1>
</div>
<div class="flex gap-2">
<button class="flex min-w-[100px] cursor-pointer items-center justify-center rounded-lg h-10 px-4 bg-[#f0f2f4] dark:bg-gray-800 text-[#111418] dark:text-white text-sm font-bold hover:bg-[#e4e7ea] transition-colors">
<span class="material-symbols-outlined ml-2 text-sm">add</span>
<span>مشتريات جديد</span>
</button>
        <button    name="save_invoice"   type="submit" class="flex min-w-[100px] cursor-pointer items-center justify-center rounded-lg h-10 px-4 bg-primary text-white text-sm font-bold hover:bg-primary/90 transition-colors">
            <span class="material-symbols-outlined ml-2 text-sm">print</span>
            <span>حفظ المشتريات</span>
        </button>
<button  onclick="window.location.href='Store_items.php'"   class="flex min-w-[100px] cursor-pointer items-center justify-center rounded-lg h-10 px-4 bg-red-50 text-red-600 border border-red-200 text-sm font-bold hover:bg-red-100 transition-colors">
<span class="material-symbols-outlined ml-2 text-sm">cancel</span>
<span>إلغاء</span>
</button>
</div>
</div>
<!-- Invoice Header Inputs -->
<div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm grid grid-cols-1 md:grid-cols-4 gap-6">
<label class="flex flex-col gap-2">
<span class="text-sm font-semibold text-[#111418] dark:text-white">رقم المشتريات</span>
<input   name="purchase_number"  class="bg-[#f0f2f4] dark:bg-gray-900 border-none rounded-lg p-3 text-sm font-mono text-[#617589]" readonly="" type="text" value="<?php echo $next_purchase_number; ?>"/>
</label>
<label class="flex flex-col gap-2">
<span class="text-sm font-semibold text-[#111418] dark:text-white">تاريخ المشتريات</span>
<input   name="purchase_date"  value="<?php echo $today_date; ?>" required class="bg-white dark:bg-gray-900 border border-[#dbe0e6] dark:border-gray-700 rounded-lg p-3 text-sm" type="date"/>
</label>
<label class="flex flex-col gap-2 md:col-span-2">
<span class="text-sm font-semibold text-[#111418] dark:text-white">اسم المورد</span>
<div class="relative">
<input type="text" name="supplier_name" class="bg-white dark:bg-gray-900 border border-[#dbe0e6] dark:border-gray-700 rounded-lg p-3 text-sm" placeholder="أدخل اسم المورد" value="مورد نقدي" required/>
<!-- <span class="material-symbols-outlined absolute left-3 top-3 text-gray-400 pointer-events-none">search</span> -->
</div>
</label>

<label class="flex flex-col gap-2 md:col-span-2">
    
<span class="text-sm font-semibold text-[#111418] dark:text-white">رقم هاتف المورد</span>
<div class="relative">
<input type="number" name="supplier_phone" class="bg-white dark:bg-gray-900 border border-[#dbe0e6] dark:border-gray-700 rounded-lg p-3 text-sm" placeholder="أدخل رقم هاتف المورد" value="" />
</div>
<span class="material-symbols-outlined absolute left-3 top-3 text-gray-400 pointer-events-none">phone</span>
</label>


<label class="flex flex-col gap-2">
    <span class="text-sm font-semibold text-[#111418] dark:text-white">طريقة الدفع</span>
                                <select name="payment_method" class="bg-white dark:bg-gray-900 border border-[#dbe0e6] dark:border-gray-700 rounded-lg p-3 text-sm">
                                    <option value="نقدي" >نقدي</option>
                                    <option value="بطاقة">بطاقة</option>
                                    <option value="تحويل">تحويل بنكي</option>
                                    <option value="شيك">شيك</option>
                                </select>
                            </label>
                            
                            <label class="flex flex-col gap-2">
                                <span class="text-sm font-semibold text-[#111418] dark:text-white">حالة الدفع</span>
                                <select name="payment_status" class="bg-white dark:bg-gray-900 border border-[#dbe0e6] dark:border-gray-700 rounded-lg p-3 text-sm">
                                    <option value="مدفوعة" selected>مدفوعة</option>
                                    <option value="غير مدفوعة">غير مدفوعة</option>
                                    <option value="جزئي">مدفوعة جزئياً</option>
                                </select>
                            </label>
                        </div>
<!-- Toolbar -->
<div class="flex justify-between items-center bg-white dark:bg-gray-800 px-4 py-2 border-x border-t border-gray-200 dark:border-gray-700 rounded-t-lg">
<div class="flex gap-1">
<button   onclick="addNewRow()" class="p-2 hover:bg-[#f0f2f4] dark:hover:bg-gray-700 rounded-md text-primary" title="إضافة سطر">
<span class="material-symbols-outlined">add_box</span>
</button>
<button class="p-2 hover:bg-[#f0f2f4] dark:hover:bg-gray-700 rounded-md text-[#111418] dark:text-white" title="تكرار السطر">
<span class="material-symbols-outlined">content_copy</span>
</button>
<button  onclick="deleteSelectedRows()" class="p-2 hover:bg-[#f0f2f4] dark:hover:bg-gray-700 rounded-md text-red-500" title="حذف الأسطر المختارة">
<span class="material-symbols-outlined">delete_sweep</span>
</button>
</div>
<div class="text-xs text-gray-500 font-medium">
                            اختصارات: F2 للبحث، F9 للحفظ، Del للحذف
                        </div>
</div>


<!-- Excel-style Grid Table -->
<div class="overflow-x-auto bg-white dark:bg-gray-800 border-x border-b border-gray-200 dark:border-gray-700 rounded-b-lg shadow-sm">
<table class="w-full excel-table text-sm">
<thead>
<tr>
<th class="w-12 text-center">#</th>
<th class="min-w-[250px]">اسم الصنف</th>
<th class="w-24">الوحدة</th>
<th class="w-28 text-center">الكمية</th>
<th class="w-32 text-center">سعر الوحدة</th>
<th class="w-24 text-center">الخصم %</th>
<th class="w-24 text-center">الضريبة</th>
<th class="w-40 text-center bg-gray-100 dark:bg-gray-900">الإجمالي</th>
<th class="w-16 text-center">حذف</th>
</tr>
</thead>
<tbody id="productsTableBody"  class="excel-grid">
<!-- Row 1 -->
<tr   id="row_0" class="hover:bg-blue-50/30 dark:hover:bg-primary/5 transition-colors">
<td class="text-center font-bold text-gray-400">1</td>
<td><input  name="products[0][product_name]"  class="dark:text-white" type="text" placeholder="ابحث عن صنف..."  required/></td>
<td>
<select name="products[0][product_unit]" class="w-full h-full border-none bg-transparent text-sm dark:text-white focus:ring-0">
<option value="كيس">كيس</option>
<option value="طن">طن</option>
<option value="لتر">لتر</option>
<option value="قطعة">قطعة</option>
<option value="متر">متر</option>
<option value="كيلو">كيلو</option>
</select>
</td>
<td><input name="products[0][product_quantity]" class="text-center font-medium dark:text-white" type="number" placeholder="5"  onchange="calculateRow(0)" onkeyup="calculateRow(0)"/></td>
<td><input name="products[0][product_price]" class="text-center dark:text-white" type="number" placeholder="$500" onchange="calculateRow(0)" onkeyup="calculateRow(0)"/></td>
<td><input name="products[0][product_discount]" class="text-center text-red-500" type="number" placeholder="$80"   onchange="calculateRow(0)" onkeyup="calculateRow(0)"/></td>
<td><input name="products[0][product_tax_rate]" class="text-center text-gray-500 dark:text-gray-400" readonly="" type="text" placeholder="%0" /></td>
<td class="bg-gray-50 dark:bg-gray-900/50"><input name="products[0][product_total]" class="text-center font-bold text-[#111418] dark:text-white" readonly="" type="text" value=""/></td>
<td class="py-3 px-4 text-center">
    <button type="button" onclick="removeRow(0)" class="text-red-500 hover:text-red-700 text-sm delete-btn" title="حذف الصف">
                                                <i class="fas fa-trash"></i>
                                            </button>
</td>
</tr>

</tbody>
</table>
</div>


<!-- Invoice Summary -->
<div class="flex flex-col md:flex-row gap-6 justify-between items-start">
<!-- Notes Section -->
<div class="w-full md:w-1/2 space-y-2">
<span class="text-sm font-semibold text-[#111418] dark:text-white">ملاحظات الفاتورة</span>
<textarea     name="notes"      class="w-full bg-white dark:bg-gray-800 border border-[#dbe0e6] dark:border-gray-700 rounded-lg p-3 text-sm focus:ring-primary focus:border-primary" placeholder="أدخل أي ملاحظات إضافية هنا..." rows="4"></textarea>
</div>
<!-- Calculations Section -->
<div class="w-full md:w-1/3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm p-6 space-y-4">
<div class="flex justify-between items-center text-sm">
<span class="text-[#617589]">الإجمالي الفرعي:</span>
<span id="subtotal" class="font-semibold text-[#111418] dark:text-white">0.00 ر.ي</span>
</div>
<div class="flex justify-between items-center text-sm">
<span class="text-[#617589]">إجمالي الخصم:</span>
<span id="totalDiscount" class="font-semibold text-red-500">0.00 ر.ي</span>
</div>
<div class="flex justify-between items-center text-sm">
<span class="text-[#617589]">إجمالي الضريبة (15%):</span>
<span id="totalTax" class="font-semibold text-[#111418] dark:text-white">0.00 ر.ي</span>
</div>
<div class="h-px bg-gray-200 dark:bg-gray-700 my-2"></div>
<div class="flex justify-between items-center">
<span class="text-lg font-bold text-[#111418] dark:text-white">المبلغ المستحق النهائي:</span>
<span id="grandTotal" class="text-2xl font-black text-primary">0.00 ر.ي</span>
</div>
<div class="pt-4">
<div class="flex items-center gap-2 p-3 bg-primary/10 rounded-lg text-primary text-xs font-medium border border-primary/20">
<span class="material-symbols-outlined text-base">info</span>
<span>سيتم خصم الكميات تلقائياً من المستودع الرئيسي عند الحفظ.</span>
</div>
</div>
</div>
</div>
</div>
</main>
</div>
</div>
</form>



<script>
        let rowCounter = 1;

        function addNewRow() {
            const tableBody = document.getElementById('productsTableBody');
            const newRowId = rowCounter++;
            
            const newRow = document.createElement('tr');
            newRow.id = `row_${newRowId}`;
            newRow.className = 'hover:bg-blue-50/30 dark:hover:bg-primary/5 transition-colors';
            
            newRow.innerHTML = `
                <td class="text-center font-bold text-gray-400">${newRowId + 1}</td>
                <td>
                    <input name="products[${newRowId}][product_name]" class="dark:text-white w-full" type="text" placeholder="ابحث عن صنف..." required/>
                </td>
                <td>
                    <select name="products[${newRowId}][product_unit]" class="w-full h-full border-none bg-transparent text-sm dark:text-white focus:ring-0">
                        <option value="كيس">كيس</option>
                        <option value="طن">طن</option>
                        <option value="لتر">لتر</option>
                        <option value="قطعة">قطعة</option>
                        <option value="متر">متر</option>
                        <option value="كيلو">كيلو</option>
                    </select>
                </td>
                <td>
                    <input name="products[${newRowId}][product_quantity]" class="text-center font-medium dark:text-white w-full" type="number" placeholder="5" value="1" onchange="calculateRow(${newRowId})" onkeyup="calculateRow(${newRowId})"/>
                </td>
                <td>
                    <input name="products[${newRowId}][product_price]" class="text-center dark:text-white w-full" type="number" placeholder="$500" value="0" onchange="calculateRow(${newRowId})" onkeyup="calculateRow(${newRowId})"/>
                </td>
                <td>
                    <input name="products[${newRowId}][product_discount]" class="text-center text-red-500 w-full" type="number" placeholder="$80" value="0" onchange="calculateRow(${newRowId})" onkeyup="calculateRow(${newRowId})"/>
                </td>
                <td>
                    <input name="products[${newRowId}][product_tax_rate]" class="text-center text-gray-500 dark:text-gray-400 w-full" readonly type="text" value="0%"/>
                </td>
                <td class="bg-gray-50 dark:bg-gray-900/50">
                    <input name="products[${newRowId}][product_total]" class="text-center font-bold text-[#111418] dark:text-white w-full" readonly type="text" value="0.00"/>
                </td>
                <td class="text-center p-1">
                    <button type="button" onclick="removeRow(${newRowId})" class="text-red-500 hover:text-red-700 text-sm delete-btn" title="حذف الصف">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            
            tableBody.appendChild(newRow);
            updateRowNumbers();
            
            const inputs = newRow.querySelectorAll('input[type="number"]');
            inputs.forEach(input => {
                input.addEventListener('input', () => calculateRow(newRowId));
            });
            
            calculateRow(newRowId);
        }

        function removeRow(rowId) {
            if (confirm("هل أنت متأكد من حذف هذا الصف؟")) {
                const row = document.getElementById(`row_${rowId}`);
                if (row) {
                    row.remove();
                    updateRowNumbers();
                    calculateTotals();
                }
            }
        }

        function deleteSelectedRows() {
            const checkboxes = document.querySelectorAll('.row-checkbox:checked');
            if (checkboxes.length === 0) {
                alert("يرجى تحديد صف واحد على الأقل للحذف");
                return;
            }
            
            if (confirm(`هل أنت متأكد من حذف ${checkboxes.length} صفوف؟`)) {
                checkboxes.forEach(checkbox => {
                    const row = checkbox.closest('tr');
                    if (row) {
                        row.remove();
                    }
                });
                updateRowNumbers();
                calculateTotals();
            }
        }

        function updateRowNumbers() {
            const rows = document.querySelectorAll('#productsTableBody tr');
            rows.forEach((row, index) => {
                const rowNumberCell = row.querySelector('td:first-child');
                rowNumberCell.textContent = index + 1;
                
                row.id = `row_${index}`;
                
                const inputs = row.querySelectorAll('input, select');
                inputs.forEach(input => {
                    const name = input.name;
                    if (name && name.includes('[') && name.includes(']')) {
                        const fieldName = name.split('[')[2].split(']')[0];
                        input.name = `products[${index}][${fieldName}]`;
                    }
                });
                
                const deleteBtn = row.querySelector('button[onclick*="removeRow"]');
                if (deleteBtn) {
                    deleteBtn.setAttribute('onclick', `removeRow(${index})`);
                }
                
                const calcInputs = row.querySelectorAll('input[onchange*="calculateRow"], input[onkeyup*="calculateRow"]');
                calcInputs.forEach(input => {
                    if (input.hasAttribute('onchange')) {
                        input.setAttribute('onchange', `calculateRow(${index})`);
                    }
                    if (input.hasAttribute('onkeyup')) {
                        input.setAttribute('onkeyup', `calculateRow(${index})`);
                    }
                });
            });
            
            rowCounter = rows.length;
        }

        function calculateRow(rowId) {
            const quantity = parseFloat(document.querySelector(`input[name="products[${rowId}][product_quantity]"]`).value) || 0;
            const price = parseFloat(document.querySelector(`input[name="products[${rowId}][product_price]"]`).value) || 0;
            const discount = parseFloat(document.querySelector(`input[name="products[${rowId}][product_discount]"]`).value) || 0;
            
            const taxRate = 0.0; // المشتريات بدون ضريبة
            
            const subtotal = quantity * price;
            const afterDiscount = Math.max(0, subtotal - discount);
            const taxAmount = afterDiscount * taxRate;
            const total = afterDiscount + taxAmount;
            
            const totalInput = document.querySelector(`input[name="products[${rowId}][product_total]"]`);
            if (totalInput) {
                totalInput.value = total.toFixed(2);
            }
            
            calculateTotals();
        }

        function calculateTotals() {
            let subtotal = 0;
            let totalDiscount = 0;
            let totalTax = 0;
            let grandTotal = 0;
            
            const rows = document.querySelectorAll('#productsTableBody tr');
            rows.forEach((row, index) => {
                const quantity = parseFloat(row.querySelector('input[name*="product_quantity"]').value) || 0;
                const price = parseFloat(row.querySelector('input[name*="product_price"]').value) || 0;
                const discount = parseFloat(row.querySelector('input[name*="product_discount"]').value) || 0;
                
                const rowSubtotal = quantity * price;
                const afterDiscount = Math.max(0, rowSubtotal - discount);
                const taxAmount = afterDiscount * 0;
                const rowTotal = afterDiscount + taxAmount;
                
                subtotal += rowSubtotal;
                totalDiscount += discount;
                totalTax += taxAmount;
                grandTotal += rowTotal;
            });
            
            document.getElementById('subtotal').textContent = subtotal.toFixed(2) + ' ر.ي';
            document.getElementById('totalDiscount').textContent = totalDiscount.toFixed(2) + ' ر.ي';
            document.getElementById('totalTax').textContent = totalTax.toFixed(2) + ' ر.ي';
            document.getElementById('grandTotal').textContent = grandTotal.toFixed(2) + ' ر.ي';
        }

        document.addEventListener('DOMContentLoaded', function() {
            calculateRow(0);
            calculateTotals();
            
            const alerts = document.querySelectorAll('.alert-message.show');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.classList.add('show');
                }, 100);
                
                setTimeout(() => {
                    closeAlertElement(alert);
                }, 5000);
            });
            
            document.querySelectorAll('input, select, textarea').forEach(field => {
                field.addEventListener('input', function() {
                    const alertContainer = document.getElementById('alertContainer');
                    if (alertContainer && alertContainer.children.length > 0) {
                        document.querySelectorAll('.alert-message').forEach(alert => {
                            closeAlertElement(alert);
                        });
                    }
                });
            });
        });

        function closeAlert(alertId) {
            const alert = document.getElementById(alertId);
            closeAlertElement(alert);
        }

        function closeAlertElement(alertElement) {
            if (alertElement) {
                alertElement.classList.remove('show');
                alertElement.classList.add('hide');
                
                setTimeout(() => {
                    if (alertElement.parentNode) {
                        alertElement.remove();
                    }
                }, 300);
            }
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'F2' || event.keyCode === 113) {
                event.preventDefault();
                addNewRow();
            }
            
            if (event.key === 'Delete' || event.keyCode === 46) {
                event.preventDefault();
                const activeElement = document.activeElement;
                const row = activeElement.closest('tr');
                if (row) {
                    const rowId = row.id.replace('row_', '');
                    removeRow(rowId);
                }
            }
            
            if (event.key === 'F9' || event.keyCode === 120) {
                event.preventDefault();
                const form = document.querySelector('form');
                if (form) {
                    const hasProducts = document.querySelectorAll('#productsTableBody tr').length > 0;
                    if (hasProducts) {
                        form.submit();
                    } else {
                        alert('يجب إضافة صنف واحد على الأقل');
                    }
                }
            }
        });
    </script>


</body></html>