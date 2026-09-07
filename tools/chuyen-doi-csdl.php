<?php
declare(strict_types=1);

/**
 * Chuyển toàn bộ dữ liệu giữa SQLite và MySQL, giữ nguyên mã số (id) nên liên
 * kết, lượt nhấp và người dùng vẫn khớp nhau sau khi chuyển.
 *
 *   php tools/chuyen-doi-csdl.php --sang=mysql     # SQLite  → MySQL
 *   php tools/chuyen-doi-csdl.php --sang=sqlite    # MySQL   → SQLite
 *   php tools/chuyen-doi-csdl.php --sang=mysql --thu     # chỉ xem trước
 *   php tools/chuyen-doi-csdl.php --sang=mysql --force   # ghi đè dữ liệu đích
 *
 * Công cụ KHÔNG hỏi mật khẩu trên dòng lệnh. Cả hai phía đều lấy thông tin từ
 * app/config.php và app/config.local.php:
 *
 *   phía SQLite : db_path
 *   phía MySQL  : db_host, db_port, db_name, db_user, db_pass, db_socket
 *
 * QUY TRÌNH CHUYỂN SANG MYSQL
 *
 *   1. Tạo cơ sở dữ liệu MySQL trống (cPanel → MySQL Databases).
 *   2. Khai db_host/db_name/db_user/db_pass trong app/config.local.php,
 *      nhưng VẪN để db_driver = 'sqlite'.
 *   3. php tools/chuyen-doi-csdl.php --sang=mysql --thu   (xem trước)
 *   4. php tools/chuyen-doi-csdl.php --sang=mysql         (chuyển thật)
 *   5. Đổi db_driver thành 'mysql' trong app/config.local.php.
 *   6. Mở trang /quan-tri/cai-dat kiểm tra, rồi giữ tệp .sqlite cũ làm bản
 *      sao lưu — đừng xoá ngay.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Công cụ này chỉ chạy được từ dòng lệnh.');
}

require dirname(__DIR__) . '/app/bootstrap.php';

// ---------------------------------------------------------------- tham số
$target = null;
foreach ($argv as $arg) {
    if (preg_match('/^--sang=(sqlite|mysql)$/', $arg, $m) === 1) {
        $target = $m[1];
    }
}
$dryRun = in_array('--thu', $argv, true);
$force = in_array('--force', $argv, true);

if ($target === null) {
    exit(<<<TXT

    Chưa cho biết chuyển sang đâu.

      php tools/chuyen-doi-csdl.php --sang=mysql     SQLite → MySQL
      php tools/chuyen-doi-csdl.php --sang=sqlite    MySQL  → SQLite

    Thêm --thu để chỉ xem trước, không ghi gì.

    TXT);
}

$source = $target === 'mysql' ? 'sqlite' : 'mysql';

/** Số dòng nạp mỗi lượt — giữ bộ nhớ ở mức thấp kể cả khi có hàng trăm nghìn lượt nhấp. */
const KICH_THUOC_LO = 2000;

/**
 * Có đang in ra màn hình thật không.
 *
 * Dòng tiến độ dùng ký tự \r để ghi đè lên chính nó. Khi kết quả bị chuyển
 * vào tệp hoặc qua ống lệnh thì \r không xoá gì, chỉ làm bản ghi rối — nên
 * những lúc đó bỏ hẳn dòng tiến độ.
 */
$raManHinh = function_exists('posix_isatty') && @posix_isatty(STDOUT);

