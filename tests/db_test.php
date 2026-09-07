<?php
declare(strict_types=1);

/**
 * Kiểm định lớp cơ sở dữ liệu — chạy trên loại đang cấu hình.
 *
 *   php tests/db_test.php
 *
 * Bộ này soi những chỗ SQLite và MySQL dễ khác nhau. Quan trọng nhất là các
 * hạng mục về KẾT QUẢ, không phải về lỗi: một câu lệnh sai cú pháp thì báo
 * ngay, còn một câu lệnh như
 *
 *     ("," || IFNULL(tags, "") || ",") LIKE '%,thẻ,%'
 *
 * chạy êm trên cả hai loại nhưng SQLite hiểu "||" là nối chuỗi còn MySQL hiểu
 * là phép HOẶC luận lý — nên MySQL trả về danh sách sai mà không báo gì. Chỉ
 * có kiểm định theo kết quả mới bắt được loại lỗi đó.
 *
 * Bộ kiểm định dùng cơ sở dữ liệu ĐANG cấu hình, có xoá và ghi dữ liệu, nên
 * tests/run_all.php luôn trỏ nó vào cơ sở dữ liệu tạm.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

$pass = 0;
$fail = 0;
$failures = [];

function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail, $failures;
    if ($ok) {
        $pass++;
        echo "  \033[32m✓\033[0m {$name}\n";
        return;
    }
    $fail++;
    $failures[] = $name . ($detail !== '' ? " — {$detail}" : '');
    echo "  \033[31m✗\033[0m {$name}" . ($detail !== '' ? " \033[2m— {$detail}\033[0m" : '') . "\n";
}

function section(string $title): void
{
    echo "\n\033[1m{$title}\033[0m\n";
}

/** So sánh hai danh sách không kể thứ tự. */
function cungTapHop(array $a, array $b): bool
{
    sort($a);
    sort($b);
    return $a === $b;
}

$pdo = Database::pdo();
$driver = Database::driver();

echo "\033[1mKiểm định lớp cơ sở dữ liệu\033[0m\n";
echo "Loại: {$driver} — " . Database::location() . "\n";

// ---------------------------------------------------------------------------
section('1. Kết nối và nhận diện');

check('Nhận diện đúng loại cơ sở dữ liệu',
    in_array($driver, Database::DRIVERS, true), "nhận '{$driver}'");
check('isMysql/isSqlite khớp với driver()',
    ($driver === 'mysql') === Database::isMysql() && ($driver === 'sqlite') === Database::isSqlite());
check('Đọc được phiên bản máy chủ',
    Database::serverVersion() !== '' && Database::serverVersion() !== '—',
    Database::serverVersion());
check('driverLabel có nội dung', Database::driverLabel() !== '');
check('Báo được dung lượng dữ liệu', Database::fileSize() > 0,
    Database::fileSize() . ' byte');

// ---------------------------------------------------------------------------
section('2. Cấu trúc bảng giống nhau ở cả hai loại');

// Dựng cấu trúc SQLite trong bộ nhớ để làm bản đối chiếu. Nếu một ngày ai đó
// thêm cột vào một bên mà quên bên kia, hạng mục này báo ngay.
$mau = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
Database::createSchema($mau, 'sqlite');

/** @return array<int, string> tên các cột của một bảng */
$cotCua = static function (PDO $conn, string $table): array {
    $stmt = $conn->query("SELECT * FROM {$table} LIMIT 0");
    $names = [];
    for ($i = 0; $i < $stmt->columnCount(); $i++) {
        $meta = $stmt->getColumnMeta($i);
        if (is_array($meta) && isset($meta['name'])) {
            $names[] = (string) $meta['name'];
        }
    }
    $stmt->closeCursor();
    return $names;
};

foreach (Database::tables() as $table) {
    $mongDoi = $cotCua($mau, $table);
    $thucTe = $cotCua($pdo, $table);
    $thieu = array_diff($mongDoi, $thucTe);
    $thua = array_diff($thucTe, $mongDoi);
    check("Bảng {$table} đủ " . count($mongDoi) . ' cột',
        cungTapHop($mongDoi, $thucTe),
        ($thieu !== [] ? 'thiếu: ' . implode(', ', $thieu) . '. ' : '')
        . ($thua !== [] ? 'thừa: ' . implode(', ', $thua) : ''));
}

// ---------------------------------------------------------------------------
section('3. Bảng settings (cột `key` trùng từ khoá của MySQL)');

