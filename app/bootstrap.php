<?php
declare(strict_types=1);

/** Khởi động hệ thống: nạp lớp, cấu hình, múi giờ, phiên làm việc. */

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('Hệ thống cần PHP 8.1 trở lên. Phiên bản đang chạy: ' . PHP_VERSION);
}

foreach (['pdo_sqlite', 'mbstring'] as $extension) {
    if (!extension_loaded($extension)) {
        http_response_code(500);
        exit("Thiếu phần mở rộng PHP bắt buộc: {$extension}. Vui lòng bật trong php.ini rồi thử lại.");
    }
}

// --- Nạp lớp tự động ------------------------------------------------------
spl_autoload_register(static function (string $class): void {
    foreach ([__DIR__ . '/lib/', __DIR__ . '/controllers/'] as $dir) {
        $file = $dir . $class . '.php';
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

require __DIR__ . '/lib/Support.php';

// --- Cấu hình và múi giờ --------------------------------------------------
Config::load();
date_default_timezone_set((string) Config::get('timezone', 'Asia/Ho_Chi_Minh'));

// --- Xử lý lỗi ------------------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$logFile = dirname((string) Config::get('db_path')) . '/error.log';
if (is_dir(dirname($logFile))) {
    ini_set('error_log', $logFile);
}

set_exception_handler(static function (Throwable $e): void {
    error_log('[rutgon] ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    $detail = 'Hệ thống gặp sự cố khi xử lý yêu cầu. Vui lòng thử lại; nếu vẫn lỗi, liên hệ quản trị viên.';
    try {
        echo render_error_page('Đã có lỗi xảy ra', $detail, 500);
    } catch (Throwable) {
        echo '<h1>Đã có lỗi xảy ra</h1><p>' . e($detail) . '</p>';
    }
});

set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
    if ((error_reporting() & $severity) === 0) {
        return false;
    }
    // Thông báo "deprecated" của PHP chỉ ghi vào nhật ký: chúng báo trước về
    // phiên bản sau chứ không phải lỗi thật, không nên làm gián đoạn người dùng.
    if (($severity & (E_DEPRECATED | E_USER_DEPRECATED)) !== 0) {
        error_log("[rutgon] Deprecated: {$message} @ {$file}:{$line}");
        return true;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// --- Bảo mật cơ bản cho phản hồi -----------------------------------------
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 0');
}

// --- Phiên làm việc -------------------------------------------------------
session_start_safe();
load_old();

// --- Cơ sở dữ liệu (tạo bảng ở lần chạy đầu tiên) -------------------------
Database::pdo();

// Dọn dữ liệu cũ theo cấu hình (chạy nhẹ, xác suất thấp để không làm chậm trang).
$retention = (int) Config::get('click_retention_days', 0);
if ($retention > 0 && random_int(1, 200) === 1) {
    Stats::pruneClicks($retention);
}
