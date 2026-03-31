<?php
session_start();
include "config.php";

$success_message = "";
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_supplier'])) {
    $supplier_name = $conn->real_escape_string($_POST['supplier_name']);
    $supplier_phone = $conn->real_escape_string($_POST['supplier_phone'] ?? '');
    $supplier_email = $conn->real_escape_string($_POST['supplier_email'] ?? '');
    $supplier_address = $conn->real_escape_string($_POST['supplier_address'] ?? '');
    $tax_number = $conn->real_escape_string($_POST['tax_number'] ?? '');
    
    $sql = "INSERT INTO suppliers (supplier_name, supplier_phone, supplier_email, supplier_address, tax_number) 
            VALUES ('$supplier_name', '$supplier_phone', '$supplier_email', '$supplier_address', '$tax_number')";
    
    if ($conn->query($sql)) {
        $_SESSION['alert'] = [
            'type' => 'success',
            'message' => "✅ تم إضافة المورد $supplier_name بنجاح"
        ];
        header("Location: suppliers.php");
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
    <title>إضافة مورد جديد</title>
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">
    <style>
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
        input, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        input:focus, textarea:focus {
            outline: 2px solid #4CAF50;
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
            background: #4CAF50;
            color: white;
        }
        .btn-primary:hover {
            background: #388E3C;
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
        <h1><i class="fas fa-handshake" style="color: #4CAF50;"></i> إضافة مورد جديد</h1>
        
        <form method="post">
            <div class="form-group">
                <label>اسم المورد <span style="color: red;">*</span></label>
                <input type="text" name="supplier_name" required>
            </div>
            
            <div class="form-group">
                <label>رقم الهاتف</label>
                <input type="text" name="supplier_phone">
            </div>
            
            <div class="form-group">
                <label>البريد الإلكتروني</label>
                <input type="email" name="supplier_email">
            </div>
            
            <div class="form-group">
                <label>العنوان</label>
                <textarea name="supplier_address" rows="3"></textarea>
            </div>
            
            <div class="form-group">
                <label>الرقم الضريبي</label>
                <input type="text" name="tax_number">
            </div>
            
            <div class="actions">
                <a href="suppliers.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> إلغاء
                </a>
                <button type="submit" name="save_supplier" class="btn btn-primary">
                    <i class="fas fa-save"></i> حفظ
                </button>
            </div>
        </form>
    </div>
</body>
</html>