Settings::set('kiem_dinh_khoa', 'giá trị có dấu — ✓');
check('Ghi rồi đọc lại được cài đặt',
    Settings::get('kiem_dinh_khoa') === 'giá trị có dấu — ✓',
    var_export(Settings::get('kiem_dinh_khoa'), true));

Settings::set('kiem_dinh_khoa', 'giá trị mới');
check('Ghi đè cài đặt cũ (không tạo dòng trùng)',
    Settings::get('kiem_dinh_khoa') === 'giá trị mới');

$soDong = (int) $pdo->query("SELECT COUNT(*) FROM settings WHERE `key` = 'kiem_dinh_khoa'")->fetchColumn();
check('Chỉ còn đúng một dòng cho mỗi khoá', $soDong === 1, "có {$soDong} dòng");

Settings::set('kiem_dinh_khoa', null);
check('Ghi được giá trị rỗng (NULL)', Settings::get('kiem_dinh_khoa', 'mặc-định') === null);

// ---------------------------------------------------------------------------
section('4. Dựng dữ liệu để thử truy vấn');

$pdo->exec('DELETE FROM clicks');
$pdo->exec('DELETE FROM links');
$pdo->exec('DELETE FROM audit_log');
$pdo->exec('DELETE FROM remember_tokens');
$pdo->exec('DELETE FROM login_attempts');
$pdo->exec('DELETE FROM users');

$hau = 'db' . bin2hex(random_bytes(2));
$chu = Auth::register("nguoidung{$hau}", 'MatKhauThu!2026', "thu.{$hau}@vidu.local",
    'Nguyễn Văn Thử', 'Đơn vị thử nghiệm')['user'];
$chuId = (int) $chu['id'];
check('Tạo được tài khoản', $chuId > 0);

$now = Clock::nowDt();
/** [mã, tiêu đề, thẻ, đang chạy, hết hạn, bắt đầu, tối đa, đã nhấp] */
$mau = [
    ["alpha-{$hau}", 'Kế hoạch năm học 2026', 'kế hoạch,quan trọng', 1, null, null, null, 0],
    ["beta-{$hau}", 'Biểu mẫu báo cáo', 'biểu mẫu', 1, null, null, null, 0],
    ["Gamma-{$hau}", 'Công văn có CHỮ HOA trong mã', 'công văn,quan trọng', 1, null, null, null, 0],
    ["delta-{$hau}", 'Liên kết đã tạm dừng', 'tạm dừng', 0, null, null, null, 0],
    ["epsilon-{$hau}", 'Liên kết đã hết hạn', 'hết hạn', 1,
        $now->modify('-2 days')->format('Y-m-d H:i:s'), null, null, 0],
    ["zeta-{$hau}", 'Liên kết hẹn giờ', 'hẹn giờ', 1, null,
        $now->modify('+5 days')->format('Y-m-d H:i:s'), null, 0],
    ["eta-{$hau}", 'Liên kết đã hết lượt', 'hết lượt', 1, null, null, 5, 5],
];

$chen = $pdo->prepare(
    'INSERT INTO links (code, target_url, title, tags, user_id, is_active,
                        expires_at, starts_at, max_clicks, click_count, created_at)
     VALUES (:code, :url, :title, :tags, :user, :active, :expires, :starts, :max, :clicks, :created)'
);
$ids = [];
foreach ($mau as $i => [$code, $title, $tags, $active, $expires, $starts, $max, $clicks]) {
    $chen->execute([
        ':code' => $code,
        ':url' => 'https://vidu.vn/' . $code,
        ':title' => $title,
        ':tags' => $tags,
        ':user' => $chuId,
        ':active' => $active,
        ':expires' => $expires,
        ':starts' => $starts,
        ':max' => $max,
        ':clicks' => $clicks,
        ':created' => $now->modify('-' . (20 - $i) . ' days')->format('Y-m-d H:i:s'),
    ]);
    $ids[$code] = (int) $pdo->lastInsertId();
}
check('Tạo được ' . count($mau) . ' liên kết thử', count($ids) === count($mau));

// ---------------------------------------------------------------------------
section('5. Tra cứu không phân biệt chữ hoa/thường');

check('Tìm mã viết thường ra liên kết viết hoa',
    LinkService::findByCode('gamma-' . $hau) !== null);
check('Tìm mã viết hoa ra liên kết viết thường',
    LinkService::findByCode(strtoupper('alpha-' . $hau)) !== null);
check('Mã đã dùng thì không còn trống (khác kiểu chữ vẫn tính là trùng)',
    !LinkService::isCodeAvailable(strtoupper('beta-' . $hau)));
