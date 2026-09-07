<?php
declare(strict_types=1);

/**
 * Kiểm định toàn bộ luồng sử dụng qua HTTP thật.
 *
 * Cách chạy:
 *   php -S 127.0.0.1:8765 _router.php     (hoặc trỏ tới máy chủ thật)
 *   php tests/app_test.php [http://127.0.0.1:8765]
 *
 * Test dùng tệp cookie riêng nên mô phỏng đúng một người dùng thật:
 * đăng ký, đăng nhập, tạo liên kết, bấm vào liên kết, xem thống kê…
 */

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8765', '/');
$cookieJar = sys_get_temp_dir() . '/rutgon-test-cookies-' . getmypid() . '.txt';
$guestJar = sys_get_temp_dir() . '/rutgon-test-guest-' . getmypid() . '.txt';
register_shutdown_function(static function () use ($cookieJar, $guestJar): void {
    @unlink($cookieJar);
    @unlink($guestJar);
});

$pass = 0;
$fail = 0;
$failures = [];

function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail, $failures;
    if ($ok) {
        $pass++;
        echo "  \033[32m✓\033[0m {$name}\n";
    } else {
        $fail++;
        $failures[] = $name . ($detail !== '' ? " — {$detail}" : '');
        echo "  \033[31m✗\033[0m {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}

function section(string $name): void
{
    echo "\n\033[1m{$name}\033[0m\n";
}

/**
 * Gửi một yêu cầu HTTP.
 *
 * @param array<string, string>|null $post
 * @return array{status: int, body: string, headers: string, location: ?string, type: string}
 */
function request(string $url, ?array $post = null, bool $follow = false, string $jar = null): array
{
    global $base, $cookieJar;
    $jar = $jar ?? $cookieJar;

    $ch = curl_init(str_starts_with($url, 'http') ? $url : $base . $url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125 Safari/537.36',
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }

    $response = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error !== '') {
        echo "  \033[31mLỗi kết nối:\033[0m {$error}\n";
    }

    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    $location = null;
    if (preg_match('/^Location:\s*(.+)$/mi', $headers, $m)) {
        $location = trim($m[1]);
    }

    return ['status' => $status, 'body' => $body, 'headers' => $headers, 'location' => $location, 'type' => $type];
}

/** Lấy mã CSRF từ một trang có biểu mẫu. */
function csrf(string $url, ?string $jar = null): string
{
    $response = request($url, null, false, $jar);
    if (preg_match('/name="_token" value="([a-f0-9]+)"/', $response['body'], $m)) {
        return $m[1];
    }
    return '';
}

/** Gửi biểu mẫu POST kèm mã CSRF lấy từ trang $formUrl. */
function submit(string $formUrl, string $action, array $fields, ?string $jar = null): array
{
    $fields['_token'] = csrf($formUrl, $jar);
    return request($action, $fields, false, $jar);
}

echo "\033[1mKiểm định hệ thống rút gọn link\033[0m — {$base}\n";

// ---------------------------------------------------------------------------
section('1. Trang công khai');

$home = request('/');
check('Trang chủ trả về 200', $home['status'] === 200, 'nhận ' . $home['status']);
check('Trang chủ có tên hệ thống đầy đủ',
    str_contains($home['body'], 'Rút gọn link') && str_contains($home['body'], 'Phòng GDPT-GDTX Sở GDĐT Đồng Nai'));
check('Chân trang có dòng bản quyền', str_contains($home['body'], 'Thiết kế bởi Trương Anh Tuấn'));
check('Chân trang ghi rõ múi giờ GMT+7', str_contains($home['body'], 'GMT+7'));
check('Trang chủ có ô nhập tên tuỳ chọn', str_contains($home['body'], 'name="code"'));

foreach (['/huong-dan' => 'Hướng dẫn', '/gioi-thieu' => 'Giới thiệu', '/api' => 'Tài liệu API',
          '/cong-cu/utm' => 'Công cụ UTM', '/dang-nhap' => 'Đăng nhập', '/dang-ky' => 'Đăng ký',
          '/quen-mat-khau' => 'Quên mật khẩu'] as $path => $label) {
    $page = request($path);
    check("{$label} ({$path}) trả về 200", $page['status'] === 200, 'nhận ' . $page['status']);
}

$robots = request('/robots.txt');
check('robots.txt trả về text/plain',
    $robots['status'] === 200 && str_contains($robots['type'], 'text/plain'));
check('robots.txt chặn khu quản trị', str_contains($robots['body'], 'Disallow: /quan-tri'));

