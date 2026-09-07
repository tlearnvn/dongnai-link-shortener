<?php
declare(strict_types=1);

/**
 * Kết nối và khởi tạo cơ sở dữ liệu.
 *
 * Hệ thống chạy được trên hai loại cơ sở dữ liệu, chọn bằng `db_driver`:
 *
 *   sqlite  Toàn bộ dữ liệu trong DUY NHẤT một tệp .sqlite — mặc định, không
 *           cần cài gì thêm, sao lưu bằng cách chép tệp.
 *   mysql   MySQL / MariaDB — dùng khi lượng truy cập lớn hoặc muốn dùng công
 *           cụ sao lưu sẵn của hosting.
 *
 * Phần còn lại của hệ thống KHÔNG cần biết đang chạy loại nào: mọi câu lệnh
 * SQL trong app/ đều viết bằng cú pháp cả hai loại đều hiểu. Cụ thể là:
 *
 *   · Mốc thời gian lưu dạng chuỗi 'Y-m-d H:i:s' theo giờ UTC+7 (xem Clock),
 *     nên so sánh và sắp xếp bằng phép so chuỗi, không cần hàm ngày tháng
 *     riêng của từng loại.
 *   · Không dùng hàm riêng của một loại (strftime, DATE_FORMAT, ||, CONCAT…).
 *   · Cột `key` của bảng settings luôn viết trong dấu ` để tránh trùng từ khoá
 *     của MySQL.
 *   · Kết quả SUM() của MySQL là chuỗi thập phân, của SQLite là số nguyên —
 *     nên mọi chỗ đọc số đều ép kiểu (int) trước khi dùng.
 */
final class Database
{
    private const SCHEMA_VERSION = 1;

    /** Các loại cơ sở dữ liệu hỗ trợ. */
    public const DRIVERS = ['sqlite', 'mysql'];

    private static ?PDO $pdo = null;
    private static ?string $driver = null;

    /** Loại cơ sở dữ liệu đang dùng: 'sqlite' hoặc 'mysql'. */
    public static function driver(): string
    {
        if (self::$driver !== null) {
            return self::$driver;
        }

        $driver = strtolower(trim((string) Config::get('db_driver', 'sqlite')));
        if ($driver === '') {
            $driver = 'sqlite';
        }
        if (!in_array($driver, self::DRIVERS, true)) {
            throw new RuntimeException(
                "db_driver không hợp lệ: '{$driver}'. Chỉ nhận: " . implode(', ', self::DRIVERS) . '.'
            );
        }

        return self::$driver = $driver;
    }

    public static function isMysql(): bool
    {
        return self::driver() === 'mysql';
    }

    public static function isSqlite(): bool
    {
        return self::driver() === 'sqlite';
    }

    /** Trả về kết nối PDO dùng chung, tự tạo bảng nếu cơ sở dữ liệu còn trống. */
    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $pdo = self::isMysql() ? self::connectMysql() : self::connectSqlite();

        self::$pdo = $pdo;
        self::migrate($pdo);

