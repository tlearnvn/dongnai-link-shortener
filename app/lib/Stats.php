<?php
declare(strict_types=1);

/**
 * Truy vấn thống kê.
 *
 * Mọi mốc thời gian đều tính theo UTC+7 (cột click_date, click_hour được ghi
 * sẵn lúc phát sinh lượt nhấp nên không phải quy đổi múi giờ khi truy vấn).
 */
final class Stats
{
    /**
     * Điều kiện lọc dùng chung: theo một liên kết, theo một chủ sở hữu,
     * hoặc toàn hệ thống.
     *
     * @return array{sql: string, params: array<string, mixed>}
     */
    private static function scope(?int $linkId, ?int $userId): array
    {
        if ($linkId !== null) {
            return ['sql' => ' AND c.link_id = :link_id', 'params' => [':link_id' => $linkId]];
        }
        if ($userId !== null) {
            return [
                'sql' => ' AND c.link_id IN (SELECT id FROM links WHERE user_id = :user_id)',
                'params' => [':user_id' => $userId],
            ];
        }
        return ['sql' => '', 'params' => []];
    }

    /**
     * Số liệu tổng quan.
     *
     * @return array<string, int|float|string|null>
     */
    public static function overview(?int $userId = null): array
    {
        $pdo = Database::pdo();
        $linkWhere = $userId !== null ? ' WHERE user_id = :user_id' : '';
        $linkParams = $userId !== null ? [':user_id' => $userId] : [];

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS total_links,
                    IFNULL(SUM(click_count), 0) AS total_clicks,
                    IFNULL(SUM(unique_count), 0) AS unique_clicks,
                    IFNULL(SUM(CASE WHEN password_hash IS NOT NULL THEN 1 ELSE 0 END), 0) AS protected_links,
                    IFNULL(SUM(CASE WHEN is_starred = 1 THEN 1 ELSE 0 END), 0) AS starred_links,
                    IFNULL(MAX(click_count), 0) AS best_clicks
             FROM links' . $linkWhere
        );
        $stmt->execute($linkParams);
        $base = $stmt->fetch() ?: [];

        $today = Clock::today();
        $scope = self::scope(null, $userId);
        $weekStart = Clock::nowDt()->modify('-6 days')->format('Y-m-d');
        $monthStart = Clock::nowDt()->modify('-29 days')->format('Y-m-d');

        $stmt = $pdo->prepare(
            'SELECT
                IFNULL(SUM(CASE WHEN c.click_date  = :today      THEN 1 ELSE 0 END), 0) AS clicks_today,
                IFNULL(SUM(CASE WHEN c.click_date >= :week_start THEN 1 ELSE 0 END), 0) AS clicks_week,
                IFNULL(SUM(CASE WHEN c.click_date >= :month_start THEN 1 ELSE 0 END), 0) AS clicks_month
             FROM clicks c WHERE 1 = 1' . $scope['sql']
        );
        $stmt->execute($scope['params'] + [
            ':today' => $today,
            ':week_start' => $weekStart,
            ':month_start' => $monthStart,
        ]);
        $periods = $stmt->fetch() ?: [];

        // Đếm liên kết đang chạy (áp dụng đúng các điều kiện như lúc chuyển hướng)
        $now = Clock::now();
        $activeSql = 'SELECT COUNT(*) FROM links WHERE is_active = 1
                        AND (expires_at IS NULL OR expires_at > :now)
                        AND (starts_at IS NULL OR starts_at <= :now)
                        AND (max_clicks IS NULL OR click_count < max_clicks)';
        $activeParams = [':now' => $now];
        if ($userId !== null) {
            $activeSql .= ' AND user_id = :user_id';
            $activeParams[':user_id'] = $userId;
        }
        $stmt = $pdo->prepare($activeSql);
        $stmt->execute($activeParams);
        $activeLinks = (int) $stmt->fetchColumn();