$notFound = request('/duong-dan-khong-ton-tai-abc123');
check('Mã không tồn tại trả về 404', $notFound['status'] === 404, 'nhận ' . $notFound['status']);
check('Trang 404 có thông báo tiếng Việt', str_contains($notFound['body'], 'không tồn tại'));

// ---------------------------------------------------------------------------
section('2. Khách rút gọn liên kết với tên tuỳ chọn');

$alias = 'ten-tuy-chon-' . bin2hex(random_bytes(3));
$target = 'https://sgddt.dongnai.gov.vn/thong-bao/tuyen-sinh-2026.pdf?loai=cong-van&so=1234';

$created = submit('/', '/rut-gon', [
    'target_url' => $target,
    'code' => $alias,
    'title' => 'Thông báo tuyển sinh 2026',
    'tags' => 'tuyển sinh, 2026',
], $guestJar);

check('Tạo liên kết chuyển hướng sang trang kết quả',
    $created['status'] === 302 && str_contains((string) $created['location'], '/ket-qua/' . $alias),
    'status ' . $created['status'] . ', location ' . (string) $created['location']);

$result = request('/ket-qua/' . $alias, null, false, $guestJar);
check('Trang kết quả trả về 200', $result['status'] === 200, 'nhận ' . $result['status']);
check('Trang kết quả hiện đúng tên tuỳ chọn', str_contains($result['body'], $alias));
check('Trang kết quả nhúng mã QR', str_contains($result['body'], '/ma-qr/' . $alias . '.svg'));
check('Trang kết quả có nút chia sẻ Zalo', str_contains($result['body'], 'zalo.me/share'));

// Tên trùng
$duplicate = submit('/', '/rut-gon', ['target_url' => 'https://vidu.vn/khac', 'code' => $alias], $guestJar);
$dupPage = request('/', null, false, $guestJar);
check('Tên tuỳ chọn trùng bị từ chối', str_contains($dupPage['body'], 'đã có người dùng'),
    'không thấy thông báo trùng tên');

// Tên bị hệ thống giữ
$reserved = submit('/', '/rut-gon', ['target_url' => 'https://vidu.vn/x', 'code' => 'quan-tri'], $guestJar);
$reservedPage = request('/', null, false, $guestJar);
check('Tên trùng đường dẫn hệ thống bị từ chối',
    str_contains($reservedPage['body'], 'hệ thống sử dụng') || str_contains($reservedPage['body'], 'chọn tên khác'));

// Địa chỉ sai
$badUrl = submit('/', '/rut-gon', ['target_url' => 'javascript:alert(1)'], $guestJar);
$badPage = request('/', null, false, $guestJar);
check('Địa chỉ javascript: bị từ chối',
    str_contains($badPage['body'], 'http://') && str_contains($badPage['body'], 'Chỉ hỗ trợ địa chỉ'));

// Thiếu mã CSRF
$noCsrf = request('/rut-gon', ['target_url' => 'https://vidu.vn/khong-co-token'], false, $guestJar);
check('Thiếu mã CSRF bị chặn (419)', $noCsrf['status'] === 419, 'nhận ' . $noCsrf['status']);

// Tự sinh mã khi bỏ trống tên
$autoCreated = submit('/', '/rut-gon', ['target_url' => 'https://vidu.vn/tu-sinh-ma'], $guestJar);
preg_match('#/ket-qua/([^/?\s]+)#', (string) $autoCreated['location'], $m);
$autoCode = $m[1] ?? '';
check('Bỏ trống tên thì hệ thống tự sinh mã',
    $autoCode !== '' && strlen($autoCode) >= 6, 'mã nhận được: ' . $autoCode);

// ---------------------------------------------------------------------------
section('3. Chuyển hướng và ghi nhận lượt nhấp');

$redirect = request('/' . $alias, null, false, $guestJar);
check('Bấm liên kết trả về 302', $redirect['status'] === 302, 'nhận ' . $redirect['status']);
check('Chuyển hướng đúng địa chỉ gốc', $redirect['location'] === $target,
    'nhận ' . (string) $redirect['location']);
check('Chuyển hướng không cho trình duyệt lưu bộ đệm',
    str_contains(strtolower($redirect['headers']), 'cache-control: no-store'));

// Chữ hoa chữ thường không ảnh hưởng
$upper = request('/' . strtoupper($alias), null, false, $guestJar);
check('Tên tuỳ chọn không phân biệt chữ hoa/thường',
    $upper['status'] === 302 && $upper['location'] === $target, 'nhận ' . $upper['status']);