check('Tên đăng nhập viết hoa vẫn đăng nhập được',
    (Auth::findByLogin(strtoupper("nguoidung{$hau}"))['username'] ?? '') === "nguoidung{$hau}");
check('Địa chỉ email viết hoa vẫn tìm ra tài khoản',
    (Auth::findByLogin(strtoupper("thu.{$hau}@vidu.local"))['username'] ?? '') === "nguoidung{$hau}");

// ---------------------------------------------------------------------------
section('6. Lọc theo thẻ (chỗ SQLite và MySQL dễ ra kết quả khác nhau)');

$theoThe = static function (string $tag) use ($chuId): array {
    $ket = LinkService::paginate(['tag' => $tag, 'user_id' => $chuId, 'per_page' => 96]);
    return array_map(static fn(array $r): string => (string) $r['code'], $ket['rows']);
};

check('Thẻ có ở nhiều liên kết trả về đúng cả hai',
    cungTapHop($theoThe('quan trọng'), ["alpha-{$hau}", "Gamma-{$hau}"]),
    'nhận: ' . implode(', ', $theoThe('quan trọng')));
check('Thẻ đứng đầu danh sách thẻ tìm được',
    cungTapHop($theoThe('kế hoạch'), ["alpha-{$hau}"]),
    'nhận: ' . implode(', ', $theoThe('kế hoạch')));
check('Thẻ là thẻ duy nhất của liên kết tìm được',
    cungTapHop($theoThe('biểu mẫu'), ["beta-{$hau}"]),
    'nhận: ' . implode(', ', $theoThe('biểu mẫu')));
check('Thẻ không có thì trả về danh sách trống',
    $theoThe('thẻ-không-tồn-tại') === [],
    'nhận: ' . implode(', ', $theoThe('thẻ-không-tồn-tại')));
// Đây là hạng mục bắt lỗi "||": MySQL hiểu "||" là HOẶC nên điều kiện luôn
// đúng và trả về HẾT liên kết, chứ không phải chỉ liên kết mang thẻ đó.
check('Lọc theo thẻ không trả về toàn bộ liên kết',
    count($theoThe('kế hoạch')) < count($mau),
    'trả về ' . count($theoThe('kế hoạch')) . '/' . count($mau) . ' liên kết');

// ---------------------------------------------------------------------------
section('7. Ô tìm kiếm');

$timKiem = static function (string $q) use ($chuId): array {
    $ket = LinkService::paginate(['q' => $q, 'user_id' => $chuId, 'per_page' => 96]);
    return array_map(static fn(array $r): string => (string) $r['code'], $ket['rows']);
};

check('Tìm theo mã', cungTapHop($timKiem("beta-{$hau}"), ["beta-{$hau}"]),
    'nhận: ' . implode(', ', $timKiem("beta-{$hau}")));
check('Tìm theo tiêu đề', cungTapHop($timKiem('Biểu mẫu báo cáo'), ["beta-{$hau}"]),
    'nhận: ' . implode(', ', $timKiem('Biểu mẫu báo cáo')));
check('Tìm theo thẻ trong ô tìm kiếm', cungTapHop($timKiem('hẹn giờ'), ["zeta-{$hau}"]),
    'nhận: ' . implode(', ', $timKiem('hẹn giờ')));
check('Tìm theo địa chỉ đích', cungTapHop($timKiem('vidu.vn/delta-' . $hau), ["delta-{$hau}"]),
    'nhận: ' . implode(', ', $timKiem('vidu.vn/delta-' . $hau)));
check('Từ khoá không có thì trả về trống', $timKiem('khong-co-tu-khoa-nay-dau') === []);

// ---------------------------------------------------------------------------
section('8. Lọc theo trạng thái');

$theoTrangThai = static function (string $status) use ($chuId): array {
    $ket = LinkService::paginate(['status' => $status, 'user_id' => $chuId, 'per_page' => 96]);
    return array_map(static fn(array $r): string => (string) $r['code'], $ket['rows']);
};

check('Đang chạy: đúng ba liên kết',
    cungTapHop($theoTrangThai('active'), ["alpha-{$hau}", "beta-{$hau}", "Gamma-{$hau}"]),
    'nhận: ' . implode(', ', $theoTrangThai('active')));
check('Tạm dừng', cungTapHop($theoTrangThai('paused'), ["delta-{$hau}"]),
    'nhận: ' . implode(', ', $theoTrangThai('paused')));
check('Hết hạn', cungTapHop($theoTrangThai('expired'), ["epsilon-{$hau}"]),
    'nhận: ' . implode(', ', $theoTrangThai('expired')));