// -------------------------------------------------------------- kết nối
/** Mở kết nối PDO tới một phía, không đi qua lớp Database (đang giữ phía khác). */
function moKetNoi(string $driver, bool $taoMoi): PDO
{
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if ($driver === 'sqlite') {
        $path = (string) Config::get('db_path');
        if ($path === '') {
            exit("Chưa khai db_path trong cấu hình.\n");
        }
        if (!$taoMoi && !is_file($path)) {
            exit("Không thấy tệp SQLite nguồn: {$path}\n");
        }
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            exit("Không tạo được thư mục: {$dir}\n");
        }
        $pdo = new PDO('sqlite:' . $path, null, null, $options);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        // Tắt kiểm tra khoá ngoại trong lúc nạp, vì bảng con có thể được ghi
        // trước khi bảng cha đủ dòng. Bật lại và kiểm tra ở cuối.
        $pdo->exec('PRAGMA foreign_keys = OFF');
        return $pdo;
    }

    $name = trim((string) Config::get('db_name'));
    $user = trim((string) Config::get('db_user'));
    if ($name === '' || $user === '') {
        exit(
            "Chưa khai thông tin MySQL. Thêm vào app/config.local.php:\n\n"
            . "    'db_host' => '127.0.0.1',\n"
            . "    'db_name' => 'ten_co_so_du_lieu',\n"
            . "    'db_user' => 'ten_dang_nhap',\n"
            . "    'db_pass' => 'mat_khau',\n\n"
        );
    }

    $charset = trim((string) Config::get('db_charset', 'utf8mb4')) ?: 'utf8mb4';
    $socket = trim((string) Config::get('db_socket'));
    $dsn = $socket !== ''
        ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $socket, $name, $charset)
        : sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            trim((string) Config::get('db_host', '127.0.0.1')) ?: '127.0.0.1',
            (int) Config::get('db_port', 3306) ?: 3306,
            $name,
            $charset
        );

    try {
        $pdo = new PDO($dsn, $user, (string) Config::get('db_pass'), $options);
    } catch (PDOException $e) {
        exit("Không kết nối được MySQL: {$e->getMessage()}\n");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    return $pdo;
}

/** Mô tả một phía để in ra cho người dùng đọc. */
function moTa(string $driver): string
{
    if ($driver === 'sqlite') {
        $path = (string) Config::get('db_path');
        return 'SQLite — ' . (realpath($path) ?: $path);
    }
    $socket = trim((string) Config::get('db_socket'));
    $where = $socket !== ''
        ? $socket
        : Config::get('db_host', '127.0.0.1') . ':' . (int) Config::get('db_port', 3306);
    return 'MySQL — ' . Config::get('db_name') . ' @ ' . $where;
}

/**
 * Tên các cột của một bảng, đọc từ chính kết nối đó.
 *
 * Dùng getColumnMeta thay cho PRAGMA/SHOW COLUMNS để một đoạn mã chạy được
 * cho cả SQLite và MySQL.
 *
 * @return array<int, string>
 */
function layTenCot(PDO $pdo, string $table): array
{
    $stmt = $pdo->query("SELECT * FROM {$table} LIMIT 0");
    $names = [];
    for ($i = 0; $i < $stmt->columnCount(); $i++) {
        $meta = $stmt->getColumnMeta($i);
        if (is_array($meta) && isset($meta['name'])) {
            $names[] = (string) $meta['name'];
        }
    }
    $stmt->closeCursor();
    return $names;
}

