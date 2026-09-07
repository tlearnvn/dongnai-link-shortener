<?php
declare(strict_types=1);

/** Các hàm tiện dụng dùng khắp hệ thống. */

/** Escape HTML — luôn dùng khi in dữ liệu người dùng ra trang. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Địa chỉ gốc của hệ thống, ví dụ https://ten-mien.vn hoặc .../rutgon. */
function base_url(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $configured = trim((string) Config::get('site_url', ''));
    if ($configured !== '') {
        return $cached = rtrim($configured, '/');
    }

    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443);

    $host = (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
    // Chỉ giữ ký tự hợp lệ của tên miền/cổng (Host là dữ liệu từ trình duyệt).
    $host = preg_replace('/[^A-Za-z0-9\.\-:\[\]]/', '', $host) ?: 'localhost';

    $dir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
    $dir = rtrim($dir, '/');

    return $cached = ($https ? 'https://' : 'http://') . $host . $dir;
}

/** Tạo địa chỉ nội bộ: url('/lien-ket') */
function url(string $path = '/'): string
{
    if ($path === '' || $path === '/') {
        return base_url() . '/';
    }
    return base_url() . '/' . ltrim($path, '/');
}

/** Địa chỉ rút gọn đầy đủ của một mã. */
function short_url(string $code): string
{
    return base_url() . '/' . $code;
}

/** Địa chỉ rút gọn dạng ngắn để hiển thị (bỏ http://). */
function short_url_display(string $code): string
{
    return preg_replace('#^https?://#', '', short_url($code)) ?? short_url($code);
}

/** Đường dẫn hiện tại đã bỏ phần thư mục gốc, luôn bắt đầu bằng "/". */
function request_path(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '/');

    $dir = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
    if ($dir !== '' && $dir !== '/' && str_starts_with($path, $dir)) {
        $path = substr($path, strlen($dir));
    }
    // Trường hợp máy chủ không bật rewrite: /index.php/ma-rut-gon
    if (str_starts_with($path, '/index.php')) {
        $path = substr($path, strlen('/index.php'));
    }

    $path = rawurldecode($path);
    $path = '/' . trim($path, '/');

    return $cached = $path;
}

function request_method(): string
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
}

function is_post(): bool
{
    return request_method() === 'POST';
}

function is_ajax(): bool
{
    return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
}

/** Chuyển hướng rồi dừng. */
function redirect(string $path, int $status = 302): never
{
    $target = str_starts_with($path, 'http') ? $path : url($path);
    header('Location: ' . $target, true, $status);
    exit;
}

/** Quay lại trang trước (nếu cùng miền), mặc định về trang chủ. */
function redirect_back(string $fallback = '/'): never
{
    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    if ($referer !== '' && str_starts_with($referer, base_url())) {
        redirect($referer);
    }
    redirect($fallback);
}

// ------------------------------------------------------------------ phiên làm việc

function session_start_safe(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = str_starts_with(base_url(), 'https://');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $https,
    ]);
    session_name('rutgon_session');
    session_start();
}

/** Ghi một thông báo hiện ở lần tải trang kế tiếp. */
function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/** @return array<int, array{type: string, message: string}> */
function take_flashes(): array
{
    $items = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return is_array($items) ? $items : [];
}

/** Giữ lại dữ liệu vừa nhập để điền lại vào biểu mẫu sau khi báo lỗi. */
function keep_input(array $data, array $errors = []): void
{
    unset($data['_token'], $data['password'], $data['password_confirm']);
    $_SESSION['_old'] = $data;
    $_SESSION['_errors'] = $errors;
}

function load_old(): void
{
    $GLOBALS['_old_input'] = $_SESSION['_old'] ?? [];
    $GLOBALS['_field_errors'] = $_SESSION['_errors'] ?? [];
    unset($_SESSION['_old'], $_SESSION['_errors']);
}

function old(string $key, string $default = ''): string
{
    $value = $GLOBALS['_old_input'][$key] ?? $default;
    return is_scalar($value) ? (string) $value : $default;
}

/**
 * Trường này có trong dữ liệu vừa gửi hay không.
 *
 * Cần phân biệt "người dùng đã xoá trống ô" với "không có dữ liệu cũ" — nếu
 * chỉ dựa vào chuỗi rỗng thì giá trị đã lưu sẽ hiện lại, làm người dùng tưởng
 * thao tác xoá của mình không có tác dụng.
 */
function has_old(string $key): bool
{
    return array_key_exists($key, (array) ($GLOBALS['_old_input'] ?? []));
}

function field_error(string $key): ?string
{
    $value = $GLOBALS['_field_errors'][$key] ?? null;
    return is_string($value) ? $value : null;
}

// ------------------------------------------------------------------------- CSRF

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

