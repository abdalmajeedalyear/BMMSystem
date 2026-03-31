<?php
// logout.php
session_start();

// مسح جميع متغيرات الجلسة
$_SESSION = array();

// إذا كنت تستخدم الكوكيز، احذفها أيضاً
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// تدمير الجلسة
session_destroy();

// إعادة التوجيه إلى صفحة تسجيل الدخول
header("Location: login.php");
exit();
?>