        return $pdo;
    }

    /** Đóng kết nối hiện tại (dùng trong bộ kiểm định khi đổi cấu hình). */
    public static function reset(): void
    {
        self::$pdo = null;
        self::$driver = null;
    }

    // ------------------------------------------------------------------ kết nối

    private static function options(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
    }

    private static function connectSqlite(): PDO
    {
        $path = (string) Config::get('db_path');
        if ($path === '') {
            throw new RuntimeException('Chưa khai db_path cho cơ sở dữ liệu SQLite.');
        }

        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Không tạo được thư mục dữ liệu: {$dir}");
        }
        if (!is_writable($dir)) {
            throw new RuntimeException("Thư mục dữ liệu không có quyền ghi: {$dir}");
        }

        $pdo = new PDO('sqlite:' . $path, null, null, self::options());
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA synchronous = NORMAL');

        return $pdo;
    }

    private static function connectMysql(): PDO
    {
        $name = trim((string) Config::get('db_name'));
        $user = trim((string) Config::get('db_user'));
        if ($name === '' || $user === '') {
            throw new RuntimeException(
                'Chọn db_driver = mysql thì phải khai db_name và db_user trong app/config.local.php.'
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
            $pdo = new PDO($dsn, $user, (string) Config::get('db_pass'), self::options());
        } catch (PDOException $e) {
            // Thông báo gọn, không lẫn mật khẩu, nhưng đủ để biết sai ở đâu.
            throw new RuntimeException(
                'Không kết nối được MySQL (' . self::describeTarget() . '): ' . $e->getMessage(),
                0,
                $e
            );
        }

        // Bảng dùng chuỗi 'Y-m-d H:i:s' chứ không dùng kiểu DATETIME, nên múi
        // giờ của máy chủ MySQL không ảnh hưởng. Vẫn đặt cho khớp để các hàm
        // NOW() gọi thủ công lúc bảo trì cũng ra giờ Việt Nam.
        try {
            $pdo->exec("SET time_zone = '+07:00'");
        } catch (PDOException) {
            // Máy chủ chưa nạp bảng múi giờ — bỏ qua, hệ thống không phụ thuộc.
        }

        return $pdo;
    }

    /** Mô tả đích kết nối để đưa vào thông báo lỗi (không có mật khẩu). */
    private static function describeTarget(): string
    {
        $socket = trim((string) Config::get('db_socket'));
        if ($socket !== '') {
            return 'socket ' . $socket . ', cơ sở dữ liệu ' . Config::get('db_name');
        }
        return sprintf(
            '%s:%d, cơ sở dữ liệu %s, người dùng %s',
            Config::get('db_host', '127.0.0.1'),
            (int) Config::get('db_port', 3306),
            (string) Config::get('db_name'),
            (string) Config::get('db_user')
        );
    }

    // ------------------------------------------------------------ cấu trúc bảng

    /**
     * Tạo cấu trúc bảng trên một kết nối bất kỳ.
     *
     * Dùng cho công cụ chuyển đổi cơ sở dữ liệu (tools/chuyen-doi-csdl.php),
     * nơi cần dựng bảng ở phía đích trong khi hệ thống vẫn đang nối vào phía
     * nguồn. Các câu lệnh đều dạng CREATE TABLE IF NOT EXISTS nên gọi lại
     * nhiều lần không sao.
     */
    public static function createSchema(PDO $pdo, string $driver): void
    {
        if (!in_array($driver, self::DRIVERS, true)) {
            throw new RuntimeException("Loại cơ sở dữ liệu không hợp lệ: {$driver}");
        }
        foreach ($driver === 'mysql' ? self::schemaMysql() : self::schemaSqlite() as $statement) {
            $pdo->exec($statement);
        }
    }

    /** Tạo/cập nhật cấu trúc bảng. */
    private static function migrate(PDO $pdo): void
    {
        self::createSchema($pdo, self::driver());

        $version = (int) Settings::get('schema_version', '0');
        if ($version < self::SCHEMA_VERSION) {
            Settings::set('schema_version', (string) self::SCHEMA_VERSION);
        }
        if (Settings::get('installed_at') === null) {
            Settings::set('installed_at', Clock::now());
        }
    }

    /**
     * Cấu trúc bảng cho SQLite.
     *
     * Mốc thời gian dùng TEXT dạng 'Y-m-d H:i:s' / 'Y-m-d' theo giờ UTC+7.
     * COLLATE NOCASE cho các cột tra cứu không phân biệt hoa/thường.
     *
     * @return array<int, string>
     */
    private static function schemaSqlite(): array
    {
        return [
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS settings (
                `key` TEXT PRIMARY KEY,
                value TEXT
            )
            SQL,
            <<<'SQL'
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
            )
            SQL,
            <<<'SQL'
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
            )
            SQL,
            'CREATE INDEX IF NOT EXISTS idx_links_user    ON links(user_id, created_at DESC)',
            'CREATE INDEX IF NOT EXISTS idx_links_created ON links(created_at DESC)',
            'CREATE INDEX IF NOT EXISTS idx_links_clicks  ON links(click_count DESC)',
            <<<'SQL'
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
            )
            SQL,
            'CREATE INDEX IF NOT EXISTS idx_clicks_link    ON clicks(link_id, clicked_at DESC)',
            'CREATE INDEX IF NOT EXISTS idx_clicks_date    ON clicks(click_date)',
            'CREATE INDEX IF NOT EXISTS idx_clicks_visitor ON clicks(link_id, visitor_hash)',
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS remember_tokens (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                selector   TEXT NOT NULL UNIQUE,
                token_hash TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                created_at TEXT NOT NULL
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS login_attempts (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                ip         TEXT NOT NULL,
                username   TEXT,
                successful INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL
            )
            SQL,
            'CREATE INDEX IF NOT EXISTS idx_attempts_ip ON login_attempts(ip, created_at DESC)',
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS audit_log (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER,
                actor      TEXT,
                action     TEXT NOT NULL,
                detail     TEXT,
                ip         TEXT,
                created_at TEXT NOT NULL
            )
            SQL,
            'CREATE INDEX IF NOT EXISTS idx_audit_created ON audit_log(created_at DESC)',
        ];
    }

    /**
     * Cấu trúc bảng cho MySQL / MariaDB — tương ứng một-một với schemaSqlite().
     *
     * Ba điểm khác biệt cố ý:
     *
     *  1. Mốc thời gian dùng VARCHAR chứ KHÔNG dùng DATETIME. Hệ thống ghi
     *     chuỗi giờ UTC+7 do PHP sinh, nên để nguyên dạng chuỗi thì mọi câu
     *     lệnh so sánh/sắp xếp chạy y hệt trên SQLite, và múi giờ của máy chủ
     *     MySQL không bao giờ làm sai số liệu.
     *  2. Chỉ mục khai luôn trong CREATE TABLE, vì MySQL 8 không hỗ trợ
     *     CREATE INDEX IF NOT EXISTS.
     *  3. Cột UNIQUE dùng VARCHAR có độ dài thay cho TEXT (MySQL không đánh
     *     chỉ mục TEXT mà không khai độ dài tiền tố). Độ dài chọn ≤ 191 để
     *     chạy được cả trên hosting cũ dùng utf8mb4 với chỉ mục 767 byte.
     *
     * Bảng dùng collation *_unicode_ci nên tra cứu username/email/code không
     * phân biệt hoa thường — đúng như COLLATE NOCASE của SQLite.
     *
     * @return array<int, string>
     */
    private static function schemaMysql(): array
    {
        $charset = trim((string) Config::get('db_charset', 'utf8mb4')) ?: 'utf8mb4';
        $collation = $charset === 'utf8mb4' ? 'utf8mb4_unicode_ci' : $charset . '_unicode_ci';
        $tail = "ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}";

        return [
            <<<SQL
            CREATE TABLE IF NOT EXISTS settings (
                `key` VARCHAR(64) NOT NULL,
                value TEXT NULL,
                PRIMARY KEY (`key`)
            ) {$tail}
            SQL,
            <<<SQL
            CREATE TABLE IF NOT EXISTS users (
                id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                username           VARCHAR(64) NOT NULL,
                email              VARCHAR(190) NULL,
                full_name          VARCHAR(190) NULL,
                unit               VARCHAR(190) NULL,
                password_hash      VARCHAR(255) NOT NULL,
                role               VARCHAR(16) NOT NULL DEFAULT 'user',
                status             VARCHAR(16) NOT NULL DEFAULT 'active',
                api_token          VARCHAR(64) NULL,
                recovery_code_hash VARCHAR(255) NULL,
                theme              VARCHAR(16) NOT NULL DEFAULT 'auto',
                link_quota         INT NOT NULL DEFAULT 0,
                created_at         VARCHAR(19) NOT NULL,
                last_login_at      VARCHAR(19) NULL,
                last_login_ip      VARCHAR(45) NULL,
                login_count        INT NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY uq_users_username (username),
                UNIQUE KEY uq_users_api_token (api_token)
            ) {$tail}
            SQL,
            <<<SQL
            CREATE TABLE IF NOT EXISTS links (
                id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                code          VARCHAR(64) NOT NULL,
                target_url    TEXT NOT NULL,
                title         VARCHAR(255) NULL,
                note          TEXT NULL,
                tags          VARCHAR(255) NULL,
                user_id       BIGINT UNSIGNED NULL,
                creator_ip    VARCHAR(45) NULL,
                password_hash VARCHAR(255) NULL,
                starts_at     VARCHAR(19) NULL,
                expires_at    VARCHAR(19) NULL,
                max_clicks    INT NULL,
                click_count   INT NOT NULL DEFAULT 0,
                unique_count  INT NOT NULL DEFAULT 0,
                is_active     TINYINT NOT NULL DEFAULT 1,
                is_starred    TINYINT NOT NULL DEFAULT 0,
                qr_dark       VARCHAR(9) NOT NULL DEFAULT '#0b1220',
                qr_light      VARCHAR(9) NOT NULL DEFAULT '#ffffff',
                last_click_at VARCHAR(19) NULL,
                created_at    VARCHAR(19) NOT NULL,
                updated_at    VARCHAR(19) NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_links_code (code),
                KEY idx_links_user (user_id, created_at),
                KEY idx_links_created (created_at),
                KEY idx_links_clicks (click_count),
                CONSTRAINT fk_links_user FOREIGN KEY (user_id)
                    REFERENCES users(id) ON DELETE SET NULL
            ) {$tail}
            SQL,
            <<<SQL
            CREATE TABLE IF NOT EXISTS clicks (
                id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                link_id      BIGINT UNSIGNED NOT NULL,
                clicked_at   VARCHAR(19) NOT NULL,
                click_date   VARCHAR(10) NOT NULL,
                click_hour   INT NOT NULL,
                weekday      INT NOT NULL,
                visitor_hash VARCHAR(64) NULL,
                is_unique    TINYINT NOT NULL DEFAULT 0,
                referer      TEXT NULL,
                referer_host VARCHAR(190) NULL,
                browser      VARCHAR(48) NULL,
                os           VARCHAR(48) NULL,
                device       VARCHAR(32) NULL,
                country      VARCHAR(8) NULL,
                language     VARCHAR(16) NULL,
                source       VARCHAR(32) NULL,
                is_bot       TINYINT NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                KEY idx_clicks_link (link_id, clicked_at),
                KEY idx_clicks_date (click_date),
                KEY idx_clicks_visitor (link_id, visitor_hash),
                CONSTRAINT fk_clicks_link FOREIGN KEY (link_id)
                    REFERENCES links(id) ON DELETE CASCADE
            ) {$tail}
            SQL,
            <<<SQL
            CREATE TABLE IF NOT EXISTS remember_tokens (
                id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id    BIGINT UNSIGNED NOT NULL,
                selector   VARCHAR(64) NOT NULL,
                token_hash VARCHAR(255) NOT NULL,
                expires_at VARCHAR(19) NOT NULL,
                created_at VARCHAR(19) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_remember_selector (selector),
                CONSTRAINT fk_remember_user FOREIGN KEY (user_id)
                    REFERENCES users(id) ON DELETE CASCADE
            ) {$tail}
            SQL,
            <<<SQL
            CREATE TABLE IF NOT EXISTS login_attempts (
                id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                ip         VARCHAR(45) NOT NULL,
                username   VARCHAR(64) NULL,
                successful TINYINT NOT NULL DEFAULT 0,
                created_at VARCHAR(19) NOT NULL,
                PRIMARY KEY (id),
                KEY idx_attempts_ip (ip, created_at)
            ) {$tail}
            SQL,
            <<<SQL
            CREATE TABLE IF NOT EXISTS audit_log (
                id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id    BIGINT UNSIGNED NULL,
                actor      VARCHAR(64) NULL,
                action     VARCHAR(48) NOT NULL,
                detail     TEXT NULL,
                ip         VARCHAR(45) NULL,
                created_at VARCHAR(19) NOT NULL,
                PRIMARY KEY (id),
                KEY idx_audit_created (created_at)
            ) {$tail}
            SQL,
        ];
    }

    // ------------------------------------------------------------ thông tin

    /** Tên loại cơ sở dữ liệu để hiện ở trang quản trị. */
    public static function driverLabel(): string
    {
        return self::isMysql() ? 'MySQL / MariaDB' : 'SQLite (một tệp)';
    }

    /** Phiên bản máy chủ cơ sở dữ liệu. */
    public static function serverVersion(): string
    {
        try {
            if (self::isMysql()) {
                return (string) self::pdo()->query('SELECT VERSION()')->fetchColumn();
            }
            return (string) self::pdo()->query('SELECT sqlite_version()')->fetchColumn();
        } catch (PDOException) {
            return '—';
        }
    }

    /**
     * Dung lượng dữ liệu, tính bằng byte.
     *
     * SQLite: kích thước tệp .sqlite (kể cả tệp -wal, -shm đi kèm).
     * MySQL : tổng dung lượng dữ liệu + chỉ mục của các bảng hệ thống dùng.
     */
    public static function fileSize(): int
    {
        if (self::isMysql()) {
            try {
                $stmt = self::pdo()->prepare(
                    'SELECT IFNULL(SUM(data_length + index_length), 0)
                     FROM information_schema.TABLES
                     WHERE table_schema = :db'
                );
                $stmt->execute([':db' => (string) Config::get('db_name')]);
                return (int) $stmt->fetchColumn();
            } catch (PDOException) {
                return 0;
            }
        }

        $path = (string) Config::get('db_path');
        $size = is_file($path) ? (int) filesize($path) : 0;
        foreach (['-wal', '-shm'] as $suffix) {
            if (is_file($path . $suffix)) {
                $size += (int) filesize($path . $suffix);
            }
        }
        return $size;
    }

    /** Mô tả nơi lưu dữ liệu, hiện ở trang quản trị. */
    public static function location(): string
    {
        if (self::isMysql()) {
            $socket = trim((string) Config::get('db_socket'));
            $where = $socket !== ''
                ? $socket
                : Config::get('db_host', '127.0.0.1') . ':' . (int) Config::get('db_port', 3306);
            return (string) Config::get('db_name') . ' @ ' . $where;
        }
        return (string) Config::get('db_path');
    }

    /** Đường dẫn tệp SQLite. Chỉ có nghĩa khi db_driver = 'sqlite'. */
    public static function path(): string
    {
        return (string) Config::get('db_path');
    }

    /** Danh sách bảng của hệ thống, theo thứ tự phụ thuộc khoá ngoại. */
    public static function tables(): array
    {
        return ['settings', 'users', 'links', 'clicks', 'remember_tokens', 'login_attempts', 'audit_log'];
    }

    /** Dồn/tối ưu cơ sở dữ liệu cho gọn (dùng ở trang quản trị). */
    public static function vacuum(): void
    {
        $pdo = self::pdo();
        if (self::isMysql()) {
            // OPTIMIZE TABLE trên InnoDB được ánh xạ thành dựng lại bảng —
            // tác dụng tương đương VACUUM của SQLite.
            //
            // Phải dùng query() rồi ĐỌC HẾT kết quả, không được dùng exec():
            // OPTIMIZE TABLE trả về một bảng kết quả (Table / Op / Msg_text),
            // mà exec() không đọc cũng không giải phóng. Kết quả còn treo lại
            // làm mọi câu lệnh sau đó trên cùng kết nối báo lỗi
            // "Cannot execute queries while other unbuffered queries are
            // active" — tức là bấm nút Dồn nén xong thì cả trang quản trị lỗi.
            $stmt = $pdo->query('OPTIMIZE TABLE ' . implode(', ', self::tables()));
            try {
                do {
                    $stmt->fetchAll();
                } while ($stmt->nextRowset());
            } catch (PDOException) {
                // Một số bản PDO báo lỗi khi gọi nextRowset() lúc đã hết kết
                // quả. Đọc xong là đủ, không cần dừng cả thao tác vì việc này.
            }
            $stmt->closeCursor();
            return;
        }
        $pdo->exec('VACUUM');
    }
}
