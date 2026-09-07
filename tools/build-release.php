<?php
declare(strict_types=1);

/**
 * Đóng gói bản phát hành để tải lên shared hosting cPanel.
 *
 *   php tools/build-release.php            # lấy số phiên bản từ CHANGELOG.md
 *   php tools/build-release.php 1.1.0      # chỉ định số phiên bản
 *
 * Tệp .zip tạo ra ở thư mục dist/ và CHỈ chứa những gì cần để chạy:
 * không có tests/, tools/, ảnh tài liệu, hay dữ liệu của bản cài đặt khác.
 */

$root = dirname(__DIR__);
$distDir = $root . '/dist';

// ---------------------------------------------------------------- phiên bản
$version = $argv[1] ?? null;
if ($version === null) {
    $changelog = (string) file_get_contents($root . '/CHANGELOG.md');
    if (preg_match('/^## \[(\d+\.\d+\.\d+)\]/m', $changelog, $m)) {
        $version = $m[1];
    } else {
        exit("Không đọc được số phiên bản từ CHANGELOG.md. Hãy truyền vào: php tools/build-release.php 1.0.0\n");
    }
}
if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
    exit("Số phiên bản phải có dạng X.Y.Z, nhận được: {$version}\n");
}

/**
 * Những gì ĐƯA VÀO bản phát hành.
 * Ghi tường minh (danh sách trắng) thay vì loại trừ, để không bao giờ lỡ
 * đóng gói tệp lạ nằm trong thư mục làm việc.
 */
$include = [
    'index.php',
    '.htaccess',
    'README.md',
    'CHANGELOG.md',
    'LICENSE',
    'app/',
    'assets/',
    'data/',
    'docs/',
    // Công cụ chuyển dữ liệu giữa SQLite và MySQL. Người đã cài rồi mới cần,
    // nên phải có trong bản tải về. An toàn để đóng gói: chỉ chạy được từ dòng
    // lệnh và thư mục tools/ bị .htaccess chặn.
    'tools/chuyen-doi-csdl.php',
];

/** Những gì LOẠI RA, kể cả khi nằm trong các mục ở trên. */
$exclude = [
    '#^tests/#',
    // tools/ bị loại, TRỪ những tệp ghi tường minh trong $include ở trên.
    // tao-du-lieu-mau.php tạo tài khoản có mật khẩu công khai nên tuyệt đối
    // không được đi theo bản phát hành.
    '#^tools/(?!chuyen-doi-csdl\.php$)#',
    '#^dist/#',
    '#^docs/images/#',       // 5 MB ảnh, chỉ cần xem trên GitHub
    '#(^|/)\.git#',            // .gitignore, .gitattributes, .git/
    '#^node_modules/#',
    '#/\.DS_Store$#',
    '#\.sqlite(-wal|-shm)?$#',  // KHÔNG bao giờ đóng gói dữ liệu thật
    '#\.log$#',
    '#^app/config\.local\.php$#',  // cấu hình riêng của từng máy chủ
];

// -------------------------------------------------------------- gom danh sách
/** @return array<int, string> đường dẫn tương đối */
function collect(string $root, array $include, array $exclude): array
{
    $files = [];

    $accept = static function (string $rel) use ($exclude): bool {
        foreach ($exclude as $pattern) {
            if (preg_match($pattern, $rel) === 1) {
                return false;
            }
        }
        return true;
    };

    foreach ($include as $entry) {
        $path = $root . '/' . rtrim($entry, '/');

        if (is_file($path)) {
            if ($accept($entry)) {
                $files[] = $entry;
            }
            continue;
        }
        if (!is_dir($path)) {
            continue; // mục không có thì bỏ qua (ví dụ LICENSE chưa tạo)
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            $rel = ltrim(str_replace($root, '', $item->getPathname()), '/');
            if (!$accept($rel)) {
                continue;
            }
            if ($item->isFile()) {
                $files[] = $rel;
            }
        }
    }

    // Tệp ẩn cần giữ (.htaccess của từng thư mục) — iterator đã bỏ dấu chấm
    foreach (['app/.htaccess', 'data/.htaccess'] as $hidden) {
        if (is_file($root . '/' . $hidden) && $accept($hidden)) {
            $files[] = $hidden;
        }
    }

    $files = array_values(array_unique($files));
    sort($files);
    return $files;
}

$files = collect($root, $include, $exclude);
if ($files === []) {
    exit("Không tìm thấy tệp nào để đóng gói.\n");
}

// ---------------------------------------------------------- chốt an toàn
/**
 * Những tệp KHÔNG BAO GIỜ được đi theo bản phát hành, và những tệp BẮT BUỘC
 * phải có. Kiểm ngay ở đây để một lần sửa danh sách $include/$exclude cho
 * hỏng là biết liền, thay vì phát hành xong mới phát hiện.
 */
