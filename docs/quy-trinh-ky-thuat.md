# Quy trình kỹ thuật

Tài liệu dành cho người quản trị hệ thống và người tiếp nhận bảo trì mã nguồn.
Người dùng cuối xem [Hướng dẫn sử dụng](huong-dan-su-dung.md).

**Hệ thống:** Rút gọn link — Phòng GDPT-GDTX Sở GDĐT Đồng Nai
**Phiên bản:** 1.0.0 · **Múi giờ:** UTC+7 (Asia/Ho_Chi_Minh)

---

## Mục lục

1. [Kiến trúc tổng thể](#1-kiến-trúc-tổng-thể)
2. [Vòng đời một yêu cầu](#2-vòng-đời-một-yêu-cầu)
3. [Cơ sở dữ liệu](#3-cơ-sở-dữ-liệu)
4. [Quy trình rút gọn liên kết](#4-quy-trình-rút-gọn-liên-kết)
5. [Quy trình chuyển hướng và ghi thống kê](#5-quy-trình-chuyển-hướng-và-ghi-thống-kê)
6. [Vòng đời trạng thái liên kết](#6-vòng-đời-trạng-thái-liên-kết)
7. [Quy trình tạo mã QR](#7-quy-trình-tạo-mã-qr)
8. [Xác thực và phân quyền](#8-xác-thực-và-phân-quyền)
9. [Xử lý thời gian UTC+7](#9-xử-lý-thời-gian-utc7)
10. [Bảo mật](#10-bảo-mật)
11. [Quy trình kiểm định](#11-quy-trình-kiểm-định)
12. [Quy trình sao lưu và phục hồi](#12-quy-trình-sao-lưu-và-phục-hồi)
13. [Quy trình nâng cấp phiên bản](#13-quy-trình-nâng-cấp-phiên-bản)
14. [Quy trình phát hành bản mới](#13b-quy-trình-phát-hành-bản-mới)
15. [Xử lý sự cố thường gặp](#14-xử-lý-sự-cố-thường-gặp)

---

## 1. Kiến trúc tổng thể

Hệ thống theo mô hình MVC tối giản, **một cửa vào duy nhất** (`index.php`).
Không dùng Composer, không framework, không thư viện JavaScript ngoài.

```mermaid
flowchart TB
    subgraph client["Phía người dùng"]
        B["Trình duyệt<br/>(máy tính · điện thoại)"]
        Q["Máy quét mã QR"]
    end

    subgraph web["Máy chủ web (Apache / Nginx)"]
        H[".htaccess<br/>chặn data· app· tests· tools<br/>rewrite về index.php"]
        S["Tệp tĩnh<br/>assets/css· js· img"]
    end

    subgraph app["Ứng dụng PHP"]
        I["index.php<br/>bảng định tuyến"]
        BS["bootstrap.php<br/>nạp lớp· cấu hình· phiên"]
        R["Router<br/>khớp phương thức + đường dẫn"]
        C["12 Controller<br/>Home· Link· Redirect· Qr<br/>Stats· Auth· Admin· Api…"]
        L["Thư viện nghiệp vụ<br/>LinkService· Stats· Auth<br/>QrCode· Chart· Clock· Ua"]
        V["Giao diện<br/>layout + partials + pages"]
    end

    DB[("data/rutgon.sqlite<br/>MỘT tệp duy nhất<br/>7 bảng")]

    B --> H
    Q --> H
    H -->|"tệp có thật"| S
    H -->|"còn lại"| I
    I --> BS --> R --> C
    C --> L
    C --> V
    L <--> DB
    V -->|"HTML· SVG· PNG· CSV· JSON"| B
```

### Nguyên tắc thiết kế

| Nguyên tắc | Lý do |
|---|---|
| Một tệp SQLite duy nhất | Sao lưu chỉ cần chép một tệp; chạy được trên shared hosting không có MySQL |
| Không phụ thuộc thư viện ngoài | Không lo thư viện ngừng bảo trì; không cần Composer trên hosting |
| Biểu đồ vẽ ở máy chủ (SVG) | Hiện ngay khi tải trang, in ra giấy vẫn đúng, không gọi CDN |
| Không gọi dịch vụ bên ngoài | Không tiết lộ danh sách địa chỉ của Sở cho bên thứ ba |
| Hoạt động khi tắt JavaScript | Biểu mẫu gửi bằng POST thường; JS chỉ để mượt hơn |

---

## 2. Vòng đời một yêu cầu

```mermaid
sequenceDiagram
    autonumber
    participant B as Trình duyệt
    participant A as Apache (.htaccess)
    participant I as index.php
    participant BS as bootstrap.php
    participant R as Router
    participant C as Controller
    participant V as View

    B->>A: GET /tuyen-sinh-10
    A->>A: Có phải data/ app/ tests/ tools/ ? → 403
    A->>A: Có phải tệp thật ? → gửi trực tiếp
    A->>I: Rewrite về index.php
    I->>BS: require bootstrap
    BS->>BS: Kiểm tra PHP ≥ 8.1, pdo_sqlite, mbstring
    BS->>BS: Nạp cấu hình + đặt múi giờ UTC+7
    BS->>BS: Bắt lỗi, đặt header an toàn
    BS->>BS: Mở phiên làm việc
    BS->>BS: Mở SQLite (tạo bảng nếu lần đầu)
    I->>R: dispatch(GET, "/tuyen-sinh-10")
    R->>C: Khớp tuyến cuối → RedirectController::go
    C->>V: Kết xuất (hoặc gửi header Location)
    V-->>B: Phản hồi
```

> **Lưu ý về thứ tự tuyến:** tuyến `/{code}` phải đặt **cuối cùng** trong
> `index.php`, sau tất cả tuyến cố định. Nếu đặt trước, nó sẽ "ăn" hết
> `/lien-ket`, `/thong-ke`… Danh sách tên bị giữ nằm trong
> `LinkService::RESERVED`.

---

## 3. Cơ sở dữ liệu

Bảy bảng, tất cả trong `data/rutgon.sqlite`. Bảng được tạo tự động ở lần
truy cập đầu tiên (`Database::migrate()`).

```mermaid
erDiagram
    users ||--o{ links : "tạo"
    users ||--o{ remember_tokens : "ghi nhớ đăng nhập"
    users ||--o{ audit_log : "hành động"
    links ||--o{ clicks : "phát sinh"

    users {
        INTEGER id PK
        TEXT username UK "COLLATE NOCASE"
        TEXT email
        TEXT full_name
        TEXT unit "đơn vị công tác"
        TEXT password_hash
        TEXT role "user | admin"
        TEXT status "active | suspended"
        TEXT api_token UK
        TEXT recovery_code_hash
        TEXT theme "auto | light | dark"
        INTEGER link_quota "0 = không giới hạn"
        TEXT created_at
        TEXT last_login_at
        INTEGER login_count
    }

    links {
        INTEGER id PK
        TEXT code UK "tên tuỳ chọn, NOCASE"
        TEXT target_url
        TEXT title
        TEXT note
        TEXT tags "phân cách bằng dấu phẩy"
        INTEGER user_id FK "NULL = khách tạo"
        TEXT creator_ip
        TEXT password_hash "NULL = không khoá"
        TEXT starts_at
        TEXT expires_at
        INTEGER max_clicks
        INTEGER click_count "số tổng hợp"
        INTEGER unique_count "số tổng hợp"
        INTEGER is_active
        INTEGER is_starred
        TEXT qr_dark
        TEXT qr_light
        TEXT last_click_at
        TEXT created_at
    }

    clicks {
        INTEGER id PK
        INTEGER link_id FK
        TEXT clicked_at
        TEXT click_date "Y-m-d, để nhóm nhanh"
        INTEGER click_hour "0-23 giờ VN"
        INTEGER weekday "0=CN … 6=T7"
        TEXT visitor_hash "băm ẩn danh, KHÔNG phải IP"
        INTEGER is_unique
        TEXT referer_host
        TEXT browser
        TEXT os
        TEXT device
        TEXT country
        TEXT language
        TEXT source "qr | NULL"
        INTEGER is_bot
    }

    remember_tokens {
        INTEGER id PK
        INTEGER user_id FK
        TEXT selector UK
        TEXT token_hash "SHA-256"
        TEXT expires_at
    }

    login_attempts {
        INTEGER id PK
        TEXT ip
        TEXT username
        INTEGER successful
        TEXT created_at
    }

    audit_log {
        INTEGER id PK
        INTEGER user_id
        TEXT actor
        TEXT action
        TEXT detail
        TEXT ip
        TEXT created_at
    }

    settings {
        TEXT key PK
        TEXT value
    }
```

### Ghi chú thiết kế bảng

- **`links.click_count` và `unique_count` là số tổng hợp**, cập nhật cùng lúc
  với việc chèn dòng vào `clicks`. Nhờ vậy trang danh sách không phải đếm lại
  hàng nghìn dòng mỗi lần tải. Khi xoá chi tiết lượt nhấp cũ (mục Bảo trì),
  số tổng hợp **vẫn giữ nguyên** — cố ý như vậy để không mất số liệu lịch sử.
- **`click_date`, `click_hour`, `weekday` được ghi sẵn** lúc phát sinh lượt
  nhấp, tính theo giờ Việt Nam. Nếu để SQLite tự tính từ `clicked_at` thì sẽ
  ra giờ UTC và biểu đồ theo giờ sẽ lệch 7 tiếng.
- **`code` dùng `COLLATE NOCASE`** nên `/Tuyen-Sinh` và `/tuyen-sinh` là một.
- **Xoá người dùng không xoá liên kết** (`ON DELETE SET NULL`): các mã QR đã
  in ra ngoài vẫn hoạt động sau khi người tạo rời đơn vị.
- **Chỉ mục** trên `links(user_id, created_at)`, `clicks(link_id, clicked_at)`,
  `clicks(click_date)`, `clicks(link_id, visitor_hash)`.
- SQLite chạy ở chế độ **WAL** (`journal_mode = WAL`) để đọc và ghi không
  chặn nhau, `busy_timeout = 5000` để chịu được truy cập đồng thời.

---

## 4. Quy trình rút gọn liên kết

```mermaid
sequenceDiagram
    autonumber
    actor U as Người dùng
    participant F as Biểu mẫu
    participant API as /api/kiem-tra-ten
    participant HC as HomeController
    participant LS as LinkService
    participant DB as SQLite

    Note over U,API: Kiểm tra tên tuỳ chọn ngay khi gõ (không bắt buộc)
    U->>F: Gõ tên "tuyen-sinh-10"
    F->>API: fetch (chờ 420ms sau lần gõ cuối)
    API->>LS: validateCode()
    LS->>DB: SELECT COUNT(*) WHERE code = ?
    DB-->>API: đã có người dùng
    API-->>F: {available: false, suggestion: "tuyen-sinh-10-2"}
    F-->>U: ✗ đã có người dùng · gợi ý tên khác

    Note over U,DB: Gửi biểu mẫu
    U->>HC: POST /rut-gon
    HC->>HC: csrf_verify()
    HC->>LS: create(data, user)
    LS->>LS: normalizeUrl — tự thêm https:// nếu thiếu
    LS->>LS: validateUrl — chỉ http/https, chặn tự trỏ về hệ thống
    LS->>LS: validateCode — ký tự, độ dài, tên bị giữ, trùng lặp
    LS->>LS: Kiểm tra hạn mức — khách 10/giờ, người dùng theo link_quota

    alt Có lỗi
        LS-->>HC: {ok: false, errors}
        HC-->>U: Về biểu mẫu, giữ dữ liệu đã nhập, hiện lỗi từng trường
    else Hợp lệ
        LS->>LS: Bỏ trống tên → generateUniqueCode()
        LS->>DB: INSERT INTO links
        LS->>DB: INSERT INTO audit_log
        LS-->>HC: {ok: true, link}
        HC-->>U: Chuyển sang /ket-qua/{code} — liên kết + mã QR + nút chia sẻ
    end
```

### Quy tắc tên tuỳ chọn (`LinkService::validateCode`)

| Kiểm tra | Quy tắc |
|---|---|
| Độ dài | 3–64 ký tự (`code_min_length` / `code_max_length`) |
| Ký tự cho phép | Chữ không dấu, số, và `-` `_` `.` |
| Vị trí | Phải bắt đầu và kết thúc bằng chữ hoặc số |
| Dấu liền nhau | Không cho hai dấu đặc biệt liền nhau (`a--b` bị từ chối) |
| Tên bị giữ | Không trùng đường dẫn hệ thống (`LinkService::RESERVED`, 45 tên) |
| Trùng lặp | So sánh không phân biệt hoa/thường |

Mã tự sinh dùng bộ ký tự đã **bỏ các chữ dễ nhìn lẫn**: không có `0` `O` `o`
`1` `l` `I`. Xem `code_alphabet` trong `app/config.php`.

---

## 5. Quy trình chuyển hướng và ghi thống kê

Đây là đường đi nóng nhất của hệ thống — mỗi lần ai đó bấm vào liên kết.

```mermaid
flowchart TD
    A["GET /{code}"] --> B{"Tìm thấy mã ?"}
    B -->|Không| B404["404 — trang 'không tìm thấy liên kết'"]
    B -->|Có| C{"is_active = 1 ?"}

    C -->|Không| G410["410 — Tạm dừng"]
    C -->|Có| D{"starts_at > bây giờ ?"}
    D -->|Có| G410b["410 — Chưa tới giờ<br/>(nêu rõ thời điểm bắt đầu)"]
    D -->|Không| E{"expires_at ≤ bây giờ ?"}
    E -->|Có| G410c["410 — Hết hạn"]
    E -->|Không| F{"click_count ≥ max_clicks ?"}
    F -->|Có| G410d["410 — Đã đủ lượt"]

    F -->|Không| P{"Có mật khẩu ?"}
    P -->|"Có, chưa mở khoá"| PF["Hiện trang nhập mật khẩu<br/>(không để lộ địa chỉ đích)"]
    PF --> PV{"Mật khẩu đúng ?"}
    PV -->|Không| PW["Chờ 0,4s rồi báo sai<br/>(giảm tốc độ dò tự động)"]
    PW --> PF
    PV -->|Đúng| MARK["Ghi cờ đã mở khoá vào phiên"]

    P -->|Không| REC
    MARK --> REC

    REC["Ghi nhận lượt nhấp"] --> R1["Đọc User-Agent → trình duyệt· HĐH· thiết bị· robot"]
    R1 --> R2["Tính visitor_hash = SHA-256(IP + UA + muối riêng)<br/>KHÔNG lưu IP"]
    R2 --> R3{"visitor_hash đã có<br/>ở liên kết này ?"}
    R3 -->|Chưa| R4["is_unique = 1"]
    R3 -->|Rồi| R5["is_unique = 0"]
    R4 --> R6
    R5 --> R6
    R6["INSERT INTO clicks"] --> R7["UPDATE links: click_count +1,<br/>unique_count, last_click_at"]
    R7 --> OUT["302 → địa chỉ gốc<br/>Cache-Control: no-store"]

    style OUT fill:#dcfce7,stroke:#16a34a
    style B404 fill:#fee2e2,stroke:#dc2626
    style G410 fill:#fef3c7,stroke:#d97706
    style G410b fill:#fef3c7,stroke:#d97706
    style G410c fill:#fef3c7,stroke:#d97706
    style G410d fill:#fef3c7,stroke:#d97706
```

### Vì sao dùng 302 mà không phải 301

Chuyển hướng **302 (tạm thời)** kèm `Cache-Control: no-store`. Nếu dùng 301
(vĩnh viễn), trình duyệt sẽ nhớ và những lần sau đi thẳng tới đích **không
qua máy chủ** — số liệu thống kê sẽ thiếu, và việc sửa địa chỉ đích sẽ không
có tác dụng với người đã bấm một lần.

### Lỗi thống kê không được cản người dùng

`RedirectController::sendTo()` bọc phần ghi thống kê trong `try/catch`. Nếu
cơ sở dữ liệu bị lỗi khi ghi, hệ thống **vẫn chuyển hướng** người dùng tới
đích và chỉ ghi lỗi vào nhật ký. Thống kê là phụ, đưa người dùng tới nơi cần
đến là chính.

---

## 6. Vòng đời trạng thái liên kết

Trạng thái **không lưu trong cơ sở dữ liệu** mà được tính lại mỗi lần cần,
từ bốn cột `is_active`, `starts_at`, `expires_at`, `max_clicks`
(`LinkService::state()`). Nhờ vậy không cần công việc định kỳ để đổi trạng
thái khi tới hạn.

```mermaid
stateDiagram-v2
    [*] --> ChoToiHan: tạo có starts_at ở tương lai
    [*] --> DangChay: tạo bình thường

    ChoToiHan: Chờ tới hạn (410)
    DangChay: Đang chạy (302 → đích)
    TamDung: Tạm dừng (410)
    HetHan: Hết hạn (410)
    DuLuot: Đủ lượt (410)

    ChoToiHan --> DangChay: tới starts_at
    DangChay --> TamDung: quản trị bấm Tạm dừng
    TamDung --> DangChay: bấm Bật lại
    DangChay --> HetHan: tới expires_at
    DangChay --> DuLuot: click_count đạt max_clicks
    HetHan --> DangChay: sửa/gia hạn expires_at
    DuLuot --> DangChay: nâng hoặc bỏ max_clicks
    TamDung --> [*]: xoá liên kết
    DangChay --> [*]: xoá liên kết

    note right of DangChay
        Chỉ trạng thái này mới
        chuyển hướng. Bốn trạng
        thái còn lại trả về 410
        kèm lý do cụ thể.
    end note
```

Thứ tự xét trạng thái (quan trọng khi một liên kết vướng nhiều điều kiện):
**Tạm dừng → Chờ tới hạn → Hết hạn → Đủ lượt → Đang chạy.**

---

## 7. Quy trình tạo mã QR

`app/lib/QrCode.php` là bộ mã hoá QR viết riêng, thuần PHP, theo chuẩn
**ISO/IEC 18004**. Hỗ trợ chế độ byte (UTF-8), phiên bản 1–40, bốn mức sửa
lỗi L/M/Q/H.

```mermaid
flowchart TD
    IN["Nội dung<br/>https://rutgon.dongnai.edu.vn/tuyen-sinh-10?s=qr"] --> V

    V["1· Chọn phiên bản nhỏ nhất chứa đủ<br/>so nhu cầu bit với dung lượng từng phiên bản"] --> E
    E["2· Mã hoá dữ liệu<br/>0100 (chế độ byte) + số ký tự + dữ liệu<br/>+ ký hiệu kết thúc + đệm 0xEC/0x11"] --> B
    B["3· Chia khối theo bảng cấu hình<br/>(phiên bản × mức sửa lỗi)"] --> RS
    RS["4· Sinh mã sửa lỗi Reed–Solomon<br/>trên GF(256), đa thức 0x11D"] --> IL
    IL["5· Đan xen các khối<br/>dữ liệu rồi tới mã sửa lỗi"] --> DR
    DR["6· Vẽ ma trận<br/>hoa văn định vị· định thời· căn chỉnh<br/>thông tin định dạng· phiên bản<br/>rải bit theo đường zigzag"] --> M
    M["7· Chọn mặt nạ<br/>thử cả 8, tính điểm phạt theo 4 quy tắc,<br/>giữ mặt nạ điểm thấp nhất"] --> OUT

    OUT{"Xuất ra"} --> SVG["SVG — vector<br/>in khổ lớn không rỗ"]
    OUT --> PNG["PNG — qua thư viện GD<br/>chèn Word· gửi Zalo"]

    style IN fill:#eef2ff,stroke:#4f46e5
    style SVG fill:#dcfce7,stroke:#16a34a
    style PNG fill:#dcfce7,stroke:#16a34a
```

### Vì sao tự viết mà không dùng thư viện

Hosting của đơn vị thường không có Composer, và các thư viện QR đều cần cài
qua Composer. Bộ mã hoá tự viết nằm gọn trong một tệp, không phụ thuộc gì,
và **được kiểm định bằng bộ giải mã độc lập** (xem mục 11).

### Tham số ảnh QR

| Tham số | Ý nghĩa | Miền giá trị |
|---|---|---|
| `co` | Kích thước mỗi ô (px) | 2–30 |
| `le` | Lề trắng quanh mã (số ô) | 0–8 (chuẩn khuyến nghị 4) |
| `mau` | Màu mã, dạng hex | `%23` thay cho `#` |
| `nen` | Màu nền, hoặc `trong` cho nền trong suốt | chỉ SVG hỗ trợ nền trong |
| `sua-loi` | Mức sửa lỗi | 0=L, 1=M, 2=Q, 3=H |
| `nguon` | `0` để bỏ dấu `?s=qr` | mặc định có gắn |
| `tai` | `1` để tải tệp về | |

Mã màu được lọc bằng biểu thức chính quy `^#[0-9a-fA-F]{6}$` trước khi ghép
vào SVG — chặn việc chèn nội dung lạ vào tệp ảnh.

---

## 8. Xác thực và phân quyền

```mermaid
sequenceDiagram
    autonumber
    actor U as Người dùng
    participant AC as AuthController
    participant A as Auth
    participant DB as SQLite

    U->>AC: POST /dang-nhap
    AC->>A: attempt(login, password, remember)
    A->>DB: Đếm lần sai của IP trong 15 phút
    alt ≥ 8 lần sai
        A-->>U: Tạm khoá, mời thử lại sau 15 phút
    else Còn lượt
        A->>DB: Tìm theo username HOẶC email
        A->>A: password_verify()
        alt Sai
            A->>DB: Ghi login_attempts (successful = 0)
            A-->>U: Tên đăng nhập hoặc mật khẩu không đúng
        else Đúng
            A->>DB: Xoá các lần sai của IP
            A->>A: password_needs_rehash → nâng cấp thuật toán băm
            A->>A: session_regenerate_id(true)
            A->>DB: Cập nhật last_login_at, login_count
            opt Chọn "ghi nhớ tôi"
                A->>A: Sinh selector + validator
                A->>DB: Lưu selector + SHA-256(validator), hạn 30 ngày
                A-->>U: Cookie "selector:validator" (HttpOnly, SameSite=Lax)
            end
            A-->>U: Vào bảng điều khiển
        end
    end
```

### Cơ chế ghi nhớ đăng nhập

Dùng mô hình **selector + validator**, an toàn hơn cookie chứa mã người dùng:

- Cookie mang `selector:validator`. Cơ sở dữ liệu chỉ lưu `selector` (tra cứu)
  và **SHA-256 của validator** (đối chiếu) — lộ cơ sở dữ liệu cũng không dựng
  lại được cookie.
- **Dùng một lần:** mỗi lần đăng nhập bằng cookie, token cũ bị xoá và cấp
  token mới (chống phát lại).
- Nếu `selector` khớp nhưng `validator` sai → dấu hiệu token bị đánh cắp →
  **xoá toàn bộ token của người đó**, buộc đăng nhập lại.
- Đổi mật khẩu sẽ thu hồi mọi token đang ghi nhớ.

### Cây quyền

```mermaid
flowchart TD
    START["Yêu cầu tới một trang"] --> PUB{"Trang công khai ?"}
    PUB -->|"/· /huong-dan· /gioi-thieu<br/>/{code}· /xem/{code}· /ma-qr/{code}"| OK1["Cho qua"]
    PUB -->|Không| LOGIN{"Đã đăng nhập ?"}

    LOGIN -->|Không| RED["Nhớ trang muốn tới →<br/>chuyển về /dang-nhap"]
    LOGIN -->|Có| ADMIN{"Trang /quan-tri ?"}

    ADMIN -->|Có| ISADM{"role = admin ?"}
    ISADM -->|Không| F403["403 — không có quyền"]
    ISADM -->|Có| OK2["Cho qua"]

    ADMIN -->|Không| OWN{"Thao tác trên<br/>một liên kết cụ thể ?"}
    OWN -->|Không| OK3["Cho qua"]
    OWN -->|Có| CAN{"canManage: là admin<br/>HOẶC link.user_id = mình ?"}
    CAN -->|Không| F403b["403 — chỉ quản lý<br/>liên kết do mình tạo"]
    CAN -->|Có| OK4["Cho qua"]

    style F403 fill:#fee2e2,stroke:#dc2626
    style F403b fill:#fee2e2,stroke:#dc2626
    style OK1 fill:#dcfce7,stroke:#16a34a
    style OK2 fill:#dcfce7,stroke:#16a34a
    style OK3 fill:#dcfce7,stroke:#16a34a
    style OK4 fill:#dcfce7,stroke:#16a34a
```

**Người dùng đầu tiên của hệ thống tự động thành quản trị viên**
(`Auth::register()` xét `userCount() === 0`).

Hai chốt an toàn ở khu quản trị:
- Quản trị viên **không thể tự** hạ quyền, tự khoá hoặc tự xoá tài khoản mình.
- Hệ thống luôn giữ **ít nhất một** quản trị viên.

---

## 9. Xử lý thời gian UTC+7

Đây là chỗ dễ sai nhất khi bảo trì, nên tách thành một lớp riêng:
`app/lib/Clock.php`.

```mermaid
flowchart LR
    subgraph dung["ĐÚNG — luôn dùng lớp Clock"]
        A1["Clock::now()<br/>'2026-09-07 18:26:00'"]
        A2["Clock::today()"]
        A3["Clock::nowDt()"]
        A4["Clock::human()<br/>'5 phút trước'"]
    end

    subgraph sai["TRÁNH — cho ra giờ UTC, lệch 7 tiếng"]
        B1["CURRENT_TIMESTAMP<br/>của SQLite"]
        B2["date('Y-m-d H:i:s')<br/>khi chưa đặt múi giờ"]
        B3["datetime('now')<br/>trong câu SQL"]
    end

    A1 --> DB[("Lưu dạng chuỗi<br/>'Y-m-d H:i:s'<br/>theo giờ VN")]
    B1 -.->|"lệch 7h"| X["Biểu đồ theo giờ sai<br/>Hạn dùng sai"]

    style dung fill:#dcfce7,stroke:#16a34a
    style sai fill:#fee2e2,stroke:#dc2626
    style X fill:#fee2e2,stroke:#dc2626
```

**Quy ước:**

1. `bootstrap.php` gọi `date_default_timezone_set('Asia/Ho_Chi_Minh')` ngay
   từ đầu.
2. Mọi mốc thời gian lưu vào cơ sở dữ liệu là **chuỗi `Y-m-d H:i:s` theo giờ
   Việt Nam**, do PHP sinh ra qua `Clock::now()`.
3. **Không dùng** `CURRENT_TIMESTAMP` hay `datetime('now')` của SQLite — hai
   hàm này trả về giờ UTC.
4. Khi cần mốc thời gian trong câu SQL (ví dụ so với `expires_at`), tính
   trong PHP rồi truyền vào bằng tham số:
   ```php
   $stmt->execute([':now' => Clock::now()]);
   ```
5. Cột `click_hour` và `weekday` được ghi sẵn lúc phát sinh, nên biểu đồ theo
   giờ / theo thứ không phải quy đổi múi giờ.
6. API trả về ISO 8601 kèm độ lệch `+07:00` (`Clock::iso()`).

Bộ kiểm định `tests/app_test.php` có hạng mục xác nhận API ghi đúng
`+07:00`.

---

## 10. Bảo mật

### Các lớp bảo vệ

```mermaid
flowchart TB
    subgraph L1["Lớp 1 — Máy chủ web"]
        A1["Chặn data/ app/ tests/ tools/<br/>(rewrite [F] + .htaccess từng thư mục)"]
        A2["Chặn theo phần mở rộng<br/>.sqlite .log .md .sh .json"]
        A3["Chặn tệp ẩn (.git .env .htaccess)"]
        A4["Options -Indexes"]
    end

    subgraph L2["Lớp 2 — Ứng dụng"]
        B1["CSRF token cho mọi POST"]
        B2["Escape toàn bộ dữ liệu in ra trang"]
        B3["Truy vấn tham số hoá (PDO prepare)"]
        B4["Chỉ nhận địa chỉ http/https"]
        B5["Chặn tự trỏ về hệ thống"]
        B6["Lọc ký tự lạ trong header Host"]
        B7["Header: nosniff· SAMEORIGIN· Referrer-Policy"]
    end

    subgraph L3["Lớp 3 — Tài khoản"]
        C1["password_hash + tự nâng cấp thuật toán"]
        C2["Khoá IP sau 8 lần sai / 15 phút"]
        C3["Token ghi nhớ dùng một lần"]
        C4["Giới hạn khách 10 liên kết/giờ"]
        C5["Hạn mức liên kết theo tài khoản"]
    end

    subgraph L4["Lớp 4 — Quyền riêng tư"]
        D1["KHÔNG lưu IP người truy cập liên kết"]
        D2["Chỉ lưu băm ẩn danh có muối riêng"]
        D3["Không gọi dịch vụ bên ngoài nào"]
        D4["Tự dọn chi tiết lượt nhấp cũ"]
    end

    L1 --> L2 --> L3 --> L4
```

### Chi tiết cần biết khi bảo trì

| Chủ đề | Cách xử lý |
|---|---|
| **CSRF** | Token 32 byte trong phiên; `csrf_verify()` so bằng `hash_equals()`; sai → 419 kèm trang giải thích |
| **XSS** | Hàm `e()` bọc `htmlspecialchars(ENT_QUOTES \| ENT_SUBSTITUTE)`; mọi dữ liệu người dùng in ra đều qua `e()` |
| **SQL injection** | 100% truy vấn dùng `prepare()` + tham số; tên cột động (thống kê) đối chiếu danh sách trắng trước |
| **Chèn mã vào SVG** | Mã màu lọc bằng regex hex 6 ký tự |
| **Header Host** | `base_url()` lọc bằng `preg_replace('/[^A-Za-z0-9\.\-:\[\]]/')`; nên đặt `site_url` để bỏ hẳn phụ thuộc vào Host |
| **SSRF** | Chỉ khi bật "tự lấy tiêu đề trang đích": `Meta::isSafeUrl()` phân giải DNS rồi chặn dải IP nội bộ; mặc định **tắt** |
| **Dò mật khẩu liên kết** | Chờ 0,4 giây sau mỗi lần sai |
| **Nhật ký lỗi** | Ghi vào `data/error.log`, không hiện chi tiết lỗi cho người dùng (`display_errors = 0`) |

> ⚠️ **Nhắc quan trọng:** nếu clone mã nguồn bằng `git` trực tiếp vào
> `public_html`, hãy chắc chắn `.htaccess` được đọc (`AllowOverride All`).
> Không có nó, thư mục `tests/` sẽ chạy được từ web và người ngoài có thể
> gọi `/tests/app_test.php` để tạo hàng loạt dữ liệu rác. Bản phát hành
> `.zip` **đã loại bỏ** `tests/` nên không gặp rủi ro này.

---

## 11. Quy trình kiểm định

```bash
php tests/run_all.php
```

Script tự bật máy chủ trên cổng trống, dùng **cơ sở dữ liệu tạm** (không
đụng dữ liệu thật), chạy bốn bộ test rồi dọn sạch.

```mermaid
flowchart TB
    RUN["php tests/run_all.php"] --> T1

    subgraph nosrv["Không cần máy chủ"]
        T1["path_test.php — 19 hạng mục<br/>nhận diện đường dẫn ở 3 kiểu triển khai"]
        T2["qr_test.php — 54 hạng mục<br/>thuật toán QR"]
    end

    T1 --> T2 --> CFG["Tạo cấu hình tạm<br/>+ cơ sở dữ liệu tạm"]
    CFG --> SRV["Bật php -S trên cổng trống"]

    subgraph srv["Cần máy chủ"]
        T3["qr_image_test.php — 10 hạng mục<br/>đọc ngược điểm ảnh PNG"]
        T4["app_test.php — 118 hạng mục<br/>luồng sử dụng qua HTTP thật"]
    end

    SRV --> T3 --> T4 --> CHK{"Nhật ký lỗi<br/>máy chủ sạch ?"}
    CHK -->|Có| PASS["201 hạng mục đạt · 0 lỗi"]
    CHK -->|Không| FAIL["In nhật ký lỗi ra"]

    style PASS fill:#dcfce7,stroke:#16a34a
    style FAIL fill:#fee2e2,stroke:#dc2626
```

### Điểm mấu chốt: bộ giải mã QR độc lập

`tests/qr_decoder.php` **không dùng lại một dòng nào** của
`app/lib/QrCode.php`. Bản đồ module chức năng, bảng vị trí hoa văn căn chỉnh
và các bảng cấu hình khối trong đó được dựng lại từ mô tả trong chuẩn.

```mermaid
flowchart LR
    T["Chuỗi gốc"] --> ENC["app/lib/QrCode.php<br/>MÃ HOÁ"]
    ENC --> M["Ma trận QR"]
    M --> PNG["Tệp PNG qua HTTP"]
    PNG --> PIX["Đọc từng điểm ảnh<br/>dò lề· suy cỡ ô"]
    PIX --> M2["Ma trận dựng lại"]
    M2 --> DEC["tests/qr_decoder.php<br/>GIẢI MÃ — viết ĐỘC LẬP"]
    DEC --> T2["Chuỗi giải ra"]
    T2 --> CMP{"Giống chuỗi gốc ?<br/>Syndrome RS = 0 ?"}
    CMP -->|Có| OK["Đạt"]
    CMP -->|Không| NG["Lỗi"]

    ENC -.->|"đối chiếu"| STD[("Bảng trong chuẩn<br/>ISO/IEC 18004<br/>vị trí căn chỉnh· dung lượng")]
    DEC -.-> STD

    style OK fill:#dcfce7,stroke:#16a34a
    style NG fill:#fee2e2,stroke:#dc2626
    style STD fill:#eef2ff,stroke:#4f46e5
```

Nhờ cách này, quá trình phát triển đã phát hiện **bảng số khối sửa lỗi mức H
bị sai** — mã QR mức H trước đó sai hoàn toàn mà nhìn bằng mắt không thấy.

### Ba lỗi thật do bộ kiểm định tìm ra

| Lỗi | Hậu quả nếu không phát hiện |
|---|---|
| Bảng `NUM_ECC_BLOCKS` mức H sai từ phiên bản 8 | Mọi mã QR mức sửa lỗi H không quét được |
| `fputcsv()` thiếu tham số `$escape` (PHP 8.4) | Chức năng xuất CSV trả về trang lỗi |
| Đang đăng nhập vẫn POST tạo thêm tài khoản được | Tạo tài khoản ngoài ý muốn, lẫn phiên |

---

## 12. Quy trình sao lưu và phục hồi

```mermaid
flowchart TB
    subgraph bk["SAO LƯU — nên đặt định kỳ hằng ngày"]
        S1["Cách an toàn nhất:<br/>sqlite3 data/rutgon.sqlite<br/>&quot;.backup 'ban-sao.sqlite'&quot;<br/>chép đúng cả khi đang chạy"]
        S2["Hoặc trên cPanel:<br/>File Manager → nén thư mục data/<br/>rồi tải về"]
        S3["Hoặc chép tay 3 tệp:<br/>.sqlite· .sqlite-wal· .sqlite-shm"]
    end

    subgraph st["LƯU GIỮ"]
        K1["Giữ 7 bản gần nhất theo ngày"]
        K2["Giữ 1 bản mỗi tháng"]
        K3["Đặt ở nơi KHÁC máy chủ"]
    end

    subgraph rs["PHỤC HỒI"]
        R1["1· Bật chế độ bảo trì<br/>hoặc tạm dừng website"]
        R2["2· Đổi tên tệp hiện tại<br/>thành .sqlite.loi"]
        R3["3· Chép bản sao vào data/"]
        R4["4· Xoá tệp -wal và -shm cũ"]
        R5["5· Kiểm tra quyền ghi thư mục data/"]
        R6["6· Mở trang chủ kiểm tra"]
    end

    bk --> st
    st -.->|"khi cần"| rs
    R1 --> R2 --> R3 --> R4 --> R5 --> R6
```

**Cần chép cả tệp `-wal`?** SQLite ở chế độ WAL ghi thay đổi mới vào tệp
`-wal` trước. Nếu chỉ chép tệp `.sqlite` khi hệ thống đang chạy, có thể mất
những thay đổi cuối. Lệnh `.backup` xử lý việc này đúng; nếu chép tay thì
chép cả ba tệp.

**Kiểm tra bản sao có đọc được:**
```bash
sqlite3 ban-sao.sqlite "PRAGMA integrity_check; SELECT COUNT(*) FROM links;"
```

---

## 13. Quy trình nâng cấp phiên bản

```mermaid
flowchart TD
    A["Tải bản phát hành mới (.zip)"] --> B["1· SAO LƯU data/rutgon.sqlite<br/>bắt buộc, không bỏ qua"]
    B --> C["2· Đọc CHANGELOG.md<br/>xem có thay đổi phá vỡ tương thích không"]
    C --> D["3· Giải nén ra thư mục tạm"]
    D --> E["4· Ghi đè: index.php· app/· assets/<br/>.htaccess"]
    E --> F["5· KHÔNG chạm vào:<br/>data/ và app/config.local.php"]
    F --> G["6· Mở trang chủ<br/>bảng mới (nếu có) tự tạo"]
    G --> H{"Hoạt động bình thường ?"}
    H -->|Có| I["Xong"]
    H -->|Không| J["Phục hồi: chép lại thư mục cũ<br/>+ bản sao cơ sở dữ liệu"]

    style B fill:#fef3c7,stroke:#d97706
    style F fill:#fef3c7,stroke:#d97706
    style I fill:#dcfce7,stroke:#16a34a
    style J fill:#fee2e2,stroke:#dc2626
```

Cấu trúc bảng có phiên bản riêng, lưu ở khoá `schema_version` trong bảng
`settings`. `Database::migrate()` chạy `CREATE TABLE IF NOT EXISTS` mỗi lần
khởi động nên thêm bảng mới là tự động; nếu bản mới cần **đổi cột**, ghi chú
sẽ nằm trong `CHANGELOG.md`.

---

## 13b. Quy trình phát hành bản mới

Dành cho người bảo trì mã nguồn.

```mermaid
flowchart TD
    A["1· Chạy php tests/run_all.php<br/>phải đạt hết, nhật ký sạch"] --> B
    B["2· Cập nhật CHANGELOG.md<br/>thêm mục ## [X.Y.Z] — ngày"] --> C
    C["3· php tools/build-release.php<br/>tự lấy số phiên bản từ CHANGELOG"] --> D
    D["4· Soát nội dung gói:<br/>unzip -Z1 dist/rutgon-link-vX.Y.Z.zip"] --> E
    E["5· Thử triển khai: giải nén ra thư mục trắng,<br/>chép tests/ vào, chạy lại bộ kiểm định"] --> F
    F["6· Commit + tạo thẻ vX.Y.Z"] --> G
    G["7· Trên GitHub: Releases → Draft a new release<br/>chọn thẻ, dán phần CHANGELOG, đính kèm .zip"] --> H
    H["Xong — người dùng tải ở trang Releases"]

    style A fill:#fef3c7,stroke:#d97706
    style E fill:#fef3c7,stroke:#d97706
    style H fill:#dcfce7,stroke:#16a34a
```

### Đóng gói

```bash
php tools/build-release.php          # lấy số phiên bản từ CHANGELOG.md
php tools/build-release.php 1.1.0    # hoặc chỉ định
```

Kết quả ở `dist/`: tệp `.zip` và tệp `.sha256` đi kèm.

Script dùng **danh sách trắng** các mục đưa vào (`$include`) chứ không phải
danh sách loại trừ — nhờ vậy tệp lạ nằm trong thư mục làm việc không bao giờ
lọt vào bản phát hành. Những thứ bị loại dứt khoát: `tests/`, `tools/`,
`dist/`, `docs/images/`, mọi tệp `.sqlite`, `.log`, và
`app/config.local.php`.

### Hai đường tải, cả hai đều triển khai được ngay

| Đường tải | Nội dung | Ghi chú |
|---|---|---|
| Tệp `.zip` đính kèm ở trang **Releases** | 77 tệp, ~200 KB | Giải nén ra là dùng ngay, có kèm `CAI-DAT-NHANH.txt` |
| Bản `.zip`/`.tar.gz` GitHub tự sinh cho mỗi thẻ | 90 tệp, ~200 KB | Nhờ `export-ignore` trong `.gitattributes` nên cũng không có `tests/`, `tools/`, ảnh tài liệu. Chỉ khác là nội dung nằm trong một thư mục con |

> Nút **"Download ZIP"** ở trang chính của repo (tải nhánh, không phải thẻ)
> **có** chứa `tests/` và `tools/`. Đừng hướng người dùng tải bằng nút đó.

### Kiểm tra trước khi công bố

```bash
# Soát nội dung gói
unzip -Z1 dist/rutgon-link-v1.0.0.zip | head -20

# Thử triển khai thật
mkdir /tmp/thu && cd /tmp/thu
unzip -q ~/dongnai-link-shortener/dist/rutgon-link-v1.0.0.zip
cp -r ~/dongnai-link-shortener/tests ./tests   # tests/ không nằm trong gói
php tests/run_all.php
```

Nếu có Apache, kiểm tra thêm rằng `.htaccess` hoạt động: `/data/rutgon.sqlite`
và `/app/config.php` phải trả về **403**. Máy chủ `php -S` **không đọc**
`.htaccess` nên không kiểm tra được việc này.

---

## 14. Xử lý sự cố thường gặp

| Hiện tượng | Nguyên nhân thường gặp | Cách xử lý |
|---|---|---|
| Trang trắng, không thông báo | Lỗi PHP, `display_errors` đang tắt | Xem `data/error.log` |
| "Thư mục dữ liệu không có quyền ghi" | Quyền thư mục `data/` | `chmod 775 data`, đúng chủ sở hữu là user của máy chủ web |
| Trang chủ chạy, nhưng `/tuyen-sinh-10` báo 404 | Chưa bật `mod_rewrite` hoặc `AllowOverride None` | Bật rewrite; tạm thời dùng `/index.php/tuyen-sinh-10` |
| Mã QR ra tên miền lạ | Chưa đặt `site_url` | Đặt `site_url` trong `app/config.local.php` |
| Liên kết in ra là `http://` dù web chạy https | Proxy/CDN không gửi `X-Forwarded-Proto` | Đặt `site_url` với `https://` |
| Không xuất được PNG, SVG vẫn được | Thiếu phần mở rộng `gd` | Bật `gd` trong cPanel → Select PHP Version → Extensions |
| Biểu đồ theo giờ lệch 7 tiếng | Có chỗ dùng giờ UTC | Kiểm tra mã mới thêm: phải dùng `Clock::`, không dùng `CURRENT_TIMESTAMP` |
| "Phiên làm việc đã hết hạn" (419) | Mở biểu mẫu quá lâu, hoặc cookie bị chặn | Tải lại trang; kiểm tra cookie của trình duyệt |
| Đăng nhập báo tạm khoá | 8 lần sai trong 15 phút | Chờ, hoặc Quản trị → Cài đặt → Bảo trì → Mở khoá đăng nhập |
| Tệp dữ liệu phình to | Nhiều bản ghi chi tiết lượt nhấp | Bảo trì → Xoá chi tiết cũ → Dồn nén (VACUUM) |
| Không đăng nhập được, mất mã dự phòng | — | Nhờ quản trị viên khác đặt lại mật khẩu |
| Mất luôn tài khoản quản trị duy nhất | — | Xem ghi chú bên dưới |

### Mất tài khoản quản trị duy nhất

Không có cửa hậu trong hệ thống. Cách xử lý bằng dòng lệnh trên máy chủ:

```bash
# Cấp quyền quản trị cho một tài khoản đang có
sqlite3 data/rutgon.sqlite "UPDATE users SET role='admin', status='active' WHERE username='ten-dang-nhap';"
```

Nếu không còn tài khoản nào, xoá bảng `users` rồi đăng ký lại — người đăng ký
đầu tiên sẽ thành quản trị viên. **Liên kết và thống kê không bị ảnh hưởng**
(chúng chỉ mất chủ sở hữu):

```bash
sqlite3 data/rutgon.sqlite "DELETE FROM users;"
```

---

## Bản đồ mã nguồn

| Tệp | Trách nhiệm |
|---|---|
| `index.php` | Bảng định tuyến — nơi duy nhất khai báo đường dẫn |
| `app/bootstrap.php` | Nạp lớp, cấu hình, múi giờ, bắt lỗi, phiên làm việc |
| `app/config.php` | Cấu hình tĩnh (`config.local.php` để ghi đè) |
| `app/lib/Database.php` | Kết nối SQLite + tạo bảng |
| `app/lib/LinkService.php` | Nghiệp vụ liên kết: kiểm tra, tạo, sửa, ghi lượt nhấp |
| `app/lib/Stats.php` | Toàn bộ truy vấn thống kê |
| `app/lib/Auth.php` | Đăng ký, đăng nhập, ghi nhớ, phân quyền, nhật ký |
| `app/lib/QrCode.php` | Bộ mã hoá QR (phiên bản 1–40, L/M/Q/H) |
| `app/lib/Chart.php` | Vẽ biểu đồ SVG ở máy chủ |
| `app/lib/Clock.php` | **Mọi** xử lý thời gian UTC+7 |
| `app/lib/Ua.php` | Nhận diện trình duyệt / HĐH / thiết bị / robot |
| `app/lib/Meta.php` | Lấy tiêu đề trang đích (tuỳ chọn, có chống SSRF) |
| `app/lib/Settings.php` | Cấu hình động quản trị viên đổi được |
| `app/lib/Support.php` | Hàm tiện dụng: `e()`, `url()`, CSRF, flash… |
| `app/lib/Router.php` | Khớp tuyến, hỗ trợ tham số `{id:\d+}` |
| `app/controllers/` | 12 bộ xử lý theo nhóm chức năng |
| `app/views/` | `layout.php` + `partials/` + `pages/` |
| `assets/css/app.css` | Toàn bộ giao diện: sáng/tối, responsive, bản in |
| `assets/js/app.js` | Tương tác — **không bắt buộc** để dùng hệ thống |
| `tests/` | Bốn bộ kiểm định + máy chủ thử nghiệm |

---

© 2026 — **Thiết kế bởi Trương Anh Tuấn** · Phòng GDPT-GDTX Sở GDĐT Đồng Nai
