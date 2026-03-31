<?php
session_start();
include "config.php";

// التحقق من صلاحية المستخدم (يمكنك تعديل حسب نظام الصلاحيات لديك)
// if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
//     header("Location: login.php");
//     exit();
// }

$backup_dir = 'E:\backups/';
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0777, true);
}
// ==================== دوال النسخ الاحتياطي ====================

// دالة الحصول على حجم الملف بتنسيق مناسب
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// دالة إنشاء اسم ملف النسخة الاحتياطية
function generateBackupFilename() {
    $date = date('Y-m-d_H-i-s');
    return "backup_{$date}.sql";
}

// دالة إنشاء نسخة احتياطية
function createBackup($conn) {
    $backup_dir = 'E:\backups/';
    
    // إنشاء مجلد النسخ الاحتياطية إذا لم يكن موجوداً
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }
    
    $filename = generateBackupFilename();
    $filepath = $backup_dir . $filename;
    
    // الحصول على قائمة الجداول
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    
    $output = "-- ============================================\n";
    $output .= "-- نظام إدارة المخازن - نسخة احتياطية\n";
    $output .= "-- تاريخ الإنشاء: " . date('Y-m-d H:i:s') . "\n";
    $output .= "-- عدد الجداول: " . count($tables) . "\n";
    $output .= "-- ============================================\n\n";
    $output .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
    
    foreach ($tables as $table) {
        // هيكل الجدول
        $result = $conn->query("SHOW CREATE TABLE `$table`");
        $row = $result->fetch_row();
        $output .= "\n-- --------------------------------------------------------\n";
        $output .= "-- هيكل الجدول: `$table`\n";
        $output .= "-- --------------------------------------------------------\n\n";
        $output .= "DROP TABLE IF EXISTS `$table`;\n";
        $output .= $row[1] . ";\n\n";
        
        // بيانات الجدول
        $result = $conn->query("SELECT * FROM `$table`");
        $num_rows = $result->num_rows;
        if ($num_rows > 0) {
            $output .= "-- --------------------------------------------------------\n";
            $output .= "-- بيانات الجدول: `$table` ($num_rows سجل)\n";
            $output .= "-- --------------------------------------------------------\n\n";
            
            while ($row = $result->fetch_assoc()) {
                $values = [];
                foreach ($row as $value) {
                    if (is_null($value)) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = "'" . $conn->real_escape_string($value) . "'";
                    }
                }
                $output .= "INSERT INTO `$table` VALUES (" . implode(", ", $values) . ");\n";
            }
            $output .= "\n";
        }
    }
    
    $output .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    
    // حفظ الملف
    file_put_contents($filepath, $output);
    
    return [
        'success' => true,
        'filename' => $filename,
        'filepath' => $filepath,
        'size' => filesize($filepath),
        'tables' => count($tables)
    ];
}

// ==================== معالجة الطلبات ====================

$message = '';
$message_type = '';

// إنشاء نسخة احتياطية جديدة
if (isset($_GET['action']) && $_GET['action'] == 'create') {
    try {
        $backup = createBackup($conn);
        $message = "✅ تم إنشاء النسخة الاحتياطية بنجاح: " . $backup['filename'] . " (حجم: " . formatFileSize($backup['size']) . ")";
        $message_type = 'success';
    } catch (Exception $e) {
        $message = "❌ خطأ في إنشاء النسخة الاحتياطية: " . $e->getMessage();
        $message_type = 'error';
    }
}

// حذف نسخة احتياطية
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['file'])) {
    $file = basename($_GET['file']); // التأمين ضد هجمات المسار
    $filepath = 'E:\backups/' . $file;
    
    if (file_exists($filepath) && is_file($filepath)) {
        if (unlink($filepath)) {
            $message = "✅ تم حذف النسخة الاحتياطية: $file";
            $message_type = 'success';
        } else {
            $message = "❌ فشل حذف النسخة الاحتياطية: $file";
            $message_type = 'error';
        }
    }
}