/** Kiểm tra token; sai thì dừng với mã 419. */
function csrf_verify(): void
{
    $sent = (string) ($_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($sent === '' || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        echo render_error_page(
            'Phiên làm việc đã hết hạn',
            'Biểu mẫu đã mở quá lâu nên mã bảo vệ không còn hiệu lực. Vui lòng tải lại trang và thao tác lại.'
        );
        exit;
    }
}

// -------------------------------------------------------------------- dữ liệu vào

/** Lấy dữ liệu POST/GET đã cắt khoảng trắng hai đầu. */
function input(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    if (is_array($value)) {
        return $default;
    }
    return trim((string) $value);
}

function input_int(string $key, int $default = 0): int
{
    $value = input($key, (string) $default);
    return $value === '' ? $default : (int) $value;
}

function input_bool(string $key): bool
{
    $value = input($key);
    return in_array($value, ['1', 'on', 'true', 'yes'], true);
}

/** Địa chỉ IP của khách, có xét proxy phổ biến. */
function client_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        $value = (string) ($_SERVER[$key] ?? '');
        if ($value === '') {
            continue;
        }
        foreach (explode(',', $value) as $candidate) {
            $candidate = trim($candidate);
            if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                return $candidate;
            }
        }
    }
    return '0.0.0.0';
}

// --------------------------------------------------------------- định dạng hiển thị

/** Số nguyên có dấu phân cách nghìn kiểu Việt Nam: 1.234.567 */
function n(int|float|null $value): string
{
    return number_format((float) ($value ?? 0), 0, ',', '.');
}

/** Dung lượng tệp dễ đọc. */
function bytes_human(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $size = (float) $bytes;
    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }
    return ($i === 0 ? (string) (int) $size : number_format($size, 1, ',', '.')) . ' ' . $units[$i];
}

/** Cắt chuỗi dài, giữ nguyên ký tự tiếng Việt. */
function truncate_str(?string $value, int $limit = 60): string
{
    $value = (string) $value;
    if (mb_strlen($value, 'UTF-8') <= $limit) {
        return $value;
    }
    return rtrim(mb_substr($value, 0, $limit - 1, 'UTF-8')) . '…';
}

/** Tên miền của một địa chỉ. */
function host_of(?string $url): string
{
    $host = (string) (parse_url((string) $url, PHP_URL_HOST) ?: '');
    return preg_replace('/^www\./i', '', $host) ?? $host;
}

/** Sinh mã ngẫu nhiên an toàn từ bộ ký tự đã cấu hình. */
function random_code(?int $length = null): string
{
    $alphabet = (string) Config::get('code_alphabet');
    $length = $length ?? (int) Config::get('code_length', 6);
    $max = strlen($alphabet) - 1;
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $alphabet[random_int(0, $max)];
    }
    return $code;
}

/** Chuyển tiếng Việt có dấu thành chuỗi thân thiện với địa chỉ web. */
function slugify(string $value): string
{
    $map = [
        'a' => 'áàảãạăắằẳẵặâấầẩẫậ', 'e' => 'éèẻẽẹêếềểễệ', 'i' => 'íìỉĩị',
        'o' => 'óòỏõọôốồổỗộơớờởỡợ', 'u' => 'úùủũụưứừửữự', 'y' => 'ýỳỷỹỵ', 'd' => 'đ',
    ];
    $value = mb_strtolower($value, 'UTF-8');
    foreach ($map as $plain => $accented) {
        $chars = preg_split('//u', $accented, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $value = str_replace($chars, $plain, $value);
    }
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
}

/** Trả về 'is-active' nếu đường dẫn hiện tại khớp — dùng để tô sáng menu. */
function nav_active(string $prefix): string
{
    $path = request_path();
    $match = $prefix === '/' ? $path === '/' : str_starts_with($path, $prefix);
    return $match ? ' is-active' : '';
}

/** Gộp tham số truy vấn hiện tại với các thay đổi mới (dùng cho phân trang). */
function query_url(array $changes = [], ?string $path = null): string
{
    $params = array_merge($_GET, $changes);
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        }
    }
    $query = http_build_query($params);
    return url($path ?? request_path()) . ($query !== '' ? '?' . $query : '');
}

// --------------------------------------------------------------------- kết xuất

/** Kết xuất JSON rồi dừng. */
function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

/** Nạp một tệp giao diện và trả về chuỗi HTML. */
function render(string $template, array $data = []): string
{
    $file = __DIR__ . '/../views/' . $template . '.php';
    if (!is_file($file)) {
        throw new RuntimeException("Không tìm thấy tệp giao diện: {$template}");
    }
    extract($data, EXTR_SKIP);
    ob_start();
    require $file;
    return (string) ob_get_clean();
}

/** Kết xuất một trang hoàn chỉnh (nội dung + khung giao diện). */
function view(string $template, array $data = []): never
{
    $content = render('pages/' . $template, $data);
    echo render('layout', array_merge($data, ['content' => $content]));
    exit;
}

/** Trang lỗi đơn giản, dùng được cả khi chưa nạp xong hệ thống. */
function render_error_page(string $title, string $message, int $code = 0): string
{
    return render('layout', [
        'pageTitle' => $title,
        'content' => render('pages/error', ['title' => $title, 'message' => $message, 'code' => $code]),
    ]);
}

/** Dừng xử lý và hiện trang lỗi. */
function abort(int $status, string $title, string $message): never
{
    http_response_code($status);
    echo render_error_page($title, $message, $status);
    exit;
}
