<?php
declare(strict_types=1);

/**
 * Cấu hình chung của hệ thống.
 *
 * Muốn thay đổi mà không sửa tệp này, hãy tạo tệp app/config.local.php
 * trả về một mảng các khoá cần ghi đè. Ví dụ:
 *
 *   <?php return ['site_url' => 'https://link.dongnaiedu.vn'];
 */

$config = [
    // --- Thông tin đơn vị -------------------------------------------------
    'site_name' => 'Rút gọn link',
    'site_owner' => 'Phòng GDPT-GDTX Sở GDĐT Đồng Nai',
    'site_tagline' => 'Rút gọn liên kết, tạo mã QR và theo dõi số lượt truy cập',
    'footer_credit' => 'Thiết kế bởi Trương Anh Tuấn',

    // Để trống thì hệ thống tự nhận diện địa chỉ từ máy chủ.
    'site_url' => '',

    // --- Múi giờ ----------------------------------------------------------
    // Toàn hệ thống dùng UTC+7 (giờ Việt Nam).
    'timezone' => 'Asia/Ho_Chi_Minh',

    // --- Cơ sở dữ liệu ----------------------------------------------------
    // 'sqlite' (mặc định) — toàn bộ dữ liệu nằm trong DUY NHẤT một tệp,
    //                       không cần cài gì thêm, sao lưu bằng cách chép tệp.
    // 'mysql'             — dùng MySQL / MariaDB, phù hợp khi lượng truy cập
    //                       lớn hoặc muốn dùng công cụ sao lưu của hosting.
    'db_driver' => 'sqlite',

    // Dùng khi db_driver = 'sqlite'.
    'db_path' => __DIR__ . '/../data/rutgon.sqlite',

    // Dùng khi db_driver = 'mysql'. Nên khai trong app/config.local.php để
    // không lẫn mật khẩu vào tệp này (tệp này có trong bản phát hành).
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_name' => '',
    'db_user' => '',
    'db_pass' => '',
    'db_charset' => 'utf8mb4',
    // Một số hosting yêu cầu nối bằng socket thay cho host:port.
    // Có giá trị thì hệ thống dùng socket và bỏ qua db_host/db_port.
    'db_socket' => '',

    // Nơi ghi tệp error.log. Để trống thì dùng thư mục data/.
    'log_dir' => '',

    // --- Mã rút gọn -------------------------------------------------------
    'code_length' => 6,
    'code_min_length' => 3,
    'code_max_length' => 64,
    // Bộ ký tự sinh mã tự động: bỏ các ký tự dễ nhìn lẫn (0 O o 1 l I).
    'code_alphabet' => '23456789abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ',

    // --- Giới hạn ---------------------------------------------------------
    'max_url_length' => 2000,
    'bulk_max_lines' => 200,
    'per_page' => 12,

    // Số lần đăng nhập sai liên tiếp trước khi tạm khoá (theo IP).
    'login_max_attempts' => 8,
    'login_lockout_minutes' => 15,

    // Số liên kết tối đa một khách (chưa đăng nhập) tạo được mỗi giờ.
    'guest_hourly_limit' => 10,

    // Số ngày giữ lại chi tiết từng lượt nhấp (0 = giữ mãi).
    // Số tổng hợp trong bảng liên kết không bị ảnh hưởng.
    'click_retention_days' => 0,

    // --- Giao diện --------------------------------------------------------
    'per_page_options' => [12, 24, 48, 96],
];

$localConfigFile = __DIR__ . '/config.local.php';
if (is_file($localConfigFile)) {
    /** @var array<string, mixed> $local */
    $local = require $localConfigFile;
    if (is_array($local)) {
        $config = array_replace($config, $local);
    }
}

return $config;