// Xem trước không tính lượt nhấp
$preview = request('/xem/' . $alias, null, false, $guestJar);
check('Trang xem trước trả về 200', $preview['status'] === 200, 'nhận ' . $preview['status']);
check('Trang xem trước hiện địa chỉ đích', str_contains($preview['body'], 'sgddt.dongnai.gov.vn'));

// ---------------------------------------------------------------------------
section('4. Mã QR');

$svg = request('/ma-qr/' . $alias . '.svg');
check('QR dạng SVG trả về đúng loại nội dung',
    $svg['status'] === 200 && str_contains($svg['type'], 'image/svg+xml'), 'type: ' . $svg['type']);
check('SVG là tài liệu hợp lệ',
    str_contains($svg['body'], '<svg') && str_contains($svg['body'], '</svg>'));

$png = request('/ma-qr/' . $alias . '.png?co=8');
check('QR dạng PNG trả về đúng loại nội dung',
    $png['status'] === 200 && str_contains($png['type'], 'image/png'), 'type: ' . $png['type']);
check('PNG có chữ ký tệp đúng', str_starts_with($png['body'], "\x89PNG\r\n\x1a\n"));

$qrPage = request('/ma-qr/' . $alias);
check('Trang mã QR trả về 200', $qrPage['status'] === 200, 'nhận ' . $qrPage['status']);
check('Trang mã QR có bảng màu có sẵn', str_contains($qrPage['body'], 'data-swatch-dark'));

$qrCustom = request('/ma-qr/' . $alias . '.svg?mau=%23b91c1c&nen=trong&sua-loi=3&le=0');
check('Tuỳ chỉnh màu QR có hiệu lực', str_contains($qrCustom['body'], '#b91c1c'));
check('Nền trong suốt thì không vẽ hình chữ nhật nền',
    !str_contains($qrCustom['body'], '<rect width="100%"'));

$qrEvil = request('/ma-qr/' . $alias . '.svg?mau=' . rawurlencode('"><script>alert(1)</script>'));
check('Mã màu lạ bị loại bỏ (không chèn được mã độc)',
    !str_contains($qrEvil['body'], '<script>'), 'SVG chứa thẻ script');

// ---------------------------------------------------------------------------
section('5. API công khai');

$checkFree = request('/api/kiem-tra-ten?code=ten-chac-chan-chua-ai-dung-' . bin2hex(random_bytes(3)));
$freeData = json_decode($checkFree['body'], true);
check('API kiểm tra tên trả về JSON', is_array($freeData), 'không giải mã được JSON');
check('Tên còn trống báo available = true', ($freeData['available'] ?? null) === true);

$checkTaken = request('/api/kiem-tra-ten?code=' . $alias);
$takenData = json_decode($checkTaken['body'], true);
check('Tên đã dùng báo available = false', ($takenData['available'] ?? null) === false);
check('Tên đã dùng kèm gợi ý tên khác',
    isset($takenData['suggestion']) && $takenData['suggestion'] !== $alias,
    'gợi ý: ' . (string) ($takenData['suggestion'] ?? 'không có'));

$noAuth = request('/api/lien-ket');
check('API danh sách yêu cầu khoá (401)', $noAuth['status'] === 401, 'nhận ' . $noAuth['status']);

// ---------------------------------------------------------------------------
section('6. Đăng ký, đăng nhập');

$username = 'kiemdinh' . bin2hex(random_bytes(3));
$password = 'MatKhauManh!2026';

$register = submit('/dang-ky', '/dang-ky', [
    'username' => $username,
    'password' => $password,
    'password_confirm' => $password,
    'full_name' => 'Người Kiểm Định',
    'unit' => 'Phòng GDPT-GDTX',
    'email' => $username . '@vidu.local',
]);
check('Đăng ký thành công và chuyển sang trang tài khoản',
    $register['status'] === 302 && str_contains((string) $register['location'], '/tai-khoan'),
    'status ' . $register['status'] . ' → ' . (string) $register['location']);

$account = request('/tai-khoan');
check('Trang tài khoản trả về 200', $account['status'] === 200, 'nhận ' . $account['status']);
check('Mã dự phòng hiện đúng một lần sau khi đăng ký',
    (bool) preg_match('/[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}/', $account['body']));

$accountAgain = request('/tai-khoan');
check('Tải lại trang thì mã dự phòng không hiện nữa',
    !preg_match('/[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}/', $accountAgain['body']));

