<?php
declare(strict_types=1);

/**
 * Chạy toàn bộ bộ kiểm định.
 *
 *   php tests/run_all.php              # kiểm định trên SQLite (mặc định)
 *   php tests/run_all.php --mysql      # kiểm định trên MySQL / MariaDB
 *
 * Script tự bật máy chủ thử nghiệm trên một cổng trống, dùng cơ sở dữ liệu
 * tạm (không đụng tới dữ liệu thật trong data/), chạy bốn bộ test rồi tắt máy
 * chủ và xoá dữ liệu tạm.
 *
 * Chạy với --mysql thì lấy thông tin kết nối từ biến môi trường (có sẵn giá
 * trị mặc định cho máy phát triển):
 *
 *   RUTGON_TEST_DB_HOST  mặc định 127.0.0.1
 *   RUTGON_TEST_DB_PORT  mặc định 3306
 *   RUTGON_TEST_DB_NAME  mặc định rutgon_test
 *   RUTGON_TEST_DB_USER  mặc định rutgon
 *   RUTGON_TEST_DB_PASS  mặc định để trống
 *
 * ⚠ Mọi bảng trong cơ sở dữ liệu đó bị XOÁ khi bắt đầu. Chỉ trỏ vào cơ sở
 *   dữ liệu dành riêng cho kiểm định.
 */

$root = dirname(__DIR__);
$useMysql = in_array('--mysql', $argv, true);

$mysql = [
    'host' => getenv('RUTGON_TEST_DB_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('RUTGON_TEST_DB_PORT') ?: 3306),
    'name' => getenv('RUTGON_TEST_DB_NAME') ?: 'rutgon_test',
    'user' => getenv('RUTGON_TEST_DB_USER') ?: 'rutgon',
    'pass' => getenv('RUTGON_TEST_DB_PASS') ?: '',
];
$port = 8700 + random_int(0, 200);
$base = "http://127.0.0.1:{$port}";

$totalPass = 0;
$totalFail = 0;
$failedSuites = [];

/** Chạy một bộ test, in kết quả và cộng dồn số liệu. */
$runSuite = static function (string $name, array $command) use ($root, &$totalPass, &$totalFail, &$failedSuites): void {
    echo "\n\033[1m▶ {$name}\033[0m\n";
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
    if (!is_resource($process)) {
        $failedSuites[] = $name;
        return;
    }
    $output = (string) stream_get_contents($pipes[1]);
    $errors = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);

    echo $output;
    if (trim($errors) !== '') {
        echo "\033[31m{$errors}\033[0m";
    }

    $plain = preg_replace('/\033\[[0-9;]*m/', '', $output) ?? '';
    if (preg_match('/Kết quả: .*?(\d+) đạt.*?(\d+) lỗi/u', $plain, $m)) {
        $totalPass += (int) $m[1];
        $totalFail += (int) $m[2];
    }
    if ($exit !== 0) {
        $failedSuites[] = $name;
    }
};

echo "\033[1m╔══════════════════════════════════════════════════════════════╗\033[0m\n";
echo "\033[1m║  Kiểm định hệ thống Rút gọn link — Sở GDĐT Đồng Nai          ║\033[0m\n";
echo "\033[1m╚══════════════════════════════════════════════════════════════╝\033[0m\n";
printf("\033[2mCơ sở dữ liệu: %s\033[0m\n", $useMysql
    ? sprintf('MySQL / MariaDB — %s@%s:%d', $mysql['name'], $mysql['host'], $mysql['port'])
    : 'SQLite (tệp tạm)');

// Bộ test không cần máy chủ. Chạy trước vì path_test có tạm thay
// app/config.local.php để thử phần tự nhận diện địa chỉ.
$runSuite('Nhận diện đường dẫn', [PHP_BINARY, $root . '/tests/path_test.php']);
$runSuite('Bộ tạo mã QR (thuật toán)', [PHP_BINARY, $root . '/tests/qr_test.php']);

// Cơ sở dữ liệu riêng cho lần kiểm định này
$tmpDir = sys_get_temp_dir() . '/rutgon-test-' . bin2hex(random_bytes(4));
mkdir($tmpDir, 0775, true);
$configFile = $root . '/app/config.local.php';
$configBackup = null;

