<?php
declare(strict_types=1);

/**
 * Tạo DỮ LIỆU MẪU để xem thử hệ thống trước khi dùng thật.
 *
 *   php tools/tao-du-lieu-mau.php
 *   php tools/tao-du-lieu-mau.php --force     # ghi đè dữ liệu đang có
 *
 * Sinh 2 tài khoản, 8 liên kết và khoảng 900 lượt nhấp rải trong 30 ngày
 * theo dạng thật (ngày làm việc đông hơn cuối tuần, giờ hành chính đông hơn
 * đêm), để mọi biểu đồ và bảng thống kê đều có số liệu xem được.
 *
 * Đây cũng chính là bộ dữ liệu dùng để chụp toàn bộ ảnh minh hoạ trong
 * docs/huong-dan-su-dung.md, nên chạy xong là bạn xem được đúng những gì
 * tài liệu mô tả. Chỉ mật khẩu là khác (đặt rõ là mật khẩu mẫu).
 *
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │ ⚠ CHỈ DÙNG ĐỂ XEM THỬ, TẬP HUẤN HOẶC TRÌNH BÀY.                    │
 * │   Tệp này KHÔNG có trong bản phát hành .zip và bị chặn truy cập     │
 * │   từ web. Tuyệt đối KHÔNG chạy trên hệ thống đang dùng thật:        │
 * │   nó XOÁ HẾT dữ liệu hiện có và tạo tài khoản có mật khẩu công khai.│
 * └──────────────────────────────────────────────────────────────────────┘
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Công cụ này chỉ chạy được từ dòng lệnh.');
}

require dirname(__DIR__) . '/app/bootstrap.php';

const MAT_KHAU_MAU = 'DuLieuMau!2026';
const TEN_QUAN_TRI = 'truonganhtuan';
const TEN_CAN_BO = 'ntbich';

$force = in_array('--force', $argv, true);
$pdo = Database::pdo();

/** Đường dẫn tệp cơ sở dữ liệu, rút gọn cho dễ đọc. */
function duongDanCsdl(): string
{
    $path = Database::path();
    return realpath($path) ?: $path;
}

// ------------------------------------------------------------------ chốt an toàn
$soNguoiDung = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$soLienKet = (int) $pdo->query('SELECT COUNT(*) FROM links')->fetchColumn();

if (($soNguoiDung > 0 || $soLienKet > 0) && !$force) {
    echo "\n\033[31m⚠ DỪNG LẠI\033[0m\n";
    echo "Cơ sở dữ liệu đang có sẵn dữ liệu:\n";
    echo "  · {$soNguoiDung} người dùng\n";
    echo "  · {$soLienKet} liên kết\n\n";
    echo "Tệp cơ sở dữ liệu: " . duongDanCsdl() . "\n\n";
    echo "Công cụ này sẽ XOÁ HẾT rồi tạo lại dữ liệu mẫu.\n";
    echo "Nếu đây là hệ thống đang dùng thật thì \033[1mđừng chạy\033[0m.\n\n";
    echo "Chắc chắn muốn xoá, chạy lại kèm --force:\n";
    echo "  php tools/tao-du-lieu-mau.php --force\n\n";
    exit(1);
}

echo "\nĐang tạo dữ liệu mẫu vào " . duongDanCsdl() . " …\n";

$pdo->exec('DELETE FROM clicks');
$pdo->exec('DELETE FROM links');
$pdo->exec('DELETE FROM users');
$pdo->exec('DELETE FROM audit_log');
$pdo->exec('DELETE FROM login_attempts');
$pdo->exec('DELETE FROM remember_tokens');

// ------------------------------------------------------------------ người dùng
// Người đầu tiên tự động thành quản trị viên (xem Auth::register)
$quanTri = Auth::register(TEN_QUAN_TRI, MAT_KHAU_MAU, 'gdpt.gdtx@vidu.local',
    'Trương Anh Tuấn', 'Phòng GDPT-GDTX — Sở GDĐT Đồng Nai');
$canBo = Auth::register(TEN_CAN_BO, MAT_KHAU_MAU, '', 'Nguyễn Thị Bích',
    'Trường THPT Trấn Biên');

$admin = $quanTri['user'];
$staff = $canBo['user'];

$pdo->prepare('UPDATE users SET last_login_at = :a, login_count = 47 WHERE id = :id')
    ->execute([':a' => Clock::nowDt()->modify('-2 hours')->format('Y-m-d H:i:s'), ':id' => $admin['id']]);
$pdo->prepare('UPDATE users SET last_login_at = :a, login_count = 12 WHERE id = :id')
    ->execute([':a' => Clock::nowDt()->modify('-3 days')->format('Y-m-d H:i:s'), ':id' => $staff['id']]);