// استعادة نسخة احتياطية
if (isset($_POST['action']) && $_POST['action'] == 'restore' && isset($_POST['file'])) {
    $file = basename($_POST['file']);
    $filepath = 'E:\backups/' . $file;
    
    if (file_exists($filepath) && is_file($filepath)) {
        try {
            $sql = file_get_contents($filepath);
            
            // تنفيذ الاستعلامات
            if ($conn->multi_query($sql)) {
                do {
                    if ($result = $conn->store_result()) {
                        $result->free();
                    }
                } while ($conn->next_result());
                
                $message = "✅ تم استعادة قاعدة البيانات من النسخة: $file";
                $message_type = 'success';
            } else {
                throw new Exception($conn->error);
            }
        } catch (Exception $e) {
            $message = "❌ خطأ في استعادة النسخة الاحتياطية: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// الحصول على قائمة النسخ الاحتياطية
$backup_files = [];
if (is_dir('E:\backups')) {
    $files = scandir('E:\backups');
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) == 'sql') {
            $filepath = 'E:\backups/' . $file;
            $backup_files[] = [
                'name' => $file,
                'size' => filesize($filepath),
                'date' => date('Y-m-d H:i:s', filemtime($filepath))
            ];
        }
    }
    // ترتيب الملفات حسب التاريخ (الأحدث أولاً)
    usort($backup_files, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>النسخ الاحتياطي - نظام المخازن</title>
    
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
            background: #f0f2f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* ==================== شريط التنقل العلوي ==================== */
        .top-bar {
            background: white;
            border-radius: 15px;
            padding: 15px 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .page-title h1 {
            font-size: 24px;
            color: #333;
        }
        
        .page-title i {
            font-size: 28px;
            color: #4CAF50;
        }
        
        .date-display {
            background: #e8f5e9;
            color: #4CAF50;
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: bold;
            border: 2px solid #4CAF50;
        }
        
        /* ==================== أزرار الإجراءات ==================== */
        .action-bar {
            background: white;
            border-radius: 15px;
            padding: 20px 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #2196F3;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1976D2;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(33,150,243,0.3);
        }
        
        .btn-success {
            background: #4CAF50;
            color: white;
        }
        
        .btn-success:hover {
            background: #388E3C;
            transform: translateY(-2px);
        }
        
        .btn-warning {
            background: #ff9800;
            color: white;
        }
        
        .btn-warning:hover {
            background: #f57c00;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: #f44336;
            color: white;
        }
        
        .btn-danger:hover {
            background: #d32f2f;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-info {
            background: #00BCD4;
            color: white;
        }
        
        .btn-info:hover {
            background: #00ACC1;
            transform: translateY(-2px);
        }
        
        /* ==================== رسائل التنبيه ==================== */
        .alert {
            padding: 15px 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }
        
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        /* ==================== إحصائيات ==================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: #e8f5e9;
            color: #4CAF50;
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
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        
        .stat-desc {
            font-size: 12px;
            color: #999;
        }
        
        /* ==================== جدول النسخ الاحتياطية ==================== */
        .backups-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .section-header {
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-header h2 {
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
        
        .filename {
            font-weight: bold;
            color: #4CAF50;
        }
        
        .file-size {
            font-family: monospace;
        }
        
        .action-buttons-cell {
            display: flex;
            gap: 8px;
        }
        
        .action-btn {
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 6px;
            background: transparent;
            color: #666;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        
        .action-btn:hover {
            background: #e8f5e9;
            color: #4CAF50;
        }
        
        .action-btn.download:hover {
            background: #e3f2fd;
            color: #2196F3;
        }
        
        .action-btn.restore:hover {
            background: #fff3e0;
            color: #ff9800;
        }
        
        .action-btn.delete:hover {
            background: #ffebee;
            color: #f44336;
        }
        
        .no-data {
            text-align: center;
            padding: 50px;
            color: #999;
        }
        
        .no-data i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.3;
        }
        
        /* ==================== نافذة التأكيد ==================== */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal-container {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 450px;
            overflow: hidden;
            animation: modalSlideIn 0.3s ease;
        }
        
        .modal-header {
            background: #ff9800;
            color: white;
            padding: 20px 25px;
        }
        
        .modal-header h3 {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-body {
            padding: 25px;
        }
        
        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .action-bar {
                flex-direction: column;
            }
            
            .action-buttons {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- ==================== شريط التنقل العلوي ==================== -->
        <div class="top-bar">
            <div class="page-title">
                <i class="fas fa-database"></i>
                <h1>النسخ الاحتياطي واستعادة البيانات</h1>
            </div>
            <div class="date-display">
                <i class="far fa-calendar-alt"></i>
                <?php echo date('Y-m-d'); ?>
            </div>
        </div>
        
        <!-- ==================== رسائل التنبيه ==================== -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <!-- ==================== أزرار الإجراءات ==================== -->
        <div class="action-bar">
            <div class="action-buttons">
                <a href="?action=create" class="btn btn-success">
                    <i class="fas fa-plus"></i>
                    إنشاء نسخة احتياطية جديدة
                </a>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-right"></i>
                    العودة للوحة التحكم
                </a>
            </div>
        </div>
        
        <!-- ==================== إحصائيات ==================== -->
        <div class="stats-grid">
            <?php
            $total_backups = count($backup_files);
            $total_size = array_sum(array_column($backup_files, 'size'));
            $latest_backup = !empty($backup_files) ? $backup_files[0]['date'] : 'لا يوجد';
            ?>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-copy"></i>
                </div>
                <div class="stat-content">
                    <h3>إجمالي النسخ</h3>
                    <div class="stat-value"><?php echo $total_backups; ?></div>
                    <div class="stat-desc">نسخة احتياطية</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-hdd"></i>
                </div>
                <div class="stat-content">
                    <h3>الحجم الإجمالي</h3>
                    <div class="stat-value"><?php echo formatFileSize($total_size); ?></div>
                    <div class="stat-desc">مساحة مستخدمة</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3>آخر نسخة</h3>
                    <div class="stat-value"><?php echo date('Y-m-d', strtotime($latest_backup)); ?></div>
                    <div class="stat-desc"><?php echo date('H:i:s', strtotime($latest_backup)); ?></div>
                </div>
            </div>
        </div>
        
        <!-- ==================== جدول النسخ الاحتياطية ==================== -->
        <div class="backups-section">
            <div class="section-header">
                <h2>
                    <i class="fas fa-history"></i>
                    قائمة النسخ الاحتياطية
                </h2>
                <span>إجمالي: <?php echo $total_backups; ?> نسخة</span>
            </div>
            
            <div class="table-wrapper">
                <?php if (!empty($backup_files)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>اسم الملف</th>
                            <th>التاريخ</th>
                            <th>الحجم</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backup_files as $index => $backup): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td class="filename"><?php echo $backup['name']; ?></td>
                            <td><?php echo $backup['date']; ?></td>
                            <td class="file-size"><?php echo formatFileSize($backup['size']); ?></td>
                            <td>
                                <div class="action-buttons-cell">
                                    <a href="E:\backups/<?php echo $backup['name']; ?>" download class="action-btn download" title="تحميل">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <?php if ($_SESSION['full_name'] == 'مدير النظام'): ?>
                                        <button class="action-btn restore" onclick="confirmRestore('<?php echo $backup['name']; ?>')" title="استعادة">
                                            <i class="fas fa-undo-alt"></i>
                                        </button>
                                        <button class="action-btn delete" onclick="confirmDelete('<?php echo $backup['name']; ?>')" title="حذف">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <button class="action-btn restore" style="display: none;" onclick="confirmRestore('<?php echo $backup['name']; ?>')" title="استعادة">
                                        <i class="fas fa-undo-alt"></i>
                                        </button>
                                        <button class="action-btn delete" style="display: none;" onclick="confirmDelete('<?php echo $backup['name']; ?>')" title="حذف">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-database"></i>
                    <p>لا توجد نسخ احتياطية بعد</p>
                    <p style="font-size: 13px; margin-top: 10px;">انقر على "إنشاء نسخة احتياطية جديدة" للبدء</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- ==================== معلومات إضافية ==================== -->
        <div style="margin-top: 30px; padding: 20px; background: #e8f5e9; border-radius: 10px; font-size: 13px; color: #2e7d32;">
            <h4 style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                <i class="fas fa-info-circle"></i>
                معلومات مهمة عن النسخ الاحتياطي
            </h4>
            <ul style="margin-right: 20px; line-height: 1.8;">
                <li>النسخ الاحتياطية تحفظ في مجلد <strong>E:\backups/</strong></li>
                <li>يمكنك تحميل أي نسخة احتياطية للاحتفاظ بها خارج النظام</li>
                <li>عند استعادة نسخة احتياطية، سيتم استبدال قاعدة البيانات الحالية بالكامل</li>
                <li>يُنصح بعمل نسخة احتياطية قبل أي تحديث كبير أو في نهاية كل يوم</li>
                <li>النسخ الاحتياطية القديمة يمكن حذفها لتوفير المساحة</li>
            </ul>
        </div>
    </div>
    
    <!-- ==================== نافذة تأكيد الاستعادة ==================== -->
    <div class="modal-overlay" id="restoreModal">
        <div class="modal-container">
            <div class="modal-header">
                <h3>
                    <i class="fas fa-exclamation-triangle"></i>
                    تأكيد استعادة النسخة الاحتياطية
                </h3>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 15px;">هل أنت متأكد من استعادة هذه النسخة الاحتياطية؟</p>
                <p style="color: #f44336; font-weight: bold;">
                    <i class="fas fa-exclamation-circle"></i>
                    تحذير: هذه العملية ستستبدل قاعدة البيانات الحالية بالكامل ولا يمكن التراجع عنها!
                </p>
                <p id="restoreFileName" style="margin-top: 15px; direction: ltr; text-align: left; font-family: monospace;"></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('restoreModal')">
                    <i class="fas fa-times"></i> إلغاء
                </button>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="restore">
                    <input type="hidden" name="file" id="restoreFileInput" value="">
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-undo-alt"></i> تأكيد الاستعادة
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- ==================== نافذة تأكيد الحذف ==================== -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-container">
            <div class="modal-header" style="background: #f44336;">
                <h3>
                    <i class="fas fa-trash"></i>
                    تأكيد حذف النسخة الاحتياطية
                </h3>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 15px;">هل أنت متأكد من حذف هذه النسخة الاحتياطية؟</p>
                <p style="color: #f44336;">
                    <i class="fas fa-exclamation-circle"></i>
                    هذا الإجراء لا يمكن التراجع عنه!
                </p>
                <p id="deleteFileName" style="margin-top: 15px; direction: ltr; text-align: left; font-family: monospace;"></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('deleteModal')">
                    <i class="fas fa-times"></i> إلغاء
                </button>
                <a href="#" id="deleteLink" class="btn btn-danger">
                    <i class="fas fa-trash"></i> تأكيد الحذف
                </a>
            </div>
        </div>
    </div>
    
    <script>
        // ==================== دوال النوافذ المنبثقة ====================
        function confirmRestore(filename) {
            document.getElementById('restoreFileName').textContent = filename;
            document.getElementById('restoreFileInput').value = filename;
            document.getElementById('restoreModal').classList.add('active');
        }
        
        function confirmDelete(filename) {
            document.getElementById('deleteFileName').textContent = filename;
            document.getElementById('deleteLink').href = '?action=delete&file=' + encodeURIComponent(filename);
            document.getElementById('deleteModal').classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        // إغلاق النوافذ عند النقر خارجها
        window.onclick = function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('active');
            }
        }
        
        // اختصارات لوحة المفاتيح
        document.addEventListener('keydown', function(e) {
            // ESC لإغلاق النوافذ
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay').forEach(modal => {
                    modal.classList.remove('active');
                });
            }
        });
    </script>
</body>
</html>