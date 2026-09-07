# Rút gọn link — Phòng GDPT-GDTX Sở GDĐT Đồng Nai

Hệ thống rút gọn liên kết dùng nội bộ cho công tác thông tin: biến địa chỉ dài
thành liên kết ngắn **tự đặt tên được**, kèm **mã QR** để in lên văn bản và
**thống kê đầy đủ** số lượt truy cập.

Viết bằng **PHP thuần + HTML + CSS**, không dùng Composer, không thư viện
JavaScript ngoài. Dữ liệu lưu trong **một tệp SQLite duy nhất** (mặc định,
không cần cài gì) hoặc **MySQL / MariaDB** — đổi qua lại được bất cứ lúc nào
mà giữ nguyên toàn bộ dữ liệu. Mọi mốc thời gian theo
**giờ Việt Nam (UTC+7)**.

*Thiết kế bởi Trương Anh Tuấn.*

![Trang chủ](docs/images/01-trang-chu.png)

---

## Tải về và cài đặt

**[⬇ Tải bản phát hành mới nhất](https://github.com/tlearnvn/dongnai-link-shortener/releases/latest)**
— tệp `.zip` đã đóng gói sẵn cho shared hosting, chỉ khoảng 200 KB.

Giải nén vào `public_html`, cấp quyền ghi cho `data/`, mở trang chủ là dùng
được. Chi tiết từng bước kèm ảnh: **[Cài đặt trên cPanel](docs/cai-dat-cpanel.md)**.

> Bản `.zip` ở trang Releases **không chứa** thư mục `tests/` và các tệp chỉ
> dùng khi phát triển, nên nhẹ và an toàn hơn nút “Download ZIP” ở trang chính.

---

## Tài liệu

| Tài liệu | Dành cho | Nội dung |
|---|---|---|
| 📘 **[Hướng dẫn sử dụng](docs/huong-dan-su-dung.md)** | Cán bộ, giáo viên | 15 mục, 30 ảnh minh hoạ: rút gọn liên kết, tên tuỳ chọn, mã QR và cách in, đọc thống kê, mật khẩu và hẹn giờ, quản trị người dùng |
| 🔧 **[Cài đặt trên cPanel](docs/cai-dat-cpanel.md)** | Người phụ trách hosting | 7 bước cài đặt, bật HTTPS, sao lưu tự động, nâng cấp, xử lý sự cố, danh sách kiểm tra khi bàn giao |
| ⚙️ **[Quy trình kỹ thuật](docs/quy-trinh-ky-thuat.md)** | Quản trị, người bảo trì mã nguồn | 14 mục, 15 sơ đồ: kiến trúc, cơ sở dữ liệu, luồng xử lý, bộ tạo mã QR, phân quyền, xử lý UTC+7, bảo mật, kiểm định |
| 📋 **[Lịch sử phiên bản](CHANGELOG.md)** | Ai cũng đọc được | Có gì mới, đã sửa lỗi gì, cần lưu ý gì khi nâng cấp |
| 🗂 **[Mục lục tài liệu](docs/README.md)** | — | Bản đồ tra cứu nhanh theo việc cần làm |

---

## Mục lục

- [Tính năng](#tính-năng)
- [Yêu cầu máy chủ](#yêu-cầu-máy-chủ)
- [Cài đặt](#cài-đặt)
- [Cấu hình](#cấu-hình)
- [Sao lưu](#sao-lưu)
- [Chạy thử trên máy cá nhân](#chạy-thử-trên-máy-cá-nhân)
- [Kiểm định](#kiểm-định)
- [Cấu trúc thư mục](#cấu-trúc-thư-mục)
- [API](#api)
- [Ghi chú về quyền riêng tư](#ghi-chú-về-quyền-riêng-tư)
- [Lịch sử phiên bản](CHANGELOG.md)

---

## Tính năng

### Rút gọn liên kết
- **Tên tuỳ chọn** (`/tuyen-sinh-2026`) — kiểm tra ngay khi gõ xem tên còn
  trống hay chưa, kèm gợi ý tên thay thế nếu đã có người dùng.
- Bỏ trống tên thì hệ thống sinh mã ngẫu nhiên, dùng bộ ký tự đã loại các chữ
  dễ nhìn lẫn (`0/O`, `1/l/I`).
- Gõ thiếu `https://` cũng được, hệ thống tự thêm.
- Tra cứu **không phân biệt chữ hoa/thường**.
- **Sửa được địa chỉ đích** mà giữ nguyên liên kết ngắn — tài liệu cập nhật
  bản mới thì không phải in lại văn bản.
- **Tạo hàng loạt**: dán danh sách, mỗi dòng một địa chỉ, đặt tên riêng cho
  từng dòng bằng dấu `|`.

### Kiểm soát liên kết
- Mật khẩu bảo vệ từng liên kết.
- Hẹn giờ bắt đầu hoạt động và thời điểm hết hạn.
- Giới hạn số lượt nhấp tối đa.
- Tạm dừng / bật lại mà không mất số liệu đã thống kê.
- Thẻ phân loại (tối đa 8 thẻ mỗi liên kết) và ghim liên kết quan trọng.
- Trang **xem trước** (`/xem/{tên}`) để biết liên kết dẫn tới đâu mà không
  tính vào lượt nhấp.

### Mã QR

![Trang mã QR](docs/images/12-ma-qr.png)

- Bộ tạo mã QR **viết riêng bằng PHP thuần** (không thư viện ngoài):
  chế độ byte/UTF-8, phiên bản 1–40, đủ 4 mức sửa lỗi L/M/Q/H.
- Xuất **PNG** (in giấy) và **SVG vector** (in băng-rôn, pa-nô khổ lớn).
- Tuỳ chỉnh cỡ ô, lề, màu mã, màu nền, nền trong suốt, mức sửa lỗi.
- Mã mang dấu `?s=qr` để thống kê tách riêng lượt quét QR với lượt bấm liên kết.

### Thống kê

![Thống kê một liên kết](docs/images/15-thong-ke-lien-ket.png)

- Lượt nhấp theo ngày (7/14/30/90/365 ngày), theo giờ trong ngày, theo thứ
  trong tuần — tất cả theo giờ Việt Nam.
- Số khách riêng biệt, lượt quét QR, lượt do robot.
- Phân tích thiết bị, trình duyệt, hệ điều hành, nguồn giới thiệu, quốc gia.
- Bảng xếp hạng liên kết, ngày cao điểm, vòng tỉ lệ cho hạn dùng và giới hạn lượt.
- Biểu đồ **vẽ sẵn ở máy chủ bằng SVG** — hiện ngay khi tải trang, in ra giấy
  vẫn đúng, tự đổi màu theo giao diện sáng/tối.
- Xuất CSV (có BOM UTF-8 nên Excel đọc đúng tiếng Việt).

### Tài khoản
- Đăng ký / đăng nhập, ghi nhớ đăng nhập 30 ngày (token dùng một lần, tự cấp lại).
- **Người dùng đầu tiên tự động thành quản trị viên.**
- Quên mật khẩu bằng **mã dự phòng** (hệ thống không gửi email).
- Khoá API riêng cho từng tài khoản.
- Chọn giao diện sáng / tối / theo hệ thống, lưu theo tài khoản.

### Quản trị
- Quản lý người dùng: cấp/bỏ quyền quản trị, tạm ngưng, đặt lại mật khẩu,
  đặt hạn mức số liên kết.
- Xem toàn bộ liên kết của mọi người, thống kê toàn hệ thống.
- Bật/tắt tự đăng ký, bật/tắt cho khách rút gọn, dải thông báo đầu trang.
- Nhật ký hoạt động.
- Bảo trì: xoá chi tiết lượt nhấp cũ, xoá liên kết hết hạn, dồn nén tệp dữ liệu.

### An toàn
- Mật khẩu băm bằng `password_hash()`, tự nâng cấp thuật toán khi cấu hình đổi.
- Chống CSRF trên mọi biểu mẫu POST.
- Chặn tạm thời theo IP sau nhiều lần đăng nhập sai.
- Giới hạn số liên kết khách tạo mỗi giờ.
- Chỉ nhận địa chỉ `http`/`https`; chặn tự trỏ vào hệ thống (vòng lặp chuyển hướng).
- Escape toàn bộ dữ liệu người dùng khi in ra trang.

---

## Yêu cầu máy chủ

| Thành phần | Yêu cầu |
|---|---|
| PHP | 8.1 trở lên (đã kiểm định trên 8.4) |
| Phần mở rộng bắt buộc | `mbstring`, và `pdo_sqlite` **hoặc** `pdo_mysql` tuỳ loại cơ sở dữ liệu |
| Phần mở rộng nên có | `gd` (để xuất mã QR dạng PNG) |
| Cơ sở dữ liệu | Không cần gì (SQLite), hoặc MySQL 5.7+ / MariaDB 10.2+ |
| Máy chủ web | Apache có `mod_rewrite`, hoặc Nginx, hoặc IIS |
| Dung lượng | Vài MB cho mã nguồn; dữ liệu lớn dần theo số lượt nhấp |

Không cần Composer, không cần Node.js. **Cách mặc định không cần cả MySQL** —
chỉ một tệp SQLite.

---

## Cài đặt

### 1. Tải mã nguồn lên máy chủ

Chép toàn bộ thư mục vào nơi máy chủ web phục vụ, ví dụ `/var/www/rutgon`
hoặc `public_html/rutgon`.

### 2. Cấp quyền ghi cho thư mục dữ liệu

```bash
chmod 775 data
chown www-data:www-data data     # tên người dùng của máy chủ web
```

Tệp cơ sở dữ liệu được tạo tự động ở lần truy cập đầu tiên.

### 3. Mở trang chủ và tạo tài khoản đầu tiên

Truy cập địa chỉ hệ thống rồi vào **Tạo tài khoản**. Tài khoản đầu tiên được
cấp quyền quản trị. **Lưu lại mã dự phòng** hiện ra sau khi đăng ký — nó chỉ
hiện đúng một lần.

### 4. Cấu hình máy chủ web

**Apache** — tệp `.htaccess` đã có sẵn, chỉ cần bật `mod_rewrite` và cho phép
đọc `.htaccess` (`AllowOverride All`):

```apache
<Directory /var/www/rutgon>
    AllowOverride All
    Require all granted
</Directory>
```

**Nginx** — thêm vào khối `server`:

```nginx
root /var/www/rutgon;
index index.php;

# Chặn truy cập trực tiếp vào dữ liệu và mã nguồn
location ~ ^/(data|app|tests)/ { deny all; return 403; }
location ~ \.(sqlite|sqlite-wal|sqlite-shm|log)$ { deny all; return 403; }

# Tệp tĩnh phục vụ trực tiếp, còn lại đưa về index.php
location / {
    try_files $uri /index.php$is_args$args;
}

location ~ \.php$ {
    include fastcgi_params;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;   # sửa cho đúng phiên bản
    fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    fastcgi_param SCRIPT_NAME /index.php;
}
```

> **Không có `mod_rewrite`?** Hệ thống vẫn chạy được với đường dẫn dạng
> `https://ten-mien.vn/index.php/tuyen-sinh-2026`. Khi đó nên đặt
> `site_url` trong cấu hình để liên kết sinh ra đúng dạng.

### 5. Đặt địa chỉ gốc (khuyến nghị cho môi trường thật)

Mặc định hệ thống tự nhận diện địa chỉ từ header `Host` của trình duyệt. Trên
máy chủ thật nên khai báo cứng để liên kết và mã QR luôn đúng tên miền:

```php
<?php
// app/config.local.php
return [
    'site_url' => 'https://link.dongnaiedu.vn',
];
```

---

## Cấu hình

Sửa `app/config.php`, hoặc tốt hơn là tạo `app/config.local.php` trả về mảng
các khoá cần ghi đè (tệp này không bị ghi đè khi cập nhật mã nguồn).

| Khoá | Mặc định | Ý nghĩa |
|---|---|---|
| `site_url` | tự nhận diện | Địa chỉ gốc của hệ thống |
| `site_name` | `Rút gọn link` | Tên hiển thị |
| `site_owner` | `Phòng GDPT-GDTX Sở GDĐT Đồng Nai` | Tên đơn vị |
| `footer_credit` | `Thiết kế bởi Trương Anh Tuấn` | Dòng chân trang |
| `timezone` | `Asia/Ho_Chi_Minh` | Múi giờ toàn hệ thống (UTC+7) |
| `db_driver` | `sqlite` | Loại cơ sở dữ liệu: `sqlite` hoặc `mysql` |
| `db_path` | `data/rutgon.sqlite` | Đường dẫn tệp — chỉ dùng khi `db_driver` = `sqlite` |
| `db_host` / `db_port` | `127.0.0.1` / `3306` | Máy chủ MySQL |
| `db_name` / `db_user` / `db_pass` | trống | Thông tin kết nối MySQL |
| `db_socket` | trống | Nối qua socket thay cho host:port (một số hosting yêu cầu) |
| `db_charset` | `utf8mb4` | Bộ ký tự MySQL |
| `log_dir` | thư mục của `db_path` | Nơi ghi `error.log` |
| `code_length` | `6` | Độ dài mã tự sinh |
| `code_min_length` / `code_max_length` | `3` / `64` | Giới hạn độ dài tên tuỳ chọn |
| `guest_hourly_limit` | `10` | Số liên kết một khách tạo được mỗi giờ |
| `login_max_attempts` | `8` | Số lần đăng nhập sai trước khi tạm khoá IP |
| `login_lockout_minutes` | `15` | Thời gian tạm khoá |
| `bulk_max_lines` | `200` | Số dòng tối đa khi tạo hàng loạt |
| `click_retention_days` | `0` | Số ngày giữ chi tiết lượt nhấp (0 = giữ mãi) |

Các mục còn lại (cho phép đăng ký, cho khách rút gọn, mức sửa lỗi QR mặc định,
dải thông báo…) đổi trực tiếp trong **Quản trị → Cài đặt**.

---

## Sao lưu

**Khi dùng SQLite** — toàn bộ dữ liệu nằm trong một tệp, sao lưu chỉ cần chép
tệp đó:

```bash
# Cách an toàn nhất (chép đúng cả khi hệ thống đang chạy)
sqlite3 data/rutgon.sqlite ".backup '/duong-dan-sao-luu/rutgon-$(date +%F).sqlite'"

# Hoặc chép thủ công, nhớ lấy cả hai tệp đi kèm nếu có
cp data/rutgon.sqlite data/rutgon.sqlite-wal data/rutgon.sqlite-shm /duong-dan-sao-luu/
```

Phục hồi: dừng máy chủ web, chép tệp trở lại `data/`, khởi động lại.

**Khi dùng MySQL:**

```bash
mysqldump --single-transaction --default-character-set=utf8mb4 \
    -u NGUOI_DUNG -p TEN_CSDL > rutgon-$(date +%F).sql
```

Nhớ sao lưu **cả tệp `app/config.local.php`** — không có nó thì bản sao cơ sở
dữ liệu vô dụng vì mất thông tin kết nối.

### Đổi giữa SQLite và MySQL

```bash
php tools/chuyen-doi-csdl.php --sang=mysql --thu   # xem trước, không ghi gì
php tools/chuyen-doi-csdl.php --sang=mysql         # chuyển thật
php tools/chuyen-doi-csdl.php --sang=sqlite        # chuyển ngược lại
```

Cả hai phía đọc thông tin kết nối từ `app/config.local.php`, nên không có mật
khẩu nào nằm trên dòng lệnh. Mã số (id) được giữ nguyên nên liên kết ngắn và
mã QR đã in ra vẫn dùng bình thường. Chuyển xong thì đổi `db_driver`.

Hướng dẫn từng bước cho cPanel (kể cả cách chạy khi hosting không có SSH):
[docs/cai-dat-cpanel.md](docs/cai-dat-cpanel.md#dùng-mysql-thay-cho-sqlite).

---

## Chạy thử trên máy cá nhân

Không cần Apache hay Nginx, PHP có sẵn máy chủ thử nghiệm:

```bash
cd dongnai-link-shortener
php -S 127.0.0.1:8000 tests/dev-server.php
```

Rồi mở <http://127.0.0.1:8000>. Tệp `tests/dev-server.php` làm thay việc
rewrite mà máy chủ thử nghiệm không có.

---

## Kiểm định

```bash
php tests/run_all.php              # kiểm định trên SQLite
php tests/run_all.php --mysql      # kiểm định trên MySQL / MariaDB
```

Script tự bật máy chủ trên một cổng trống, dùng **cơ sở dữ liệu tạm** (không
đụng tới dữ liệu thật), chạy năm bộ test rồi dọn sạch:

| Bộ test | Nội dung |
|---|---|
| `tests/path_test.php` | Nhận diện đường dẫn ở cả ba kiểu triển khai: ngay gốc tên miền, trong thư mục con, và máy chủ không bật rewrite (`/index.php/...`); kèm các tình huống proxy/CDN, cổng lẻ, header `Host` chứa ký tự lạ |
| `tests/qr_test.php` | Thuật toán tạo mã QR: đối chiếu bảng vị trí hoa văn căn chỉnh và bảng dung lượng với chuẩn ISO/IEC 18004, giải mã ngược ma trận về chuỗi gốc cho cả 40 phiên bản × 4 mức sửa lỗi, kiểm tra syndrome Reed–Solomon bằng 0 |
| `tests/db_test.php` | Lớp cơ sở dữ liệu, chạy trên loại đang cấu hình: so cấu trúc bảng của loại đang chạy với cấu trúc SQLite dựng trong bộ nhớ, tra cứu không phân biệt hoa/thường, lọc theo thẻ và theo trạng thái, sắp xếp, ràng buộc khoá ngoại, kiểu trả về của `SUM()` |
| `tests/qr_image_test.php` | Đọc từng điểm ảnh của tệp PNG do máy chủ trả về, dựng lại ma trận rồi giải mã — đúng đường mà máy quét thật đi, thử 9 kiểu tuỳ chỉnh |
| `tests/app_test.php` | Luồng sử dụng qua HTTP thật: rút gọn, tên tuỳ chọn trùng/bị giữ, chuyển hướng, mật khẩu liên kết, hạn dùng, giới hạn lượt, phân quyền, CSRF, XSS, API, khu quản trị |

Bộ giải mã QR trong `tests/qr_decoder.php` được viết **độc lập** với
`app/lib/QrCode.php` (dựng lại từ mô tả trong chuẩn), nên nếu bộ mã hoá sai
thì test phát hiện được, chứ không phải hai bên cùng sai rồi triệt tiêu nhau.

Kết quả mong đợi: **272 hạng mục đạt, 0 lỗi**, nhật ký máy chủ sạch — số
hạng mục **bằng nhau trên cả hai loại cơ sở dữ liệu**.

`tests/db_test.php` kiểm theo **kết quả trả về**, không chỉ kiểm có báo lỗi
hay không. Lỗi tương thích nguy hiểm nhất là loại chạy êm mà trả về sai: câu
lệnh lọc theo thẻ dùng phép `||` hoạt động trên SQLite (nối chuỗi) nhưng MySQL
hiểu `||` là phép HOẶC luận lý, nên trả về danh sách sai **mà không báo gì**.

---

## Cấu trúc thư mục

```
.
├── index.php               Cửa vào duy nhất + bảng định tuyến
├── .htaccess               Rewrite, chặn truy cập dữ liệu, nén, bộ đệm
├── app/
│   ├── bootstrap.php       Nạp lớp, cấu hình, múi giờ, phiên làm việc
│   ├── config.php          Cấu hình (config.local.php để ghi đè)
│   ├── lib/
│   │   ├── QrCode.php      Bộ tạo mã QR thuần PHP (phiên bản 1–40, L/M/Q/H)
│   │   ├── Chart.php       Vẽ biểu đồ SVG ở máy chủ
│   │   ├── Database.php    Kết nối SQLite hoặc MySQL + tạo bảng
│   │   ├── LinkService.php Nghiệp vụ liên kết, ghi nhận lượt nhấp
│   │   ├── Stats.php       Truy vấn thống kê
│   │   ├── Auth.php        Đăng ký, đăng nhập, phân quyền
│   │   ├── Clock.php       Toàn bộ thời gian theo UTC+7
│   │   ├── Ua.php          Nhận diện thiết bị / trình duyệt
│   │   ├── Meta.php        Lấy tiêu đề trang đích (tuỳ chọn, có chống SSRF)
│   │   ├── Settings.php    Cấu hình động do quản trị viên đổi
│   │   ├── Router.php      Bộ định tuyến
│   │   ├── Config.php      Đọc cấu hình
│   │   └── Support.php     Các hàm tiện dụng
│   ├── controllers/        12 bộ xử lý theo nhóm chức năng
│   └── views/              layout, partials, pages
├── assets/
│   ├── css/app.css         Toàn bộ giao diện (sáng/tối, responsive, bản in)
│   ├── js/app.js           Tương tác (không bắt buộc để dùng hệ thống)
│   └── img/favicon.svg
├── data/                   Tệp SQLite + nhật ký lỗi (đã chặn truy cập web)
├── docs/                   Tài liệu + 30 ảnh minh hoạ
├── tests/                  Bộ kiểm định + máy chủ thử nghiệm
├── tools/
│   └── build-release.php   Đóng gói bản phát hành cho hosting
├── CHANGELOG.md            Lịch sử phiên bản
└── .gitattributes          Loại tệp dev khỏi bản tải tự động của GitHub
```

Đóng gói bản phát hành mới:

```bash
php tools/build-release.php          # lấy số phiên bản từ CHANGELOG.md
php tools/build-release.php 1.1.0    # hoặc chỉ định
```

Tệp `.zip` ra ở `dist/`, kèm tệp `.sha256` để đối chiếu.

---

## API

Xác thực bằng khoá API lấy ở trang **Tài khoản**:

```bash
# Tạo liên kết
curl -X POST "https://ten-mien.vn/api/rut-gon" \
     -H "Authorization: Bearer dnl_xxxxxxxx" \
     -H "Content-Type: application/json" \
     -d '{"url":"https://vidu.vn/tai-lieu.pdf","code":"tai-lieu-1","title":"Tài liệu 1"}'

# Kiểm tra tên còn trống (không cần khoá)
curl "https://ten-mien.vn/api/kiem-tra-ten?code=tuyen-sinh-2026"

# Danh sách liên kết của mình
curl "https://ten-mien.vn/api/lien-ket?page=1" -H "Authorization: Bearer dnl_xxxxxxxx"

# Thống kê một liên kết
curl "https://ten-mien.vn/api/thong-ke/tai-lieu-1?ngay=30" -H "Authorization: Bearer dnl_xxxxxxxx"
```

Ảnh mã QR truy cập trực tiếp, không cần khoá:

```
https://ten-mien.vn/ma-qr/tai-lieu-1.png?co=12&le=4&mau=%230b1220&nen=%23ffffff&sua-loi=1
https://ten-mien.vn/ma-qr/tai-lieu-1.svg
```

Tài liệu đầy đủ ở trang `/api` của hệ thống.

---

## Ghi chú về quyền riêng tư

- Hệ thống **không lưu địa chỉ IP của người truy cập liên kết**. IP chỉ được
  dùng ngay tại thời điểm đó để tính một mã băm ẩn danh (SHA-256 với muối
  riêng của từng bản cài đặt) nhằm phân biệt khách riêng biệt.
- Có lưu: thời điểm, loại thiết bị, tên trình duyệt, hệ điều hành, tên miền
  nguồn giới thiệu, ngôn ngữ trình duyệt.
- Thông tin quốc gia lấy từ header do CDN cung cấp (`CF-IPCountry`…) hoặc suy
  từ ngôn ngữ trình duyệt — chỉ mang tính tham khảo.
- IP **của người tạo liên kết** có được lưu, phục vụ việc giới hạn số lượng và
  xử lý lạm dụng.
- Giao diện không gọi ra dịch vụ bên ngoài nào: không phông chữ từ Google, không
  dịch vụ favicon, không thư viện CDN. Nhãn tên miền trên thẻ liên kết được
  sinh tại chỗ.
- Quản trị viên có thể xoá chi tiết lượt nhấp cũ trong **Quản trị → Cài đặt →
  Bảo trì**, hoặc đặt `click_retention_days` để hệ thống tự dọn.

---

© 2026 — **Thiết kế bởi Trương Anh Tuấn** · Phòng GDPT-GDTX Sở GDĐT Đồng Nai