// ------------------------------------------------------------------- liên kết
$now = Clock::nowDt();

// [mã, địa chỉ đích, tiêu đề, thẻ, số lượt, tạo cách đây (ngày),
//  ghim, mật khẩu, hết hạn, chủ sở hữu, đang chạy]
$danhSach = [
    ['tuyen-sinh-10', 'https://sgddt.dongnai.gov.vn/thong-bao/tuyen-sinh-lop-10-nam-hoc-2026-2027.pdf',
        'Thông báo tuyển sinh lớp 10 năm học 2026–2027', 'tuyển sinh,2026', 340, 28, 1, null, null, 'admin', 1],
    ['tap-huan-nls', 'https://drive.google.com/file/d/1aBcD2efGhIjK3lMnOpQrStUvWxYz/view',
        'Tài liệu tập huấn năng lực số theo Thông tư 02/2025', 'tập huấn,năng lực số', 214, 24, 0, null, null, 'admin', 1],
    ['ky-thi-hsg', 'https://sgddt.dongnai.gov.vn/ke-hoach/ky-thi-hoc-sinh-gioi-cap-tinh-2026.pdf',
        'Kế hoạch kỳ thi chọn học sinh giỏi cấp tỉnh', 'kỳ thi,HSG', 156, 21, 1, null, null, 'admin', 1],
    ['lich-cong-tac-t9', 'https://sgddt.dongnai.gov.vn/lich-cong-tac/thang-9-2026.aspx',
        'Lịch công tác tháng 9/2026', 'lịch công tác', 118, 18, 0, null, null, 'admin', 1],
    ['bao-cao-truong', 'https://sgddt.dongnai.gov.vn/bieu-mau/bao-cao-hoc-ky-1.xlsx',
        'Biểu mẫu báo cáo học kỳ 1 — THPT Trấn Biên', 'biểu mẫu,hk1', 86, 15, 0, null, null, 'staff', 1],
    ['cong-van-1234', 'https://sgddt.dongnai.gov.vn/van-ban/1234-sgddt-gdpt.pdf',
        'Công văn 1234/SGDĐT-GDPT về công tác đầu năm', 'công văn', 62, 12, 0, null,
        $now->modify('+9 days')->format('Y-m-d H:i:s'), 'admin', 1],
    ['de-thi-thu-2026', 'https://sgddt.dongnai.gov.vn/de-thi/de-thi-thu-tot-nghiep-2026.pdf',
        'Đề thi thử tốt nghiệp THPT 2026 (nội bộ)', 'đề thi,nội bộ', 44, 9, 0, 'matkhau2026', null, 'admin', 1],
    ['huong-dan-nhap-diem', 'https://sgddt.dongnai.gov.vn/huong-dan/nhap-diem-vnedu.pdf',
        'Hướng dẫn nhập điểm trên VnEdu (bản cũ)', 'hướng dẫn', 31, 6, 0, null, null, 'admin', 0],
];

$themLienKet = $pdo->prepare(
    'INSERT INTO links (code, target_url, title, tags, user_id, creator_ip, password_hash,
                        starts_at, expires_at, max_clicks, is_active, is_starred,
                        qr_dark, qr_light, click_count, unique_count, last_click_at, created_at)
     VALUES (:code, :url, :title, :tags, :user, :ip, :pass, NULL, :expires, NULL, :active, :star,
             :dark, :light, 0, 0, NULL, :created)'
);

$dsLienKet = [];
foreach ($danhSach as [$code, $url, $title, $tags, $soLuot, $ngayTruoc, $ghim, $matKhau, $hetHan, $chuSoHuu, $dangChay]) {
    $themLienKet->execute([
        ':code' => $code,
        ':url' => $url,
        ':title' => $title,
        ':tags' => $tags,
        ':user' => $chuSoHuu === 'staff' ? (int) $staff['id'] : (int) $admin['id'],
        ':ip' => '10.0.0.5',
        ':pass' => $matKhau !== null ? password_hash($matKhau, PASSWORD_DEFAULT) : null,
        ':expires' => $hetHan,
        ':active' => $dangChay,
        ':star' => $ghim,
        ':dark' => '#0b1220',
        ':light' => '#ffffff',
        ':created' => $now->modify("-{$ngayTruoc} days")->format('Y-m-d H:i:s'),
    ]);
    $dsLienKet[$code] = ['id' => (int) $pdo->lastInsertId(), 'total' => $soLuot, 'days' => $ngayTruoc];
}

