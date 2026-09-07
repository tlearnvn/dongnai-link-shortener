<?php
declare(strict_types=1);

/**
 * Kiểm định việc nhận diện đường dẫn.
 *
 * Hệ thống phải chạy đúng ở cả ba kiểu triển khai thường gặp:
 *   1. Ngay gốc tên miền              https://rutgon.vn/tuyen-sinh
 *   2. Trong thư mục con              https://sogd.vn/rutgon/tuyen-sinh
 *   3. Không có mod_rewrite           https://sogd.vn/index.php/tuyen-sinh
 *
 * Chạy: php tests/path_test.php
 */

require __DIR__ . '/../app/lib/Config.php';
require __DIR__ . '/../app/lib/Support.php';

$pass = 0;
$fail = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  \033[32m✓\033[0m {$name}\n";
    } else {
        $fail++;
        echo "  \033[31m✗\033[0m {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}

/**
 * Chạy một hàm trong môi trường máy chủ giả lập.
 *
 * base_url() và request_path() đều dùng bộ nhớ đệm tĩnh, nên mỗi tình huống
 * phải chạy trong một tiến trình PHP riêng để không lẫn kết quả của nhau.
 *
 * @param array<string, string> $server
 * @return array{base: string, path: string, url: string, short: string}
 */
function inScenario(array $server): array
{
    $root = dirname(__DIR__);
    $code = sprintf(
        '<?php
        require %s . "/app/lib/Config.php";
        require %s . "/app/lib/Support.php";
        $_SERVER = array_merge($_SERVER, %s);
        echo json_encode([
            "base"  => base_url(),
            "path"  => request_path(),
            "url"   => url("/lien-ket"),
            "short" => short_url("tuyen-sinh"),
        ], JSON_UNESCAPED_SLASHES);',
        var_export($root, true),
        var_export($root, true),
        var_export($server, true)
    );

    $tmp = tempnam(sys_get_temp_dir(), 'pathtest') . '.php';
    file_put_contents($tmp, $code);
    $output = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmp) . ' 2>&1');
    @unlink($tmp);

    $decoded = json_decode(trim($output), true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Không đọc được kết quả: ' . $output);
    }

    return $decoded;
}

/**
 * Test này đo phần TỰ NHẬN DIỆN địa chỉ, nên phải chắc chắn không có
 * app/config.local.php nào đang khai báo sẵn site_url (ví dụ tệp do
 * tests/run_all.php sinh ra). Tạm cất tệp đó đi, xong thì trả lại.
 */
$configFile = dirname(__DIR__) . '/app/config.local.php';
$configBackup = is_file($configFile) ? (string) file_get_contents($configFile) : null;

$restoreConfig = static function () use ($configFile, $configBackup): void {
    if ($configBackup !== null) {
        file_put_contents($configFile, $configBackup);
    } else {
        @unlink($configFile);
    }
};
register_shutdown_function($restoreConfig);

// Cấu hình rỗng: buộc hệ thống tự nhận diện địa chỉ từ thông tin máy chủ.
file_put_contents($configFile, '<?php return ["site_url" => ""];');

echo "\033[1mKiểm định nhận diện đường dẫn\033[0m\n\n";

// --- 1. Đặt ngay gốc tên miền ---------------------------------------------
echo "\033[1m1. Ngay gốc tên miền\033[0m\n";
$root = inScenario([
    'HTTP_HOST' => 'rutgon.dongnai.edu.vn',
    'SCRIPT_NAME' => '/index.php',
    'REQUEST_URI' => '/tuyen-sinh-2026',
    'HTTPS' => 'on',
]);
check('Địa chỉ gốc đúng', $root['base'] === 'https://rutgon.dongnai.edu.vn', $root['base']);
check('Đường dẫn nhận ra đúng mã', $root['path'] === '/tuyen-sinh-2026', $root['path']);
check('Liên kết nội bộ đúng', $root['url'] === 'https://rutgon.dongnai.edu.vn/lien-ket', $root['url']);
check('Liên kết rút gọn đúng', $root['short'] === 'https://rutgon.dongnai.edu.vn/tuyen-sinh', $root['short']);

// --- 2. Đặt trong thư mục con ---------------------------------------------
echo "\n\033[1m2. Trong thư mục con /rutgon\033[0m\n";
$sub = inScenario([
    'HTTP_HOST' => 'sgddt.dongnai.gov.vn',
    'SCRIPT_NAME' => '/rutgon/index.php',
    'REQUEST_URI' => '/rutgon/tuyen-sinh-2026',
    'HTTPS' => 'on',
]);
check('Địa chỉ gốc gồm cả thư mục con',
    $sub['base'] === 'https://sgddt.dongnai.gov.vn/rutgon', $sub['base']);
check('Đường dẫn đã bỏ tiền tố thư mục', $sub['path'] === '/tuyen-sinh-2026', $sub['path']);
check('Liên kết nội bộ đúng',
    $sub['url'] === 'https://sgddt.dongnai.gov.vn/rutgon/lien-ket', $sub['url']);