$camTuyetDoi = [
    '#^tools/tao-du-lieu-mau\.php$#' => 'công cụ dựng dữ liệu mẫu (tạo tài khoản có mật khẩu công khai)',
    '#^tools/build-release\.php$#' => 'chính công cụ đóng gói',
    '#^tests/#' => 'bộ kiểm định (mã PHP chạy được, sẽ tạo dữ liệu rác)',
    '#\.sqlite(-wal|-shm)?$#' => 'tệp dữ liệu thật',
    '#^app/config\.local\.php$#' => 'cấu hình riêng của máy chủ (có mật khẩu cơ sở dữ liệu)',
    '#(^|/)\.git#' => 'tệp của git',
];
$batBuoc = [
    'index.php',
    '.htaccess',
    'app/bootstrap.php',
    'app/config.php',
    'app/lib/Database.php',
    'tools/chuyen-doi-csdl.php',
];

$viPham = [];
foreach ($files as $rel) {
    foreach ($camTuyetDoi as $pattern => $lyDo) {
        if (preg_match($pattern, $rel) === 1) {
            $viPham[] = "  · {$rel} — {$lyDo}";
        }
    }
}
foreach ($batBuoc as $rel) {
    if (!in_array($rel, $files, true)) {
        $viPham[] = "  · thiếu {$rel} — tệp này bắt buộc phải có";
    }
}
if ($viPham !== []) {
    echo "\n\033[31m✗ Danh sách đóng gói không hợp lệ, đã dừng:\033[0m\n";
    echo implode("\n", $viPham) . "\n\n";
    echo "Sửa \$include / \$exclude trong tools/build-release.php rồi chạy lại.\n\n";
    exit(1);
}

// ------------------------------------------------------------------ tạo .zip
if (!is_dir($distDir) && !mkdir($distDir, 0775, true) && !is_dir($distDir)) {
    exit("Không tạo được thư mục dist/\n");
}

$zipName = "rutgon-link-v{$version}.zip";
$zipPath = $distDir . '/' . $zipName;
@unlink($zipPath);

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    exit("Không mở được tệp zip để ghi.\n");
}

$zip->setArchiveComment(
    "Rut gon link v{$version} — Phong GDPT-GDTX So GDDT Dong Nai\n"
    . "Thiet ke boi Truong Anh Tuan\n"
    . 'Dong goi luc ' . date('Y-m-d H:i:s') . ' (UTC+7)'
);

foreach ($files as $rel) {
    $zip->addFile($root . '/' . $rel, $rel);
    $zip->setCompressionName($rel, ZipArchive::CM_DEFLATE, 9);
}

// Thư mục data/ phải tồn tại trong bản giải nén dù chưa có tệp dữ liệu
$zip->addEmptyDir('data');

// Hướng dẫn nhanh dạng .txt để mở được bằng Notepad trên Windows
$zip->addFromString('CAI-DAT-NHANH.txt', quickStart($version));

$zip->close();

// ------------------------------------------------------------------ tổng kết
$size = (int) filesize($zipPath);
$sha = hash_file('sha256', $zipPath);
file_put_contents($zipPath . '.sha256', "{$sha}  {$zipName}\n");

echo "\n\033[1mĐã đóng gói bản phát hành\033[0m\n";
echo str_repeat('─', 62) . "\n";
printf("  Tệp        : dist/%s\n", $zipName);
printf("  Dung lượng : %s\n", $size >= 1048576
    ? number_format($size / 1048576, 2, ',', '.') . ' MB'
    : number_format($size / 1024, 0, ',', '.') . ' KB');
printf("  Số tệp     : %d\n", count($files) + 1);
printf("  SHA-256    : %s\n", $sha);
echo str_repeat('─', 62) . "\n";

// Liệt kê theo nhóm để dễ soát
$groups = [];
foreach ($files as $rel) {
    $top = str_contains($rel, '/') ? explode('/', $rel)[0] . '/' : '(gốc)';
    $groups[$top] = ($groups[$top] ?? 0) + 1;
}
foreach ($groups as $group => $count) {
    printf("  %-12s %d tệp\n", $group, $count);
}

echo "\nKiểm tra nhanh nội dung:  unzip -l dist/{$zipName}\n";

