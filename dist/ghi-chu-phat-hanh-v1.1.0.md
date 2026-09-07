## Tải về

| Tệp | Dung lượng | Dùng khi |
|---|---|---|
| **rutgon-link-v1.1.0.zip** | 233 KB | Giải nén thẳng vào `public_html` trên cPanel — đã sẵn sàng chạy |
| Source code (zip / tar.gz) | | Bản GitHub sinh tự động, nội dung tương đương |

```
SHA-256  48664fa4909c988a157cf2ae544998f0dd25320007db3f6c790d7042870ad818
```

Giải nén xong đọc **`CAI-DAT-NHANH.txt`** ngay trong tệp — có đủ các bước cài
trong khoảng 15 phút, kể cả cách cài dùng MySQL.

**Yêu cầu:** PHP 8.1+ với `mbstring`, và `pdo_sqlite` (mặc định) hoặc
`pdo_mysql`. Không cần Composer, Node.js hay SSH.

---

Thêm lựa chọn dùng **MySQL / MariaDB** thay cho SQLite. Đã kiểm định
**272 hạng mục, 0 lỗi** trên **cả hai** loại cơ sở dữ liệu.

> **Nâng cấp từ 1.0.0:** ghi đè `index.php`, `.htaccess`, `app/`, `assets/`,
> `tools/` là xong. **Không phải làm gì thêm** — mặc định vẫn là SQLite và
> tệp dữ liệu cũ dùng tiếp bình thường. Chỉ khi nào muốn đổi sang MySQL mới
> cần đọc mục mới trong hướng dẫn cài đặt.

### Thêm mới

#### Hỗ trợ MySQL / MariaDB

- Chọn loại cơ sở dữ liệu bằng `db_driver`: `sqlite` (mặc định, giữ nguyên
  cách cũ) hoặc `mysql`. Yêu cầu MySQL 5.7+ hoặc MariaDB 10.2+
- Cấu hình mới: `db_host`, `db_port`, `db_name`, `db_user`, `db_pass`,
  `db_charset`, `db_socket` (cho hosting yêu cầu nối qua socket) và `log_dir`
- Bảng được tạo tự động ở lần truy cập đầu tiên, giống như với SQLite
- Trang **Quản trị → Cài đặt** hiện đúng loại cơ sở dữ liệu, phiên bản máy
  chủ, dung lượng dữ liệu và cách sao lưu tương ứng
- Thông báo lỗi kết nối ghi rõ máy chủ và cơ sở dữ liệu nào (không kèm mật
  khẩu), và chỉ luôn cách bật `pdo_mysql` trên cPanel

#### Công cụ chuyển đổi cơ sở dữ liệu

- `tools/chuyen-doi-csdl.php` chuyển dữ liệu **hai chiều** giữa SQLite và
  MySQL, **giữ nguyên mã số (id)** nên khoá ngoại vẫn khớp và mã QR đã in ra
  vẫn dùng được
- Chế độ `--thu` xem trước số dòng sẽ chuyển, không ghi gì
- Chốt an toàn: không ghi đè dữ liệu ở phía đích khi chưa có `--force`
- Cả lượt chuyển nằm trong **một giao dịch** — lỗi giữa đường thì hoàn tác
  toàn bộ, không bao giờ để lại cơ sở dữ liệu chuyển dở
- Đọc theo lô 2000 dòng nên không vượt `memory_limit` dù có hàng trăm nghìn
  lượt nhấp
- Chuyển xong tự đối chiếu số dòng hai phía và kiểm tra khoá ngoại
- Không nhận mật khẩu trên dòng lệnh — cả hai phía đọc từ
  `app/config.local.php`
- Công cụ này **có** trong bản phát hành `.zip` và trong bản tải từ thẻ
  GitHub, khác với `tools/tao-du-lieu-mau.php`

#### Dữ liệu mẫu

- Công cụ dựng dữ liệu mẫu `tools/tao-du-lieu-mau.php`: 2 tài khoản, 8 liên
  kết thuộc hai chủ sở hữu, khoảng 900 lượt nhấp rải trong 30 ngày — để xem
  thử, tập huấn hoặc trình bày. Đây cũng chính là bộ dữ liệu dùng để chụp ảnh
  minh hoạ trong tài liệu. Chỉ chạy được từ dòng lệnh, tự dừng nếu cơ sở dữ
  liệu đã có dữ liệu, và **không** nằm trong bản phát hành (vì tạo tài khoản
  có mật khẩu công khai)

