<?php
session_start();
include "config.php";

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: logout.php");
    exit();
}


// ==================== إحصائيات المرتجعات ====================
// إجمالي مرتجعات المبيعات
$total_sales_returns = $conn->query("SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total FROM sales_returns")->fetch_assoc();

// إجمالي مرتجعات المشتريات
$total_purchase_returns = $conn->query("SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total FROM purchase_returns")->fetch_assoc();

// ==================== استعلام جميع المرتجعات (بدون تحديد حد) ====================
$all_returns = $conn->query("
    (SELECT 
        'sales' as type, 
        return_id, 
        return_number, 
        return_date, 
        customer_name as party_name, 
        total_amount, 
        created_at 
     FROM sales_returns)
    UNION ALL
    (SELECT 
        'purchase' as type, 
        return_id, 
        return_number, 
        return_date, 
        supplier_name as party_name, 
        total_amount, 
        created_at 
     FROM purchase_returns)
    ORDER BY return_date DESC, created_at DESC
");

$has_returns = ($all_returns && $all_returns->num_rows > 0);

// ==================== ترقيم الصفحات ====================
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 20; // عدد المرتجعات في كل صفحة
$offset = ($page - 1) * $limit;

// استعلام المرتجعات مع ترقيم الصفحات
$returns_query = "
    (SELECT 
        'sales' as type, 
        return_id, 
        return_number, 
        return_date, 
        customer_name as party_name, 
        total_amount, 
        created_at 
     FROM sales_returns)
    UNION ALL
    (SELECT 
        'purchase' as type, 
        return_id, 
        return_number, 
        return_date, 
        supplier_name as party_name, 
        total_amount, 
        created_at 
     FROM purchase_returns)
    ORDER BY return_date DESC, created_at DESC
    LIMIT $offset, $limit
";

$returns_result = $conn->query($returns_query);

// حساب إجمالي عدد الصفحات
$total_returns = $has_returns ? $all_returns->num_rows : 0;
$total_pages = ceil($total_returns / $limit);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المرتجعات - نظام المخازن</title>
    
    <!-- Font Awesome محلي -->
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">
    
    <!-- ملف CSS الرئيسي -->
    <link rel="stylesheet" href="Style/reports.css">
    
    <style>
        /* تنسيقات إضافية */
        .returns-container {
            padding: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .stat-content h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #333;
        }
        
        .stat-desc {
            font-size: 12px;
            color: #999;
        }
        
        .action-cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .action-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .action-card.purchase {
            background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%);
        }
        
        .action-card:hover {
        
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .action-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transform: rotate(45deg);
        }
        
        .action-card h3 {
            font-size: 24px;
            margin-bottom: 10px;
            position: relative;
        }
        
        .action-card p {
            opacity: 0.9;
            margin-bottom: 20px;
            position: relative;
        }
        
        .action-btn {
            background: rgba(255,255,255,0.2);
            border: 2px solid white;
            color: white;
            padding: 10px 25px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            position: relative;
        }
        
        .action-btn:hover {
            background: white;
            color: #333;
        }
        
        .filters-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }
        
        .search-box i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 40px 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .filter-select {
            padding: 12px 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            min-width: 150px;
        }
        
        .table-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .table-header {
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-header h3 {
            font-size: 18px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .table-wrapper {
            overflow-x: auto;
            padding: 0 25px 25px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
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
            padding: 15px 10px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
            color: #333;
        }
        
        tbody tr:hover {
            background: #f5f5f5;
        }
        
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }
        
        .badge-sales {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-purchase {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .amount {
            font-weight: bold;
            color: #f44336;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .action-btn-small {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 6px;
            background: transparent;
            color: #666;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        
        .action-btn-small:hover {
            background: #e3f2fd;
            color: #2196F3;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
        }
        
        .page-btn {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: white;
            color: #666;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .page-btn:hover {
            background: #f0f0f0;
            border-color: #999;
        }
        
        .page-btn.active {
            background: #FF9800;
            color: white;
            border-color: #FF9800;
        }
        
        .page-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        @media (max-width: 768px) {
            .stats-grid,
            .action-cards {
                grid-template-columns: 1fr;
            }
            
            .filters-section {
                flex-direction: column;
            }
            
            .search-box {
                width: 100%;
            }
            
            .filter-select {
                width: 100%;
            }
        }
        .disabled-element{
            pointer-events:none;
        }
    </style>
</head>
<body>
    <div class="layout">
        <!-- ==================== الشريط الجانبي ==================== -->
        <aside class="sidebar" >
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
                <a href="Daily_Dashboard.php" class="nav-item">
                    <i class="fas fa-calendar-day"></i>
                    <span>التقرير اليومي</span>
                </a>
                <a href="Store_items.php" class="nav-item">
                    <i class="fas fa-boxes"></i>
                    <span>المخزون</span>
                </a>
                
                <!-- <a href="New_sales_invoice.php" class="nav-item">
                    <i class="fas fa-file-invoice"></i>
                    <span>فاتورة مبيعات</span>
                </a>
                <a href="add_item_tostore.php" class="nav-item">
                    <i class="fas fa-truck"></i>
                    <span>فاتورة مشتريات</span>
                </a> -->
                <a href="invoice_page.php" class="nav-item">
                    <i class="fas fa-list"></i>
                    <span>المبيعات</span>
                </a>
                <a href="purchases_page.php" class="nav-item">
                    <i class="fas fa-truck-loading"></i>
                    <span>المشتريات</span>
                </a>
                <a href="returns_page.php" class="nav-item active">
                    <i class="fas fa-undo-alt"></i>
                    <span>المرتجعات</span>
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
                    <i class="fas fa-truck"></i>
                    <span>الموردين</span>   
                </a>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <i class="fas fa-user-circle"></i>
                    <div style="display: flex; flex-direction: column;">
                        <span style="font-weight: bold;"><?php echo $_SESSION['full_name'] ?? 'مدير النظام'; ?></span>
                        <span style="font-size: 11px; opacity: 0.8;"><?php echo $_SESSION['user_role'] ?? ''; ?></span>
                    </div>
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>تسجيل الخروج</span>
                </a>
            </div>
        </aside>
        
        <!-- ==================== المحتوى الرئيسي ==================== -->
        <main class="main-content">
            <!-- ==================== شريط التنقل العلوي ==================== -->
            <header class="top-bar">
                <button class="menu-toggle" id="menuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                
                <div class="page-title">
                    <h1><i class="fas fa-undo-alt" style="color: #FF9800; margin-left: 10px;"></i> إدارة المرتجعات</h1>
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
            
            <div class="returns-container">
                <!-- ==================== إحصائيات ==================== -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e3f2fd; color: #2196F3;">
                            <i class="fas fa-undo-alt"></i>
                        </div>
                        <div class="stat-content">
                            <h3>مرتجعات مبيعات</h3>
                            <div class="stat-value"><?php echo $total_sales_returns['count']; ?></div>
                            <div class="stat-desc"><?php echo number_format($total_sales_returns['total'], 2); ?> ر.ي</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e8f5e9; color: #4CAF50;">
                            <i class="fas fa-undo-alt"></i>
                        </div>
                        <div class="stat-content">
                            <h3>مرتجعات مشتريات</h3>
                            <div class="stat-value"><?php echo $total_purchase_returns['count']; ?></div>
                            <div class="stat-desc"><?php echo number_format($total_purchase_returns['total'], 2); ?> ر.ي</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #fff3e0; color: #FF9800;">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <div class="stat-content">
                            <h3>إجمالي المرتجعات</h3>
                            <div class="stat-value"><?php echo $total_sales_returns['count'] + $total_purchase_returns['count']; ?></div>
                            <div class="stat-desc">حركة</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #f3e5f5; color: #9C27B0;">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-content">
                            <h3>القيمة الإجمالية</h3>
                            <div class="stat-value"><?php echo number_format($total_sales_returns['total'] + $total_purchase_returns['total'], 2); ?> ر.ي</div>
                            <div class="stat-desc">جميع المرتجعات</div>
                        </div>
                    </div>
                </div>
                
                <!-- ==================== بطاقات الإجراءات ==================== -->
                <div class="action-cards" >
                    <div class="action-card" onclick="window.location.href='sales_return.php'">
                        <h3><i class="fas fa-undo-alt"></i> مرتجع مبيعات</h3>
                        <p>إرجاع منتجات من عميل وإضافة الكميات إلى المخزون</p>
                        <div class="action-btn">
                            <i class="fas fa-plus"></i>
                            إنشاء مرتجع
                        </div>
                    </div>
                    
                    <div class="action-card purchase" onclick="window.location.href='purchase_return.php'"  style="pointer-events:none; cursor:not-allowed;">
                        <h3><i class="fas fa-undo-alt"></i> مرتجع مشتريات</h3>
                        <p>إرجاع منتجات للمورد وخصم الكميات من المخزون</p>
                        <div class="action-btn">
                            <i class="fas fa-plus"></i>
                            إنشاء مرتجع
                        </div>
                    </div>
                </div>
                
                <!-- ==================== قسم الفلاتر ==================== -->
                <div class="filters-section">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="بحث برقم المرتجع أو اسم العميل/المورد..." onkeyup="filterTable()">
                    </div>
                    
                    <select class="filter-select" id="typeFilter" onchange="filterTable()">
                        <option value="">جميع المرتجعات</option>
                        <option value="sales">مرتجعات مبيعات فقط</option>
                        <option value="purchase">مرتجعات مشتريات فقط</option>
                    </select>
                    
                    <select class="filter-select" id="sortFilter" onchange="sortTable()">
                        <option value="date_desc">الأحدث أولاً</option>
                        <option value="date_asc">الأقدم أولاً</option>
                        <option value="amount_desc">الأعلى قيمة</option>
                        <option value="amount_asc">الأقل قيمة</option>
                    </select>
                </div>
                
                <!-- ==================== جدول المرتجعات ==================== -->
                <div class="table-container">
                    <div class="table-header">
                        <h3>
                            <i class="fas fa-list-ul" style="color: #FF9800;"></i>
                            جميع المرتجعات
                        </h3>
                        <span>إجمالي: <?php echo $total_returns; ?> مرتجع</span>
                    </div>
                    
                    <div class="table-wrapper">
                        <table id="returnsTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>نوع المرتجع</th>
                                    <th>رقم المرتجع</th>
                                    <th>التاريخ</th>
                                    <th>العميل/المورد</th>
                                    <th>المبلغ</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <?php 
                                if ($has_returns):
                                    $counter = $offset + 1;
                                    while ($return = $returns_result->fetch_assoc()): 
                                ?>
                                <tr data-type="<?php echo $return['type']; ?>" data-date="<?php echo $return['return_date']; ?>" data-amount="<?php echo $return['total_amount']; ?>">
                                    <td><?php echo $counter++; ?></td>
                                    <td>
                                        <span class="badge <?php echo $return['type'] == 'sales' ? 'badge-sales' : 'badge-purchase'; ?>">
                                            <?php echo $return['type'] == 'sales' ? 'مرتجع مبيعات' : 'مرتجع مشتريات'; ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo $return['return_number']; ?></strong></td>
                                    <td><?php echo $return['return_date']; ?></td>
                                    <td><?php echo $return['party_name'] ?: '---'; ?></td>
                                    <td class="amount"><?php echo number_format($return['total_amount'], 2); ?> ر.ي</td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="action-btn-small" onclick="viewReturn(<?php echo $return['return_id']; ?>, '<?php echo $return['type']; ?>')" title="عرض">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="action-btn-small" onclick="printReturn(<?php echo $return['return_id']; ?>, '<?php echo $return['type']; ?>')" title="طباعة">
                                                <i class="fas fa-print"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php 
                                    endwhile;
                                else: 
                                ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 50px;">
                                        <i class="fas fa-undo-alt" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
                                        <p style="color: #999;">لا توجد مرتجعات لعرضها</p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- ==================== ترقيم الصفحات ==================== -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo ($page - 1); ?>" class="page-btn">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-btn disabled">
                            <i class="fas fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="page-btn active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>" class="page-btn"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo ($page + 1); ?>" class="page-btn">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-btn disabled">
                            <i class="fas fa-chevron-left"></i>
                        </span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script>
        // ==================== دوال الإجراءات ====================
        function viewReturn(returnId, type) {
            window.location.href = (type === 'sales' ? 'view_sales_return.php' : 'view_purchase_return.php') + '?id=' + returnId;
        }
        
        function printReturn(returnId, type) {
            window.open((type === 'sales' ? 'print_sales_return.php' : 'print_purchase_return.php') + '?id=' + returnId, '_blank');
        }
        
        // ==================== فلترة الجدول ====================
        function filterTable() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const typeFilter = document.getElementById('typeFilter').value;
            const rows = document.querySelectorAll('#tableBody tr');
            
            rows.forEach(row => {
                let showRow = true;
                
                if (searchTerm) {
                    const text = row.textContent.toLowerCase();
                    if (!text.includes(searchTerm)) {
                        showRow = false;
                    }
                }
                
                if (typeFilter) {
                    const rowType = row.getAttribute('data-type');
                    if (rowType !== typeFilter) {
                        showRow = false;
                    }
                }
                
                row.style.display = showRow ? '' : 'none';
            });
        }
        
        // ==================== ترتيب الجدول ====================
        function sortTable() {
            const sortBy = document.getElementById('sortFilter').value;
            const tbody = document.getElementById('tableBody');
            const rows = Array.from(document.querySelectorAll('#tableBody tr'));
            
            rows.sort((a, b) => {
                if (sortBy === 'date_desc') {
                    return new Date(b.getAttribute('data-date')) - new Date(a.getAttribute('data-date'));
                } else if (sortBy === 'date_asc') {
                    return new Date(a.getAttribute('data-date')) - new Date(b.getAttribute('data-date'));
                } else if (sortBy === 'amount_desc') {
                    return parseFloat(b.getAttribute('data-amount')) - parseFloat(a.getAttribute('data-amount'));
                } else if (sortBy === 'amount_asc') {
                    return parseFloat(a.getAttribute('data-amount')) - parseFloat(b.getAttribute('data-amount'));
                }
                return 0;
            });
            
            // إعادة ترتيب الصفوف
            rows.forEach(row => tbody.appendChild(row));
            
            // تحديث أرقام الصفوف
            rows.forEach((row, index) => {
                row.cells[0].textContent = index + 1 + <?php echo $offset; ?>;
            });
        }
        
        // ==================== قائمة جانبية قابلة للطي ====================
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('collapsed');
            document.querySelector('.main-content').classList.toggle('expanded');
        });
    </script>
</body>
</html>