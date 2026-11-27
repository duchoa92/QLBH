# Hướng dẫn cài đặt QLBH (Ubuntu và Windows)

Tài liệu này hướng dẫn **từng bước**, làm theo thứ tự từ trên xuống là xong.

**Quy trình chung chỉ có 3 việc:**

1. **Tải mã nguồn** về máy (từ GitHub hoặc từ file zip).
2. **Chạy 1 script cài đặt** (script tự cài PHP, web server, thư viện…).
3. **Mở trang `/setup`** trên trình duyệt, điền thông tin database và tài khoản quản trị.

> Phát triển bởi Đức Hòa Computer – Vạn Xuân, Hưng Yên.
> Hỗ trợ kỹ thuật: ĐT/Zalo **0906064789** – Facebook: fb.com/duchoait

---

## Mục lục

- [0. Chuẩn bị](#0-chuẩn-bị)
- [1. Tải mã nguồn](#1-tải-mã-nguồn)
- [2. Cài trên Ubuntu](#2-cài-trên-ubuntu-2204--2404)
- [3. Cài trên Windows (Laragon)](#3-cài-trên-windows-1011-laragon)
- [4. Hoàn tất bằng trang /setup](#4-hoàn-tất-bằng-trang-setup-cả-hai-hệ-điều-hành)
- [5. Cài chứng chỉ gốc trên điện thoại](#5-cài-chứng-chỉ-gốc-trên-điện-thoại-để-dùng-camera)
- [6. Cập nhật phiên bản mới](#6-cập-nhật-phiên-bản-mới)
- [7. Sao lưu và bảo trì](#7-sao-lưu-và-bảo-trì)
- [8. Xử lý lỗi thường gặp](#8-xử-lý-lỗi-thường-gặp)

---

## 0. Chuẩn bị

### 0.1. Bạn cần có

| Thứ cần có | Ubuntu | Windows |
|---|---|---|
| Hệ điều hành | Ubuntu 22.04 hoặc 24.04 | Windows 10 / 11 |
| Quyền quản trị | Tài khoản có `sudo` | Tài khoản Administrator |
| Internet | Có (để tải PHP, thư viện) | Có |
| Địa chỉ IP máy chủ | Xem bằng lệnh `hostname -I` | Xem bằng lệnh `ipconfig` (dòng *IPv4 Address*) |

Ghi lại **địa chỉ IP của máy chủ** (ví dụ `192.168.100.201`), các bước sau sẽ dùng.

### 0.2. Script sẽ tự làm giúp bạn những gì

| Việc | Ubuntu | Windows (Laragon) |
|---|---|---|
| Cài PHP 8.4 + extension, web server, Composer | Tự động | Tự động (cài Laragon qua winget) |
| Cài thư viện PHP (`composer install`), tạo `.env`, `APP_KEY` | Tự động | Tự động |
| Cấp quyền thư mục, `storage:link` | Tự động | Tự động |
| Lịch chạy nền (sao lưu tự động, nhắc việc) | Tự động (cron) | Tự động (Scheduled Task) |
| Mở cổng tường lửa | Tự động (nếu ufw đang bật) | Tự động (Windows Firewall) |
| Cài MariaDB + tạo user cho trang setup | Tự động (thêm `--with-mariadb`) | Có sẵn trong Laragon |
| Chứng chỉ HTTPS nội bộ | Tự động (thêm `--https`) | Tự động tạo, bạn bật SSL trong Laragon |
| Tạo database, bảng, tài khoản quản trị | Trang `/setup` | Trang `/setup` |
| Cài chứng chỉ gốc lên điện thoại | **Bạn làm** (mục 5) | **Bạn làm** (mục 5) |
| Đặt Document Root trong Laragon | – | **Bạn làm** (1 thao tác) |

Yêu cầu kỹ thuật (script tự lo, chỉ để tham khảo): PHP 8.2 trở lên (khuyến nghị 8.4), MySQL/MariaDB (hoặc PostgreSQL/SQLite), các extension `curl fileinfo gd intl mbstring openssl pdo_* zip xml`. Giao diện đã build sẵn trong `public/build`; chỉ cần Node.js 22 nếu bạn sửa giao diện.

---

## 1. Tải mã nguồn

Chọn **một** trong hai cách: **A. Từ GitHub** (khuyến nghị, dễ cập nhật sau này) hoặc **B. Từ file zip**.

> Trong các lệnh dưới đây, `<TÀI-KHOẢN>/<TÊN-REPO>` là địa chỉ kho mã nguồn trên GitHub, ví dụ `duchoait/qlbh`. Hãy thay bằng địa chỉ thật của bạn.

### Cách A. Tải từ GitHub

#### A1. Nếu kho mã là **công khai (public)**

**Ubuntu**

1. Cài Git (nếu chưa có):
   ```bash
   sudo apt update && sudo apt install -y git unzip
   ```
2. Tải mã nguồn về `/var/www/qlbh`:
   ```bash
   sudo mkdir -p /var/www
   sudo git clone https://github.com/duchoa92/QLBH.git /var/www/qlbh
   cd /var/www/qlbh
   ```

   Cập nhật trên Ubuntu

      bash
         cd /var/www/qlbh
      bash
         sudo git pull

      Nếu git pull báo lỗi "dubious ownership", chạy lệnh này rồi git pull lại:

      bash
         sudo git config --global --add safe.directory /var/www/qlbh

      Sau khi kéo về, chạy tiếp từng lệnh:

      bash
         sudo composer install --no-dev --optimize-autoloader
      bash
         sudo php artisan migrate --force
      bash
         sudo php artisan optimize:clear
      bash
         sudo chown -R www-data:www-data storage bootstrap/cache

      Nên sao lưu dữ liệu trước (Cài đặt → Sao lưu và khôi phục trong ứng dụng).

**Windows**

1. Cài Git: mở **PowerShell** và chạy
   ```powershell
   winget install --id Git.Git -e
   ```
   Sau đó **đóng và mở lại PowerShell** để nhận lệnh `git`.
2. Tải mã nguồn về `C:\laragon\www\qlbh`:
   ```powershell
   New-Item -ItemType Directory -Force C:\laragon\www | Out-Null
   git clone https://github.com/<TÀI-KHOẢN>/<TÊN-REPO>.git C:\laragon\www\qlbh
   cd C:\laragon\www\qlbh
   ```

#### A2. Nếu kho mã là **riêng tư (private)**

GitHub không cho nhập mật khẩu tài khoản khi `git clone`, bạn cần **Personal Access Token (PAT)**:

1. Đăng nhập GitHub → bấm ảnh đại diện → **Settings**.
2. Kéo xuống cuối menu trái → **Developer settings** → **Personal access tokens** → **Tokens (classic)**.
3. Bấm **Generate new token (classic)**, đặt tên (ví dụ `qlbh-server`), chọn hạn dùng, tick quyền **`repo`**, rồi bấm **Generate token**.
4. **Sao chép token ngay** (chỉ hiện một lần).
5. Chạy lệnh clone, khi hỏi *Username* nhập tên tài khoản GitHub, khi hỏi *Password* **dán token** (không phải mật khẩu GitHub):
   ```bash
   git clone https://github.com/<TÀI-KHOẢN>/<TÊN-REPO>.git
   ```
   Hoặc dán token vào thẳng địa chỉ (tiện nhưng token sẽ nằm trong lịch sử lệnh):
   ```bash
   git clone https://<TÀI-KHOẢN>:<TOKEN>@github.com/<TÀI-KHOẢN>/<TÊN-REPO>.git /var/www/qlbh
   ```

#### A3. Tải bằng nút "Download ZIP" (không cần cài Git)

1. Mở trang kho mã trên GitHub.
2. Bấm nút xanh **Code** → **Download ZIP**.
3. Giải nén như **Cách B** bên dưới.

> Cách này không cập nhật được bằng `git pull` sau này; muốn cập nhật phải tải ZIP mới và chép đè.

### Cách B. Tải từ file zip (`qlbh.zip`)

**Ubuntu**

```bash
sudo apt update && sudo apt install -y unzip
sudo mkdir -p /var/www
sudo unzip qlbh.zip -d /var/www/qlbh
cd /var/www/qlbh
```

> Sau khi giải nén, hãy kiểm tra có file `artisan` nằm ngay trong `/var/www/qlbh`. Nếu nó nằm trong thư mục con (ví dụ `/var/www/qlbh/QLBH/artisan`), hãy chuyển nội dung thư mục con ra ngoài.

**Windows**

1. Chuột phải file `qlbh.zip` → **Extract All…**
2. Chọn thư mục đích: `C:\laragon\www\qlbh`.
3. Kiểm tra có file `artisan` nằm ngay trong `C:\laragon\www\qlbh`.

### Kiểm tra đã tải đúng chưa

Thư mục `qlbh` phải có các mục: `app`, `public`, `scripts`, `artisan`, `composer.json`.

Nếu thấy thư mục `scripts` chứa `install-ubuntu.sh` (Ubuntu) hoặc `install-windows.ps1` (Windows) là đúng, chuyển sang mục 2 hoặc 3.

> **Lưu ý khi dùng GitHub:** thư mục `public/build` (giao diện đã build) phải có trong kho mã. Nếu kho của bạn không đưa thư mục này lên, trang sẽ trắng hoặc lỗi giao diện. Khi đó cài Node.js 22 rồi chạy `npm ci && npm run build` trong thư mục dự án (script Ubuntu tự làm việc này, trừ khi dùng `--no-build`).

---

## 2. Cài trên Ubuntu 22.04 / 24.04

### 2.1. Cài tự động (khuyến nghị)

**Bước 1.** Mở terminal, vào thư mục dự án:
```bash
cd /var/www/qlbh
```

**Bước 2.** Chạy script cài đặt (bằng quyền root). Lệnh đầy đủ khuyến nghị:
```bash
sudo bash scripts/install-ubuntu.sh --with-mariadb --https
```

Ý nghĩa các tùy chọn (chọn cái bạn cần):

| Tùy chọn | Tác dụng |
|---|---|
| `--with-mariadb` | Cài MariaDB và tạo user `qlbh` (được tạo database có tên bắt đầu bằng `qlbh`). Mật khẩu lưu ở `/root/qlbh-db-credentials.txt`. |
| `--https` | Tạo CA nội bộ và chứng chỉ cho IP máy chủ, nginx lắng nghe cổng 443 (**cần nếu muốn dùng camera điện thoại**). |
| `--server-name qlbh.local` | Dùng tên miền/tên máy thay vì chỉ IP. |
| `--php-version 8.4` | Chọn phiên bản PHP (mặc định 8.4, tự thêm PPA `ondrej/php` nếu Ubuntu chưa có). |
| `--no-nginx` | Bỏ qua nginx (bạn tự cấu hình Apache/nginx). |
| `--no-build` | Không build giao diện. |

Script chạy lại nhiều lần vẫn an toàn: không ghi đè `.env`, không tạo lại database.

**Bước 3.** Chờ script chạy xong (vài phút). Cuối cùng script in ra địa chỉ để mở.

**Bước 4.** Nếu dùng `--with-mariadb`, xem mật khẩu database (dùng ở mục 4):
```bash
sudo cat /root/qlbh-db-credentials.txt
```

**Bước 5.** Mở trình duyệt, vào:
- `https://<IP-máy-chủ>/setup` nếu dùng `--https`
- `http://<IP-máy-chủ>/setup` nếu không dùng `--https`

Rồi sang **mục 4** để hoàn tất.

> Lần đầu mở bằng `https://` trình duyệt sẽ báo "Không bảo mật" vì chưa cài chứng chỉ gốc. Bạn vẫn bấm **Nâng cao → Tiếp tục** để vào được. Cài chứng chỉ gốc theo mục 5 để hết cảnh báo.

### 2.2. Các bước thủ công tương ứng (chỉ dùng khi muốn tự làm)

<details>
<summary>Bấm để xem</summary>

```bash
# 1. PHP 8.4 + extension, nginx, MariaDB
sudo add-apt-repository -y ppa:ondrej/php && sudo apt update
sudo apt install -y php8.4-{cli,fpm,common,mbstring,xml,curl,zip,gd,intl,bcmath,sqlite3,mysql,pgsql} nginx mariadb-server unzip git cron

# 2. Composer: https://getcomposer.org/download/

# 3. Thư viện và .env
cd /var/www/qlbh
composer install --no-dev --optimize-autoloader
cp -n .env.example .env && php artisan key:generate
sed -i 's/^APP_ENV=.*/APP_ENV=production/; s/^APP_DEBUG=.*/APP_DEBUG=false/' .env

# 4. Quyền thư mục
sudo chown -R www-data:www-data storage bootstrap/cache database .env
sudo chmod -R ug+rwX storage bootstrap/cache database
php artisan storage:link

# 5. Cron cho user www-data (chạy lịch nền mỗi phút)
( sudo crontab -u www-data -l 2>/dev/null; echo "* * * * * cd /var/www/qlbh && /usr/bin/php8.4 artisan schedule:run >> /dev/null 2>&1" ) | sudo crontab -u www-data -
```

**Cấu hình nginx cần lưu ý:** cổng vào của dự án là `public/setup.php` (giống `public/.htaccess`), không phải `index.php`:
```nginx
index setup.php index.php;
location / { try_files $uri $uri/ /setup.php?$query_string; }
```

**Nếu dùng Apache:** bật `mod_rewrite`, đặt `DocumentRoot` là thư mục `public`, `AllowOverride All`.

</details>

---

## 3. Cài trên Windows 10/11 (Laragon)

### 3.1. Cài tự động

**Bước 1.** Đảm bảo mã nguồn đã nằm ở `C:\laragon\www\qlbh` (xem mục 1). Nếu chưa có Laragon, script sẽ tự cài.

**Bước 2.** Mở **PowerShell bằng quyền quản trị**: bấm nút Start, gõ `PowerShell`, chuột phải → **Run as administrator**.

**Bước 3.** Chạy lần lượt 2 lệnh:
```powershell
cd C:\laragon\www\qlbh
powershell -ExecutionPolicy Bypass -File scripts\install-windows.ps1
```

Script sẽ lần lượt: cài Laragon (winget) nếu chưa có → kiểm tra PHP ≥ 8.2 → bật các extension còn thiếu trong `php.ini` → `composer install` → tạo `.env` + `APP_KEY` → `storage:link` → tạo Scheduled Task chạy `schedule:run` mỗi phút → mở cổng 80/443 cho mạng nội bộ.

**Bước 4.** Mở **Laragon** và bấm **Start All** (khởi động Apache + MySQL). Nếu script báo đã sửa `php.ini` thì bấm **Stop All** rồi **Start All** lại.

**Bước 5.** Đặt Document Root (để mở được bằng IP máy, ví dụ `http://192.168.0.103`):
- Laragon → Menu → **Preferences** → tab **General** → ô **Document Root** nhập: `C:\laragon\www\qlbh\public` → **Close**.
- Bấm **Stop All** rồi **Start All** một lần nữa.

**Bước 6.** Mở trình duyệt, vào `http://localhost/setup` rồi sang **mục 4**.

> Nếu Laragon cài sẵn PHP thấp hơn 8.2: Laragon → Menu → **PHP** → **Version** → chọn bản 8.2 trở lên (hoặc thêm PHP 8.4 vào `C:\laragon\bin\php`).

### 3.2. Bật HTTPS cho camera (Windows)

Camera điện thoại chỉ chạy trên HTTPS.

**Bước 1.** Mở PowerShell quản trị, chạy:
```powershell
cd C:\laragon\www\qlbh
powershell -ExecutionPolicy Bypass -File scripts\windows-https.ps1
```
Script tạo CA nội bộ, chứng chỉ cho IP máy, file vhost Apache `etc\apache2\sites-enabled\00-qlbh-ssl.conf` và chép chứng chỉ gốc vào `public\certs\rootCA.crt`.

**Bước 2.** Trong Laragon: Menu → **Apache** → **SSL** → **Enabled**.

**Bước 3.** Bấm **Stop All** rồi **Start All**.

**Bước 4.** Mở `https://<IP-máy>/setup`.

> Script này chưa kiểm thử trên mọi phiên bản Laragon. Nếu Apache không khởi động, xem file `error.log` của Apache.

---

## 4. Hoàn tất bằng trang `/setup` (cả hai hệ điều hành)

Trang này có **3 bước** (Database → Quản trị → Xác nhận). Trang chỉ chạy **một lần**: sau khi xong, `/setup` tự chuyển về trang đăng nhập.

### Bước 4.0. Kiểm tra máy chủ (đầu trang, chỉ hiện khi có vấn đề)

- Nếu thấy khung cảnh báo "Máy chủ chưa sẵn sàng" hoặc "Cần lưu ý": bấm **Chi tiết** để xem mục nào lỗi (mỗi lỗi có lệnh sửa để sao chép).
- Bấm **Tự cấu hình** để trang tự tạo thư mục, `storage:link`… rồi xem lại kết quả.
- Không thấy khung cảnh báo thì bỏ qua, làm tiếp.

### Bước 4.1. Cơ sở dữ liệu

Điền thông tin theo bảng:

| Môi trường | Loại | Máy chủ | Cổng | Tài khoản | Mật khẩu | Tên database |
|---|---|---|---|---|---|---|
| **Ubuntu** có `--with-mariadb` | MySQL / MariaDB | `127.0.0.1` | `3306` | `qlbh` | lấy trong `/root/qlbh-db-credentials.txt` | bắt đầu bằng `qlbh` (ví dụ `qlbh`) |
| **Laragon** (Windows) | MySQL / MariaDB | `127.0.0.1` | `3306` (nếu MySQL của bạn dùng cổng khác, ví dụ `3307`, nhập đúng cổng đó) | `root` | để trống | tên mới, ví dụ `qlbh` |
| Chỉ dùng thử | SQLite | – | – | – | – | tên file, ví dụ `qlbh` |

Sau đó:
1. Bấm **Kiểm tra kết nối**. Thấy thông báo xanh "Kết nối thành công" là đúng.
2. Bấm **Tiếp tục**.

> Trang sẽ tự tạo database nếu user có quyền. Database phải **trống**, trang không ghi đè dữ liệu đã có.

### Bước 4.2. Quản trị viên

Điền: **Tên đăng nhập**, **Họ và tên**, **Số điện thoại** (8–15 chữ số), **Email**, **Mật khẩu** (từ 8 ký tự), **Nhập lại mật khẩu**. Bấm **Tiếp tục**.

> Ghi nhớ tên đăng nhập và mật khẩu này, đây là tài khoản **Super Admin** dùng để vào hệ thống.

### Bước 4.3. Xác nhận

1. Kiểm tra lại thông tin (bấm **Sửa** bên cạnh mục nào cần đổi).
2. Trên Linux sẽ có ô **Tự cài lịch chạy nền (cron)**, nên để bật.
3. Bấm **Khởi tạo** và chờ vài giây (nút hiện "Đang khởi tạo…").
4. Khi thấy màn hình **"Thiết lập thành công"**, bấm **Đăng nhập** và đăng nhập bằng tài khoản vừa tạo.

---

## 5. Cài chứng chỉ gốc trên điện thoại (để dùng camera)

Camera chỉ hoạt động khi trình duyệt tin tưởng chứng chỉ HTTPS. Làm một lần cho mỗi thiết bị.

**Bước 1.** Trên điện thoại, mở trình duyệt và tải file: `http://<IP>/certs/rootCA.crt`

**Bước 2.** Cài theo hệ điều hành:

**Android**
1. Cài đặt → Bảo mật → Mã hóa & thông tin xác thực → **Cài đặt chứng chỉ** → **Chứng chỉ CA**.
2. Chọn file `rootCA.crt` vừa tải.
(Tên menu khác nhau tùy hãng máy.)

**iPhone (dùng Safari)**
1. Mở link trên **Safari** → **Cho phép** tải hồ sơ.
2. Cài đặt → **Đã tải hồ sơ** → **Cài đặt**.
3. Cài đặt → Cài đặt chung → Giới thiệu → **Cài đặt tin cậy chứng nhận** → bật **QLBH Local CA**.

**Máy tính Windows / Chrome**
1. Mở file `.crt` → **Install Certificate**.
2. Chọn **Local Machine** → chọn **Trusted Root Certification Authorities** → hoàn tất.

**Bước 3.** Mở `https://<IP>`: không còn cảnh báo, trình duyệt sẽ xin quyền dùng camera, bấm **Cho phép**.

> Chứng chỉ máy chủ có hạn 825 ngày. Hết hạn thì chạy lại script (`--https` trên Ubuntu, `windows-https.ps1` trên Windows). Điện thoại **không cần cài lại** chứng chỉ gốc (hạn 10 năm).

---

## 6. Cập nhật phiên bản mới

> Nên **sao lưu** trước khi cập nhật (xem mục 7).

### 6.1. Nếu bạn cài từ GitHub (cách A)

**Ubuntu**
```bash
cd /var/www/qlbh
sudo git pull
sudo composer install --no-dev --optimize-autoloader
sudo php artisan migrate --force
sudo php artisan optimize:clear
sudo chown -R www-data:www-data storage bootstrap/cache
```

**Windows (PowerShell)**
```powershell
cd C:\laragon\www\qlbh
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
```

> Nếu `git pull` báo có thay đổi cục bộ gây xung đột (ví dụ bạn đã sửa file), hãy liên hệ hỗ trợ kỹ thuật hoặc sao lưu file đã sửa trước khi xử lý.

### 6.2. Nếu bạn cài từ file zip (cách B)

1. Sao lưu thư mục dự án và file `.env`.
2. Giải nén bản mới đè lên thư mục cũ, **giữ lại** file `.env`, thư mục `storage` và `public/storage`.
3. Chạy 3 lệnh `composer install`, `migrate --force`, `optimize:clear` như ở trên.

### 6.3. Sau khi cập nhật

Trên điện thoại có thể cần **tải lại trang** (hoặc xóa dữ liệu trang) để lấy bản giao diện mới, do Service Worker lưu cache bản cũ.

---

## 7. Sao lưu và bảo trì

**Sao lưu dữ liệu:** vào *Cài đặt → Sao lưu và khôi phục* trong ứng dụng. Bật **Sao lưu tự động** thì cần cron (Ubuntu) hoặc Scheduled Task (Windows) đã cài ở trên, và máy phải đang bật vào giờ sao lưu.

**Camera cửa / nhận diện khuôn mặt** (tùy chọn): cấu hình trong Cài đặt. Trên Ubuntu, nền tảng camera chạy bằng `docker compose up -d --build` trong thư mục `deploy/camera-platform` (thư mục này **không nằm** trong gói này).

---

## 8. Xử lý lỗi thường gặp

| Hiện tượng | Nguyên nhân / cách xử lý |
|---|---|
| Lỗi 500 ngay khi mở `/setup` (Ubuntu) | Thiếu quyền ghi. Chạy `sudo chown -R www-data:www-data storage bootstrap/cache .env`. Xem log: `storage/logs/laravel.log` và `/var/log/nginx/error.log`. |
| Lỗi 500 sau khi bấm **Khởi tạo** | Bản mới đã sửa (tự chuyển sang trang thành công). Nếu vẫn gặp, tải lại trang: nếu vào được trang đăng nhập thì cài đặt đã xong. Gửi vài dòng cuối của `storage/logs/laravel.log` cho hỗ trợ kỹ thuật. |
| 502 Bad Gateway | PHP-FPM chưa chạy hoặc sai socket. Kiểm tra `sudo systemctl status php8.4-fpm`; socket phải khớp `fastcgi_pass unix:/run/php/php8.4-fpm.sock`. |
| 404 ở mọi trang ngoài `/` | nginx chưa trỏ về `setup.php` (xem mục 2.2) hoặc Apache chưa bật `mod_rewrite`. |
| 419 Page Expired | Giờ máy chủ lệch, hoặc mở bằng 2 địa chỉ khác nhau (IP và tên miền). Dùng một địa chỉ nhất quán và xóa cookie. |
| Trang setup báo thiếu `pdo_mysql` | Ubuntu: `sudo apt install php8.4-mysql && sudo systemctl restart php8.4-fpm`. Windows: bật `extension=pdo_mysql` trong `php.ini`. |
| "Không thể kết nối… Connection refused" | MySQL chưa chạy hoặc sai cổng. Laragon: bấm **Start All** và nhập đúng cổng MySQL (3306 hoặc 3307…). Ubuntu: `sudo systemctl start mariadb`. |
| "Access denied" | Sai tên người dùng hoặc mật khẩu database. |
| "Database đã có bảng" | Trang setup không ghi đè dữ liệu. Dùng database trống hoặc đặt tên khác. |
| `git clone` báo "Authentication failed" | Kho riêng tư cần token. Làm theo mục 1, phần A2 (dán token vào ô *Password*). |
| `git clone` báo "git is not recognized" (Windows) | Chưa cài Git hoặc chưa mở lại PowerShell sau khi cài. |
| Camera không mở / báo cần HTTPS | Dùng `https://`, đã cài chứng chỉ gốc (mục 5), cho phép quyền camera trong trình duyệt. |
| Sao lưu tự động không chạy | Kiểm tra cron: `sudo crontab -u www-data -l` (Ubuntu) hoặc Task Scheduler "QLBH Laravel Scheduler" (Windows); bật **Sao lưu tự động** trong Cài đặt. |
| Điện thoại hiện trang offline sau khi máy chủ khởi động lại | Chờ máy chủ chạy xong rồi tải lại. Nếu vẫn lỗi, xóa dữ liệu trang web trong trình duyệt (Service Worker đang giữ bản cũ). |

---

Cần hỗ trợ thêm? Liên hệ **Đức Hòa Computer** – ĐT/Zalo: 0906064789 – fb.com/duchoait