-- إنشاء جدول أصناف المخزون
CREATE TABLE IF NOT EXISTS `store_items` (
  `item_id` int(11) NOT NULL AUTO_INCREMENT,
  `item_code` varchar(50) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 0.00,
  `unit` varchar(50) DEFAULT 'قطعة',
  `cost_price` decimal(10,2) DEFAULT 0.00,
  `selling_price` decimal(10,2) DEFAULT 0.00,
  `min_stock_level` decimal(10,2) DEFAULT 10.00,
  `description` text DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`item_id`),
  UNIQUE KEY `item_code` (`item_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إدخال بيانات تجريبية
INSERT INTO `store_items` (`item_code`, `item_name`, `category`, `quantity`, `unit`, `cost_price`, `selling_price`, `min_stock_level`, `description`) VALUES
('BM-001', 'أسمنت مقاوم للأملاح', 'مواد أساسية', 500.00, 'كيس', 25.00, 35.00, 50.00, 'أسمنت عالي الجودة مقاوم للأملاح والكبريتات'),
('BM-002', 'حديد تسليح سابك 12 ملم', 'حديد', 120.00, 'طن', 2800.00, 3200.00, 100.00, 'حديد تسليح من إنتاج سابك قطر 12 ملم'),
('BM-003', 'طوب أحمر مفرغ 20سم', 'طوب', 1500.00, 'حبة', 1.50, 2.20, 200.00, 'طوب أحمر مفرغ مقاس 20×10×5 سم'),
('BM-004', 'دهان جوتن بلاستيك مطفي', 'أصباغ', 40.00, 'جالون', 120.00, 180.00, 80.00, 'دهان جوتن بلاستيك لامع ومطفي للأعمال الداخلية'),
('BM-005', 'رمل بناء مغسول ممتاز', 'مواد أساسية', 15.00, 'شاحنة', 450.00, 600.00, 5.00, 'رمل بناء نظيف ومغسول خالٍ من الشوائب'),
('BM-006', 'خشب بليود 18 ملم', 'نجارة', 200.00, 'لوح', 85.00, 115.00, 50.00, 'خشب بليود معالج ضد الرطوبة والحشرات'),
('BM-007', 'سيراميك أرضيات 60×60', 'سيراميك', 800.00, 'متر مربع', 45.00, 65.00, 100.00, 'سيراميك أرضيات مقاس 60×60 سم جودة أولى'),
('BM-008', 'أنابيب PVC 4 بوصة', 'سباكة', 300.00, 'متر', 15.00, 22.00, 50.00, 'أنابيب بلاستيكية للصرف الصحي قطر 4 بوصة'),
('BM-009', 'زجاج عازل للحرارة', 'زجاج', 100.00, 'متر مربع', 120.00, 180.00, 30.00, 'زجاج مزدوج الطبقات عازل للحرارة والصوت'),
('BM-010', 'كيماويات بناء شاملة', 'كيماويات', 50.00, 'علبة', 75.00, 110.00, 20.00, 'مجموعة كيماويات بناء متكاملة');