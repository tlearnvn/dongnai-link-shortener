<?php
declare(strict_types=1);

/** Bộ định tuyến nhỏ gọn: khớp phương thức + đường dẫn rồi gọi hàm xử lý. */
final class Router
{
    /** @var array<int, array{method: string, regex: string, keys: array<int, string>, handler: callable|array}> */
    private array $routes = [];

    /** @var (callable(): void)|null */
    private $notFound = null;

    public function get(string $pattern, callable|array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable|array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    /** Đăng ký cho cả GET và POST. */
    public function any(string $pattern, callable|array $handler): void
    {
        $this->add('GET', $pattern, $handler);
        $this->add('POST', $pattern, $handler);
    }

    public function fallback(callable $handler): void
    {
        $this->notFound = $handler;
    }

    private function add(string $method, string $pattern, callable|array $handler): void
    {
        $keys = [];
        // {ten} khớp một đoạn đường dẫn; {ten:bieu-thuc} cho phép tự đặt biểu thức.
        $regex = preg_replace_callback(
            '/\{([a-z_]+)(?::([^}]+))?\}/i',
            static function (array $m) use (&$keys): string {
                $keys[] = $m[1];
                return '(' . ($m[2] ?? '[^/]+') . ')';
            },
            $pattern
        ) ?? $pattern;

        $this->routes[] = [
            'method' => $method,
            'regex' => '#^' . $regex . '$#u',
            'keys' => $keys,
            'handler' => $handler,
        ];
    }

    /** Tìm tuyến khớp và chạy; không có thì gọi hàm dự phòng. */
    public function dispatch(string $method, string $path): void
    {
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }
            $pathMatched = true;
            if ($route['method'] !== $method) {
                continue;
            }

            $params = [];
            foreach ($route['keys'] as $i => $key) {
                $params[$key] = $matches[$i + 1] ?? '';
            }

            $handler = $route['handler'];
            if (is_array($handler)) {
                [$class, $action] = $handler;
                $handler = [$class, $action];
            }
            /** @var callable $handler */
            $handler($params);
            return;
        }

        // Đúng đường dẫn nhưng sai phương thức -> 405
        if ($pathMatched) {
            http_response_code(405);
            header('Allow: GET, POST');
            abort(405, 'Phương thức không được phép', 'Yêu cầu này không dùng đúng phương thức HTTP.');
        }

        if ($this->notFound !== null) {
            ($this->notFound)();
            return;
        }

        abort(404, 'Không tìm thấy trang', 'Đường dẫn bạn truy cập không tồn tại.');
    }
}