// Mật khẩu không khớp — dùng phiên riêng vì phiên chính đang đăng nhập
$badJar = sys_get_temp_dir() . '/rutgon-test-bad-' . getmypid() . '.txt';
submit('/dang-ky', '/dang-ky', [
    'username' => 'khac' . bin2hex(random_bytes(3)),
    'password' => $password,
    'password_confirm' => 'khac-hoan-toan',
], $badJar);
$badRegisterPage = request('/dang-ky', null, false, $badJar);
check('Nhập lại mật khẩu sai bị từ chối', str_contains($badRegisterPage['body'], 'chưa giống nhau'));

// Đã đăng nhập thì POST /dang-ky bị đưa về bảng điều khiển (không tạo thêm tài khoản)
$registerWhileLoggedIn = submit('/tai-khoan', '/dang-ky', [
    'username' => 'khonghople' . bin2hex(random_bytes(3)),
    'password' => $password,
    'password_confirm' => $password,
]);
check('Đang đăng nhập thì không tạo thêm được tài khoản',
    $registerWhileLoggedIn['status'] === 302
    && str_contains((string) $registerWhileLoggedIn['location'], '/bang-dieu-khien'),
    'nhận ' . $registerWhileLoggedIn['status'] . ' → ' . (string) $registerWhileLoggedIn['location']);
@unlink($badJar);

// Đăng nhập sai
$freshJar = sys_get_temp_dir() . '/rutgon-test-fresh-' . getmypid() . '.txt';
$wrongLogin = submit('/dang-nhap', '/dang-nhap', ['login' => $username, 'password' => 'sai-be-bet'], $freshJar);
$wrongPage = request('/dang-nhap', null, false, $freshJar);
check('Đăng nhập sai mật khẩu bị từ chối',
    str_contains($wrongPage['body'], 'không đúng'), 'không thấy thông báo lỗi');

// Đăng nhập đúng
$login = submit('/dang-nhap', '/dang-nhap', ['login' => $username, 'password' => $password, 'remember' => '1'], $freshJar);
check('Đăng nhập đúng thì vào bảng điều khiển',
    $login['status'] === 302 && str_contains((string) $login['location'], '/bang-dieu-khien'),
    'status ' . $login['status'] . ' → ' . (string) $login['location']);
@unlink($freshJar);

// ---------------------------------------------------------------------------
section('7. Quản lý liên kết (đã đăng nhập)');

foreach (['/bang-dieu-khien' => 'Bảng điều khiển', '/lien-ket' => 'Danh sách liên kết',
          '/lien-ket/tao' => 'Biểu mẫu tạo liên kết', '/tao-hang-loat' => 'Tạo hàng loạt',
          '/thong-ke' => 'Thống kê tổng hợp'] as $path => $label) {
    $page = request($path);
    check("{$label} trả về 200", $page['status'] === 200, 'nhận ' . $page['status']);
}

$ownAlias = 'lich-thi-' . bin2hex(random_bytes(3));
$ownCreate = submit('/lien-ket/tao', '/lien-ket/tao', [
    'target_url' => 'vidu.vn/lich-thi-hoc-ky-1',  // cố tình thiếu https://
    'code' => $ownAlias,
    'title' => 'Lịch thi học kỳ 1',
    'tags' => 'lịch thi, hk1',
    'note' => 'Ghi chú nội bộ',
    'max_clicks' => '3',
    'qr_dark' => '#0f766e',
    'qr_light' => '#f0fdfa',
]);
check('Tạo liên kết từ biểu mẫu đầy đủ thành công',
    $ownCreate['status'] === 302 && str_contains((string) $ownCreate['location'], '/ket-qua/' . $ownAlias),
    'status ' . $ownCreate['status'] . ' → ' . (string) $ownCreate['location']);

$ownRedirect = request('/' . $ownAlias);
check('Địa chỉ thiếu https:// được tự thêm',
    $ownRedirect['location'] === 'https://vidu.vn/lich-thi-hoc-ky-1',
    'nhận ' . (string) $ownRedirect['location']);

// Giới hạn 3 lượt nhấp: đã dùng 1, bấm thêm 2 lần nữa rồi lần thứ 4 phải bị chặn
request('/' . $ownAlias);
request('/' . $ownAlias);
$exhausted = request('/' . $ownAlias);
check('Vượt giới hạn lượt nhấp trả về 410',
    $exhausted['status'] === 410, 'nhận ' . $exhausted['status']);
check('Trang thông báo đã dùng hết số lượt',
    str_contains($exhausted['body'], 'hết số lượt') || str_contains($exhausted['body'], 'Đủ lượt'));

// Tìm id của liên kết để thao tác tiếp
$listPage = request('/lien-ket?q=' . rawurlencode($ownAlias));
preg_match('#/lien-ket/(\d+)/sua#', $listPage['body'], $m);
$linkId = (int) ($m[1] ?? 0);
check('Tìm được liên kết trong danh sách qua ô tìm kiếm', $linkId > 0);

