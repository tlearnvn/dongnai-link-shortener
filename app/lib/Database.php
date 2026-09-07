<?php
declare(strict_types=1);

/**
 * Kết nối và khởi tạo cơ sở dữ liệu SQLite.
 *
 * Toàn bộ dữ liệu của hệ thống (người dùng, liên kết, lượt nhấp, cấu hình,
 * nhật ký) nằm trong DUY NHẤT một tệp .sqlite — tiện sao lưu, di chuyển.
 */
final class Database
{
    private const SCHEMA_VERSION = 1;

    private static ?PDO $pdo = null;

    /** Trả về kết nối PDO dùng chung, tự tạo bảng nếu tệp còn trống. */
    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $path = (string) Config::get('db_path');
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Không tạo được thư mục dữ liệu: {$dir}");
        }
        if (!is_writable($dir)) {
            throw new RuntimeException("Thư mục dữ liệu không có quyền ghi: {$dir}");
        }

        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA synchronous = NORMAL');

        self::$pdo = $pdo;
        self::migrate($pdo);

        return $pdo;
    }

    /** Tạo/cập nhật cấu trúc bảng. */
    private static function migrate(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS settings (
            key   TEXT PRIMARY KEY,
            value TEXT
        );

        CREATE TABLE IF NOT EXISTS users (
            id                 INTEGER PRIMARY KEY AUTOINCREMENT,
            username           TEXT NOT NULL UNIQUE COLLATE NOCASE,
            email              TEXT COLLATE NOCASE,
            full_name          TEXT,
            unit               TEXT,
            password_hash      TEXT NOT NULL,
            role               TEXT NOT NULL DEFAULT 'user',
            status             TEXT NOT NULL DEFAULT 'active',
            api_token          TEXT UNIQUE,
            recovery_code_hash TEXT,
            theme              TEXT NOT NULL DEFAULT 'auto',
            link_quota         INTEGER NOT NULL DEFAULT 0,
            created_at         TEXT NOT NULL,
            last_login_at      TEXT,
            last_login_ip      TEXT,
            login_count        INTEGER NOT NULL DEFAULT 0
        );

        CREATE TABLE IF NOT EXISTS links (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            code          TEXT NOT NULL UNIQUE COLLATE NOCASE,
            target_url    TEXT NOT NULL,
            title         TEXT,
            note          TEXT,
            tags          TEXT,
            user_id       INTEGER REFERENCES users(id) ON DELETE SET NULL,
            creator_ip    TEXT,
            password_hash TEXT,
            starts_at     TEXT,
            expires_at    TEXT,
            max_clicks    INTEGER,
            click_count   INTEGER NOT NULL DEFAULT 0,
            unique_count  INTEGER NOT NULL DEFAULT 0,
            is_active     INTEGER NOT NULL DEFAULT 1,
            is_starred    INTEGER NOT NULL DEFAULT 0,
            qr_dark       TEXT NOT NULL DEFAULT '#0b1220',
            qr_light      TEXT NOT NULL DEFAULT '#ffffff',
            last_click_at TEXT,
            created_at    TEXT NOT NULL,
            updated_at    TEXT
        );

        CREATE INDEX IF NOT EXISTS idx_links_user    ON links(user_id, created_at DESC);
        CREATE INDEX IF NOT EXISTS idx_links_created ON links(created_at DESC);
        CREATE INDEX IF NOT EXISTS idx_links_clicks  ON links(click_count DESC);

        CREATE TABLE IF NOT EXISTS clicks (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            link_id      INTEGER NOT NULL REFERENCES links(id) ON DELETE CASCADE,
            clicked_at   TEXT NOT NULL,
            click_date   TEXT NOT NULL,
            click_hour   INTEGER NOT NULL,
            weekday      INTEGER NOT NULL,
            visitor_hash TEXT,
            is_unique    INTEGER NOT NULL DEFAULT 0,
            referer      TEXT,
            referer_host TEXT,
            browser      TEXT,
            os           TEXT,
            device       TEXT,
            country      TEXT,
            language     TEXT,
            source       TEXT,
            is_bot       INTEGER NOT NULL DEFAULT 0
        );

        CREATE INDEX IF NOT EXISTS idx_clicks_link    ON clicks(link_id, clicked_at DESC);
        CREATE INDEX IF NOT EXISTS idx_clicks_date    ON clicks(click_date);
        CREATE INDEX IF NOT EXISTS idx_clicks_visitor ON clicks(link_id, visitor_hash);

        CREATE TABLE IF NOT EXISTS remember_tokens (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            selector   TEXT NOT NULL UNIQUE,
            token_hash TEXT NOT NULL,
            expires_at TEXT NOT NULL,
            created_at TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS login_attempts (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            ip         TEXT NOT NULL,
            username   TEXT,
            successful INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL
        );

        CREATE INDEX IF NOT EXISTS idx_attempts_ip ON login_attempts(ip, created_at DESC);

        CREATE TABLE IF NOT EXISTS audit_log (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id    INTEGER,
            actor      TEXT,
            action     TEXT NOT NULL,
            detail     TEXT,
            ip         TEXT,
            created_at TEXT NOT NULL
        );

        CREATE INDEX IF NOT EXISTS idx_audit_created ON audit_log(created_at DESC);
        SQL);

        $version = (int) Settings::get('schema_version', '0');
        if ($version < self::SCHEMA_VERSION) {
            Settings::set('schema_version', (string) self::SCHEMA_VERSION);
        }
        if (Settings::get('installed_at') === null) {
            Settings::set('installed_at', Clock::now());
        }
    }

    /** Số byte tệp cơ sở dữ liệu (kể cả tệp WAL đi kèm). */
    public static function fileSize(): int
    {
        $path = (string) Config::get('db_path');
        $size = is_file($path) ? (int) filesize($path) : 0;
        foreach (['-wal', '-shm'] as $suffix) {
            if (is_file($path . $suffix)) {
                $size += (int) filesize($path . $suffix);
            }
        }
        return $size;
    }

    public static function path(): string
    {
        return (string) Config::get('db_path');
    }

    /** Dồn tệp cơ sở dữ liệu cho gọn (dùng ở trang quản trị). */
    public static function vacuum(): void
    {
        self::pdo()->exec('VACUUM');
    }
}