### Đã sửa lỗi

Bốn lỗi tương thích dưới đây chỉ xuất hiện khi chạy trên MySQL. Bản 1.0.0 chỉ
chạy SQLite nên **không ai bị ảnh hưởng**, nhưng ghi lại vì cùng một kiểu sai
có thể tái diễn khi bảo trì:

- **Dùng lặp một tên tham số** trong cùng câu lệnh (`:q` năm lần ở ô tìm kiếm,
  `:now` ba lần ở lọc trạng thái, và ở `Auth::findByLogin`,
  `Stats::overview`, `AdminController::users`). SQLite chấp nhận, MySQL với
  native prepares báo `Invalid parameter number`
- **Nối chuỗi bằng `||` khi lọc theo thẻ.** Đây là lỗi nguy hiểm nhất vì
  **không báo lỗi gì**: SQLite hiểu `||` là nối chuỗi, MySQL hiểu là phép
  HOẶC luận lý, nên MySQL trả về danh sách sai. Đã thay bằng bốn dạng khớp
  tường minh
- **`Database::vacuum()` gọi `OPTIMIZE TABLE` bằng `exec()`.** Lệnh này trả
  về một bảng kết quả mà `exec()` không đọc cũng không giải phóng, nên mọi
  câu lệnh sau đó trên cùng kết nối đều lỗi — bấm nút *Dồn nén* ở trang quản
  trị xong là cả trang lỗi
- **Chuỗi rỗng viết bằng dấu nháy kép** (`""`). Trong SQL chuẩn đó là tên cột;
  MySQL bật `ANSI_QUOTES` sẽ báo lỗi. Đã đổi hết sang `''`

Ngoài ra:

- `tests/dev-server.php` (bộ định tuyến cho máy chủ chạy thử ở máy cá nhân)
  chặn `data|app|tests` nhưng **thiếu `tools`**, không khớp với `.htaccess`.
  Không ảnh hưởng máy chủ thật vì `.htaccess` vẫn chặn, và bản thân công cụ
  cũng có chốt `PHP_SAPI`; nhưng đã bổ sung cho hai danh sách khớp nhau

### Thay đổi

- `COLLATE NOCASE` khi sắp theo mã đổi thành `LOWER()` — cả hai loại cơ sở dữ
  liệu đều hiểu, thứ tự không đổi