if (is_file($configFile)) {
    $configBackup = (string) file_get_contents($configFile);
}
if ($useMysql) {
    // Xoá sạch bảng cũ để mỗi lần kiểm định bắt đầu từ cơ sở dữ liệu trắng,
    // giống như SQLite luôn được cấp một tệp mới.
    try {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $mysql['host'], $mysql['port'], $mysql['name']);
        $probe = new PDO($dsn, $mysql['user'], $mysql['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $probe->exec('SET FOREIGN_KEY_CHECKS = 0');
        $tables = $probe->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $probe->exec('DROP TABLE IF EXISTS `' . str_replace('`', '', (string) $table) . '`');
        }
        $probe->exec('SET FOREIGN_KEY_CHECKS = 1');
        printf("\033[2mĐã xoá %d bảng cũ trong %s\033[0m\n", count($tables), $mysql['name']);
    } catch (PDOException $e) {
        exit("\033[31mKhông kết nối được MySQL để kiểm định: {$e->getMessage()}\033[0m\n\n"
            . "Tạo cơ sở dữ liệu kiểm định rồi chạy lại. Ví dụ:\n"
            . "  mysql -e \"CREATE DATABASE {$mysql['name']} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci\"\n"
            . "  mysql -e \"CREATE USER '{$mysql['user']}'@'{$mysql['host']}' IDENTIFIED BY '…'\"\n"
            . "  mysql -e \"GRANT ALL ON {$mysql['name']}.* TO '{$mysql['user']}'@'{$mysql['host']}'\"\n");
    }

    $localConfig = <<<PHP
    <?php
    // Tệp này do tests/run_all.php sinh ra và sẽ được xoá khi chạy xong.
    return [
        'db_driver' => 'mysql',
        'db_host' => '{$mysql['host']}',
        'db_port' => {$mysql['port']},
        'db_name' => '{$mysql['name']}',
        'db_user' => '{$mysql['user']}',
        'db_pass' => '{$mysql['pass']}',
        'log_dir' => '{$tmpDir}',
        'site_url' => '{$base}',
    ];
    PHP;
} else {
    $localConfig = <<<PHP
    <?php
    // Tệp này do tests/run_all.php sinh ra và sẽ được xoá khi chạy xong.
    return [
        'db_driver' => 'sqlite',
        'db_path' => '{$tmpDir}/kiem-dinh.sqlite',
        'log_dir' => '{$tmpDir}',
        'site_url' => '{$base}',
    ];
    PHP;
}
file_put_contents($configFile, $localConfig);

$server = null;
$cleanup = static function () use (&$server, $configFile, $configBackup, $tmpDir): void {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
        $server = null;
    }
    if ($configBackup !== null) {
        file_put_contents($configFile, $configBackup);
    } else {
        @unlink($configFile);
    }
    foreach (glob($tmpDir . '/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($tmpDir);
};
register_shutdown_function($cleanup);
pcntl_async_signals(true);
foreach ([SIGINT, SIGTERM] as $signal) {
    pcntl_signal($signal, static function () use ($cleanup): void {
        $cleanup();
        exit(130);
    });
}

echo "\n\033[2mMáy chủ thử nghiệm: {$base}\033[0m\n";
echo "\033[2mDữ liệu tạm:        " . ($useMysql
    ? "MySQL {$mysql['name']} (bảng bị xoá trước khi chạy)"
    : "{$tmpDir}/kiem-dinh.sqlite") . "\033[0m\n";

$descriptors = [1 => ['file', '/dev/null', 'w'], 2 => ['file', $tmpDir . '/server.log', 'w']];
$server = proc_open(
    [PHP_BINARY, '-S', "127.0.0.1:{$port}", 'tests/dev-server.php'],
    $descriptors,
    $pipes,
    $root
);
if (!is_resource($server)) {
    exit("Không bật được máy chủ thử nghiệm.\n");
}

// Chờ máy chủ sẵn sàng
$ready = false;
for ($i = 0; $i < 50; $i++) {
    usleep(120000);
    $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.4);
    if ($socket !== false) {
        fclose($socket);
        $ready = true;
        break;
    }
}
if (!$ready) {
    exit("Máy chủ thử nghiệm không phản hồi.\n");
}

// Lớp cơ sở dữ liệu: chạy trước bộ HTTP vì nó xoá sạch bảng để dựng dữ liệu
// riêng, còn bộ HTTP thì tự tạo tài khoản đầu tiên của nó.
$runSuite('Lớp cơ sở dữ liệu', [PHP_BINARY, $root . '/tests/db_test.php']);
$runSuite('Ảnh mã QR trả về từ máy chủ', [PHP_BINARY, $root . '/tests/qr_image_test.php', $base]);
$runSuite('Luồng sử dụng qua HTTP', [PHP_BINARY, $root . '/tests/app_test.php', $base]);

// Nhật ký lỗi của máy chủ (nếu có) là dấu hiệu vấn đề tiềm ẩn
$errorLog = $tmpDir . '/error.log';
$serverErrors = is_file($errorLog) ? trim((string) file_get_contents($errorLog)) : '';

echo "\n\033[1m" . str_repeat('═', 62) . "\033[0m\n";
printf("\033[1mTỔNG CỘNG: \033[32m%d đạt\033[0m", $totalPass);
if ($totalFail > 0) {
    printf(", \033[31m%d lỗi\033[0m", $totalFail);
} else {
    echo ", \033[32m0 lỗi\033[0m";
}
echo "\n";

if ($failedSuites !== []) {
    echo "\033[31mBộ test chưa đạt: " . implode(', ', $failedSuites) . "\033[0m\n";
}
if ($serverErrors !== '') {
    echo "\n\033[33mNhật ký lỗi của máy chủ:\033[0m\n{$serverErrors}\n";
}
if ($failedSuites === [] && $totalFail === 0 && $serverErrors === '') {
    echo "\033[32m✓ Tất cả đều đạt, nhật ký máy chủ sạch.\033[0m\n";
}

exit($failedSuites === [] && $totalFail === 0 ? 0 : 1);
