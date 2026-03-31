<?php
session_start();

// إذا كان المستخدم مسجل دخوله بالفعل، حوله للصفحة الرئيسية
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

include "config.php";

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $conn->real_escape_string($_POST['password']); // بدون تشفير
    
    // مقارنة مباشرة بدون password_verify
    $sql = "SELECT user_id, username, full_name, password, user_role FROM users WHERE username = ? AND password = ? AND is_active = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['user_role'] = $user['user_role'];
        
        // تحديث آخر تسجيل دخول
        $conn->query("UPDATE users SET last_login = NOW() WHERE user_id = {$user['user_id']}");
        
        header("Location: index.php");
        exit();
    } else {
        $error = "❌ اسم المستخدم أو كلمة المرور غير صحيحة";
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - نظام المخازن</title>
    
    <!-- Font Awesome محلي -->
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Tahoma', Arial, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 400px;
            overflow: hidden;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-header {
            background: linear-gradient(135deg, #2196F3, #1976D2);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .login-header i {
            font-size: 60px;
            margin-bottom: 15px;
        }
        
        .login-header h1 {
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .login-header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .login-body {
            padding: 40px 30px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: bold;
            color: #333;
            margin-bottom: 8px;
        }
        
        .form-group label i {
            color: #2196F3;
            margin-left: 8px;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-wrapper i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        
        .input-wrapper input {
            width: 100%;
            padding: 15px 45px 15px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .input-wrapper input:focus {
            outline: none;
            border-color: #2196F3;
            box-shadow: 0 0 0 3px rgba(33,150,243,0.1);
        }
        
        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #2196F3, #1976D2);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(33,150,243,0.3);
        }
        
        .error-message {
            background: #ffebee;
            border: 1px solid #f44336;
            color: #f44336;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        
        .footer {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-warehouse"></i>
            <h1>نظام إدارة المخازن</h1>
            <p>تسجيل الدخول للوحة التحكم</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> اسم المستخدم</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" name="username" placeholder="أدخل اسم المستخدم" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> كلمة المرور</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" placeholder="أدخل كلمة المرور" required>
                    </div>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i>
                    تسجيل الدخول
                </button>
            </form>
        </div>
        
        <div class="footer">
            &copy; <?php echo date('Y'); ?> نظام إدارة المخازن - جميع الحقوق محفوظة
        </div>
    </div>
</body>
</html>