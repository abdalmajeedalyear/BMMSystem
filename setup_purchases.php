<?php
include "config.php";

echo "<h2>إعداد جداول المشتريات وإدخال البيانات</h2>";

// 1. إنشاء جدول المشتريات
$sql_purchase_table = "
CREATE TABLE IF NOT EXISTS `purchases` (
  `purchase_id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_number` varchar(50) NOT NULL,
  `purchase_date` date NOT NULL,
  `supplier_name` varchar(255) NOT NULL,
  `supplier_phone` varchar(50) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `total_discount` decimal(10,2) DEFAULT 0.00,
  `grand_total` decimal(10,2) DEFAULT 0.00,
  `payment_method` varchar(50) DEFAULT 'نقدي',
  `payment_status` varchar(50) DEFAULT 'غير مدفوعة',
  `notes` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`purchase_id`),
  UNIQUE KEY `purchase_number` (`purchase_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_purchase_table) === TRUE) {
    echo "✅ جدول المشتريات تم إنشاؤه بنجاح<br>";
} else {
    echo "❌ خطأ في إنشاء جدول المشتريات: " . $conn->error . "<br>";
}

// 2. إنشاء جدول تفاصيل المشتريات
$sql_purchase_items_table = "
CREATE TABLE IF NOT EXISTS `purchase_items` (
  `item_id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_unit` varchar(50) DEFAULT 'قطعة',
  `product_quantity` decimal(10,2) DEFAULT 0.00,
  `product_price` decimal(10,2) DEFAULT 0.00,
  `product_discount` decimal(10,2) DEFAULT 0.00,
  `product_total` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`item_id`),
  FOREIGN KEY (`purchase_id`) REFERENCES `purchases`(`purchase_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_purchase_items_table) === TRUE) {
    echo "✅ جدول تفاصيل المشتريات تم إنشاؤه بنجاح<br>";
} else {
    echo "❌ خطأ في إنشاء جدول تفاصيل المشتريات: " . $conn->error . "<br>";
}

// 3. إنشاء جدول المدفوعات
$sql_payments_table = "
CREATE TABLE IF NOT EXISTS `payments` (
  `payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` int(11) DEFAULT NULL,
  `payment_date` date NOT NULL,
  `payment_amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT 'نقدي',
  `notes` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  FOREIGN KEY (`purchase_id`) REFERENCES `purchases`(`purchase_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_payments_table) === TRUE) {
    echo "✅ جدول المدفوعات تم إنشاؤه بنجاح<br>";
} else {
    echo "❌ خطأ في إنشاء جدول المدفوعات: " . $conn->error . "<br>";
}

// 4. التحقق من الجداول
echo "<br><h3>التحقق من الجداول:</h3>";
$tables = ['purchases', 'purchase_items', 'payments', 'store_items'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "✅ جدول $table موجود<br>";
    } else {
        echo "❌ جدول $table غير موجود<br>";
    }
}

// 5. عرض روابط الصفحات
echo "<br><h3>روابط الصفحات:</h3>";
echo "<a href='add_item_tostore.php'>صفحة إضافة مشتريات</a><br>";
echo "<a href='Store_items.php'>صفحة المخزون</a><br>";
echo "<a href='index.php'>الصفحة الرئيسية</a><br>";
?>