// ----------------------------------------------------------------- lượt nhấp
$thietBi = [
    ['Chrome', 'Windows', 'Máy tính', 'VN', 'vi-VN'],
    ['Chrome', 'Android', 'Điện thoại', 'VN', 'vi-VN'],
    ['Safari', 'iOS', 'Điện thoại', 'VN', 'vi-VN'],
    ['Safari', 'macOS', 'Máy tính', 'VN', 'vi-VN'],
    ['Edge', 'Windows', 'Máy tính', 'VN', 'vi-VN'],
    ['Firefox', 'Windows', 'Máy tính', 'VN', 'vi-VN'],
    ['Zalo', 'Android', 'Điện thoại', 'VN', 'vi-VN'],
    ['Zalo', 'iOS', 'Điện thoại', 'VN', 'vi-VN'],
    ['Safari', 'iPadOS', 'Máy tính bảng', 'VN', 'vi-VN'],
    ['Cốc Cốc', 'Windows', 'Máy tính', 'VN', 'vi-VN'],
    ['Chrome', 'Windows', 'Máy tính', 'US', 'en-US'],
];
$nguon = [
    ['zalo.me', 'https://zalo.me/'],
    ['', ''],
    ['', ''],
    ['sgddt.dongnai.gov.vn', 'https://sgddt.dongnai.gov.vn/tin-tuc'],
    ['facebook.com', 'https://www.facebook.com/'],
    ['', ''],
    ['mail.google.com', 'https://mail.google.com/'],
];
// Giờ hành chính xuất hiện nhiều lần hơn trong bảng nên được chọn nhiều hơn
$gioTrongNgay = [7, 8, 8, 9, 9, 10, 10, 11, 13, 14, 14, 15, 15, 16, 17, 19, 20, 21, 6, 12, 18, 22];

$themLuot = $pdo->prepare(
    'INSERT INTO clicks (link_id, clicked_at, click_date, click_hour, weekday, visitor_hash, is_unique,
                         referer, referer_host, browser, os, device, country, language, source, is_bot)
     VALUES (:l, :at, :d, :h, :w, :v, :u, :ref, :refhost, :b, :os, :dev, :c, :lang, :src, :bot)'
);

$pdo->beginTransaction();
foreach ($dsLienKet as $code => $tin) {
    $daThay = [];
    $traiRong = max(1, $tin['days']);

    for ($k = 0; $k < $tin['total']; $k++) {
        // Dồn nhiều vào những ngày đầu sau khi phát hành, thưa dần về sau
        $tyLe = ($k / $tin['total']) ** 1.9;
        $ngay = $now->modify('-' . ($traiRong - (int) round($tyLe * $traiRong)) . ' days');

        // Cuối tuần bớt 40% lượt
        $thu = (int) $ngay->format('w');
        if (($thu === 0 || $thu === 6) && random_int(1, 100) <= 40) {
            continue;
        }

        $luc = $ngay->setTime($gioTrongNgay[random_int(0, count($gioTrongNgay) - 1)], random_int(0, 59), random_int(0, 59));
        if ($luc > $now) {
            $luc = $now->modify('-' . random_int(5, 300) . ' minutes');
        }

        $tb = $thietBi[random_int(0, count($thietBi) - 1)];
        $ng = $nguon[random_int(0, count($nguon) - 1)];
        $quetQr = random_int(1, 100) <= 26;
        $laRobot = random_int(1, 100) <= 3;

        // Một số khách quay lại nhiều lần → unique_count nhỏ hơn click_count
        $khach = 'v' . random_int(1, (int) max(3, $tin['total'] * 0.42));
        $dauVet = substr(hash('sha256', $code . $khach), 0, 32);
        $lanDau = isset($daThay[$dauVet]) ? 0 : 1;
        $daThay[$dauVet] = true;

        $themLuot->execute([
            ':l' => $tin['id'],
            ':at' => $luc->format('Y-m-d H:i:s'),
            ':d' => $luc->format('Y-m-d'),
            ':h' => (int) $luc->format('G'),
            ':w' => (int) $luc->format('w'),
            ':v' => $dauVet,
            ':u' => $lanDau,
            ':ref' => $quetQr || $ng[1] === '' ? null : $ng[1],
            ':refhost' => $quetQr || $ng[0] === '' ? null : $ng[0],
            ':b' => $tb[0],
            ':os' => $tb[1],
            ':dev' => $tb[2],
            ':c' => $tb[3],
            ':lang' => $tb[4],
            ':src' => $quetQr ? 'qr' : null,
            ':bot' => $laRobot ? 1 : 0,
        ]);
    }

    // Đồng bộ số tổng hợp với bảng clicks
    $pdo->prepare(
        'UPDATE links SET
            click_count   = (SELECT COUNT(*) FROM clicks WHERE link_id = :id),
            unique_count  = (SELECT IFNULL(SUM(is_unique), 0) FROM clicks WHERE link_id = :id),
            last_click_at = (SELECT MAX(clicked_at) FROM clicks WHERE link_id = :id)
         WHERE id = :id'
    )->execute([':id' => $tin['id']]);
}
$pdo->commit();