check('Hẹn giờ', cungTapHop($theoTrangThai('scheduled'), ["zeta-{$hau}"]),
    'nhận: ' . implode(', ', $theoTrangThai('scheduled')));
check('Hết lượt', cungTapHop($theoTrangThai('exhausted'), ["eta-{$hau}"]),
    'nhận: ' . implode(', ', $theoTrangThai('exhausted')));

// ---------------------------------------------------------------------------
section('9. Sắp xếp');

$sapXep = static function (string $sort) use ($chuId): array {
    $ket = LinkService::paginate(['sort' => $sort, 'user_id' => $chuId, 'per_page' => 96]);
    return array_map(static fn(array $r): string => (string) $r['code'], $ket['rows']);
};

// Sắp theo mã phải không phân biệt hoa/thường: Gamma nằm giữa epsilon và eta.
$theoMa = $sapXep('code');
$viTri = array_search("Gamma-{$hau}", $theoMa, true);
check('Sắp theo mã không phân biệt chữ hoa (Gamma sau epsilon/eta)',
    is_int($viTri) && $viTri > 0
    && strtolower($theoMa[$viTri - 1]) < strtolower("gamma-{$hau}"),
    'thứ tự: ' . implode(', ', $theoMa));
check('Sắp mới nhất trước và cũ nhất trước là ngược nhau',
    $sapXep('newest') === array_reverse($sapXep('oldest')));
check('Sắp theo lượt nhấp giảm dần đưa liên kết hết lượt lên đầu',
    ($sapXep('clicks')[0] ?? '') === "eta-{$hau}",
    'đầu danh sách: ' . ($sapXep('clicks')[0] ?? '(trống)'));
check('Sắp theo lần nhấp gần nhất chạy được (không dùng NULLS LAST)',
    count($sapXep('recent_click')) === count($mau));

// ---------------------------------------------------------------------------
section('10. Ghi lượt nhấp và số tổng hợp');

$link = LinkService::findByCode("alpha-{$hau}");
for ($i = 0; $i < 3; $i++) {
    LinkService::recordClick($link);
}
$sauKhiNhap = LinkService::findByCode("alpha-{$hau}");
check('click_count tăng đúng 3', (int) $sauKhiNhap['click_count'] === 3,
    'nhận ' . $sauKhiNhap['click_count']);
check('last_click_at được ghi', ($sauKhiNhap['last_click_at'] ?? '') !== '');

$soLuot = (int) $pdo->query('SELECT COUNT(*) FROM clicks')->fetchColumn();
check('Bảng clicks có 3 dòng', $soLuot === 3, "nhận {$soLuot}");

$luot = $pdo->query('SELECT * FROM clicks ORDER BY id LIMIT 1')->fetch();
check('click_date đúng dạng Y-m-d',
    (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $luot['click_date']),
    (string) $luot['click_date']);
check('clicked_at đúng dạng Y-m-d H:i:s',
    (bool) preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $luot['clicked_at']),
    (string) $luot['clicked_at']);
check('click_hour trong khoảng 0–23',
    (int) $luot['click_hour'] >= 0 && (int) $luot['click_hour'] <= 23);
check('Không lưu địa chỉ IP của người nhấp',
    !in_array('ip', array_keys($luot), true)
    && !in_array('visitor_ip', array_keys($luot), true));

// ---------------------------------------------------------------------------
section('11. Thống kê (SUM của MySQL trả về chuỗi, của SQLite trả về số)');

$tong = Stats::overview($chuId);
check('Tổng số liên kết đúng', $tong['total_links'] === count($mau),
    'nhận ' . var_export($tong['total_links'], true));
check('Tổng lượt nhấp là số nguyên', is_int($tong['total_clicks']),
    gettype($tong['total_clicks']));
check('Tổng lượt nhấp đúng (3 của alpha + 5 của eta)', $tong['total_clicks'] === 8,
    'nhận ' . $tong['total_clicks']);
check('Số liên kết đang chạy đúng', $tong['active_links'] === 3,
    'nhận ' . var_export($tong['active_links'], true));
check('Lượt nhấp hôm nay là số nguyên và bằng 3',
    is_int($tong['clicks_today']) && $tong['clicks_today'] === 3,
    'nhận ' . var_export($tong['clicks_today'], true));

$chuoiNgay = Stats::dailySeries(7, null, $chuId);
check('Chuỗi theo ngày đủ 7 mốc', count($chuoiNgay) === 7);
check('Mỗi mốc trong chuỗi có số nguyên',
    is_int($chuoiNgay[0]['clicks'] ?? null) && is_int($chuoiNgay[0]['unique'] ?? null));
