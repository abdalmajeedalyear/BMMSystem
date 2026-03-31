<?php
session_start();
include "config.php";



// ==================== استقبال رقم الفاتورة من الرابط ====================
$preselected_invoice_id = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : 0;

// ==================== البحث عن الفواتير ====================
if (isset($_GET['search_invoices'])) {
    header('Content-Type: application/json');
    $search = $_GET['search_invoices'];
    
    if (strlen($search) < 2) {
        echo json_encode([]);
        exit;
    }
    
    $search_term = "%$search%";
    
    $stmt = $conn->prepare("SELECT 
                                i.invoice_id,
                                i.invoice_number,
                                i.invoice_date,
                                c.customer_name,
                                i.grand_total
                            FROM invoices i
                            LEFT JOIN customers c ON i.customer_id = c.customer_id
                            WHERE i.invoice_number LIKE ? OR c.customer_name LIKE ?
                            LIMIT 10");
    $stmt->bind_param("ss", $search_term, $search_term);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $invoices = [];
    while ($row = $result->fetch_assoc()) {
        $invoices[] = [
            'id' => $row['invoice_id'],
            'number' => $row['invoice_number'],
            'date' => $row['invoice_date'],
            'customer' => $row['customer_name'] ?? 'عميل نقدي',
            'total' => $row['grand_total']
        ];
    }
    
    echo json_encode($invoices);
    exit;
}

// ==================== الحصول على معلومات فاتورة محددة (جديد) ====================
if (isset($_GET['get_invoice_info'])) {
    header('Content-Type: application/json');
    $invoice_id = intval($_GET['get_invoice_info']);
    
    $stmt = $conn->prepare("SELECT 
                                i.invoice_id,
                                i.invoice_number,
                                i.invoice_date,
                                c.customer_name
                            FROM invoices i
                            LEFT JOIN customers c ON i.customer_id = c.customer_id
                            WHERE i.invoice_id = ?");
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'id' => $row['invoice_id'],
            'number' => $row['invoice_number'],
            'date' => $row['invoice_date'],
            'customer' => $row['customer_name'] ?? 'عميل نقدي'
        ]);
    } else {
        echo json_encode(['error' => 'الفاتورة غير موجودة']);
    }
    exit;
}

// ==================== البحث عن منتجات فاتورة معينة ====================
// ==================== البحث عن منتجات فاتورة معينة ====================
if (isset($_GET['get_invoice_items'])) {
    header('Content-Type: application/json');
    $invoice_id = intval($_GET['get_invoice_items']);
    
    $stmt = $conn->prepare("SELECT 
                                item_id,
                                product_name,
                                product_unit,
                                product_quantity,
                                product_price,
                                product_discount,
                                product_total
                            FROM invoice_items 
                            WHERE invoice_id = ?");
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'id' => $row['item_id'],
            'product_id' => $row['item_id'],
            'name' => $row['product_name'],
            'unit' => $row['product_unit'],
            'quantity' => $row['product_quantity'],
            'price' => $row['product_price'],
            'discount' => $row['product_discount'],
            'total' => $row['product_total']
        ];
    }
    
    echo json_encode($items);
    exit;
}

// ==================== توليد رقم مرتجع المبيعات ====================
function generateSalesReturnNumber($conn) {
    $year = date('Y');
    $month = date('m');
    
    $sql = "SELECT MAX(return_id) as max_id FROM sales_returns";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $next_id = ($row['max_id'] ? $row['max_id'] : 0) + 1;
    } else {
        $next_id = 1;
    }
    
    return "SR-" . $year . $month . "-" . str_pad($next_id, 4, '0', STR_PAD_LEFT);
}

