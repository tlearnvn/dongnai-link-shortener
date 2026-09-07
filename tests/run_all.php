<?php
declare(strict_types=1);

/**
 * Chạy toàn bộ bộ kiểm định.
 *
 *   php tests/run_all.php
 *
 * Script tự bật máy chủ thử nghiệm trên một cổng trống, dùng cơ sở dữ liệu
 * tạm (không đụng tới dữ liệu thật trong data/), chạy ba bộ test rồi tắt máy
 * chủ và xoá dữ liệu tạm.
 */

$root = dirname(__DIR__);
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
file_put_contents($configFile, <<<PHP
<?php
// Tệp này do tests/run_all.php sinh ra và sẽ được xoá khi chạy xong.
return [
    'db_path' => '{$tmpDir}/kiem-dinh.sqlite',
    'site_url' => '{$base}',
];
PHP);

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
echo "\033[2mDữ liệu tạm:        {$tmpDir}/kiem-dinh.sqlite\033[0m\n";

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