check('Liên kết rút gọn đúng',
    $sub['short'] === 'https://sgddt.dongnai.gov.vn/rutgon/tuyen-sinh', $sub['short']);

// --- 3. Không có mod_rewrite ----------------------------------------------
echo "\n\033[1m3. Máy chủ không bật rewrite (/index.php/...)\033[0m\n";
$noRewrite = inScenario([
    'HTTP_HOST' => 'sgddt.dongnai.gov.vn',
    'SCRIPT_NAME' => '/index.php',
    'REQUEST_URI' => '/index.php/tuyen-sinh-2026',
    'HTTPS' => 'on',
]);
check('Đường dẫn bỏ được phần /index.php',
    $noRewrite['path'] === '/tuyen-sinh-2026', $noRewrite['path']);

$noRewriteSub = inScenario([
    'HTTP_HOST' => 'sgddt.dongnai.gov.vn',
    'SCRIPT_NAME' => '/rutgon/index.php',
    'REQUEST_URI' => '/rutgon/index.php/tuyen-sinh-2026',
    'HTTPS' => 'on',
]);
check('Vừa thư mục con vừa không rewrite',
    $noRewriteSub['path'] === '/tuyen-sinh-2026', $noRewriteSub['path']);

// --- 4. Các tình huống lẻ -------------------------------------------------
echo "\n\033[1m4. Tình huống lẻ\033[0m\n";

$withQuery = inScenario([
    'HTTP_HOST' => 'rutgon.vn',
    'SCRIPT_NAME' => '/index.php',
    'REQUEST_URI' => '/tuyen-sinh?s=qr&utm_source=zalo',
]);
check('Bỏ đúng phần tham số truy vấn', $withQuery['path'] === '/tuyen-sinh', $withQuery['path']);

$homePage = inScenario([
    'HTTP_HOST' => 'rutgon.vn',
    'SCRIPT_NAME' => '/index.php',
    'REQUEST_URI' => '/',
]);
check('Trang chủ cho đường dẫn "/"', $homePage['path'] === '/', $homePage['path']);
check('Địa chỉ trang chủ có dấu / ở cuối', $homePage['url'] === 'http://rutgon.vn/lien-ket', $homePage['url']);

$encoded = inScenario([
    'HTTP_HOST' => 'rutgon.vn',
    'SCRIPT_NAME' => '/index.php',
    'REQUEST_URI' => '/ma-qr/tuyen-sinh%2D2026.svg',
]);
check('Giải mã đúng ký tự đã mã hoá URL',
    $encoded['path'] === '/ma-qr/tuyen-sinh-2026.svg', $encoded['path']);

$proxied = inScenario([
    'HTTP_HOST' => 'rutgon.vn',
    'SCRIPT_NAME' => '/index.php',
    'REQUEST_URI' => '/tuyen-sinh',
    'HTTP_X_FORWARDED_PROTO' => 'https',
]);
check('Nhận ra https khi chạy sau proxy/CDN',
    str_starts_with($proxied['base'], 'https://'), $proxied['base']);

$httpPlain = inScenario([
    'HTTP_HOST' => '192.168.1.50:8080',
    'SCRIPT_NAME' => '/rutgon/index.php',
    'REQUEST_URI' => '/rutgon/',
]);
check('Chạy nội bộ qua http kèm cổng',
    $httpPlain['base'] === 'http://192.168.1.50:8080/rutgon', $httpPlain['base']);

// Header Host là dữ liệu do trình duyệt gửi, phải lọc ký tự lạ
$evilHost = inScenario([
    'HTTP_HOST' => 'rutgon.vn"><script>alert(1)</script>',
    'SCRIPT_NAME' => '/index.php',
    'REQUEST_URI' => '/',
]);
check('Lọc ký tự lạ trong header Host',
    !str_contains($evilHost['base'], '<') && !str_contains($evilHost['base'], '"'),
    $evilHost['base']);

// --- 5. Khi đã khai báo site_url thì phải dùng đúng giá trị đó ------------
echo "\n\033[1m5. Khai báo site_url trong cấu hình\033[0m\n";
file_put_contents($configFile, '<?php return ["site_url" => "https://rutgon.dongnai.edu.vn/"];');

$configured = inScenario([
    'HTTP_HOST' => 'may-chu-noi-bo.local',
    'SCRIPT_NAME' => '/index.php',
    'REQUEST_URI' => '/tuyen-sinh',
]);
check('Ưu tiên site_url trong cấu hình, bỏ qua header Host',
    $configured['base'] === 'https://rutgon.dongnai.edu.vn', $configured['base']);
check('Bỏ dấu / thừa ở cuối site_url',
    $configured['short'] === 'https://rutgon.dongnai.edu.vn/tuyen-sinh', $configured['short']);

$restoreConfig();

echo "\n" . str_repeat('─', 60) . "\n";
printf("Kết quả: \033[32m%d đạt\033[0m, %s%d lỗi\033[0m\n", $pass, $fail > 0 ? "\033[31m" : "\033[32m", $fail);
exit($fail > 0 ? 1 : 0);