- Cột `key` của bảng `settings` luôn viết trong dấu `` ` `` (MySQL coi `key`
  là từ khoá; SQLite cũng nhận dấu này)
- `Settings::set()` dùng xoá-rồi-thêm trong một giao dịch, thay cho cú pháp
  `ON CONFLICT … excluded` riêng của SQLite
- Kiểm tra phần mở rộng PHP lúc khởi động giờ theo `db_driver`: cần
  `pdo_sqlite` hoặc `pdo_mysql`, không cần cả hai
- Nơi ghi `error.log` đặt được riêng bằng `log_dir` (trước đây luôn suy ra từ
  `db_path`, không còn hợp lý khi dùng MySQL)
- `tools/build-release.php` thêm chốt kiểm tra danh sách đóng gói: dừng hẳn
  nếu lỡ đưa `tao-du-lieu-mau.php`, `tests/`, tệp `.sqlite` hay
  `config.local.php` vào bản phát hành, hoặc thiếu tệp bắt buộc

### Kiểm định

- Bộ mới `tests/db_test.php` — 65 hạng mục soi đúng những chỗ hai loại cơ sở
  dữ liệu dễ khác nhau, **kiểm theo kết quả trả về** chứ không chỉ kiểm có
  báo lỗi hay không (vì lỗi `||` nói trên chạy êm mà trả về sai). Trong đó có
  hạng mục so cấu trúc bảng của loại đang chạy với cấu trúc SQLite dựng trong
  bộ nhớ, nên thêm cột một bên mà quên bên kia là biết ngay
- `tests/run_all.php` thêm cờ `--mysql`, lấy thông tin kết nối từ biến môi
  trường `RUTGON_TEST_DB_*`
- Thêm 4 hạng mục canh danh sách chặn của bộ định tuyến chạy thử
  (`/data/`, `/app/`, `/tests/`, `/tools/`). Hạng mục đòi đúng **thông báo của
  bộ định tuyến** chứ không chỉ đòi mã 403, vì riêng `/tools/` còn có chốt
  `PHP_SAPI` bên trong tệp cũng trả 403 — chỉ xét mã trạng thái thì bỏ danh
  sách chặn đi mà hạng mục vẫn "đạt"
- Tổng: **272 hạng mục, 0 lỗi** trên cả hai loại cơ sở dữ liệu (trước: 201)

### Tài liệu

- Hướng dẫn cài đặt: mục mới **Dùng MySQL thay cho SQLite** — bảng so sánh nên
  dùng loại nào, **Cách 1** cài mới dùng MySQL ngay từ đầu (không cần dòng
  lệnh), **Cách 2** chuyển từ SQLite sang (kèm sơ đồ), ba cách chạy công cụ
  trên hosting (cPanel Terminal / Cron Jobs / SSH), và 7 mục xử lý sự cố MySQL
- `CAI-DAT-NHANH.txt` trong bản `.zip` thêm hẳn quy trình cài mới dùng MySQL
  gồm 5 bước, để không phải mở tài liệu đầy đủ mới cài được
- Quy trình kỹ thuật: mục mới **§3b Hai loại cơ sở dữ liệu** — sơ đồ lớp
  `Database`, bảng đối chiếu hai bản cấu trúc, giải thích vì sao mốc thời gian
  dùng `VARCHAR` chứ không dùng `DATETIME`, bốn cái bẫy tương thích, và sơ đồ
  công cụ chuyển đổi
- Hướng dẫn sử dụng: mục **Xem toàn bộ dữ liệu của mọi người**,
  **Người dùng thường thấy gì**, **Quản trị viên *không* xem được gì** và
  **Hệ thống đang lưu dữ liệu ở đâu**, kèm 6 ảnh minh hoạ mới (`31`–`36`)
- Ghi rõ ba chỗ liên quan tới địa chỉ IP: IP người nhấp liên kết **không được
  lưu**, còn `audit_log.ip` và `links.creator_ip` là IP của người đăng nhập /
  người tạo liên kết
- 24 sơ đồ Mermaid (trước: 20), 36 ảnh minh hoạ (trước: 30)
- **Chụp lại toàn bộ 36 ảnh minh hoạ theo tên miền thật `link.dongnaiedu.vn`**,
  và đổi tên miền trong mọi ví dụ ở tài liệu, `app/config.php` và bộ kiểm định
- Năm ảnh trước đây chụp theo **toạ độ cắt cố định** giờ chụp theo **phần tử**
  (`02`, `03`, `04`, `09`, `13`, `16`). Toạ độ cứng đã cắt mất phần quan trọng:
  ảnh `09` mất cả hàng nút thao tác dù chú thích ghi "có đủ nút thao tác", ảnh
  `16` mất tiêu đề biểu đồ và đỉnh đường cong. Chụp theo phần tử thì bố cục hay
  tên miền đổi thế nào cũng lấy đúng trọn khối

---

## Tài liệu

- [Hướng dẫn sử dụng](https://github.com/tlearnvn/dongnai-link-shortener/blob/claude/eager-meitner-f7stav/docs/huong-dan-su-dung.md) — cho cán bộ, giáo viên
- [Cài đặt trên cPanel](https://github.com/tlearnvn/dongnai-link-shortener/blob/claude/eager-meitner-f7stav/docs/cai-dat-cpanel.md) — từng bước, kèm xử lý sự cố
- [Quy trình kỹ thuật](https://github.com/tlearnvn/dongnai-link-shortener/blob/claude/eager-meitner-f7stav/docs/quy-trinh-ky-thuat.md) — kiến trúc, cơ sở dữ liệu, bảo mật

---

*Thiết kế bởi Trương Anh Tuấn — Phòng GDPT-GDTX Sở GDĐT Đồng Nai*
