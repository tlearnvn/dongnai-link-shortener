<?php
declare(strict_types=1);

/** Đọc cấu hình từ app/config.php (và app/config.local.php nếu có). */
final class Config
{
    /** @var array<string, mixed>|null */
    private static ?array $items = null;

    public static function load(): void
    {
        if (self::$items === null) {
            /** @var array<string, mixed> $items */
            $items = require __DIR__ . '/../config.php';
            self::$items = $items;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();
        return self::$items[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        self::load();
        return self::$items ?? [];
    }
}
