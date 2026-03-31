<?php
session_start();
include "config.php";

// التحقق من وجود معرف الصنف
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['alert'] = [
        'type' => 'error',
        'message' => "❌ معرف الصنف غير صحيح"
    ];
    header("Location: Store_items.php");
    exit();
}

$item_id = intval($_GET['id']);

// التحقق من وجود الصنف
$check = $conn->query("SELECT product_id, product_name, product_code FROM products WHERE product_id = $item_id");
if ($check->num_rows === 0) {
    $_SESSION['alert'] = [
        'type' => 'error',
        'message' => "❌ الصنف غير موجود"
    ];
    header("Location: Store_items.php");
    exit();
}

$item = $check->fetch_assoc();
$product_name = $item['product_name'];
$product_code = $item['product_code'];

// التحقق من عدم وجود فواتير مبيعات مرتبطة بهذا المنتج
$check_invoice_items = $conn->query("SELECT COUNT(*) as count FROM invoice_items WHERE product_name = '$product_name'");
$invoice_count = $check_invoice_items->fetch_assoc()['count'];

// التحقق من عدم وجود فواتير مشتريات مرتبطة بهذا المنتج
$check_purchase_items = $conn->query("SELECT COUNT(*) as count FROM purchase_items WHERE product_name = '$product_name'");
$purchase_count = $check_purchase_items->fetch_assoc()['count'];

$total_related = $invoice_count + $purchase_count;

// إذا تم تأكيد الحذف
if (isset($_GET['confirm']) && $_GET['confirm'] == 'yes') {
    
    if ($total_related > 0) {
        $_SESSION['alert'] = [
            'type' => 'error',
            'message' => "❌ لا يمكن حذف الصنف '<strong>$product_name</strong>' لأنه مرتبط بـ $total_related حركة في الفواتير"
        ];
        header("Location: Store_items.php");
        exit();
    }
    
    // بدء المعاملة
    $conn->begin_transaction();
    
    try {
        // حذف الصنف
        $delete = $conn->query("DELETE FROM products WHERE product_id = $item_id");
        
        if (!$delete) {
            throw new Exception("خطأ في حذف الصنف: " . $conn->error);
        }
        
        $conn->commit();
        
        $_SESSION['alert'] = [
            'type' => 'success',
            'message' => "✅ تم حذف الصنف '<strong>$product_name</strong>' بنجاح"
        ];
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['alert'] = [
            'type' => 'error',
            'message' => "❌ حدث خطأ أثناء الحذف: " . $e->getMessage()
        ];
    }
    
    header("Location: Store_items.php");
    exit();
}

// إذا كان للمنتج حركات في الفواتير، نمنع الحذف ونعرض رسالة
if ($total_related > 0) {
    $_SESSION['alert'] = [
        'type' => 'error',
        'message' => "❌ لا يمكن حذف الصنف '<strong>$product_name</strong>' لأنه مرتبط بـ $total_related حركة في الفواتير"
    ];
    header("Location: Store_items.php");
    exit();
}

// إذا لم يتم التأكيد، نعرض صفحة تأكيد الحذف
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>حذف الصنف - نظام المخازن</title>
    
    <!-- Font Awesome محلي -->
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Tahoma', Arial, sans-serif;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .confirm-container {
            max-width: 500px;
            width: 100%;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                transform: translateY(-30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .header {
            background: linear-gradient(135deg, #f44336, #d32f2f);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header i {
            font-size: 60px;
            margin-bottom: 15px;
        }
        
        .header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .content {
            padding: 30px;
        }
        
        .info-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            border-right: 4px solid #f44336;
        }
        
        .info-item {
            display: flex;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px dashed #ddd;
        }
        
        .info-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .info-label {
            width: 100px;
            color: #666;
            font-weight: bold;
        }
        
        .info-value {
            flex: 1;
            color: #333;
            font-weight: 600;
        }
        
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            color: #856404;
        }
        
        .warning-box i {
            font-size: 24px;
        }
        
        .warning-box p {
            font-size: 14px;
            line-height: 1.6;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
        }
        
        .btn {
            flex: 1;
            padding: 15px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn-danger {
            background: #f44336;
            color: white;
        }
        
        .btn-danger:hover {
            background: #d32f2f;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(244,67,54,0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        @media (max-width: 480px) {
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="confirm-container">
        <div class="header">
            <i class="fas fa-exclamation-triangle"></i>
            <h1>تأكيد الحذف</h1>
            <p>هل أنت متأكد من حذف هذا الصنف؟</p>
        </div>
        
        <div class="content">
            <div class="info-box">
                <div class="info-item">
                    <span class="info-label">كود الصنف:</span>
                    <span class="info-value"><?php echo $product_code; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">اسم الصنف:</span>
                    <span class="info-value"><?php echo $product_name; ?></span>
                </div>
            </div>
            
            <div class="warning-box">
                <i class="fas fa-info-circle"></i>
                <p>
                    <strong>تنبيه:</strong> هذا الإجراء لا يمكن التراجع عنه. سيتم حذف الصنف نهائياً من قاعدة البيانات.
                </p>
            </div>
            
            <div class="action-buttons">
                <a href="Store_items.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i>
                    إلغاء
                </a>
                <a href="delete_item.php?id=<?php echo $item_id; ?>&confirm=yes" class="btn btn-danger">
                    <i class="fas fa-trash"></i>
                    نعم، قم بالحذف
                </a>
            </div>
        </div>
    </div>
</body>
</html>