if ($linkId > 0) {
    $statsPage = request('/thong-ke/' . $linkId);
    check('Trang thống kê liên kết trả về 200', $statsPage['status'] === 200, 'nhận ' . $statsPage['status']);
    check('Thống kê ghi nhận 3 lượt nhấp',
        str_contains($statsPage['body'], '>3<') || str_contains($statsPage['body'], '3</strong>'),
        'không thấy số 3 trong trang');
    check('Thống kê nêu rõ không lưu địa chỉ IP',
        str_contains($statsPage['body'], 'không lưu địa chỉ IP'));
    check('Thống kê có biểu đồ SVG', str_contains($statsPage['body'], 'class="chart'));

    // Nâng giới hạn lượt nhấp rồi bấm lại
    $edit = submit('/lien-ket/' . $linkId . '/sua', '/lien-ket/' . $linkId . '/sua', [
        'target_url' => 'https://vidu.vn/lich-thi-hoc-ky-1-ban-moi',
        'code' => $ownAlias,
        'title' => 'Lịch thi học kỳ 1 (bản cập nhật)',
        'max_clicks' => '0',
        'is_active' => '1',
        'qr_dark' => '#0f766e',
        'qr_light' => '#f0fdfa',
    ]);
    check('Sửa liên kết thành công', $edit['status'] === 302, 'nhận ' . $edit['status']);

    $afterEdit = request('/' . $ownAlias);
    check('Sửa địa chỉ đích mà giữ nguyên tên tuỳ chọn',
        $afterEdit['status'] === 302 && $afterEdit['location'] === 'https://vidu.vn/lich-thi-hoc-ky-1-ban-moi',
        'nhận ' . $afterEdit['status'] . ' → ' . (string) $afterEdit['location']);

    // Tạm dừng
    $toggle = submit('/thong-ke/' . $linkId, '/lien-ket/' . $linkId . '/trang-thai', []);
    $paused = request('/' . $ownAlias);
    check('Tạm dừng thì liên kết trả về 410', $paused['status'] === 410, 'nhận ' . $paused['status']);

    // Bật lại
    submit('/thong-ke/' . $linkId, '/lien-ket/' . $linkId . '/trang-thai', []);
    $resumed = request('/' . $ownAlias);
    check('Bật lại thì liên kết hoạt động bình thường', $resumed['status'] === 302, 'nhận ' . $resumed['status']);

    // Ghim
    submit('/thong-ke/' . $linkId, '/lien-ket/' . $linkId . '/danh-dau', []);
    $starred = request('/lien-ket?starred=1');
    check('Ghim liên kết rồi lọc theo mục đã ghim', str_contains($starred['body'], $ownAlias));
}

// Xuất CSV
$csv = request('/xuat-csv');
check('Xuất CSV trả về đúng loại tệp',
    $csv['status'] === 200 && str_contains($csv['type'], 'text/csv'), 'type: ' . $csv['type']);
check('CSV có dấu BOM để Excel đọc đúng tiếng Việt', str_starts_with($csv['body'], "\xEF\xBB\xBF"));
check('CSV có dòng tiêu đề tiếng Việt', str_contains($csv['body'], 'Mã rút gọn'));
check('CSV chứa liên kết vừa tạo', str_contains($csv['body'], $ownAlias));

// Tạo hàng loạt
$bulkA = 'lo-a-' . bin2hex(random_bytes(2));
$bulkB = 'lo-b-' . bin2hex(random_bytes(2));
$bulk = submit('/tao-hang-loat', '/tao-hang-loat', [
    'links' => "https://vidu.vn/mot | {$bulkA} | Tài liệu một\nhttps://vidu.vn/hai | {$bulkB}\nhttps://vidu.vn/ba\nkhong-phai-dia-chi-hop-le",
    'tags' => 'đợt kiểm định',
]);
check('Tạo hàng loạt chuyển về trang kết quả', $bulk['status'] === 302, 'nhận ' . $bulk['status']);
$bulkPage = request('/tao-hang-loat');
check('Tạo hàng loạt tạo được 3 liên kết', str_contains($bulkPage['body'], 'Đã tạo 3 liên kết'));
check('Tạo hàng loạt báo lỗi dòng không hợp lệ',
    str_contains($bulkPage['body'], 'chưa xử lý được'));
check('Tạo hàng loạt dùng đúng tên tuỳ chọn từng dòng', str_contains($bulkPage['body'], $bulkA));

// ---------------------------------------------------------------------------
section('8. Liên kết có mật khẩu');