// ==================== معالجة حفظ مرتجع المبيعات ====================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_sales_return'])) {
    
    $return_number = generateSalesReturnNumber($conn);
    $return_date = $conn->real_escape_string($_POST['return_date']);
    $invoice_id = intval($_POST['invoice_id']);
    $reason = $conn->real_escape_string($_POST['reason'] ?? '');
    $created_by = $_SESSION['username'] ?? 'system';
    
    // الحصول على معلومات العميل من الفاتورة
    $invoice_info = $conn->query("SELECT i.customer_id, c.customer_name 
                                   FROM invoices i 
                                   LEFT JOIN customers c ON i.customer_id = c.customer_id 
                                   WHERE i.invoice_id = $invoice_id")->fetch_assoc();
    
    $customer_id = $invoice_info['customer_id'] ?? 0;
    $customer_name = $conn->real_escape_string($invoice_info['customer_name'] ?? 'عميل نقدي');
    
    // التحقق من وجود منتجات
    $has_products = false;
    $items_to_return = [];

    // في قسم معالجة POST
    if (isset($_POST['items']) && is_array($_POST['items'])) {
        foreach ($_POST['items'] as $item) {
            if (!empty($item['product_id']) && !empty($item['return_quantity']) && $item['return_quantity'] > 0) {
                
                $original_item_id = intval($item['original_item_id'] ?? 0);
                $product_id = intval($item['product_id']);
                
                // التحقق من الكمية
                if ($original_item_id > 0) {
                    $check_quantity = $conn->query("SELECT product_quantity, product_discount, product_price FROM invoice_items WHERE item_id = $original_item_id");
                    if ($check_quantity && $check_quantity->num_rows > 0) {
                        $inv_item = $check_quantity->fetch_assoc();
                        $original_qty = $inv_item['product_quantity'];
                        $original_discount = $inv_item['product_discount'];
                        $original_price = $inv_item['product_price'];
                        $return_qty = floatval($item['return_quantity']);
                        
                        if ($return_qty > $original_qty) {
                            throw new Exception("الكمية المرتجعة ({$return_qty}) تتجاوز الكمية الأصلية ({$original_qty})");
                        }
                        
                        // حساب السعر بعد الخصم للوحدة الواحدة
                        $original_total = $original_qty * $original_price;
                        $total_after_discount = $original_total - $original_discount;
                        $unit_price_after_discount = $original_qty > 0 ? ($total_after_discount / $original_qty) : $original_price;
                        
                        // تعيين السعر للارتجاع
                        $return_price = $unit_price_after_discount;
                    }
                }
                
                $has_products = true;
                
                $items_to_return[] = [
                    'product_id' => $product_id,
                    'name' => $conn->real_escape_string($item['product_name']),
                    'unit' => $conn->real_escape_string($item['product_unit'] ?? 'قطعة'),
                    'qty' => floatval($item['return_quantity']),
                    'price' => $return_price ?? floatval($item['return_price']),
                    'discount' => 0,
                    'total' => floatval($item['return_total']),
                    'original_item_id' => $original_item_id
                ];
            }
        }
    }
    
    if (!$has_products) {
        $_SESSION['alert'] = [
            'type' => 'error',
            'message' => "❌ يجب إضافة منتج واحد على الأقل"
        ];
        header("Location: " . $_SERVER['PHP_SELF'] . "?invoice_id=" . $invoice_id);
        exit();
    }
    
    // بدء المعاملة
    $conn->begin_transaction();
    
    try {
        // حساب الإجمالي
        $total_amount = 0;
        foreach ($items_to_return as $item) {
            $total_amount += $item['total'];
        }
        
        // إدخال مرتجع المبيعات
        $stmt_return = $conn->prepare("INSERT INTO sales_returns 
            (return_number, return_date, invoice_id, customer_id, customer_name, total_amount, reason, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt_return->bind_param(
            "ssiisdss",
            $return_number,
            $return_date,
            $invoice_id,
            $customer_id,
            $customer_name,
            $total_amount,
            $reason,
            $created_by
        );
        
        if (!$stmt_return->execute()) {
            throw new Exception("خطأ في حفظ مرتجع المبيعات: " . $stmt_return->error);
        }
        
        $return_id = $conn->insert_id;
        
        // إدخال عناصر المرتجع وتحديث المخزون
        foreach ($items_to_return as $item) {
            
            $stmt_item = $conn->prepare("INSERT INTO sales_return_items 
                (return_id, product_id, product_name, product_unit, return_quantity, return_price, return_total, original_invoice_item_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt_item->bind_param(
                "iissdddi",
                $return_id,
                $item['product_id'],
                $item['name'],
                $item['unit'],
                $item['qty'],
                $item['price'],
                $item['total'],
                $item['original_item_id']
            );
            
            if (!$stmt_item->execute()) {
                throw new Exception("خطأ في حفظ عناصر المرتجع: " . $stmt_item->error);
            }
            
            // تحديث المخزون
            $update_stock = "UPDATE products SET current_quantity = current_quantity + {$item['qty']} 
                            WHERE product_name = '{$item['name']}' AND product_unit = '{$item['unit']}'";
            if (!$conn->query($update_stock)) {
                throw new Exception("خطأ في تحديث المخزون: " . $conn->error);
            }
        }
        
        // تأكيد المعاملة
        $conn->commit();
        
        $_SESSION['alert'] = [
            'type' => 'success',
            'message' => "✅ تم حفظ مرتجع المبيعات رقم <strong>$return_number</strong> بنجاح"
        ];
        
        header("Location: returns_page.php");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['alert'] = [
            'type' => 'error',
            'message' => "❌ حدث خطأ: " . $e->getMessage()
        ];
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?invoice_id=" . $invoice_id);
        exit();
    }
}

$today_date = date('Y-m-d');
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مرتجع مبيعات - نظام المخازن</title>
    
    <!-- Font Awesome محلي -->
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">
    
    <style>
        /* أكمل التنسيقات هنا كما كانت سابقاً */
        body { 
            font-family: 'Tahoma', sans-serif; 
            background: #f5f5f5; 
            margin: 0; 
            padding: 20px; 
        }
        .container { 
            max-width: 1200px;
            margin: 25px auto;
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
        .btn { 
            padding: 10px 20px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            font-weight: bold; 
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary { background: #FF9800; color: white; }
        .btn-success { background: #4CAF50; color: white; }
        .btn-danger { background: #f44336; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        
        .invoice-info {
            background: #e8f4fd;
            border: 1px solid #2196F3;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .invoice-info-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .invoice-info-item i {
            color: #2196F3;
            font-size: 20px;
        }
        
        .invoice-info-label {
            font-weight: bold;
            color: #666;
        }
        
        .invoice-info-value {
            font-size: 18px;
            font-weight: bold;
            color: #2196F3;
        }
        
        .delete-btn { 
            background: none; 
            border: none; 
            color: #f44336; 
            cursor: pointer; 
            font-size: 16px; 
        }
        
        .summary-section { 
            margin-top: 20px; 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 20px; 
        }
        
        .totals { 
            background: #fff3e0; 
            padding: 20px; 
            border: 1px solid #FF9800; 
            border-radius: 8px; 
        }
        
        .footer-actions { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-top: 20px; 
            padding-top: 20px; 
            border-top: 1px solid #ddd; 
        }
        
        .head_link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
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
        }
        
        .date-badge {
            background: #fff3e0;
            color: #FF9800;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: bold;
            border: 2px solid #FF9800;
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
    </style>
</head>
<body>

<!-- رسائل التنبيه -->
<?php if (isset($_SESSION['alert'])): ?>
    <div class="alert alert-<?php echo $_SESSION['alert']['type']; ?>" id="alertMessage">
        <i class="fas <?php echo $_SESSION['alert']['type'] == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
        <?php echo $_SESSION['alert']['message']; ?>
    </div>
    <script>
        setTimeout(() => {
            const alert = document.getElementById('alertMessage');
            if (alert) alert.remove();
        }, 5000);
    </script>
    <?php unset($_SESSION['alert']); ?>
<?php endif; ?>

<div class="head_link">
    <a href="#" onclick="goback()" class="back-link">
        <i class="fas fa-arrow-right"></i>
        <span>العودة</span>
    </a>
    <div class="date-badge">
        <i class="far fa-calendar-alt"></i> <?php echo $today_date; ?>
    </div>
</div>

<div class="container">
    
    <h1>
        <i class="fas fa-undo-alt" style="color: #FF9800;"></i>
        مرتجع مبيعات جديد
    </h1>
    
    <form method="post" id="returnForm">
        
        <!-- ==================== معلومات الفاتورة المحددة ==================== -->
        <div id="invoiceInfoContainer" style="display: none;">
            <div class="invoice-info">
                <div class="invoice-info-item">
                    <i class="fas fa-file-invoice"></i>
                    <div>
                        <span class="invoice-info-label">رقم الفاتورة:</span>
                        <span class="invoice-info-value" id="displayInvoiceNumber"></span>
                    </div>
                </div>
                <div class="invoice-info-item">
                    <i class="fas fa-user"></i>
                    <div>
                        <span class="invoice-info-label">العميل:</span>
                        <span class="invoice-info-value" id="displayCustomerName"></span>
                    </div>
                </div>
                <div class="invoice-info-item">
                    <i class="fas fa-calendar"></i>
                    <div>
                        <span class="invoice-info-label">تاريخ الفاتورة:</span>
                        <span class="invoice-info-value" id="displayInvoiceDate"></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ==================== بحث عن الفاتورة (يظهر فقط إذا لم يكن هناك invoice_id) ==================== -->
        <div class="invoice-search" id="invoiceSearchSection" <?php echo $preselected_invoice_id > 0 ? 'style="display:none;"' : ''; ?>>
            <h3 style="margin-top: 0; color: #FF9800;">البحث عن الفاتورة الأصلية</h3>
            <div class="search-row">
                <div class="form-group">
                    <label>ابحث برقم الفاتورة أو اسم العميل</label>
                    <div class="autocomplete-wrapper">
                        <input type="text" id="invoiceSearch" placeholder="اكتب للبحث..." autocomplete="off" oninput="searchInvoices(this.value)">
                        <div class="autocomplete-dropdown" id="invoiceDropdown"></div>
                    </div>
                </div>
                <input type="hidden" name="invoice_id" id="selectedInvoiceId" value="<?php echo $preselected_invoice_id; ?>">
                <button type="button" class="btn btn-primary" onclick="clearSelectedInvoice()">إلغاء التحديد</button>
            </div>
        </div>
        
        <!-- ==================== تاريخ المرتجع ==================== -->
        <div style="margin-bottom: 20px;">
            <label style="font-weight: bold;">تاريخ المرتجع</label>
            <input type="date" name="return_date" value="<?php echo $today_date; ?>" style="width: 200px;">
        </div>
        
        <!-- ==================== شريط الأدوات ==================== -->
        <div class="toolbar">
            <div>
                <button type="button" onclick="addReturnItem()" class="btn btn-primary" style="padding: 5px 10px;" id="addItemBtn" disabled>
                    <i class="fas fa-plus"></i> إضافة منتج للمرتجع
                </button>
            </div>
            <div class="shortcuts">
                <span>F2</span> إضافة
                <span>F9</span> حفظ
                <span>Del</span> حذف
            </div>
        </div>
        
        <!-- ==================== جدول منتجات المرتجع ==================== -->
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>اسم المنتج</th>
                    <th>الوحدة</th>
                    <th>الكمية المرتجعة</th>
                    <th>سعر الشراء</th>
                    <th>الخصم</th>
                    <th>الإجمالي</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="itemsTableBody">
            </tbody>
        </table>
        
        <!-- ==================== الملخص ==================== -->
        <div class="summary-section">
            <div>
                <label style="font-weight: bold; display: block; margin-bottom: 10px;">
                    <i class="fas fa-sticky-note"></i>
                    سبب المرتجع
                </label>
                <textarea name="reason" rows="4" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:4px;" placeholder="أدخل سبب المرتجع..."></textarea>
            </div>
            
            <div class="totals">
                <h3 style="margin-top:0; color: #FF9800;">
                    <i class="fas fa-calculator"></i>
                    ملخص المرتجع
                </h3>
                <div style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #ddd;">
                    <span>عدد الأصناف:</span>
                    <span id="display_items_count">0</span>
                </div>
                <div style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #ddd;">
                    <span>إجمالي الكميات:</span>
                    <span id="display_total_quantity">0</span>
                </div>
                <div style="display:flex; justify-content:space-between; padding:15px 0; font-size:18px; font-weight:bold;">
                    <span>الإجمالي النهائي:</span>
                    <span id="display_grand_total" style="color: #FF9800;">0.00 ر.ي</span>
                </div>
                
                <div style="margin-top:15px; padding:10px; background:#e8f5e9; border-radius:4px; font-size:13px; color:#2e7d32;">
                    <i class="fas fa-info-circle"></i>
                    سيتم إضافة المنتجات إلى المخزون تلقائياً
                </div>
            </div>
        </div>
        
        <!-- ==================== أزرار التحكم ==================== -->
        <div class="footer-actions">
            <button type="button" onclick="resetForm()" class="btn btn-danger">
                <i class="fas fa-redo-alt"></i> إعادة تعيين
            </button>
            <div>
                <button type="button" onclick="window.location.href='invoice_page.php'" class="btn btn-secondary" style="margin-left:10px;">
                    <i class="fas fa-times"></i> إلغاء
                </button>
                <button type="submit" name="save_sales_return" class="btn btn-success" id="saveBtn" disabled>
                    <i class="fas fa-save"></i> حفظ المرتجع
                </button>
            </div>
        </div>
        
    </form>
</div>

<script>
    function goback(){
        window.history.back();
    }
    let rowCounter = 0;
    let searchTimeout = null;
    let selectedInvoiceId = <?php echo $preselected_invoice_id; ?>;
    let originalItems = [];
    
    // ==================== تحميل بيانات الفاتورة المحددة مسبقاً ====================
    document.addEventListener('DOMContentLoaded', function() {
        if (selectedInvoiceId > 0) {
            loadPreselectedInvoice(selectedInvoiceId);
        }
    });
    
    function loadPreselectedInvoice(invoiceId) {
        console.log('جاري تحميل الفاتورة رقم:', invoiceId);
        
        // جلب منتجات الفاتورة أولاً
        fetch(`?get_invoice_items=${invoiceId}`)
            .then(response => {
                if (!response.ok) throw new Error('فشل الاتصال بالخادم');
                return response.json();
            })
            .then(items => {
                console.log('المنتجات المحملة:', items);
                
                if (items && items.length > 0) {
                    originalItems = items;
                    
                    // تفعيل الأزرار
                    document.getElementById('addItemBtn').disabled = false;
                    document.getElementById('saveBtn').disabled = false;
                    
                    // إضافة صف افتراضي
                    addReturnItem();
                    
                    // الآن نعرض معلومات الفاتورة بشكل يدوي
                    // بما أن لدينا معرف الفاتورة، يمكننا عرضه مباشرة
                    document.getElementById('displayInvoiceNumber').textContent = 'فاتورة رقم: ' + invoiceId;
                    document.getElementById('displayCustomerName').textContent = 'جاري تحميل...';
                    document.getElementById('displayInvoiceDate').textContent = 'جاري تحميل...';
                    document.getElementById('invoiceInfoContainer').style.display = 'block';
                    
                    // يمكنك إضافة استعلام لجلب معلومات العميل إذا أردت
                    fetchInvoiceInfo(invoiceId);
                    
                } else {
                    alert('لا توجد منتجات في هذه الفاتورة');
                }
            })
            .catch(error => {
                console.error('خطأ:', error);
                alert('حدث خطأ في تحميل بيانات الفاتورة');
            });
    }

    // دالة منفصلة لجلب معلومات الفاتورة (اختياري)
    function fetchInvoiceInfo(invoiceId) {
        fetch(`?get_invoice_info=${invoiceId}`)
            .then(response => response.json())
            .then(info => {
                if (info && !info.error) {
                    document.getElementById('displayInvoiceNumber').textContent = info.number;
                    document.getElementById('displayCustomerName').textContent = info.customer;
                    document.getElementById('displayInvoiceDate').textContent = info.date;
                }
            })
            .catch(error => console.error('خطأ في جلب معلومات الفاتورة:', error));
    }
    
    // ==================== البحث عن الفواتير ====================
    function searchInvoices(query) {
        if (query.length < 2) {
            document.getElementById('invoiceDropdown').classList.remove('show');
            return;
        }
        
        if (searchTimeout) clearTimeout(searchTimeout);
        
        searchTimeout = setTimeout(() => {
            fetch(`?search_invoices=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(invoices => {
                    showInvoiceSuggestions(invoices);
                })
                .catch(error => {
                    console.error('خطأ في البحث:', error);
                });
        }, 300);
    }
    
    function showInvoiceSuggestions(invoices) {
        const dropdown = document.getElementById('invoiceDropdown');
        
        if (invoices.length === 0) {
            dropdown.innerHTML = '<div class="autocomplete-item">لا توجد نتائج</div>';
            dropdown.classList.add('show');
            return;
        }
        
        let html = '';
        invoices.forEach(invoice => {
            html += `
                <div class="autocomplete-item" onclick="selectInvoice(${invoice.id}, '${invoice.number}', '${invoice.customer}', '${invoice.date}')">
                    <div><strong>${invoice.number}</strong> - ${invoice.customer}</div>
                    <div style="font-size: 12px; color: #666;">التاريخ: ${invoice.date}</div>
                </div>
            `;
        });
        
        dropdown.innerHTML = html;
        dropdown.classList.add('show');
    }
    
    function selectInvoice(id, number, customer, date) {
        selectedInvoiceId = id;
        document.getElementById('selectedInvoiceId').value = id;
        document.getElementById('invoiceSearchSection').style.display = 'none';
        
        // عرض معلومات الفاتورة
        document.getElementById('displayInvoiceNumber').textContent = number;
        document.getElementById('displayCustomerName').textContent = customer;
        document.getElementById('displayInvoiceDate').textContent = date;
        document.getElementById('invoiceInfoContainer').style.display = 'block';
        document.getElementById('invoiceDropdown').classList.remove('show');
        
        // تفعيل الأزرار وجلب المنتجات
        document.getElementById('addItemBtn').disabled = false;
        document.getElementById('saveBtn').disabled = false;
        
        loadInvoiceItems(id);
    }
    
    function clearSelectedInvoice() {
        selectedInvoiceId = 0;
        document.getElementById('selectedInvoiceId').value = '';
        document.getElementById('invoiceInfoContainer').style.display = 'none';
        document.getElementById('invoiceSearchSection').style.display = 'block';
        document.getElementById('invoiceSearch').value = '';
        document.getElementById('itemsTableBody').innerHTML = '';
        rowCounter = 0;
        originalItems = [];
        calculateTotals();
        
        document.getElementById('addItemBtn').disabled = true;
        document.getElementById('saveBtn').disabled = true;
    }
    
    function loadInvoiceItems(invoiceId) {
        fetch(`?get_invoice_items=${invoiceId}`)
            .then(response => response.json())
            .then(items => {
                originalItems = items;
            })
            .catch(error => console.error('Error:', error));
    }
    
    // ==================== إضافة صف مرتجع ====================
    // ==================== إضافة صف مرتجع ====================
    function addReturnItem() {
        if (originalItems.length === 0) {
            alert('الرجاء اختيار فاتورة أولاً');
            return;
        }
        
        const tbody = document.getElementById('itemsTableBody');
        const newRowId = rowCounter++;
        
        let options = '<option value="">اختر منتج</option>';
        originalItems.forEach(item => {
            // حساب السعر بعد الخصم لكل وحدة
            const itemTotal = parseFloat(item.total);
            const itemQty = parseFloat(item.quantity);
            const unitPriceAfterDiscount = itemQty > 0 ? (itemTotal / itemQty).toFixed(2) : item.price;
            
            options += `<option value="${item.id}" 
                                data-name="${item.name}" 
                                data-unit="${item.unit}" 
                                data-price="${item.price}" 
                                data-discount="${item.discount}"
                                data-total="${item.total}"
                                data-max-quantity="${item.quantity}"
                                data-unit-price-after-discount="${unitPriceAfterDiscount}">
                                ${item.name} (${item.unit}) - السعر بعد الخصم: ${unitPriceAfterDiscount} - الكمية المتاحة: ${item.quantity}
                            </option>`;
        });
        
        const newRow = document.createElement('tr');
        newRow.id = `row_${newRowId}`;
        newRow.innerHTML = `
            <td class="text-center">${newRowId + 1}</td>
            <td>
                <select name="items[${newRowId}][product_id]" id="product_select_${newRowId}" class="product-select" onchange="selectProduct(${newRowId})" required>
                    ${options}
                </select>
                <input type="hidden" name="items[${newRowId}][product_name]" id="product_name_${newRowId}" value="">
                <input type="hidden" name="items[${newRowId}][original_item_id]" id="original_item_id_${newRowId}" value="">
                <input type="hidden" id="max_quantity_${newRowId}" value="0">
                <input type="hidden" id="unit_price_after_discount_${newRowId}" value="0">
            </td>
            <td>
                <input type="text" name="items[${newRowId}][product_unit]" id="unit_${newRowId}" readonly style="background:#f5f5f5;">
            </td>
            <td>
                <input type="number" name="items[${newRowId}][return_quantity]" id="quantity_${newRowId}" value="1" min="0" step="0.1" oninput="validateQuantity(${newRowId})" required>
            </td>
            <td>
                <input type="number" name="items[${newRowId}][return_price]" id="price_${newRowId}" step="0.01" oninput="calculateRow(${newRowId})" required>
            </td>
            <td>
                <input type="text" name="items[${newRowId}][product_discount]" id="discount_${newRowId}" readonly style="background:#f5f5f5;">
            </td>
            <td>
                <input type="text" name="items[${newRowId}][return_total]" id="total_${newRowId}" readonly style="background:#f5f5f5;">
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
    
    function selectProduct(rowId) {
        const select = document.getElementById(`product_select_${rowId}`);
        const selectedOption = select.options[select.selectedIndex];
        
        if (selectedOption.value) {
            const itemId = selectedOption.value;
            const productName = selectedOption.getAttribute('data-name');
            const unit = selectedOption.getAttribute('data-unit');
            const originalPrice = parseFloat(selectedOption.getAttribute('data-price')) || 0;
            const discount = parseFloat(selectedOption.getAttribute('data-discount')) || 0;
            const total = parseFloat(selectedOption.getAttribute('data-total')) || 0;
            const maxQuantity = parseFloat(selectedOption.getAttribute('data-max-quantity')) || 0;
            const unitPriceAfterDiscount = parseFloat(selectedOption.getAttribute('data-unit-price-after-discount')) || originalPrice;
            
            document.getElementById(`product_name_${rowId}`).value = productName;
            document.getElementById(`original_item_id_${rowId}`).value = itemId;
            document.getElementById(`unit_${rowId}`).value = unit;
            
            // تعيين السعر بعد الخصم (سعر الوحدة بعد الخصم)
            document.getElementById(`price_${rowId}`).value = unitPriceAfterDiscount;
            
            // تخزين الخصم الكلي للمنتج
            document.getElementById(`discount_${rowId}`).value = discount;
            document.getElementById(`max_quantity_${rowId}`).value = maxQuantity;
            document.getElementById(`unit_price_after_discount_${rowId}`).value = unitPriceAfterDiscount;
            
            // تحديث حقل الكمية بالحد الأقصى
            const quantityInput = document.getElementById(`quantity_${rowId}`);
            quantityInput.max = maxQuantity;
            quantityInput.title = `الحد الأقصى للإرجاع: ${maxQuantity}`;
            
            calculateRow(rowId);
        }
    }
    
    function validateQuantity(rowId) {
        const qtyInput = document.getElementById(`quantity_${rowId}`);
        const maxQty = parseFloat(document.getElementById(`max_quantity_${rowId}`).value) || 0;
        const currentQty = parseFloat(qtyInput.value) || 0;
        const unitPriceAfterDiscount = parseFloat(document.getElementById(`unit_price_after_discount_${rowId}`).value) || 0;
        
        if (currentQty > maxQty && maxQty > 0) {
            alert(`⚠️ الكمية المدخلة (${currentQty}) تتجاوز الكمية المتاحة (${maxQty})`);
            qtyInput.value = maxQty;
            calculateRow(rowId);
        } else if (currentQty < 0) {
            qtyInput.value = 0;
            calculateRow(rowId);
        } else {
            calculateRow(rowId);
        }
    }
    
    function calculateRow(rowId) {
        const qty = parseFloat(document.getElementById(`quantity_${rowId}`).value) || 0;
        const price = parseFloat(document.getElementById(`price_${rowId}`).value) || 0;
        const total = qty * price;
        
        document.getElementById(`total_${rowId}`).value = total.toFixed(2);
        calculateTotals();
    }
    
    function calculateTotals() {
        let itemsCount = 0;
        let totalQuantity = 0;
        let grandTotal = 0;
        
        const rows = document.querySelectorAll('#itemsTableBody tr');
        
        rows.forEach(row => {
            const qty = parseFloat(row.querySelector('input[name*="[return_quantity]"]')?.value) || 0;
            const total = parseFloat(row.querySelector('input[name*="[return_total]"]')?.value) || 0;
            
            if (qty > 0) {
                itemsCount++;
                totalQuantity += qty;
                grandTotal += total;
            }
        });
        
        document.getElementById('display_items_count').textContent = itemsCount;
        document.getElementById('display_total_quantity').textContent = totalQuantity.toFixed(2);
        document.getElementById('display_grand_total').textContent = grandTotal.toFixed(2) + ' ر.ي';
    }
    
    function removeRow(rowId) {
        if (document.querySelectorAll('#itemsTableBody tr').length <= 1) {
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
        const rows = document.querySelectorAll('#itemsTableBody tr');
        rows.forEach((row, index) => {
            row.querySelector('td:first-child').textContent = index + 1;
            row.id = `row_${index}`;
        });
        rowCounter = rows.length;
    }
    
    function resetForm() {
        if (confirm('هل أنت متأكد من إعادة تعيين النموذج؟')) {
            location.reload();
        }
    }
    
    document.addEventListener('keydown', function(e) {
        if ((e.key === 'F2' || e.keyCode === 113) && !document.getElementById('addItemBtn').disabled) {
            e.preventDefault();
            addReturnItem();
        }
        if ((e.key === 'F9' || e.keyCode === 120) && document.querySelectorAll('#itemsTableBody tr').length > 0) {
            e.preventDefault();
            document.querySelector('form').submit();
        }
    });
    
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.autocomplete-wrapper')) {
            document.getElementById('invoiceDropdown').classList.remove('show');
        }
    });
</script>

</body>
</html>