<?php
// Chặn liệt kê thư mục nếu máy chủ không đọc .htaccess.
http_response_code(403);
exit('403 — Không có quyền truy cập.');
