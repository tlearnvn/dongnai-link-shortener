# Cài đặt trên shared hosting cPanel

Hướng dẫn từng bước để đưa hệ thống lên hosting dùng cPanel — loại hosting
phổ biến nhất ở Việt Nam. Không cần SSH, không cần Composer, không cần
MySQL.

**Thời gian:** khoảng 15 phút.

---

## Mục lục

1. [Chuẩn bị](#1-chuẩn-bị)
2. [Toàn cảnh quy trình](#2-toàn-cảnh-quy-trình)
3. [Bước 1 — Tải bản phát hành](#bước-1--tải-bản-phát-hành)
4. [Bước 2 — Kiểm tra phiên bản PHP](#bước-2--kiểm-tra-phiên-bản-php)
5. [Bước 3 — Tải tệp lên và giải nén](#bước-3--tải-tệp-lên-và-giải-nén)
6. [Bước 4 — Cấp quyền ghi cho thư mục data](#bước-4--cấp-quyền-ghi-cho-thư-mục-data)
7. [Bước 5 — Đặt địa chỉ gốc](#bước-5--đặt-địa-chỉ-gốc)
8. [Bước 6 — Tạo tài khoản quản trị](#bước-6--tạo-tài-khoản-quản-trị)
9. [Bước 7 — Kiểm tra sau khi cài](#bước-7--kiểm-tra-sau-khi-cài)
10. [Cài vào thư mục con](#cài-vào-thư-mục-con)
11. [Bật HTTPS](#bật-https)
12. [Đặt sao lưu tự động](#đặt-sao-lưu-tự-động)
13. [Dùng MySQL thay cho SQLite](#dùng-mysql-thay-cho-sqlite)
14. [Nâng cấp lên bản mới](#nâng-cấp-lên-bản-mới)
15. [Xử lý sự cố khi cài đặt](#xử-lý-sự-cố-khi-cài-đặt)

---

## 1. Chuẩn bị

| Cần có | Ghi chú |
|---|---|
| Tài khoản cPanel | Của hosting hoặc do đơn vị cấp |
| Tên miền hoặc tên miền con | Ví dụ `link.dongnaiedu.vn` |
| PHP 8.1 trở lên | Đổi được trong cPanel, xem Bước 2 |
| Phần mở rộng `pdo_sqlite` | Hầu hết hosting đã bật sẵn |
| Phần mở rộng `mbstring` | Hầu hết hosting đã bật sẵn |
| Phần mở rộng `gd` | Cần cho mã QR dạng PNG (SVG không cần) |

**Không cần:** MySQL, Composer, Node.js, SSH, quyền root.

---

## 2. Toàn cảnh quy trình

```mermaid
flowchart TD
    A["Tải rutgon-link-v1.0.0.zip<br/>từ trang Releases trên GitHub"] --> B

    B["cPanel → Select PHP Version<br/>Chọn PHP 8.1+ · bật pdo_sqlite· mbstring· gd"] --> C

    C["cPanel → File Manager<br/>Vào public_html"] --> D
    D["Upload tệp .zip"] --> E
    E["Chuột phải → Extract"] --> F

    F["Chọn thư mục data/<br/>→ Permissions → 755 (hoặc 775)"] --> G

    G["Tạo app/config.local.php<br/>đặt site_url = địa chỉ thật"] --> H

    H["Mở trang chủ trên trình duyệt"] --> I
    I["Bấm Tạo tài khoản<br/>→ LƯU MÃ DỰ PHÒNG"] --> J

    J{"Kiểm tra sau khi cài"} --> K1["Rút gọn thử một liên kết"]
    J --> K2["Bấm thử liên kết đó"]
    J --> K3["Tải thử mã QR PNG"]
    J --> K4["Thử mở /data/rutgon.sqlite<br/>phải ra 403"]

    K1 --> DONE["Xong"]
    K2 --> DONE
    K3 --> DONE
    K4 --> DONE

    style A fill:#eef2ff,stroke:#4f46e5
    style G fill:#fef3c7,stroke:#d97706
    style I fill:#fef3c7,stroke:#d97706
    style DONE fill:#dcfce7,stroke:#16a34a
```

Hai bước tô vàng là hai bước dễ bỏ sót nhất, đọc kỹ.

---

## Bước 1 — Tải bản phát hành

Vào trang **Releases** của dự án trên GitHub, tải tệp:

```
rutgon-link-v1.0.0.zip
```

> **Đừng dùng nút "Download ZIP" ở trang chính** của repo — tệp đó chứa cả
> thư mục `tests/` và các tệp chỉ dùng khi phát triển. Bản phát hành ở trang
> Releases đã được đóng gói sẵn cho hosting.

Bản phát hành gồm:

```
index.php          Cửa vào duy nhất
.htaccess          Cấu hình Apache (đã có sẵn quy tắc chặn)
app/               Mã nguồn ứng dụng
assets/            CSS, JavaScript, biểu tượng
data/              Thư mục dữ liệu (còn trống)
docs/              Tài liệu
README.md
CHANGELOG.md
```

---

## Bước 2 — Kiểm tra phiên bản PHP

1. Đăng nhập cPanel
2. Tìm **Select PHP Version** (hoặc **MultiPHP Manager**)
3. Chọn **PHP 8.1** trở lên (nên chọn 8.2 hoặc 8.3)
4. Sang tab **Extensions**, đảm bảo các mục sau **đã tick**:

| Phần mở rộng | Vai trò |
|---|---|
| `mbstring` | **Bắt buộc** — xử lý tiếng Việt |
| `pdo_sqlite` | **Bắt buộc** nếu dùng SQLite (cách mặc định) |
| `sqlite3` | Đi kèm `pdo_sqlite` |
| `pdo_mysql` | **Bắt buộc** nếu dùng MySQL — xem [mục 13](#dùng-mysql-thay-cho-sqlite) |
| `gd` | Cần cho mã QR dạng PNG |
| `openssl` | Nên có |
| `curl` | Chỉ cần khi bật "tự lấy tiêu đề trang đích" |

5. Bấm **Save**

> Cài lần đầu thì **cứ để SQLite** — không cần tick `pdo_mysql`, không cần tạo
> cơ sở dữ liệu, không cần mật khẩu. Đổi sang MySQL lúc nào cũng được và giữ
> nguyên toàn bộ dữ liệu (mục 13).

> **Cách kiểm tra nhanh:** tạo tệp `kiemtra.php` trong `public_html` với nội
> dung `<?php phpinfo();` rồi mở `ten-mien.vn/kiemtra.php`. Tìm mục
> `pdo_sqlite`. **Xoá tệp này ngay sau khi kiểm tra xong** — nó để lộ thông
> tin cấu hình máy chủ.

---

## Bước 3 — Tải tệp lên và giải nén

1. cPanel → **File Manager**
2. Vào thư mục `public_html`
   - Nếu dùng **tên miền con** (ví dụ `link.dongnaiedu.vn`), vào thư mục
     tương ứng của tên miền con đó, thường là `public_html/rutgon` hoặc
     `link.dongnaiedu.vn` — xem ở cPanel → **Domains**
3. Bấm **Upload**, chọn tệp `rutgon-link-v1.0.0.zip`
4. Xong thì về File Manager, **chuột phải** vào tệp zip → **Extract**
5. Giải nén vào **chính thư mục đó** (không tạo thêm thư mục con)
6. **Xoá tệp .zip** sau khi giải nén

Sau bước này, trong `public_html` phải thấy `index.php`, `app`, `assets`,
`data`.

> ⚠️ **Bật hiện tệp ẩn.** Trong File Manager, vào **Settings** (góc trên
> phải) → tick **Show Hidden Files (dotfiles)**. Tệp `.htaccess` là tệp ẩn —
> **không có nó thì liên kết rút gọn sẽ báo 404** và thư mục dữ liệu không
> được bảo vệ. Sau khi giải nén, kiểm tra `.htaccess` có nằm cùng chỗ với
> `index.php` không.

---

## Bước 4 — Cấp quyền ghi cho thư mục data

Hệ thống cần ghi được vào thư mục `data/` để tạo tệp cơ sở dữ liệu.

1. Trong File Manager, chọn thư mục **`data`**
2. Chuột phải → **Change Permissions**
3. Đặt **755**. Nếu vẫn báo lỗi không ghi được, đổi thành **775**

```mermaid
flowchart LR
    A["Mở trang chủ"] --> B{"Có báo lỗi<br/>'không có quyền ghi' ?"}
    B -->|Không| OK["Xong — tệp cơ sở dữ liệu<br/>đã được tạo tự động"]
    B -->|Có| C["Đổi quyền data/ thành 775"]
    C --> D{"Còn lỗi ?"}
    D -->|Không| OK
    D -->|Có| E["Liên hệ hosting:<br/>chủ sở hữu thư mục phải là<br/>user của máy chủ web"]

    style OK fill:#dcfce7,stroke:#16a34a
```

Tệp `data/rutgon.sqlite` được tạo **tự động** ở lần truy cập đầu tiên. Bạn
không cần tạo tay, cũng không cần import tệp SQL nào.

---

## Bước 5 — Đặt địa chỉ gốc

**Bước này rất quan trọng nếu bạn sẽ in mã QR lên văn bản.**

Nếu không đặt, hệ thống tự nhận tên miền từ trình duyệt. Nghĩa là nếu bạn vào
bằng địa chỉ tạm của hosting (ví dụ `server123.hosting.vn/~taikhoan`), mã QR
tải về sẽ chứa **địa chỉ tạm đó** — in ra 500 bản công văn là 500 bản có mã
QR chết.

Cách đặt:

1. File Manager → vào thư mục `app`
2. Bấm **+ File**, đặt tên `config.local.php`
3. Chuột phải vào tệp → **Edit**
4. Dán nội dung sau, thay tên miền cho đúng:

```php
<?php
return [
    'site_url' => 'https://link.dongnaiedu.vn',
];
```

5. **Save Changes**

Vài lưu ý:

- Dùng `https://` nếu website đã có SSL (xem [Bật HTTPS](#bật-https))
- Không cần dấu `/` ở cuối, có cũng được — hệ thống tự bỏ
- Nếu cài vào thư mục con, ghi cả thư mục con:
  `'site_url' => 'https://dongnaiedu.vn/rutgon'`
- Tệp này **không bị ghi đè** khi nâng cấp bản mới

Tệp `config.local.php` cũng là nơi đổi các cấu hình khác. Xem bảng đầy đủ
trong [README](../README.md#cấu-hình). Ví dụ:

```php
<?php
return [
    'site_url' => 'https://link.dongnaiedu.vn',
    'guest_hourly_limit' => 5,        // khách chỉ tạo 5 liên kết/giờ
    'code_length' => 7,               // mã tự sinh dài 7 ký tự
    'click_retention_days' => 730,    // tự xoá chi tiết lượt nhấp cũ hơn 2 năm
];
```

---

## Bước 6 — Tạo tài khoản quản trị

1. Mở địa chỉ website trên trình duyệt
2. Bấm **Tạo tài khoản**
3. Điền tên đăng nhập, họ tên, đơn vị, mật khẩu

**Tài khoản đầu tiên tự động được cấp quyền quản trị.**

### ⚠️ Lưu mã dự phòng ngay

Ngay sau khi tạo xong, hệ thống hiện **mã dự phòng** 12 ký tự dạng
`ABCD-EFGH-JKMN`. Hệ thống **không gửi email**, nên đây là cách duy nhất để
tự lấy lại mật khẩu. **Mã chỉ hiện một lần.**

Chụp ảnh hoặc ghi vào sổ ngay.

### Nên tắt tự đăng ký sau khi cài

Sau khi tạo tài khoản quản trị, vào **Quản trị → Cài đặt** và cân nhắc:

- **Tắt "Cho phép tự đăng ký"** nếu chỉ muốn cấp tài khoản có kiểm soát
- **Tắt "Cho khách rút gọn"** nếu hệ thống chỉ dùng nội bộ

---

## Bước 7 — Kiểm tra sau khi cài

Làm đủ 6 mục này trước khi thông báo cho các đơn vị dùng:

| # | Việc kiểm tra | Kết quả đúng |
|---|---|---|
| 1 | Mở trang chủ | Hiện trang, không lỗi |
| 2 | Rút gọn một liên kết thử, đặt tên `kiem-tra` | Ra trang kết quả |
| 3 | Bấm vào liên kết `ten-mien.vn/kiem-tra` | Chuyển đúng tới đích |
| 4 | Vào **Mã QR** → **Tải ảnh PNG** | Tải được tệp .png |
| 5 | Quét mã QR bằng điện thoại | Mở đúng trang đích |
| 6 | Mở `ten-mien.vn/data/rutgon.sqlite` | **Phải báo 403 Forbidden** |

**Mục 6 là mục an ninh quan trọng nhất.** Nếu nó tải về tệp cơ sở dữ liệu thay
vì báo 403, nghĩa là `.htaccess` chưa hoạt động — toàn bộ dữ liệu người dùng
đang bị lộ. Xem [Xử lý sự cố](#xử-lý-sự-cố-khi-cài-đặt).

Kiểm tra thêm ba địa chỉ này, cả ba **phải ra 403**:

```
ten-mien.vn/app/config.php
ten-mien.vn/data/
ten-mien.vn/README.md
```

Xong phần kiểm tra, nhớ **xoá liên kết thử** `kiem-tra`.

---

## Cài vào thư mục con

Hệ thống chạy được khi đặt trong thư mục con, ví dụ
`https://dongnaiedu.vn/rutgon/`.

1. Giải nén vào `public_html/rutgon`
2. Đặt `site_url` **có cả thư mục con**:

```php
<?php
return ['site_url' => 'https://dongnaiedu.vn/rutgon'];
```

Liên kết rút gọn sẽ có dạng `dongnaiedu.vn/rutgon/tuyen-sinh-10`.

> Liên kết dài hơn một chút. Nếu định in mã QR nhiều, nên xin **tên miền
> con riêng** (`link.dongnaiedu.vn`) để liên kết ngắn gọn hơn.

---

## Bật HTTPS

Nên bật trước khi phát liên kết ra ngoài.

1. cPanel → **SSL/TLS Status** (hoặc **Let's Encrypt SSL**)
2. Chọn tên miền → **Run AutoSSL** / **Issue**
3. Chờ vài phút cho chứng thư được cấp
4. Sửa `app/config.local.php`, đổi `http://` thành `https://`
5. (Nên làm) Buộc chuyển sang HTTPS: thêm vào **đầu** tệp `.htaccess`, ngay
   sau dòng `RewriteEngine On`:

```apache
    # Buộc dùng HTTPS
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

> Đặt sau `RewriteEngine On` và **trước** quy tắc chặn `(data|app|tests|tools)`.

---

## Đặt sao lưu tự động

Toàn bộ dữ liệu nằm trong một tệp, nên sao lưu rất đơn giản.

### Cách 1 — Sao lưu tay (nhanh nhất)

1. File Manager → chọn thư mục `data`
2. Chuột phải → **Compress** → **Zip Archive**
3. Tải tệp .zip về máy, đặt tên theo ngày: `data-2026-09-07.zip`

Nên làm mỗi tuần, và **trước mỗi lần nâng cấp**.

### Cách 2 — Cron Job (tự động)

cPanel → **Cron Jobs**, thêm công việc chạy hằng ngày:

```bash
mkdir -p ~/backups && cp ~/public_html/data/rutgon.sqlite ~/backups/rutgon-$(date +\%F).sqlite && find ~/backups -name 'rutgon-*.sqlite' -mtime +30 -delete
```

Lệnh này chép tệp cơ sở dữ liệu vào `~/backups` mỗi ngày và tự xoá bản cũ
hơn 30 ngày.

> Nếu hosting có sẵn `sqlite3`, dùng lệnh này chính xác hơn (chép đúng cả khi
> hệ thống đang có người dùng):
> ```bash
> sqlite3 ~/public_html/data/rutgon.sqlite ".backup '$HOME/backups/rutgon-$(date +\%F).sqlite'"
> ```

### Cách 3 — Backup của cPanel

cPanel → **Backup** → **Download a Home Directory Backup**. Cách này sao lưu
toàn bộ, gồm cả cơ sở dữ liệu.

> Trong cron, dấu `%` phải viết là `\%` — đây là quy định của cPanel.

---

## Dùng MySQL thay cho SQLite

Hệ thống chạy được trên hai loại cơ sở dữ liệu. Mặc định là **SQLite** — một
tệp duy nhất, không cần cài gì. Đổi sang **MySQL / MariaDB** lúc nào cũng
được, và giữ nguyên toàn bộ dữ liệu đang có.

Mục này có hai đường, đọc đúng một đường là đủ:

| Bạn đang… | Đọc |
|---|---|
| **Cài mới**, muốn dùng MySQL ngay từ đầu | [Cách 1](#cách-1--cài-mới-dùng-mysql-ngay-từ-đầu) — khoảng 10 phút, không cần dòng lệnh |
| Đã chạy SQLite, muốn đổi sang MySQL | [Cách 2](#cách-2--đang-dùng-sqlite-chuyển-sang-mysql) — cần chạy một lệnh từ Terminal/Cron |

### Nên dùng loại nào

| | SQLite (mặc định) | MySQL / MariaDB |
|---|---|---|
| Cài đặt | Không cần làm gì | Tạo cơ sở dữ liệu + người dùng trong cPanel |
| Sao lưu | Chép một tệp là xong | phpMyAdmin → Export, hoặc Backup của cPanel |
| Di chuyển sang hosting khác | Chép tệp | Export rồi Import |
| Nhiều người ghi cùng lúc | Ghi tuần tự, một lúc một người | Ghi song song tốt hơn |
| Dung lượng dữ liệu | Tốt tới hàng trăm nghìn lượt nhấp | Không lo về dung lượng |
| Hosting giới hạn số inode | Chỉ tốn 1–3 tệp | Không tốn tệp nào |
| Hosting giới hạn dung lượng ổ đĩa | Tệp nằm trong hạn mức đĩa | Thường tính vào hạn mức riêng |

**Cứ dùng SQLite** nếu chỉ một Phòng dùng và mỗi ngày vài trăm tới vài nghìn
lượt nhấp — nó đơn giản hơn hẳn ở khâu sao lưu. Chuyển sang MySQL khi:

- Hosting **giới hạn dung lượng thư mục** nhưng cấp cơ sở dữ liệu riêng
- Nhiều người trong Phòng **tạo liên kết cùng lúc** và thấy chậm
- Đơn vị đã có **quy trình sao lưu MySQL** sẵn và muốn dùng chung
- `data/` không cấp được quyền ghi (hosting khoá), nhưng MySQL thì có

### Cách 1 — Cài mới, dùng MySQL ngay từ đầu

**A. Tạo cơ sở dữ liệu trong cPanel**

1. cPanel → **MySQL® Databases**
2. Ô *Create New Database*: nhập tên, ví dụ `rutgon` → **Create Database**
   → cPanel sẽ đặt tên đầy đủ kiểu `tenhosting_rutgon`, **ghi lại tên này**
3. Kéo xuống *Add New User*: nhập tên và mật khẩu (dùng nút *Password
   Generator* rồi **lưu mật khẩu lại**) → **Create User**
4. Kéo xuống *Add User To Database*: chọn người dùng vừa tạo và cơ sở dữ liệu
   vừa tạo → **Add** → tick **ALL PRIVILEGES** → **Make Changes**

**B. Bật phần mở rộng**

cPanel → **Select PHP Version** → tab **Extensions** → tick `pdo_mysql`
→ **Save**.

**C. Khai vào cấu hình**

Tạo (hoặc sửa) tệp `app/config.local.php`:

```php
<?php
return [
    'site_url' => 'https://ten-mien-cua-ban.vn',

    'db_driver' => 'mysql',
    'db_host' => 'localhost',
    'db_name' => 'tenhosting_rutgon',
    'db_user' => 'tenhosting_rutgon',
    'db_pass' => 'mật-khẩu-vừa-tạo',
];
```

> `db_host` trên cPanel gần như luôn là `localhost`. Chỉ khi nhà cung cấp ghi
> rõ một địa chỉ khác (kiểu `mysql.tenhosting.vn`) thì mới thay.

**D. Mở trang chủ.** Hệ thống tự tạo **7 bảng** (`users`, `links`, `clicks`,
`settings`, `remember_tokens`, `login_attempts`, `audit_log`) ngay ở lần truy
cập đầu tiên rồi hiện trang rút gọn. **Không phải chạy tệp `.sql` nào**, không
phải nhập gì vào phpMyAdmin.

**E. Tạo tài khoản** — như [Bước 6](#bước-6--tạo-tài-khoản-quản-trị): người
đăng ký **đầu tiên** tự động thành quản trị viên, và **nhớ lưu mã dự phòng**.

**F. Kiểm tra.** Ngoài [Bước 7](#bước-7--kiểm-tra-sau-khi-cài), kiểm thêm ba
mục riêng của MySQL:

| Kiểm | Kết quả đúng |
|---|---|
| phpMyAdmin → chọn cơ sở dữ liệu | Thấy **7 bảng**, bảng `users` có 1 dòng |
| `/quan-tri/cai-dat` → *Thông tin kỹ thuật* | **Loại cơ sở dữ liệu: MySQL / MariaDB**, kèm phiên bản máy chủ |
| Mở `ten-mien.vn/app/config.local.php` | **403 Forbidden** — tệp này chứa mật khẩu cơ sở dữ liệu, bắt buộc phải chặn được |

Tiêu đề liên kết có dấu tiếng Việt phải hiện đúng ngay từ đầu. Nếu ra dấu hỏi
hoặc ô vuông thì cơ sở dữ liệu không phải `utf8mb4` — xoá đi tạo lại với
**Collation** `utf8mb4_unicode_ci`.

> **Thư mục `data/` vẫn cần quyền ghi**, dù dùng MySQL — hệ thống ghi
> `error.log` vào đó. Nhưng nếu hosting khoá hẳn không cho ghi thì hệ thống
> **vẫn chạy được** (chỉ mất nhật ký lỗi); còn với SQLite thì không chạy nổi.
> Đây là một lý do để chọn MySQL trên hosting khó tính.

### Cách 2 — Đang dùng SQLite, chuyển sang MySQL

Dữ liệu hiện có được chuyển sang nguyên vẹn: liên kết, mã QR, người dùng, toàn
bộ lượt nhấp và thống kê. **Mã số (id) giữ nguyên** nên liên kết ngắn và mã QR
đã in ra vẫn dùng bình thường.

```mermaid
flowchart TD
    A["1· SAO LƯU data/rutgon.sqlite<br/>chép ra chỗ khác, đừng bỏ qua"] --> B
    B["2· Tạo cơ sở dữ liệu MySQL trống<br/>cPanel → MySQL Databases"] --> C
    C["3· Tick pdo_mysql<br/>Select PHP Version → Extensions"] --> D
    D["4· Khai db_host/db_name/db_user/db_pass<br/>NHƯNG vẫn để db_driver = 'sqlite'"] --> E
    E["5· Chạy thử: --sang=mysql --thu<br/>chỉ xem trước, không ghi gì"] --> F
    F{"Số dòng có đúng<br/>như đang dùng ?"}
    F -->|Không| D
    F -->|Có| G["6· Chuyển thật: --sang=mysql"] --> H
    H["7· Đổi db_driver thành 'mysql'"] --> I
    I["8· Mở /quan-tri/cai-dat kiểm tra"] --> J
    J{"Loại cơ sở dữ liệu<br/>đã là MySQL ?"}
    J -->|Có| K["Xong — GIỮ tệp .sqlite cũ<br/>làm bản sao lưu"]
    J -->|Không| L["Đổi db_driver về 'sqlite'<br/>là quay lại như cũ ngay"]

    style A fill:#fef3c7,stroke:#d97706
    style D fill:#fef3c7,stroke:#d97706
    style E fill:#dbeafe,stroke:#2563eb
    style K fill:#dcfce7,stroke:#16a34a
    style L fill:#fee2e2,stroke:#dc2626
```

**Bước 1–4** làm như Cách 1 ở trên, chỉ khác một điểm quan trọng: **chưa đổi
`db_driver`**. Cứ để `'sqlite'`, và thêm bốn dòng MySQL vào bên dưới:

```php
<?php
return [
    'site_url' => 'https://ten-mien-cua-ban.vn',

    // Vẫn là sqlite — chưa đổi. Đổi sau khi chuyển xong dữ liệu.
    'db_driver' => 'sqlite',

    'db_host' => 'localhost',
    'db_name' => 'tenhosting_rutgon',
    'db_user' => 'tenhosting_rutgon',
    'db_pass' => 'mật-khẩu-vừa-tạo',
];
```

**Bước 5 — Xem trước.** Công cụ chuyển đổi chạy từ dòng lệnh. Có ba cách chạy
trên hosting, chọn cách nào có sẵn:

<details>
<summary><b>Cách A — cPanel Terminal</b> (nhanh nhất, nếu hosting có)</summary>

cPanel → **Terminal**, rồi gõ:

```bash
cd ~/public_html
php tools/chuyen-doi-csdl.php --sang=mysql --thu
```

Kết quả in ra ngay trên màn hình.
</details>

<details>
<summary><b>Cách B — cPanel Cron Jobs</b> (hosting nào cũng có)</summary>

cPanel → **Cron Jobs**:

1. *Common Settings*: chọn **Once Per Five Minutes**
2. *Command*: dán lệnh sau, thay `tenhosting` bằng tên tài khoản hosting

   ```
   /usr/local/bin/php /home/tenhosting/public_html/tools/chuyen-doi-csdl.php --sang=mysql --thu
   ```

3. Điền email vào ô **Cron Email** ở đầu trang để nhận kết quả
4. **Add New Cron Job**, chờ tới 5 phút, đọc email
5. **XOÁ cron job này ngay sau khi xong** — nếu không nó chạy mãi

> Không biết đường dẫn `php`? Trong *Select PHP Version* của cPanel thường có
> ghi. Hoặc thử lần lượt `/usr/local/bin/php`, `/usr/bin/php`,
> `/opt/cpanel/ea-php82/root/usr/bin/php`.
</details>

<details>
<summary><b>Cách C — SSH</b> (nếu hosting cấp)</summary>

```bash
ssh tenhosting@ten-mien-cua-ban.vn
cd public_html
php tools/chuyen-doi-csdl.php --sang=mysql --thu
```
</details>

Kết quả xem trước trông như sau — đối chiếu số liên kết và số lượt nhấp với
những gì trang **Quản trị** đang hiện:

```
CHUYỂN ĐỔI CƠ SỞ DỮ LIỆU
──────────────────────────────────────────────────────────────────
  Nguồn : SQLite — /home/tenhosting/public_html/data/rutgon.sqlite
  Đích  : MySQL — tenhosting_rutgon @ localhost:3306
  Chế độ: XEM TRƯỚC — không ghi gì vào phía đích
──────────────────────────────────────────────────────────────────

Số dòng sẽ chuyển
  settings                  9 dòng
  users                     4 dòng
  links                    37 dòng
  clicks                4.128 dòng
  remember_tokens           2 dòng
  login_attempts           15 dòng
  audit_log               196 dòng
  TỔNG                  4.391 dòng
```

**Bước 6 — Chuyển thật.** Chạy lại cùng lệnh, **bỏ `--thu`**:

```bash
php tools/chuyen-doi-csdl.php --sang=mysql
```

Công cụ ghi trong **một giao dịch**: có lỗi ở giữa thì hoàn tác toàn bộ, phía
MySQL trở lại đúng trạng thái trước khi chạy — không bao giờ để lại dữ liệu
chuyển dở. Chạy xong nó đối chiếu số dòng hai phía và kiểm tra khoá ngoại.

**Bước 7 — Đổi `db_driver`** trong `app/config.local.php` thành `'mysql'`.

**Bước 8 — Kiểm tra.** Mở `/quan-tri/cai-dat`, mục **Thông tin kỹ thuật**:

![Trang quản trị khi chạy MySQL](images/36-quan-tri-mysql.png)

Rồi bấm thử một liên kết rút gọn và mở trang thống kê của nó, xem lượt nhấp
mới có được ghi không.

> **Quay lại SQLite?** Đổi `db_driver` về `'sqlite'` là xong — tệp `.sqlite`
> cũ vẫn nguyên. Chỉ mất những gì tạo thêm trong lúc đang chạy MySQL. Muốn
> mang cả những thứ đó về thì chạy
> `php tools/chuyen-doi-csdl.php --sang=sqlite --force`.

### Sau khi chuyển sang MySQL

| Việc | Thay đổi |
|---|---|
| **Sao lưu** | Không còn chép tệp `data/` nữa. Dùng cPanel → **Backup** → *Download a MySQL Database Backup*, hoặc phpMyAdmin → **Export** → SQL. Nhớ sao lưu **cả tệp `app/config.local.php`** vì tệp này chứa mật khẩu kết nối |
| **Nâng cấp bản mới** | Vẫn ghi đè `index.php`, `.htaccess`, `app/`, `assets/`, `tools/`; vẫn **không chạm** `app/config.local.php`. Thư mục `data/` giờ chỉ còn chứa `error.log` |
| **Tệp `.sqlite` cũ** | **Giữ lại** ít nhất vài tuần. Chắc chắn ổn rồi mới tải về máy lưu và xoá khỏi hosting |
| **Bảo mật** | `app/config.local.php` giờ chứa mật khẩu cơ sở dữ liệu. `.htaccess` đã chặn cả thư mục `app/`, nhưng nên mở thử `ten-mien.vn/app/config.local.php` để chắc chắn nhận **403** |

---

## Nâng cấp lên bản mới

```mermaid
flowchart TD
    A["1· SAO LƯU thư mục data/<br/>bắt buộc, đừng bỏ qua"] --> B
    B["2· Đọc CHANGELOG.md của bản mới"] --> C
    C["3· Tải bản .zip mới, giải nén ra máy"] --> D
    D["4· Upload và ghi đè:<br/>index.php· .htaccess· app/· assets/"] --> E
    E["5· KHÔNG chạm vào:<br/>data/ và app/config.local.php"] --> F
    F["6· Mở trang chủ — bảng mới (nếu có) tự tạo"] --> G
    G{"Hoạt động bình thường ?"}
    G -->|Có| H["Xong"]
    G -->|Không| I["Phục hồi: chép lại thư mục cũ<br/>+ bản sao data/"]

    style A fill:#fef3c7,stroke:#d97706
    style E fill:#fef3c7,stroke:#d97706
    style H fill:#dcfce7,stroke:#16a34a
    style I fill:#fee2e2,stroke:#dc2626
```

Cách an toàn nhất trên cPanel:

1. Sao lưu `data/` (xem mục trên)
2. Đổi tên thư mục hiện tại: `rutgon` → `rutgon-cu`
3. Giải nén bản mới vào `rutgon`
4. Chép `rutgon-cu/data/` và `rutgon-cu/app/config.local.php` sang `rutgon/`
5. Kiểm tra hoạt động
6. Xong thì xoá `rutgon-cu`

Nếu có vấn đề, chỉ cần đổi tên ngược lại là về nguyên trạng.

---

## Xử lý sự cố khi cài đặt

<details>
<summary><b>Trang chủ chạy, nhưng liên kết rút gọn báo 404</b></summary>

Nguyên nhân: `.htaccess` không được đọc, hoặc `mod_rewrite` chưa bật.

Cách xử lý:

1. Kiểm tra tệp `.htaccess` **có tồn tại** cùng chỗ với `index.php` (bật
   *Show Hidden Files* trong File Manager)
2. Thử mở `ten-mien.vn/index.php/kiem-tra`. Nếu **cách này chạy được** thì
   đúng là vấn đề rewrite
3. Liên hệ hosting yêu cầu bật `mod_rewrite` và đặt `AllowOverride All`
4. Tạm thời vẫn dùng được với đường dẫn dạng `/index.php/ten-tuy-chon` — nhớ
   đặt `site_url` để liên kết sinh ra đúng dạng đó
</details>

<details>
<summary><b>Mở /data/rutgon.sqlite lại tải về được tệp — NGUY HIỂM</b></summary>

`.htaccess` không hoạt động. Toàn bộ dữ liệu người dùng đang bị lộ. Xử lý ngay:

1. Kiểm tra `.htaccess` có tồn tại trong thư mục gốc và trong `data/`
2. Yêu cầu hosting đặt `AllowOverride All` cho thư mục
3. **Trong lúc chờ:** đổi vị trí tệp cơ sở dữ liệu ra ngoài `public_html` —
   sửa `app/config.local.php`:
   ```php
   <?php
   return [
       'site_url' => 'https://link.dongnaiedu.vn',
       'db_path'  => '/home/TEN_TAI_KHOAN/rutgon-data/rutgon.sqlite',
   ];
   ```
   Tạo thư mục `rutgon-data` **ngang hàng** với `public_html` (không nằm
   trong), cấp quyền 755, rồi chép tệp `.sqlite` sang. Nằm ngoài
   `public_html` thì web không thể truy cập được, dù `.htaccess` có hoạt
   động hay không.
</details>

<details>
<summary><b>Trang trắng, không thông báo gì</b></summary>

Có lỗi PHP nhưng hệ thống không hiện chi tiết ra ngoài (đúng như thiết kế).

1. Mở tệp `data/error.log` bằng File Manager → **Edit** để xem lỗi
2. Kiểm tra phiên bản PHP có phải 8.1+ (Bước 2)
3. Kiểm tra `pdo_sqlite` và `mbstring` đã bật
4. Xem cả **Errors** trong cPanel (mục **Metrics → Errors**)
</details>

<details>
<summary><b>"Thư mục dữ liệu không có quyền ghi"</b></summary>

1. Đặt quyền thư mục `data` = **755**, nếu chưa được thì **775**
2. Nếu vẫn lỗi: chủ sở hữu thư mục không phải user của máy chủ web. Liên hệ
   hosting, hoặc thử xoá thư mục `data` rồi tạo lại bằng File Manager (thư
   mục do bạn tạo sẽ có chủ sở hữu đúng)
</details>

<details>
<summary><b>Tải được SVG nhưng không tải được PNG</b></summary>

Thiếu phần mở rộng `gd`. Vào cPanel → **Select PHP Version** → **Extensions**
→ tick `gd` → **Save**.

SVG vẫn dùng bình thường trong lúc chờ, và SVG thậm chí tốt hơn khi in khổ lớn.
</details>

<details>
<summary><b>Liên kết và mã QR ra sai tên miền</b></summary>

Chưa đặt `site_url`. Xem [Bước 5](#bước-5--đặt-địa-chỉ-gốc).

Nếu đã in mã QR sai tên miền ra giấy: đặt `site_url` cho đúng, rồi **trỏ tên
miền cũ về cùng máy chủ** này. Hệ thống chấp nhận truy cập từ mọi tên miền
trỏ tới nó, nên các mã QR đã in vẫn hoạt động.
</details>

<details>
<summary><b>Tiếng Việt hiện thành dấu hỏi hoặc ô vuông</b></summary>

Thiếu `mbstring`. Bật trong cPanel → **Select PHP Version** → **Extensions**.
</details>

<details>
<summary><b>Tệp CSV xuất ra mở bằng Excel bị lỗi font</b></summary>

Tệp đã có BOM UTF-8 nên Excel bản mới đọc đúng. Nếu vẫn lỗi: mở Excel →
**Data** → **From Text/CSV** → chọn encoding **UTF-8** → **Load**.
</details>

<details>
<summary><b>Hosting giới hạn dung lượng — tệp dữ liệu to dần</b></summary>

Mỗi lượt nhấp là một dòng trong bảng `clicks`. Một triệu lượt nhấp khoảng
150–200 MB.

Xử lý: **Quản trị → Cài đặt → Bảo trì**:
1. **Xoá chi tiết lượt nhấp cũ** hơn N ngày (số tổng hợp của từng liên kết
   vẫn giữ nguyên)
2. **Dồn nén cơ sở dữ liệu** để thu nhỏ tệp

Hoặc đặt tự động trong `app/config.local.php`:
```php
'click_retention_days' => 365,   // chỉ giữ chi tiết 1 năm
```
</details>

<details>
<summary><b>MySQL — báo "cần phần mở rộng PHP pdo_mysql"</b></summary>

Đã đặt `db_driver` = `'mysql'` nhưng chưa bật phần mở rộng.

cPanel → **Select PHP Version** → tab **Extensions** → tick `pdo_mysql`
→ **Save**. Tải lại trang.

Lưu ý chọn đúng phiên bản PHP mà tên miền đang dùng: nếu hosting có
**MultiPHP Manager** thì mỗi tên miền có thể chạy một phiên bản khác nhau, và
phải bật `pdo_mysql` cho đúng phiên bản đó.
</details>

<details>
<summary><b>MySQL — báo "Access denied for user"</b></summary>

Sai tên đăng nhập, sai mật khẩu, hoặc **chưa gán người dùng vào cơ sở dữ
liệu**. Việc bị bỏ sót nhiều nhất là bước cuối:

cPanel → **MySQL® Databases** → kéo xuống *Add User To Database* → chọn đúng
cặp người dùng + cơ sở dữ liệu → **Add** → tick **ALL PRIVILEGES** →
**Make Changes**.

Nhớ hai điều nữa:
- cPanel **tự thêm tiền tố** tên tài khoản hosting. Tạo tên `rutgon` thì tên
  thật là `tenhosting_rutgon` — trong cấu hình phải ghi **tên đầy đủ**.
- Mật khẩu có ký tự `$` hay `\` thì trong PHP phải đặt trong dấu **nháy đơn**
  (`'mat$khau'`), đừng dùng nháy kép.
</details>

<details>
<summary><b>MySQL — báo "Unknown database"</b></summary>

Tên cơ sở dữ liệu sai, gần như luôn là do thiếu tiền tố. Vào cPanel →
**MySQL® Databases**, xem bảng *Current Databases* và **chép đúng nguyên văn**
tên ở đó vào `db_name`.
</details>

<details>
<summary><b>MySQL — báo "Connection refused" hoặc treo rất lâu</b></summary>

`db_host` sai. Trên shared hosting cPanel gần như luôn là `localhost` (không
phải `127.0.0.1`, và không phải tên miền của bạn).

Nếu nhà cung cấp yêu cầu nối qua socket, khai thêm dòng này và bỏ qua
`db_host`/`db_port`:

```php
'db_socket' => '/var/lib/mysql/mysql.sock',
```
</details>

<details>
<summary><b>Chuyển đổi báo "DỪNG LẠI — phía đích đã có sẵn dữ liệu"</b></summary>

Đây là **chốt an toàn**, không phải lỗi: cơ sở dữ liệu đích không trống nên
công cụ không tự ghi đè.

- Đây là cơ sở dữ liệu **mới tạo, chỉ để chuyển sang** → chạy lại kèm
  `--force` để ghi đè.
- Đây là cơ sở dữ liệu **đang có dữ liệu thật của việc khác** → **đừng** dùng
  `--force`. Tạo một cơ sở dữ liệu khác rồi sửa `db_name`. Hệ thống cần một cơ
  sở dữ liệu riêng, không dùng chung với ứng dụng khác.
</details>

<details>
<summary><b>Chạy công cụ chuyển đổi từ trình duyệt thì báo 403</b></summary>

Đúng như thiết kế. `tools/chuyen-doi-csdl.php` chỉ chạy được từ **dòng lệnh**
(Terminal, Cron Job hoặc SSH) — xem ba cách ở
[mục 13](#dùng-mysql-thay-cho-sqlite). Nếu chạy được từ trình duyệt thì người
ngoài cũng gọi được, nên hệ thống chặn hẳn.
</details>

<details>
<summary><b>Hosting không có Terminal, không có SSH, cron cũng không chạy</b></summary>

Vẫn chuyển được, nhưng bằng tay và mất dữ liệu thống kê chi tiết:

1. Xuất danh sách liên kết: đăng nhập quản trị → **Xuất CSV toàn bộ**
2. Đổi `db_driver` sang `'mysql'` → mở trang chủ → hệ thống tạo bảng trắng
3. Tạo lại tài khoản, rồi dùng **Tạo hàng loạt** để nhập lại các liên kết
   (dán theo dạng `địa-chỉ | tên-tuỳ-chọn | tiêu-đề`)

Số lượt nhấp cũ sẽ về 0. Nếu số liệu thống kê quan trọng thì nên
**cứ dùng SQLite** — nó không cần dòng lệnh cho bất cứ việc gì.
</details>

---

## Danh sách kiểm tra khi bàn giao

Sao chép danh sách này để tick khi triển khai:

- [ ] PHP 8.1+ đã chọn, có `mbstring`, `gd`, và `pdo_sqlite` **hoặc**
      `pdo_mysql` tuỳ loại cơ sở dữ liệu đã chọn
- [ ] Đã giải nén đúng thư mục, `.htaccess` nằm cùng `index.php`
- [ ] Thư mục `data/` có quyền ghi (SQLite cần để chứa dữ liệu, MySQL cần để
      ghi `error.log`)
- [ ] Đã tạo `app/config.local.php` với `site_url` đúng tên miền
- [ ] Đã bật HTTPS và buộc chuyển sang HTTPS
- [ ] Đã tạo tài khoản quản trị và **lưu mã dự phòng**
- [ ] Đã tắt tự đăng ký (nếu chỉ cấp tài khoản có kiểm soát)
- [ ] `/data/rutgon.sqlite` trả về **403**
- [ ] `/app/config.php` trả về **403**
- [ ] `/app/config.local.php` trả về **403** (quan trọng khi dùng MySQL — tệp
      này chứa mật khẩu cơ sở dữ liệu)
- [ ] `/tools/chuyen-doi-csdl.php` trả về **403**
- [ ] Trang `/quan-tri/cai-dat` hiện đúng **loại cơ sở dữ liệu** đang dùng
- [ ] Rút gọn thử → bấm thử → tải mã QR → quét thử: đều đúng
- [ ] Đã đặt sao lưu định kỳ — chép `data/` nếu dùng SQLite, hoặc Export
      cơ sở dữ liệu **cùng với** `app/config.local.php` nếu dùng MySQL
- [ ] Đã xoá liên kết thử, tệp `kiemtra.php` và cron job tạm (nếu có tạo)

---

© 2026 — **Thiết kế bởi Trương Anh Tuấn** · Phòng GDPT-GDTX Sở GDĐT Đồng Nai
