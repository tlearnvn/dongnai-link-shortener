# Lịch sử phiên bản

Mọi thay đổi đáng chú ý của **Rút gọn link — Phòng GDPT-GDTX Sở GDĐT Đồng Nai**
đều được ghi ở đây.

Tài liệu theo khuôn [Keep a Changelog](https://keepachangelog.com/vi/1.1.0/),
số phiên bản theo [Semantic Versioning](https://semver.org/lang/vi/).

---

## Cách đọc số phiên bản

Số phiên bản có dạng **X.Y.Z**:

| Phần | Tăng khi | Việc cần làm khi nâng cấp |
|---|---|---|
| **X** — lớn | Có thay đổi phá vỡ tương thích | Đọc kỹ ghi chú, có thể phải sửa cấu hình |
| **Y** — vừa | Thêm tính năng mới, vẫn tương thích | Ghi đè tệp là xong |
| **Z** — nhỏ | Chỉ sửa lỗi | Ghi đè tệp là xong |

Mọi bản nâng cấp: **luôn sao lưu `data/` trước**, và **không ghi đè**
`data/` cùng `app/config.local.php`.

Nhóm thay đổi dùng các nhãn: **Thêm mới**, **Thay đổi**, **Đã sửa lỗi**,
**Bảo mật**, **Bỏ đi**, **Ngừng hỗ trợ**.

---

## [Chưa phát hành]

### Thêm mới

- Công cụ dựng dữ liệu mẫu `tools/tao-du-lieu-mau.php`: 2 tài khoản, 8 liên
  kết thuộc hai chủ sở hữu, khoảng 900 lượt nhấp rải trong 30 ngày — để xem
  thử, tập huấn hoặc trình bày. Chỉ chạy được từ dòng lệnh, tự dừng nếu cơ sở
  dữ liệu đã có dữ liệu, và không nằm trong bản phát hành

### Tài liệu

- Hướng dẫn sử dụng: thêm mục **Xem toàn bộ dữ liệu của mọi người**,
  **Người dùng thường thấy gì** và **Quản trị viên *không* xem được gì**, kèm
  5 ảnh minh hoạ mới (`31`–`35`)
- Quy trình kỹ thuật: thêm mục **Phạm vi dữ liệu đọc được**,
  **Những gì không ai đọc được**, **Truy cập thô bằng công cụ SQLite** và
  **Dựng dữ liệu mẫu để xem thử**
- Ghi rõ ba chỗ liên quan tới địa chỉ IP: IP người nhấp liên kết **không được
  lưu**, còn `audit_log.ip` và `links.creator_ip` là IP của người đăng nhập /
  người tạo liên kết

---

## [1.0.0] — 2026-09-07

Bản phát hành đầu tiên. Hệ thống hoàn chỉnh, đã kiểm định **201 hạng mục,
0 lỗi**.

### Thêm mới

#### Rút gọn liên kết

- Rút gọn liên kết với **tên tuỳ chọn** do người dùng tự đặt
  (`/tuyen-sinh-2026`), kiểm tra tính khả dụng **ngay khi gõ** và gợi ý tên
  thay thế nếu đã có người dùng
- Sinh mã tự động bằng bộ ký tự đã loại các chữ dễ nhìn lẫn (`0/O`, `1/l/I`)
- Tự thêm `https://` khi người dùng gõ thiếu
- Tra cứu không phân biệt chữ hoa/thường
- **Sửa được địa chỉ đích mà giữ nguyên liên kết ngắn** — tài liệu ra bản mới
  không phải in lại văn bản
- Chặn 45 tên trùng đường dẫn hệ thống, chặn tự trỏ về chính hệ thống
- **Tạo hàng loạt**: dán danh sách, đặt tên riêng từng dòng bằng dấu `|`,
  tối đa 200 dòng mỗi lần
- Trang **xem trước** `/xem/{tên}` — biết liên kết dẫn tới đâu mà không tính
  vào lượt nhấp

#### Kiểm soát liên kết

- Mật khẩu bảo vệ từng liên kết (trang nhập mật khẩu không để lộ địa chỉ đích)
- Hẹn giờ bắt đầu hoạt động và thời điểm hết hạn
- Giới hạn số lượt nhấp tối đa
- Tạm dừng / bật lại mà không mất số liệu đã thống kê
- Thẻ phân loại (tối đa 8 thẻ mỗi liên kết) và ghim liên kết quan trọng
- Ghi chú nội bộ

#### Mã QR

- **Bộ tạo mã QR viết riêng bằng PHP thuần**, theo chuẩn ISO/IEC 18004:
  chế độ byte/UTF-8, phiên bản 1–40, đủ bốn mức sửa lỗi L/M/Q/H
- Xuất **PNG** (qua thư viện GD) và **SVG vector** (in khổ lớn không rỗ)
- Tuỳ chỉnh kích thước ô, lề, màu mã, màu nền, nền trong suốt, mức sửa lỗi
- Sáu bảng màu có sẵn; xem trước đổi ngay khi kéo thanh trượt
- Mã mang dấu `?s=qr` để thống kê tách riêng lượt quét QR với lượt bấm liên kết
- Nút in mã QR ra giấy

#### Thống kê

- Lượt nhấp theo ngày (7 / 14 / 30 / 90 / 365 ngày), theo giờ trong ngày,
  theo thứ trong tuần — **tất cả theo giờ Việt Nam**
- Số khách riêng biệt, lượt quét QR, lượt do robot
- Phân tích thiết bị, trình duyệt, hệ điều hành, nguồn giới thiệu, quốc gia,
  cách tiếp cận
- Bảng xếp hạng liên kết, ngày cao điểm
- Vòng tỉ lệ cho thời hạn và giới hạn lượt nhấp
- Bảng chi tiết từng lượt nhấp gần đây
- **Biểu đồ vẽ sẵn ở máy chủ bằng SVG** — hiện ngay khi tải trang, in ra
  giấy vẫn đúng, tự đổi màu theo giao diện sáng/tối, không gọi CDN
- Xuất CSV có BOM UTF-8 (Excel đọc đúng tiếng Việt)

#### Tài khoản

- Đăng ký / đăng nhập bằng tên đăng nhập hoặc email
- Ghi nhớ đăng nhập 30 ngày theo mô hình selector + validator, token dùng
  một lần
- **Người dùng đầu tiên tự động thành quản trị viên**
- Quên mật khẩu bằng **mã dự phòng** (hệ thống không gửi email)
- Khoá API riêng cho từng tài khoản
- Chọn giao diện sáng / tối / theo hệ thống, lưu theo tài khoản
- Thanh đo độ mạnh mật khẩu

#### Quản trị

- Quản lý người dùng: cấp/bỏ quyền quản trị, tạm ngưng, đặt lại mật khẩu,
  đặt hạn mức số liên kết, xoá tài khoản (liên kết cũ vẫn hoạt động)
- Xem toàn bộ liên kết của mọi người, thống kê toàn hệ thống
- Bật/tắt tự đăng ký, bật/tắt cho khách rút gọn, chặn tự trỏ, tự lấy tiêu đề
  trang đích, mức sửa lỗi QR mặc định, dải thông báo đầu trang
- Nhật ký hoạt động
- Bảo trì: xoá chi tiết lượt nhấp cũ, xoá liên kết hết hạn, xoá nhật ký,
  mở khoá đăng nhập, dồn nén cơ sở dữ liệu (VACUUM)

#### API

- `GET /api/kiem-tra-ten` — kiểm tra tên còn trống (không cần khoá)
- `POST /api/rut-gon` — tạo liên kết
- `GET /api/lien-ket` — danh sách liên kết của mình
- `GET /api/thong-ke/{tên}` — số liệu thống kê
- Ảnh mã QR truy cập trực tiếp: `/ma-qr/{tên}.png` và `.svg`
- Mốc thời gian trả về theo ISO 8601 kèm độ lệch `+07:00`
- Trang tài liệu API tại `/api`

#### Công cụ

- Trình tạo liên kết gắn thẻ UTM, kèm bảng gợi ý cách đặt cho đơn vị giáo dục

#### Giao diện

- Nền động, thẻ kính mờ, hiệu ứng confetti khi tạo liên kết xong
- Giao diện sáng / tối, tự theo hệ điều hành
- Responsive tới màn hình điện thoại
- Bản in riêng (`@media print`)
- Tôn trọng `prefers-reduced-motion`
- **Hoạt động khi tắt JavaScript**: biểu mẫu gửi bằng POST thường, biểu đồ
  vẽ ở máy chủ, mã QR đổi bằng nút Áp dụng

#### Hạ tầng

- Toàn bộ dữ liệu trong **một tệp SQLite duy nhất**, 7 bảng, tự tạo ở lần
  truy cập đầu tiên
- SQLite chế độ WAL, `busy_timeout` 5 giây
- Không cần Composer, không framework, không thư viện JavaScript ngoài
- Chạy được ở gốc tên miền, trong thư mục con, và cả khi máy chủ **không có**
  `mod_rewrite`
- Cấu hình riêng của từng máy chủ tách ra `app/config.local.php`, không bị
  ghi đè khi nâng cấp

#### Bảo mật

- Mật khẩu băm bằng `password_hash()`, tự nâng cấp thuật toán khi cấu hình đổi
- Chống CSRF trên mọi biểu mẫu POST
- Escape toàn bộ dữ liệu người dùng khi in ra trang
- Truy vấn tham số hoá 100%; tên cột động đối chiếu danh sách trắng
- Chỉ nhận địa chỉ `http`/`https`
- Lọc ký tự lạ trong header `Host`
- Khoá IP sau 8 lần đăng nhập sai trong 15 phút
- Giới hạn khách 10 liên kết/giờ theo IP
- Chờ 0,4 giây sau mỗi lần nhập sai mật khẩu liên kết
- Chống SSRF cho chức năng lấy tiêu đề trang đích (mặc định tắt)
- Chặn truy cập web vào `data/`, `app/`, `tests/`, `tools/` bằng hai lớp
  (rewrite + `.htaccess` từng thư mục)

#### Quyền riêng tư

- **Không lưu địa chỉ IP của người truy cập liên kết** — chỉ lưu mã băm ẩn
  danh SHA-256 với muối riêng của từng bản cài đặt
- Không gọi dịch vụ bên ngoài nào: không phông chữ Google, không dịch vụ
  favicon, không thư viện CDN
- Nhãn tên miền trên thẻ liên kết được sinh tại chỗ
- Tuỳ chọn tự dọn chi tiết lượt nhấp cũ theo số ngày

#### Tài liệu

- [Hướng dẫn sử dụng](docs/huong-dan-su-dung.md) — 15 mục, 30 ảnh minh hoạ
- [Quy trình kỹ thuật](docs/quy-trinh-ky-thuat.md) — 14 mục, 15 sơ đồ Mermaid
- [Cài đặt trên cPanel](docs/cai-dat-cpanel.md) — 7 bước kèm danh sách kiểm tra
- README với bảng cấu hình đầy đủ

#### Kiểm định

- `tests/path_test.php` — 19 hạng mục: nhận diện đường dẫn ở ba kiểu triển khai
- `tests/qr_test.php` — 54 hạng mục: đối chiếu bảng trong chuẩn ISO/IEC 18004,
  giải mã ngược cả 40 phiên bản × 4 mức sửa lỗi
- `tests/qr_image_test.php` — 10 hạng mục: đọc ngược điểm ảnh tệp PNG do máy
  chủ trả về
- `tests/app_test.php` — 118 hạng mục: luồng sử dụng qua HTTP thật, gồm phân
  quyền, CSRF, XSS, hạn dùng, giới hạn lượt
- `tests/run_all.php` — chạy tất cả bằng một lệnh, dùng cơ sở dữ liệu tạm
- Bộ giải mã QR (`tests/qr_decoder.php`) viết **độc lập** với bộ mã hoá

### Đã sửa lỗi

Ba lỗi thật do bộ kiểm định phát hiện trong quá trình hoàn thiện trước phát
hành. Ghi lại vì đều là loại lỗi khó thấy bằng mắt:

- **Bảng số khối sửa lỗi mức H sai từ phiên bản 8 trở lên.** Mọi mã QR ở mức
  sửa lỗi H đều không quét được, dù nhìn vẫn ra hình mã QR bình thường. Phát
  hiện khi đối chiếu với bảng trong chuẩn ISO/IEC 18004.
- **`fputcsv()` trên PHP 8.4 báo deprecated do thiếu tham số `$escape`.** Bộ
  xử lý lỗi biến cảnh báo này thành ngoại lệ, làm chức năng **xuất CSV trả về
  trang lỗi** thay vì tệp. Đã truyền tham số tường minh, và cho cảnh báo
  deprecated chỉ ghi nhật ký chứ không làm gián đoạn người dùng.
- **Đang đăng nhập vẫn `POST /dang-ky` tạo thêm tài khoản được.** Đã chặn,
  chuyển về bảng điều khiển.

### Bảo mật

- **Bịt lỗ hổng thư mục `tests/` chạy được từ web.** Trước khi đóng gói phát
  hành, phát hiện `.htaccess` gốc chưa chặn `tests/` — trên Apache thật, bất
  kỳ ai cũng gọi được `/tests/app_test.php` và bộ test sẽ tạo hàng loạt liên
  kết rác vào cơ sở dữ liệu thật. Đã xử lý:
  - Thêm quy tắc `RewriteRule ^(data|app|tests|tools)(/|$) - [F,L]` và đặt nó
    **trước** phần định tuyến (đặt sau sẽ không bao giờ được xét, vì quy tắc
    định tuyến có cờ `[L]` và dừng ngay khi gặp tệp có thật)
  - Thêm `.htaccess` riêng cho `tests/`
  - Chặn thêm theo phần mở rộng `.sh`, `.json`, `.yml`, và mọi tệp ẩn
  - Đã kiểm chứng bằng Apache thật: 14 đường dẫn phải chặn đều trả về 403,
    3 đường dẫn tệp tĩnh vẫn 200, 4 đường dẫn lạ đều được đưa về `index.php`
  - **Bản phát hành `.zip` không chứa `tests/`** nên không gặp rủi ro này

### Ghi chú kỹ thuật

- Yêu cầu PHP **8.1** trở lên; đã kiểm định trên PHP **8.4.19**
- Phần mở rộng bắt buộc: `pdo_sqlite`, `mbstring`. Nên có: `gd` (cho PNG)
- Cấu trúc cơ sở dữ liệu ở phiên bản **1** (khoá `schema_version` trong bảng
  `settings`)
- Múi giờ cố định **UTC+7 (Asia/Ho_Chi_Minh)**; mọi mốc thời gian lưu dạng
  chuỗi `Y-m-d H:i:s` theo giờ Việt Nam, **không** dùng `CURRENT_TIMESTAMP`
  của SQLite (hàm đó trả giờ UTC)
- Chuyển hướng dùng **302** kèm `Cache-Control: no-store` để thống kê không
  bị thiếu do bộ đệm trình duyệt và để việc sửa địa chỉ đích có hiệu lực ngay

---

## Dự kiến các bản sau

Chưa có lịch cụ thể, ghi lại để tham khảo:

- Trang thống kê in ra PDF gọn cho báo cáo
- Nhập liên kết từ tệp CSV (hiện chỉ có dán danh sách)
- Nhóm liên kết theo thư mục, ngoài thẻ phân loại
- Bảng thống kê so sánh nhiều liên kết cạnh nhau
- Đăng nhập bằng tài khoản chung của Sở (nếu có hệ thống SSO)

Đề xuất tính năng: liên hệ Phòng GDPT-GDTX, Sở GDĐT Đồng Nai.

---

[1.0.0]: https://github.com/tlearnvn/dongnai-link-shortener/releases/tag/v1.0.0

---

© 2026 — **Thiết kế bởi Trương Anh Tuấn** · Phòng GDPT-GDTX Sở GDĐT Đồng Nai
