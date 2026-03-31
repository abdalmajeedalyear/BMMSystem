<?php
session_start();
include "config.php";

$success_message = "";
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_customer'])) {
    $customer_name = $conn->real_escape_string($_POST['customer_name']);
    $customer_phone = $conn->real_escape_string($_POST['customer_phone'] ?? '');
    $customer_email = $conn->real_escape_string($_POST['customer_email'] ?? '');
    $customer_address = $conn->real_escape_string($_POST['customer_address'] ?? '');
    $customer_type = $conn->real_escape_string($_POST['customer_type'] ?? 'فرد');
    $tax_number = $conn->real_escape_string($_POST['tax_number'] ?? '');
    
    $sql = "INSERT INTO customers (customer_name, customer_phone, customer_email, customer_address, customer_type, tax_number) 
            VALUES ('$customer_name', '$customer_phone', '$customer_email', '$customer_address', '$customer_type', '$tax_number')";
    
    if ($conn->query($sql)) {
        $_SESSION['alert'] = [
            'type' => 'success',
            'message' => "✅ تم إضافة العميل $customer_name بنجاح"
        ];
        header("Location: customers.php");
        exit();
    } else {
        $error_message = "❌ حدث خطأ: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>إضافة عميل جديد</title>
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">
    <style>
        /* نفس تنسيقات صفحة العملاء */
        body {
            font-family: 'Tahoma', sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #666;
        }
        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        input:focus, select:focus, textarea:focus {
            outline: 2px solid #2196F3;
        }
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .btn-primary {
            background: #2196F3;
            color: white;
        }
        .btn-primary:hover {
            background: #1976D2;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-user-plus" style="color: #2196F3;"></i> إضافة عميل جديد</h1>
        
        <form method="post">
            <div class="form-group">
                <label>اسم العميل <span style="color: red;">*</span></label>
                <input type="text" name="customer_name" required>
            </div>
            
            <div class="form-group">
                <label>رقم الهاتف</label>
                <input type="text" name="customer_phone">
            </div>
            
            <div class="form-group">
                <label>البريد الإلكتروني</label>
                <input type="email" name="customer_email">
            </div>
            
            <div class="form-group">
                <label>العنوان</label>
                <textarea name="customer_address" rows="3"></textarea>
            </div>
            
            <div class="form-group">
                <label>نوع العميل</label>
                <select name="customer_type">
                    <option value="فرد">فرد</option>
                    <option value="شركة">شركة</option>
                    <option value="جهة حكومية">جهة حكومية</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>الرقم الضريبي</label>
                <input type="text" name="tax_number">
            </div>
            
            <div class="actions">
                <a href="customers.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> إلغاء
                </a>
                <button type="submit" name="save_customer" class="btn btn-primary">
                    <i class="fas fa-save"></i> حفظ
                </button>
            </div>
        </form>
    </div>
</body>
</html>