check('Tổng chuỗi 7 ngày bằng 3 lượt vừa ghi',
    array_sum(array_column($chuoiNgay, 'clicks')) === 3,
    'nhận ' . array_sum(array_column($chuoiNgay, 'clicks')));

$danhSachThe = LinkService::tagCounts($chuId);
check('Đọc được danh sách thẻ', count($danhSachThe) > 0, count($danhSachThe) . ' thẻ');

// ---------------------------------------------------------------------------
section('12. Ràng buộc toàn vẹn');

try {
    $pdo->prepare('INSERT INTO links (code, target_url, created_at) VALUES (:c, :u, :t)')
        ->execute([':c' => "beta-{$hau}", ':u' => 'https://vidu.vn/trung', ':t' => Clock::now()]);
    check('Mã trùng bị từ chối', false, 'cơ sở dữ liệu vẫn cho thêm');
} catch (PDOException) {
    check('Mã trùng bị từ chối', true);
}

// Xoá liên kết phải xoá luôn lượt nhấp của nó (khoá ngoại ON DELETE CASCADE)
$pdo->prepare('DELETE FROM links WHERE id = :id')->execute([':id' => $ids["alpha-{$hau}"]]);
$conLai = (int) $pdo->query('SELECT COUNT(*) FROM clicks')->fetchColumn();
check('Xoá liên kết thì lượt nhấp của nó cũng mất (CASCADE)', $conLai === 0,
    "còn {$conLai} dòng");

// ---------------------------------------------------------------------------
section('13. Dồn nén cơ sở dữ liệu');

try {
    Database::vacuum();
    check('Chạy được lệnh dồn nén', true);
} catch (Throwable $e) {
    check('Chạy được lệnh dồn nén', false, $e->getMessage());
}

// OPTIMIZE TABLE của MySQL trả về một bảng kết quả. Nếu không đọc hết thì kết
// nối bị treo và MỌI câu lệnh sau đó đều lỗi — tức là bấm nút "Dồn nén" ở
// trang quản trị xong thì trang lỗi. Hạng mục này canh đúng chỗ đó.
try {
    $demSauDonNen = (int) $pdo->query('SELECT COUNT(*) FROM links')->fetchColumn();
    check('Vẫn truy vấn được ngay sau khi dồn nén', $demSauDonNen >= 0);
} catch (Throwable $e) {
    check('Vẫn truy vấn được ngay sau khi dồn nén', false, $e->getMessage());
}

try {
    Database::vacuum();
    $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    check('Gọi dồn nén hai lần liên tiếp vẫn được', true);
} catch (Throwable $e) {
    check('Gọi dồn nén hai lần liên tiếp vẫn được', false, $e->getMessage());
}

// ---------------------------------------------------------------------------
section('14. Dọn sạch sau khi kiểm định');

// Bộ này ghi dữ liệu trực tiếp vào bảng, nên phải trả cơ sở dữ liệu về trạng
// thái trắng. Nếu để lại một tài khoản thì bộ kiểm định HTTP chạy sau sẽ không
// còn là "người dùng đầu tiên" và mất quyền quản trị viên.
foreach (['clicks', 'links', 'remember_tokens', 'login_attempts', 'audit_log', 'users'] as $table) {
    $pdo->exec("DELETE FROM {$table}");
}
$pdo->prepare('DELETE FROM settings WHERE `key` = :k')->execute([':k' => 'kiem_dinh_khoa']);

$conSot = [];
foreach (['users', 'links', 'clicks', 'remember_tokens', 'login_attempts', 'audit_log'] as $table) {
    $con = (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    if ($con > 0) {
        $conSot[] = "{$table}={$con}";
    }
}
check('Không còn dòng dữ liệu nào sót lại', $conSot === [], implode(', ', $conSot));
check('Không còn cài đặt thử nghiệm',
    (int) $pdo->query("SELECT COUNT(*) FROM settings WHERE `key` = 'kiem_dinh_khoa'")->fetchColumn() === 0);

// ---------------------------------------------------------------------------
echo "\n" . str_repeat('─', 62) . "\n";
if ($failures !== []) {
    echo "\033[1mCác hạng mục chưa đạt:\033[0m\n";
    foreach ($failures as $item) {
        echo "  • {$item}\n";
    }
    echo "\n";
}
printf("Kết quả: \033[32m%d đạt\033[0m, %s\n", $pass,
    $fail > 0 ? "\033[31m{$fail} lỗi\033[0m" : "\033[32m0 lỗi\033[0m");

exit($fail === 0 ? 0 : 1);