// --------------------------------------------------------------------- nội dung
function quickStart(string $version): string
{
    return <<<TXT
    ================================================================
     RUT GON LINK - Phong GDPT-GDTX So GDDT Dong Nai
     Phien ban {$version}
     Thiet ke boi Truong Anh Tuan
    ================================================================

    CAI DAT NHANH TREN CPANEL (khoang 15 phut)

    1. KIEM TRA PHP
       cPanel -> Select PHP Version
       - Chon PHP 8.1 tro len (nen 8.2 hoac 8.3)
       - Tab Extensions: tick pdo_sqlite, sqlite3, mbstring, gd
       - Bam Save

       (Chi khi dung MySQL thi tick them pdo_mysql. Cai lan dau
        thi CU DE SQLITE - khong can tao co so du lieu, khong
        can mat khau. Doi sang MySQL luc nao cung duoc.)

    2. GIAI NEN
       cPanel -> File Manager -> vao public_html
       - Upload tep .zip nay
       - Chuot phai -> Extract (giai nen vao chinh thu muc do)
       - Xoa tep .zip sau khi giai nen

       QUAN TRONG: bat hien tep an
       File Manager -> Settings -> tick "Show Hidden Files (dotfiles)"
       Kiem tra tep .htaccess co nam cung cho voi index.php khong.
       KHONG CO NO THI LIEN KET RUT GON SE BAO LOI 404.

    3. CAP QUYEN GHI
       Chon thu muc "data" -> chuot phai -> Change Permissions -> 755
       (neu bao loi khong ghi duoc thi doi thanh 775)

    4. DAT DIA CHI GOC  <-- DUNG BO QUA NEU SE IN MA QR
       Tao tep app/config.local.php voi noi dung:

         <?php
         return [
             'site_url' => 'https://ten-mien-cua-ban.vn',
         ];

       Khong dat buoc nay thi ma QR co the mang ten mien tam cua
       hosting. In ra giay roi la khong sua duoc.

    5. TAO TAI KHOAN QUAN TRI
       Mo dia chi website -> bam "Tao tai khoan"
       Nguoi dung DAU TIEN tu dong thanh quan tri vien.

       LUU MA DU PHONG hien ra ngay sau do. He thong khong gui
       email, day la cach duy nhat de tu lay lai mat khau.
       Ma chi hien DUNG MOT LAN.

    6. KIEM TRA
       - Rut gon thu mot lien ket, dat ten "kiem-tra"
       - Bam thu lien ket do
       - Tai thu ma QR dang PNG
       - Mo ten-mien.vn/data/rutgon.sqlite
         -> PHAI bao 403 Forbidden. Neu tai ve duoc tep thi
            .htaccess chua hoat dong, du lieu dang bi lo.
       - Xoa lien ket thu sau khi kiem tra xong

    ----------------------------------------------------------------
    SAO LUU

    Toan bo du lieu nam trong MOT tep: data/rutgon.sqlite
    Sao luu = chep thu muc data/ (nen chep ca tep -wal, -shm neu co).
    Nen sao luu moi tuan va TRUOC MOI LAN NANG CAP.

    ----------------------------------------------------------------
    MUON DUNG MYSQL THAY CHO SQLITE ?

    Khong bat buoc. SQLite du dung cho hau het truong hop va sao
    luu de hon nhieu. Doi sang MySQL khi hosting gioi han dung
    luong thu muc, hoac nhieu nguoi tao lien ket cung luc.

    Doi luc nao cung duoc va GIU NGUYEN toan bo du lieu:
    lien ket ngan va ma QR da in ra van dung binh thuong.

      php tools/chuyen-doi-csdl.php --sang=mysql --thu   (xem truoc)
      php tools/chuyen-doi-csdl.php --sang=mysql         (chuyen that)

    Huong dan tung buoc, ke ca cach chay khi hosting khong co SSH:
    docs/cai-dat-cpanel.md, muc "Dung MySQL thay cho SQLite".

    ----------------------------------------------------------------
    NANG CAP LEN BAN MOI

    1. Sao luu thu muc data/ (hoac Export co so du lieu MySQL)
    2. Ghi de: index.php, .htaccess, app/, assets/, tools/
    3. KHONG cham vao: data/ va app/config.local.php
    4. Mo trang chu - bang moi (neu co) tu tao

    ----------------------------------------------------------------
    TAI LIEU DAY DU

    docs/huong-dan-su-dung.md    Huong dan cho nguoi dung
    docs/cai-dat-cpanel.md       Cai dat chi tiet + xu ly su co
    docs/quy-trinh-ky-thuat.md   Kien truc, co so du lieu, bao mat
    CHANGELOG.md                 Lich su phien ban

    Ban co anh minh hoa va so do xem tren GitHub:
    https://github.com/tlearnvn/dongnai-link-shortener

    ----------------------------------------------------------------
    KHONG CAN: MySQL, Composer, Node.js, SSH, quyen root.

    (c) 2026 - Thiet ke boi Truong Anh Tuan
    ================================================================
    TXT;
}