        $totalLinks = (int) ($base['total_links'] ?? 0);
        $totalClicks = (int) ($base['total_clicks'] ?? 0);

        return [
            'total_links' => $totalLinks,
            'active_links' => $activeLinks,
            'total_clicks' => $totalClicks,
            'unique_clicks' => (int) ($base['unique_clicks'] ?? 0),
            'protected_links' => (int) ($base['protected_links'] ?? 0),
            'starred_links' => (int) ($base['starred_links'] ?? 0),
            'best_clicks' => (int) ($base['best_clicks'] ?? 0),
            'clicks_today' => (int) ($periods['clicks_today'] ?? 0),
            'clicks_week' => (int) ($periods['clicks_week'] ?? 0),
            'clicks_month' => (int) ($periods['clicks_month'] ?? 0),
            'avg_per_link' => $totalLinks > 0 ? round($totalClicks / $totalLinks, 1) : 0.0,
        ];
    }

    /**
     * Số lượt nhấp theo từng ngày, đã điền 0 cho ngày không có lượt nào.
     *
     * @return array<int, array{date: string, label: string, clicks: int, unique: int}>
     */
    public static function dailySeries(int $days = 30, ?int $linkId = null, ?int $userId = null): array
    {
        $days = max(1, min(365, $days));
        $start = Clock::nowDt()->modify('-' . ($days - 1) . ' days');
        $scope = self::scope($linkId, $userId);

        $stmt = Database::pdo()->prepare(
            'SELECT c.click_date AS d, COUNT(*) AS total, SUM(c.is_unique) AS uniq
             FROM clicks c
             WHERE c.click_date >= :start' . $scope['sql'] . '
             GROUP BY c.click_date'
        );
        $stmt->execute($scope['params'] + [':start' => $start->format('Y-m-d')]);

        $found = [];
        foreach ($stmt->fetchAll() as $row) {
            $found[(string) $row['d']] = [
                'clicks' => (int) $row['total'],
                'unique' => (int) $row['uniq'],
            ];
        }

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $start->modify("+{$i} days");
            $key = $day->format('Y-m-d');
            $series[] = [
                'date' => $key,
                'label' => $day->format('d/m'),
                'clicks' => $found[$key]['clicks'] ?? 0,
                'unique' => $found[$key]['unique'] ?? 0,
            ];
        }

        return $series;
    }

    /**
     * Phân bố lượt nhấp theo 24 giờ trong ngày.
     *
     * @return array<int, array{label: string, value: int}>
     */
    public static function hourly(?int $linkId = null, ?int $userId = null): array
    {
        $scope = self::scope($linkId, $userId);
        $stmt = Database::pdo()->prepare(
            'SELECT c.click_hour AS h, COUNT(*) AS total FROM clicks c
             WHERE 1 = 1' . $scope['sql'] . ' GROUP BY c.click_hour'
        );
        $stmt->execute($scope['params']);

        $found = [];
        foreach ($stmt->fetchAll() as $row) {
            $found[(int) $row['h']] = (int) $row['total'];
        }

        $result = [];
        for ($h = 0; $h < 24; $h++) {
            $result[] = ['label' => sprintf('%02dh', $h), 'value' => $found[$h] ?? 0];
        }
        return $result;
    }

    /**
     * Phân bố theo thứ trong tuần.
     *
     * @return array<int, array{label: string, value: int}>
     */
    public static function weekday(?int $linkId = null, ?int $userId = null): array
    {
        $scope = self::scope($linkId, $userId);
        $stmt = Database::pdo()->prepare(
            'SELECT c.weekday AS w, COUNT(*) AS total FROM clicks c
             WHERE 1 = 1' . $scope['sql'] . ' GROUP BY c.weekday'
        );
        $stmt->execute($scope['params']);

        $found = [];
        foreach ($stmt->fetchAll() as $row) {
            $found[(int) $row['w']] = (int) $row['total'];
        }

        $result = [];
        // Bắt đầu từ Thứ hai cho quen mắt người Việt, Chủ nhật xếp cuối.
        foreach ([1, 2, 3, 4, 5, 6, 0] as $w) {
            $result[] = [
                'label' => ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'][$w],
                'value' => $found[$w] ?? 0,
            ];
        }
        return $result;
    }

    /**
     * Xếp hạng theo một thuộc tính của lượt nhấp (trình duyệt, hệ điều hành...).
     *
     * @return array<int, array{label: string, value: int, percent: float}>
     */
    public static function breakdown(string $column, ?int $linkId = null, ?int $userId = null, int $limit = 8): array
    {
        $allowed = ['browser', 'os', 'device', 'country', 'referer_host', 'language', 'source'];
        if (!in_array($column, $allowed, true)) {
            throw new InvalidArgumentException('Cột thống kê không hợp lệ: ' . $column);
        }

        $scope = self::scope($linkId, $userId);
        $stmt = Database::pdo()->prepare(
            "SELECT IFNULL(NULLIF(c.{$column}, ''), '—') AS label, COUNT(*) AS total
             FROM clicks c WHERE 1 = 1" . $scope['sql'] . '
             GROUP BY label ORDER BY total DESC LIMIT ' . max(1, min(50, $limit))
        );
        $stmt->execute($scope['params']);
        $rows = $stmt->fetchAll();

        $total = 0;
        foreach ($rows as $row) {
            $total += (int) $row['total'];
        }

        $result = [];
        foreach ($rows as $row) {
            $value = (int) $row['total'];
            $label = (string) $row['label'];
            if ($column === 'referer_host' && ($label === '—' || $label === '')) {
                $label = 'Truy cập trực tiếp';
            }
            if ($column === 'source') {
                // Giá trị lưu trong bảng là mã ngắn, đổi sang tiếng Việt khi hiển thị.
                $label = match ($label) {
                    '—' => 'Nhấp liên kết',
                    'qr' => 'Quét mã QR',
                    'zalo' => 'Từ Zalo',
                    'fb' => 'Từ Facebook',
                    'email' => 'Từ email',
                    'sms' => 'Từ tin nhắn',
                    default => $label,
                };
            }
            $result[] = [
                'label' => $label,
                'value' => $value,
                'percent' => $total > 0 ? round($value * 100 / $total, 1) : 0.0,
            ];
        }

        return $result;
    }

    /**
     * Các liên kết được nhấp nhiều nhất.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function topLinks(?int $userId = null, int $limit = 5): array
    {
        $sql = 'SELECT l.*, u.username AS owner_username FROM links l
                LEFT JOIN users u ON u.id = l.user_id';
        $params = [];
        if ($userId !== null) {
            $sql .= ' WHERE l.user_id = :u';
            $params[':u'] = $userId;
        }
        $sql .= ' ORDER BY l.click_count DESC, l.created_at DESC LIMIT ' . max(1, min(50, $limit));

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Số lượt nhấp theo ngày của nhiều liên kết cùng lúc, dùng để vẽ đường
     * biểu diễn nhỏ trên từng thẻ liên kết. Chỉ một truy vấn cho cả danh sách
     * (tránh tình trạng mỗi thẻ một truy vấn).
     *
     * @param array<int, int> $linkIds
     * @return array<int, array<int, int>> [id liên kết => mảng số lượt theo ngày]
     */
    public static function sparkForLinks(array $linkIds, int $days = 14): array
    {
        $linkIds = array_values(array_unique(array_map('intval', $linkIds)));
        if ($linkIds === []) {
            return [];
        }
        $days = max(2, min(90, $days));
        $start = Clock::nowDt()->modify('-' . ($days - 1) . ' days');

        $placeholders = implode(',', array_fill(0, count($linkIds), '?'));
        $stmt = Database::pdo()->prepare(
            "SELECT link_id, click_date, COUNT(*) AS total FROM clicks
             WHERE click_date >= ? AND link_id IN ({$placeholders})
             GROUP BY link_id, click_date"
        );
        $stmt->execute(array_merge([$start->format('Y-m-d')], $linkIds));

        $found = [];
        foreach ($stmt->fetchAll() as $row) {
            $found[(int) $row['link_id']][(string) $row['click_date']] = (int) $row['total'];
        }

        // Danh sách ngày liên tục để đường biểu diễn không bị "nhảy" ngày trống
        $dates = [];
        for ($i = 0; $i < $days; $i++) {
            $dates[] = $start->modify("+{$i} days")->format('Y-m-d');
        }

        $result = [];
        foreach ($linkIds as $id) {
            $values = [];
            foreach ($dates as $date) {
                $values[] = $found[$id][$date] ?? 0;
            }
            $result[$id] = $values;
        }

        return $result;
    }

    /**
     * Danh sách lượt nhấp gần đây của một liên kết.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function recentClicks(int $linkId, int $limit = 20): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM clicks WHERE link_id = :l ORDER BY clicked_at DESC LIMIT ' . max(1, min(200, $limit))
        );
        $stmt->execute([':l' => $linkId]);
        return $stmt->fetchAll();
    }

    /** Số lượt nhấp do robot/công cụ quét sinh ra. */
    public static function botClicks(?int $linkId = null, ?int $userId = null): int
    {
        $scope = self::scope($linkId, $userId);
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM clicks c WHERE c.is_bot = 1' . $scope['sql']
        );
        $stmt->execute($scope['params']);
        return (int) $stmt->fetchColumn();
    }

    /** Số lượt quét bằng mã QR. */
    public static function qrClicks(?int $linkId = null, ?int $userId = null): int
    {
        $scope = self::scope($linkId, $userId);
        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM clicks c WHERE c.source = 'qr'" . $scope['sql']
        );
        $stmt->execute($scope['params']);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Ngày có nhiều lượt nhấp nhất.
     *
     * @return array{date: string, clicks: int}|null
     */
    public static function busiestDay(?int $linkId = null, ?int $userId = null): ?array
    {
        $scope = self::scope($linkId, $userId);
        $stmt = Database::pdo()->prepare(
            'SELECT c.click_date AS d, COUNT(*) AS total FROM clicks c
             WHERE 1 = 1' . $scope['sql'] . '
             GROUP BY c.click_date ORDER BY total DESC LIMIT 1'
        );
        $stmt->execute($scope['params']);
        $row = $stmt->fetch();
        return $row === false ? null : ['date' => (string) $row['d'], 'clicks' => (int) $row['total']];
    }

    /** Thống kê chung của toàn hệ thống (trang quản trị). */
    public static function systemSummary(): array
    {
        $pdo = Database::pdo();
        return [
            'users' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'admins' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
            'links' => (int) $pdo->query('SELECT COUNT(*) FROM links')->fetchColumn(),
            'guest_links' => (int) $pdo->query('SELECT COUNT(*) FROM links WHERE user_id IS NULL')->fetchColumn(),
            'clicks' => (int) $pdo->query('SELECT COUNT(*) FROM clicks')->fetchColumn(),
            'db_size' => Database::fileSize(),
            'installed_at' => Settings::get('installed_at'),
        ];
    }

    /** Xoá chi tiết lượt nhấp cũ hơn N ngày (số tổng hợp vẫn giữ nguyên). */
    public static function pruneClicks(int $days): int
    {
        if ($days <= 0) {
            return 0;
        }
        $cutoff = Clock::nowDt()->modify("-{$days} days")->format('Y-m-d');
        $stmt = Database::pdo()->prepare('DELETE FROM clicks WHERE click_date < :cutoff');
        $stmt->execute([':cutoff' => $cutoff]);
        return $stmt->rowCount();
    }
}