$lockedAlias = 'noi-bo-' . bin2hex(random_bytes(3));
submit('/lien-ket/tao', '/lien-ket/tao', [
    'target_url' => 'https://vidu.vn/tai-lieu-noi-bo',
    'code' => $lockedAlias,
    'password' => 'matkhau123',
]);

$lockedJar = sys_get_temp_dir() . '/rutgon-test-locked-' . getmypid() . '.txt';
$locked = request('/' . $lockedAlias, null, false, $lockedJar);
check('Liên kết có mật khẩu hiện trang nhập mật khẩu',
    $locked['status'] === 200 && str_contains($locked['body'], 'name="link_password"'),
    'nhận ' . $locked['status']);
check('Trang nhập mật khẩu không để lộ địa chỉ đích',
    !str_contains($locked['body'], 'tai-lieu-noi-bo'));

$wrongPass = submit('/' . $lockedAlias, '/' . $lockedAlias, ['link_password' => 'sai-roi'], $lockedJar);
check('Mật khẩu sai thì không cho qua',
    $wrongPass['status'] === 200 && str_contains($wrongPass['body'], 'chưa đúng'),
    'nhận ' . $wrongPass['status']);

$rightPass = submit('/' . $lockedAlias, '/' . $lockedAlias, ['link_password' => 'matkhau123'], $lockedJar);
check('Mật khẩu đúng thì chuyển tới đích',
    $rightPass['status'] === 302 && str_contains((string) $rightPass['location'], 'tai-lieu-noi-bo'),
    'nhận ' . $rightPass['status'] . ' → ' . (string) $rightPass['location']);

$afterUnlock = request('/' . $lockedAlias, null, false, $lockedJar);
check('Đã mở khoá thì lần sau không phải nhập lại trong cùng phiên',
    $afterUnlock['status'] === 302, 'nhận ' . $afterUnlock['status']);
@unlink($lockedJar);

// ---------------------------------------------------------------------------
section('9. Hẹn giờ và hạn dùng');

$expiredAlias = 'het-han-' . bin2hex(random_bytes(3));
submit('/lien-ket/tao', '/lien-ket/tao', [
    'target_url' => 'https://vidu.vn/da-het-han',
    'code' => $expiredAlias,
    'expires_at' => (new DateTimeImmutable('+1 hour', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d\TH:i'),
]);
$listExpired = request('/lien-ket?q=' . rawurlencode($expiredAlias));
preg_match('#/lien-ket/(\d+)/sua#', $listExpired['body'], $m);
$expiredId = (int) ($m[1] ?? 0);

// Đặt hạn về quá khứ (biểu mẫu chặn hạn quá khứ khi tạo mới, nên sửa sau)
if ($expiredId > 0) {
    submit('/lien-ket/' . $expiredId . '/sua', '/lien-ket/' . $expiredId . '/sua', [
        'target_url' => 'https://vidu.vn/da-het-han',
        'code' => $expiredAlias,
        'expires_at' => (new DateTimeImmutable('-2 hours', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d\TH:i'),
        'is_active' => '1',
    ]);
    $expired = request('/' . $expiredAlias);
    check('Liên kết hết hạn trả về 410', $expired['status'] === 410, 'nhận ' . $expired['status']);
    check('Trang hết hạn nói rõ lý do', str_contains($expired['body'], 'hết hạn'));
}

$futureAlias = 'chua-toi-gio-' . bin2hex(random_bytes(3));
submit('/lien-ket/tao', '/lien-ket/tao', [
    'target_url' => 'https://vidu.vn/sap-cong-bo',
    'code' => $futureAlias,
    'starts_at' => (new DateTimeImmutable('+2 days', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d\TH:i'),
]);
$scheduled = request('/' . $futureAlias);
check('Liên kết chưa tới giờ trả về 410', $scheduled['status'] === 410, 'nhận ' . $scheduled['status']);
check('Trang chưa tới giờ nói rõ thời điểm bắt đầu',
    str_contains($scheduled['body'], 'chưa tới giờ') || str_contains($scheduled['body'], 'bắt đầu hoạt động'));

// ---------------------------------------------------------------------------
section('10. API với khoá');

$tokenPage = submit('/tai-khoan', '/tai-khoan/api-token', []);
$accountWithToken = request('/tai-khoan');
preg_match('/(dnl_[0-9a-f]{48})/', $accountWithToken['body'], $m);
$apiToken = $m[1] ?? '';
check('Cấp lại khoá API và hiện khoá mới một lần', $apiToken !== '', 'không tìm thấy khoá');

if ($apiToken !== '') {
    $apiAlias = 'qua-api-' . bin2hex(random_bytes(3));
    $ch = curl_init($base . '/api/rut-gon');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiToken, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'url' => 'https://vidu.vn/tu-api',
            'code' => $apiAlias,
            'title' => 'Tạo bằng API',
        ], JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 20,
    ]);
    $apiBody = (string) curl_exec($ch);
    $apiStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $apiData = json_decode($apiBody, true);
    check('API tạo liên kết trả về 201', $apiStatus === 201, 'nhận ' . $apiStatus . ': ' . substr($apiBody, 0, 120));
    check('API trả về địa chỉ rút gọn đầy đủ',
        str_contains((string) ($apiData['link']['short_url'] ?? ''), $apiAlias));
    check('API trả về sẵn địa chỉ ảnh QR',
        str_contains((string) ($apiData['link']['qr_png'] ?? ''), '.png'));
    check('API ghi thời gian kèm độ lệch +07:00',
        str_contains((string) ($apiData['link']['created_at'] ?? ''), '+07:00'),
        'nhận: ' . (string) ($apiData['link']['created_at'] ?? ''));

    // Thống kê qua API
    $ch = curl_init($base . '/api/thong-ke/' . $apiAlias);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiToken],
        CURLOPT_TIMEOUT => 20,
    ]);
    $statsBody = (string) curl_exec($ch);
    curl_close($ch);
    $statsData = json_decode($statsBody, true);
    check('API thống kê trả về đủ các mục',
        isset($statsData['daily'], $statsData['hourly'], $statsData['weekday'], $statsData['devices']));
    check('API thống kê ghi rõ múi giờ UTC+7', ($statsData['timezone'] ?? '') === 'UTC+7');

    // Sai khoá
    $ch = curl_init($base . '/api/lien-ket');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer dnl_khoa-sai-hoan-toan'],
        CURLOPT_TIMEOUT => 20,
    ]);
    curl_exec($ch);
    $badStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    check('Khoá API sai bị từ chối (401)', $badStatus === 401, 'nhận ' . $badStatus);
}