// ------------------------------------------------------------------- nhật ký
$nhatKy = [
    ['link_create', 'tuyen-sinh-10 → sgddt.dongnai.gov.vn/thong-bao/tuyen-sinh…', 28],
    ['login', 'Đăng nhập thành công', 26],
    ['link_create', 'tap-huan-nls → drive.google.com/file/d/1aBcD2ef…', 24],
    ['link_update', 'lich-cong-tac-t8 → lich-cong-tac-t9', 9],
    ['admin_settings', 'Cập nhật cài đặt hệ thống', 6],
    ['link_toggle', 'huong-dan-nhap-diem → tạm dừng', 4],
    ['link_create', 'de-thi-thu-2026 → sgddt.dongnai.gov.vn/de-thi/…', 3],
    ['login', 'Đăng nhập thành công', 0],
];
foreach ($nhatKy as [$hanhDong, $chiTiet, $ngayTruoc]) {
    $pdo->prepare(
        'INSERT INTO audit_log (user_id, actor, action, detail, ip, created_at)
         VALUES (:u, :a, :ac, :d, :ip, :at)'
    )->execute([
        ':u' => (int) $admin['id'],
        ':a' => TEN_QUAN_TRI,
        ':ac' => $hanhDong,
        ':d' => $chiTiet,
        ':ip' => '10.0.0.5',
        ':at' => $now->modify("-{$ngayTruoc} days")->modify('-' . random_int(1, 300) . ' minutes')->format('Y-m-d H:i:s'),
    ]);
}

Settings::set('announcement', 'Đây là DỮ LIỆU MẪU để xem thử. Hãy xoá trước khi dùng thật.');

// -------------------------------------------------------------------- tổng kết
$tongLuot = (int) $pdo->query('SELECT COUNT(*) FROM clicks')->fetchColumn();

echo "\n\033[1m✓ Đã tạo xong dữ liệu mẫu\033[0m\n";
echo str_repeat('─', 64) . "\n";
printf("  %-22s %s\n", 'Người dùng', '2');
printf("  %-22s %s\n", 'Liên kết', (string) count($dsLienKet));
printf("  %-22s %s\n", 'Lượt nhấp', number_format($tongLuot, 0, ',', '.'));
echo str_repeat('─', 64) . "\n";
echo "\n\033[1mTÀI KHOẢN QUẢN TRỊ\033[0m — xem được toàn bộ dữ liệu của mọi người\n";
printf("  Tên đăng nhập : \033[1m%s\033[0m\n", TEN_QUAN_TRI);
printf("  Mật khẩu      : \033[1m%s\033[0m\n", MAT_KHAU_MAU);
printf("  Mã dự phòng   : %s\n", $quanTri['recovery_code']);
echo "\n\033[1mTÀI KHOẢN NGƯỜI DÙNG THƯỜNG\033[0m — chỉ thấy liên kết của mình\n";
printf("  Tên đăng nhập : \033[1m%s\033[0m\n", TEN_CAN_BO);
printf("  Mật khẩu      : \033[1m%s\033[0m\n", MAT_KHAU_MAU);
printf("  Mã dự phòng   : %s\n", $canBo['recovery_code']);
echo "\n\033[1mLIÊN KẾT CÓ MẬT KHẨU BẢO VỆ\033[0m\n";
echo "  /de-thi-thu-2026 → mật khẩu: matkhau2026\n";

echo "\n\033[1mNơi xem toàn bộ dữ liệu (đăng nhập bằng tài khoản quản trị)\033[0m\n";
echo "  /quan-tri                     Tổng quan toàn hệ thống\n";
echo "  /quan-tri/lien-ket            TẤT CẢ liên kết của mọi người\n";
echo "  /quan-tri/nguoi-dung          Danh sách người dùng\n";
echo "  /quan-tri/nhat-ky             Nhật ký hoạt động\n";
echo "  /bang-dieu-khien?pham-vi=tat-ca   Bảng điều khiển phạm vi toàn hệ thống\n";
echo "  /thong-ke?pham-vi=tat-ca          Thống kê toàn hệ thống\n";
echo "  /xuat-csv?pham-vi=tat-ca          Xuất CSV toàn bộ liên kết\n";

echo "\n\033[33m⚠ Nhớ xoá dữ liệu mẫu trước khi dùng thật:\033[0m\n";
echo "  Xoá tệp " . duongDanCsdl() . " rồi mở lại trang chủ,\n";
echo "  hệ thống sẽ tạo cơ sở dữ liệu trắng và bạn đăng ký tài khoản mới.\n\n";