/** Đếm số dòng của từng bảng, bảng chưa có thì trả 0. */
function demDong(PDO $pdo, array $tables): array
{
    $counts = [];
    foreach ($tables as $table) {
        try {
            $counts[$table] = (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        } catch (PDOException) {
            $counts[$table] = 0;
        }
    }
    return $counts;
}

$tables = Database::tables();

echo "\n\033[1mCHUYỂN ĐỔI CƠ SỞ DỮ LIỆU\033[0m\n";
echo str_repeat('─', 66) . "\n";
printf("  Nguồn : %s\n", moTa($source));
printf("  Đích  : %s\n", moTa($target));
if ($dryRun) {
    echo "  Chế độ: \033[33mXEM TRƯỚC — không ghi gì vào phía đích\033[0m\n";
}
echo str_repeat('─', 66) . "\n";

$src = moKetNoi($source, false);
$dst = moKetNoi($target, true);

$soNguon = demDong($src, $tables);
if (array_sum($soNguon) === 0) {
    exit("\nPhía nguồn không có dòng nào. Không có gì để chuyển.\n\n");
}

// ------------------------------------------------------------- chốt an toàn
Database::createSchema($dst, $target);
$soDich = demDong($dst, $tables);

// Bảng settings luôn có vài dòng do lớp Database tự ghi lúc tạo bảng, nên chỉ
// xét các bảng dữ liệu thật khi quyết định phía đích có "trống" hay không.
$bangDuLieu = array_values(array_diff($tables, ['settings']));
$daCoDuLieu = 0;
foreach ($bangDuLieu as $table) {
    $daCoDuLieu += $soDich[$table];
}

if ($daCoDuLieu > 0 && !$force && !$dryRun) {
    echo "\n\033[31m⚠ DỪNG LẠI\033[0m\n";
    echo "Phía đích đã có sẵn dữ liệu:\n";
    foreach ($bangDuLieu as $table) {
        if ($soDich[$table] > 0) {
            printf("  · %-16s %s dòng\n", $table, number_format($soDich[$table], 0, ',', '.'));
        }
    }
    echo "\nChuyển tiếp sẽ XOÁ HẾT dữ liệu đó rồi ghi lại từ nguồn.\n";
    echo "Chắc chắn thì chạy lại kèm --force:\n";
    echo "  php tools/chuyen-doi-csdl.php --sang={$target} --force\n\n";
    exit(1);
}

// -------------------------------------------------------------------- xem trước
echo "\n\033[1mSố dòng sẽ chuyển\033[0m\n";
foreach ($tables as $table) {
    printf("  %-16s %10s dòng\n", $table, number_format($soNguon[$table], 0, ',', '.'));
}
printf("  %-16s %10s dòng\n", 'TỔNG', number_format(array_sum($soNguon), 0, ',', '.'));

if ($dryRun) {
    echo "\n\033[33mChế độ xem trước — chưa ghi gì.\033[0m\n";
    echo "Chuyển thật: php tools/chuyen-doi-csdl.php --sang={$target}\n\n";
    exit(0);
}

// ------------------------------------------------------------------- chuyển
echo "\n\033[1mĐang chuyển\033[0m\n";

$batDau = microtime(true);
$dst->beginTransaction();

try {
    // Xoá theo thứ tự ngược để không vướng khoá ngoại
    foreach (array_reverse($tables) as $table) {
        $dst->exec("DELETE FROM {$table}");
    }

    $tongDaGhi = 0;
    foreach ($tables as $table) {
        $tong = $soNguon[$table];
        if ($tong === 0) {
            printf("  %-16s %s\n", $table, "\033[2mtrống\033[0m");
            continue;
        }

        // Lấy đúng danh sách cột có ở CẢ hai phía, để chuyển được giữa hai bản
        // phát hành lệch nhau một cột mà không vỡ.
        $cot = array_values(array_intersect(layTenCot($src, $table), layTenCot($dst, $table)));
        if ($cot === []) {
            throw new RuntimeException("Bảng {$table}: hai phía không có cột nào trùng tên.");
        }

        $danhSachCot = implode(', ', array_map(static fn(string $c): string => "`{$c}`", $cot));
        $thamSo = implode(', ', array_map(static fn(string $c): string => ':' . $c, $cot));
        $chen = $dst->prepare("INSERT INTO {$table} ({$danhSachCot}) VALUES ({$thamSo})");

        // Đọc theo lô, sắp theo id để lô sau không lặp lô trước
        $sapXep = in_array('id', $cot, true) ? 'ORDER BY id' : '';
        $daGhi = 0;
        for ($offset = 0; ; $offset += KICH_THUOC_LO) {
            $doc = $src->query(
                "SELECT {$danhSachCot} FROM {$table} {$sapXep} LIMIT " . KICH_THUOC_LO . " OFFSET {$offset}"
            );
            $rows = $doc->fetchAll();
            if ($rows === []) {
                break;
            }
            foreach ($rows as $row) {
                $binds = [];
                foreach ($cot as $c) {
                    $binds[':' . $c] = $row[$c] ?? null;
                }
                $chen->execute($binds);
                $daGhi++;
            }
            if ($raManHinh) {
                printf("\r  %-16s %s/%s dòng", $table,
                    number_format($daGhi, 0, ',', '.'), number_format($tong, 0, ',', '.'));
            }
        }

        $tongDaGhi += $daGhi;
        printf("%s  %-16s \033[32m✓\033[0m %s dòng%s\n", $raManHinh ? "\r" : '', $table,
            number_format($daGhi, 0, ',', '.'), $raManHinh ? str_repeat(' ', 14) : '');
    }

    $dst->commit();
} catch (Throwable $e) {
    if ($dst->inTransaction()) {
        $dst->rollBack();
    }
    echo "\n\n\033[31m✗ Chuyển đổi thất bại, đã hoàn tác toàn bộ.\033[0m\n";
    echo "Phía đích trở lại đúng trạng thái trước khi chạy.\n\n";
    echo "Lý do: {$e->getMessage()}\n\n";
    exit(1);
}

// ------------------------------------------------------------------ đối chiếu
if ($target === 'mysql') {
    $dst->exec('SET FOREIGN_KEY_CHECKS = 1');
} else {
    $dst->exec('PRAGMA foreign_keys = ON');
}

$soSauKhiChuyen = demDong($dst, $tables);
$lech = [];
foreach ($tables as $table) {
    if ($soSauKhiChuyen[$table] !== $soNguon[$table]) {
        $lech[] = sprintf('%s (nguồn %d, đích %d)', $table, $soNguon[$table], $soSauKhiChuyen[$table]);
    }
}

// Kiểm tra khoá ngoại còn khớp: mọi lượt nhấp phải trỏ tới một liên kết có thật
$mocoi = (int) $dst->query(
    'SELECT COUNT(*) FROM clicks c LEFT JOIN links l ON l.id = c.link_id WHERE l.id IS NULL'
)->fetchColumn();

$giay = round(microtime(true) - $batDau, 1);

echo "\n" . str_repeat('─', 66) . "\n";
if ($lech === [] && $mocoi === 0) {
    printf("\033[32m✓ Đã chuyển %s dòng sang %s trong %s giây.\033[0m\n",
        number_format($tongDaGhi, 0, ',', '.'), strtoupper($target), $giay);
    echo "  Số dòng hai phía khớp nhau, khoá ngoại nguyên vẹn.\n";
} else {
    echo "\033[33m⚠ Chuyển xong nhưng có chỗ chưa khớp:\033[0m\n";
    foreach ($lech as $dong) {
        echo "  · {$dong}\n";
    }
    if ($mocoi > 0) {
        echo "  · {$mocoi} lượt nhấp trỏ tới liên kết không còn tồn tại\n";
    }
}
echo str_repeat('─', 66) . "\n";

echo "\n\033[1mVIỆC CẦN LÀM TIẾP\033[0m\n";
echo "  1. Mở app/config.local.php, đổi:\n";
echo "         'db_driver' => '{$target}',\n";
echo "  2. Mở trang /quan-tri/cai-dat xem mục \"Loại cơ sở dữ liệu\" đã đổi chưa.\n";
echo "  3. Bấm thử một liên kết rút gọn và xem trang thống kê.\n";
if ($target === 'mysql') {
    $tepCu = (string) Config::get('db_path');
    echo '  4. GIỮ tệp ' . (realpath($tepCu) ?: $tepCu) . " làm bản sao lưu.\n";
    echo "     Chỉ xoá khi đã chắc chắn hệ thống chạy ổn trên MySQL.\n";
} else {
    echo "  4. GIỮ cơ sở dữ liệu MySQL cũ làm bản sao lưu.\n";
}
echo "\n";