// ---------------------------------------------------------------------------
section('11. Khu quản trị (người dùng đầu tiên là quản trị viên)');

$admin = request('/quan-tri');
$isAdmin = $admin['status'] === 200;
check('Truy cập khu quản trị', $isAdmin, 'nhận ' . $admin['status']
    . ($admin['status'] === 403 ? ' (tài khoản này không phải người đầu tiên — bỏ qua phần sau)' : ''));

if ($isAdmin) {
    foreach (['/quan-tri/nguoi-dung' => 'Quản lý người dùng', '/quan-tri/lien-ket' => 'Toàn bộ liên kết',
              '/quan-tri/cai-dat' => 'Cài đặt hệ thống', '/quan-tri/nhat-ky' => 'Nhật ký'] as $path => $label) {
        $page = request($path);
        check("{$label} trả về 200", $page['status'] === 200, 'nhận ' . $page['status']);
    }

    $settingsPage = request('/quan-tri/cai-dat');
    check('Cài đặt hiện đường dẫn tệp cơ sở dữ liệu',
        str_contains($settingsPage['body'], '.sqlite'));
    check('Cài đặt hiện phiên bản SQLite', str_contains($settingsPage['body'], 'Phiên bản SQLite'));

    // Lưu thông báo trên đầu trang
    $notice = 'Thông báo kiểm định ' . bin2hex(random_bytes(2));
    submit('/quan-tri/cai-dat', '/quan-tri/cai-dat', [
        'allow_registration' => '1',
        'allow_guest_shorten' => '1',
        'block_open_redirect' => '1',
        'default_ecc' => '1',
        'announcement' => $notice,
    ]);
    $withNotice = request('/');
    check('Thông báo của quản trị viên hiện trên đầu trang', str_contains($withNotice['body'], $notice));

    // Xoá thông báo cho sạch
    submit('/quan-tri/cai-dat', '/quan-tri/cai-dat', [
        'allow_registration' => '1',
        'allow_guest_shorten' => '1',
        'block_open_redirect' => '1',
        'default_ecc' => '1',
        'announcement' => '',
    ]);

    $auditPage = request('/quan-tri/nhat-ky');
    check('Nhật ký ghi lại việc tạo liên kết', str_contains($auditPage['body'], 'Tạo liên kết'));
    check('Nhật ký ghi lại việc đăng nhập', str_contains($auditPage['body'], 'Đăng nhập'));

    // Chống tự khoá tài khoản của chính mình
    $usersPage = request('/quan-tri/nguoi-dung');
    preg_match('#/quan-tri/nguoi-dung/(\d+)#', $usersPage['body'], $m);
    $selfId = (int) ($m[1] ?? 0);
    if ($selfId > 0) {
        $selfSuspend = submit('/quan-tri/nguoi-dung', '/quan-tri/nguoi-dung/' . $selfId,
            ['action' => 'suspend']);
        $afterSelf = request('/quan-tri/nguoi-dung');
        check('Không cho quản trị viên tự khoá tài khoản của mình',
            str_contains($afterSelf['body'], 'không thể tự thay đổi') || request('/quan-tri')['status'] === 200);
    }
}

