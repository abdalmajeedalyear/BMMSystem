<?php
include "config.php";

echo "<h2>فحص وإصلاح جداول المشتريات</h2>";

// 1. حذف الجداول القديمة إذا كانت موجودة
echo "<h3>حذف الجداول القديمة...</h3>";
$conn->query("DROP TABLE IF EXISTS payments");
$conn->query("DROP TABLE IF EXISTS purchase_items");
$conn->query("DROP TABLE IF EXISTS purchases");

// 2. إنشاء جدول المشتريات - تصحيح أسماء الأعمدة
$sql_purchases = "
CREATE TABLE `purchases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  PRIMARY KEY (`id`),
  UNIQUE KEY `purchase_number` (`purchase_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_purchases) === TRUE) {
    echo "✅ جدول المشتريات تم إنشاؤه بنجاح<br>";
} else {
    echo "❌ خطأ في إنشاء جدول المشتريات: " . $conn->error . "<br>";
}

// 3. إنشاء جدول تفاصيل المشتريات - استخدام id بدلاً من purchase_id
$sql_purchase_items = "
CREATE TABLE `purchase_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_unit` varchar(50) DEFAULT 'قطعة',
  `product_quantity` decimal(10,2) DEFAULT 0.00,
  `product_price` decimal(10,2) DEFAULT 0.00,
  `product_discount` decimal(10,2) DEFAULT 0.00,
  `product_total` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`purchase_id`) REFERENCES `purchases`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_purchase_items) === TRUE) {
    echo "✅ جدول تفاصيل المشتريات تم إنشاؤه بنجاح<br>";
} else {
    echo "❌ خطأ في إنشاء جدول تفاصيل المشتريات: " . $conn->error . "<br>";
}

// 4. إنشاء جدول المدفوعات - استخدام id بدلاً من purchase_id
$sql_payments = "
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` int(11) DEFAULT NULL,
  `payment_date` date NOT NULL,
  `payment_amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT 'نقدي',
  `notes` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`purchase_id`) REFERENCES `purchases`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_payments) === TRUE) {
    echo "✅ جدول المدفوعات تم إنشاؤه بنجاح<br>";
} else {
    echo "❌ خطأ في إنشاء جدول المدفوعات: " . $conn->error . "<br>";
}

// 5. التحقق من بنية الجداول
echo "<br><h3>التحقق من بنية الجداول:</h3>";
$tables_to_check = ['purchases', 'purchase_items', 'payments'];
foreach ($tables_to_check as $table) {
    $result = $conn->query("DESCRIBE $table");
    if ($result) {
        echo "✅ بنية جدول $table:<br>";
        while ($row = $result->fetch_assoc()) {
            echo "  - {$row['Field']} ({$row['Type']})<br>";
        }
        echo "<br>";
    } else {
        echo "❌ جدول $table غير موجود<br>";
    }
}

// 6. روابط الصفحات
echo "<h3>روابط الصفحات:</h3>";
echo "<a href='add_item_tostore.php'>صفحة إضافة مشتريات</a><br>";
echo "<a href='Store_items.php'>صفحة المخزون</a><br>";
?>