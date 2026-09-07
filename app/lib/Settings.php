<?php
declare(strict_types=1);

/** Cấu hình động do quản trị viên đổi được, lưu trong bảng `settings`. */
final class Settings
{
    /** @var array<string, string|null>|null */
    private static ?array $cache = null;

    /** Giá trị mặc định khi cơ sở dữ liệu chưa có bản ghi. */
    private const DEFAULTS = [
        'allow_registration' => '1',
        'allow_guest_shorten' => '1',
        'require_login_for_stats' => '1',
        'announcement' => '',
        'default_ecc' => '1',           // mức sửa lỗi QR: 0=L, 1=M, 2=Q, 3=H
        'auto_title' => '0',            // tự lấy tiêu đề trang đích
        'block_open_redirect' => '1',   // chặn rút gọn chính địa chỉ của hệ thống
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        self::prime();
        if (array_key_exists($key, self::$cache ?? [])) {
            return self::$cache[$key];
        }
        return $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default ? '1' : '0');
        return $value === '1' || $value === 'true';
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);
        return $value === null || $value === '' ? $default : (int) $value;
    }

    public static function set(string $key, ?string $value): void
    {
        // Xoá rồi thêm, gói trong một giao dịch: cú pháp "chèn hoặc cập nhật"
        // của SQLite (ON CONFLICT … excluded) và của MySQL (ON DUPLICATE KEY
        // UPDATE … VALUES()) khác nhau, còn cách này cả hai đều hiểu. Cài đặt
        // chỉ đổi khi quản trị viên bấm lưu nên không cần tối ưu thêm.
        $pdo = Database::pdo();
        $ownTransaction = !$pdo->inTransaction();
        if ($ownTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $pdo->prepare('DELETE FROM settings WHERE `key` = :k')->execute([':k' => $key]);
            $pdo->prepare('INSERT INTO settings (`key`, value) VALUES (:k, :v)')
                ->execute([':k' => $key, ':v' => $value]);
            if ($ownTransaction) {
                $pdo->commit();
            }
        } catch (PDOException $e) {
            if ($ownTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        self::prime();
        self::$cache[$key] = $value;
    }

    /** @return array<string, string|null> */
    public static function all(): array
    {
        self::prime();
        return array_replace(self::DEFAULTS, self::$cache ?? []);
    }

    private static function prime(): void
    {
        if (self::$cache !== null) {
            return;
        }
        self::$cache = [];
        try {
            $rows = Database::pdo()->query('SELECT `key`, value FROM settings')->fetchAll();
        } catch (PDOException) {
            return; // bảng chưa tồn tại (đang trong lúc tạo cấu trúc)
        }
        foreach ($rows as $row) {
            self::$cache[(string) $row['key']] = $row['value'] === null ? null : (string) $row['value'];
        }
    }
}