// ---------------------------------------------------------------------------
section('12. Phân quyền');

// Tạo người dùng thứ hai và kiểm tra không xem được liên kết của người khác
$otherJar = sys_get_temp_dir() . '/rutgon-test-other-' . getmypid() . '.txt';
$otherUser = 'nguoikhac' . bin2hex(random_bytes(3));
submit('/dang-ky', '/dang-ky', [
    'username' => $otherUser,
    'password' => $password,
    'password_confirm' => $password,
], $otherJar);

if ($linkId > 0) {
    $forbidden = request('/thong-ke/' . $linkId, null, false, $otherJar);
    check('Không xem được thống kê liên kết của người khác (403)',
        $forbidden['status'] === 403, 'nhận ' . $forbidden['status']);

    $forbiddenEdit = request('/lien-ket/' . $linkId . '/sua', null, false, $otherJar);
    check('Không sửa được liên kết của người khác (403)',
        $forbiddenEdit['status'] === 403, 'nhận ' . $forbiddenEdit['status']);

    $forbiddenDelete = submit('/lien-ket', '/lien-ket/' . $linkId . '/xoa', [], $otherJar);
    check('Không xoá được liên kết của người khác (403)',
        $forbiddenDelete['status'] === 403, 'nhận ' . $forbiddenDelete['status']);
}

$noAdmin = request('/quan-tri', null, false, $otherJar);
check('Người dùng thường không vào được khu quản trị (403)',
    $noAdmin['status'] === 403, 'nhận ' . $noAdmin['status']);

$mustLogin = request('/bang-dieu-khien', null, false, $guestJar);
check('Khách bị đưa về trang đăng nhập khi vào bảng điều khiển',
    $mustLogin['status'] === 302 && str_contains((string) $mustLogin['location'], '/dang-nhap'),
    'nhận ' . $mustLogin['status']);
@unlink($otherJar);

// ---------------------------------------------------------------------------
section('13. Chống chèn mã (XSS)');

$xssAlias = 'thu-xss-' . bin2hex(random_bytes(3));
submit('/lien-ket/tao', '/lien-ket/tao', [
    'target_url' => 'https://vidu.vn/binh-thuong',
    'code' => $xssAlias,
    'title' => '<script>alert("xss")</script>',
    'note' => '"><img src=x onerror=alert(1)>',
    'tags' => '<b>the</b>',
]);
$xssList = request('/lien-ket?q=' . rawurlencode($xssAlias));
check('Tiêu đề chứa thẻ script được escape',
    !str_contains($xssList['body'], '<script>alert("xss")</script>')
    && str_contains($xssList['body'], '&lt;script&gt;'),
    'trang hiển thị thẻ script thô');
check('Thẻ phân loại chứa HTML được escape',
    !str_contains($xssList['body'], '<b>the</b>'));

// ---------------------------------------------------------------------------
section('14. Đăng xuất');

$logout = submit('/tai-khoan', '/dang-xuat', []);
check('Đăng xuất chuyển về trang chủ',
    $logout['status'] === 302 && !str_contains((string) $logout['location'], '/tai-khoan'),
    'nhận ' . $logout['status'] . ' → ' . (string) $logout['location']);

$afterLogout = request('/bang-dieu-khien');
check('Sau khi đăng xuất không vào được bảng điều khiển',
    $afterLogout['status'] === 302 && str_contains((string) $afterLogout['location'], '/dang-nhap'),
    'nhận ' . $afterLogout['status']);

// ---------------------------------------------------------------------------
echo "\n" . str_repeat('─', 62) . "\n";
if ($fail > 0) {
    echo "\033[1mCác hạng mục chưa đạt:\033[0m\n";
    foreach ($failures as $item) {
        echo "  • {$item}\n";
    }
    echo "\n";
}
printf("Kết quả: \033[32m%d đạt\033[0m, %s%d lỗi\033[0m\n", $pass, $fail > 0 ? "\033[31m" : "\033[32m", $fail);
exit($fail > 0 ? 1 : 0);
