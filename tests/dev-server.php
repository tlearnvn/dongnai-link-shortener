<?php
declare(strict_types=1);

/**
 * Bộ định tuyến cho máy chủ thử nghiệm có sẵn của PHP.
 *
 * Máy chủ này không đọc .htaccess nên cần một tệp nhỏ làm thay việc rewrite:
 * tệp thật (CSS, JS, ảnh) thì phục vụ trực tiếp, còn lại đưa hết về index.php.
 *
 * Chạy từ thư mục gốc của dự án:
 *   php -S 127.0.0.1:8000 tests/dev-server.php
 * Rồi mở http://127.0.0.1:8000
 */

$root = dirname(__DIR__);
$path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
$file = $root . $path;

// Không bao giờ phục vụ trực tiếp tệp dữ liệu hay mã nguồn.
$blocked = preg_match('#^/(data|app|tests)/#', $path) === 1
    || preg_match('/\.(sqlite|sqlite-wal|sqlite-shm|log)$/', $path) === 1;

if ($blocked) {
    http_response_code(403);
    exit('403 — Không có quyền truy cập.');
}

if ($path !== '/' && is_file($file)) {
    return false; // để máy chủ tự gửi tệp tĩnh
}

// Giả lập môi trường như khi có mod_rewrite
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';

require $root . '/index.php';
