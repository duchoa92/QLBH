# Hướng dẫn cài đặt QLBH (Ubuntu và Windows)

Mục tiêu: cài xong chỉ cần **chạy 1 script, rồi mở trang `/setup`**. Phần nào script không làm được (cần bạn thao tác) được ghi rõ.

## 0. Tổng quan

| Việc | Ubuntu | Windows (Laragon) |
|---|---|---|
| PHP 8.4 + extension, web server, Composer | Tự động | Tự động (cài Laragon qua winget) |
| Thư viện PHP (`composer install`), `.env`, `APP_KEY` | Tự động | Tự động |
| Quyền thư mục, `storage:link` | Tự động | Tự động |
| Lịch chạy nền (sao lưu tự động, nhắc việc) | Tự động (cron) | Tự động (Scheduled Task) |
| Mở cổng tường lửa | Tự động (ufw nếu đang bật) | Tự động (Windows Firewall) |
| Cài MariaDB + tạo user cho trang setup | Tự động (`--with-mariadb`) | Có sẵn trong Laragon |
| Chứng chỉ HTTPS nội bộ | Tự động (`--https`) | Tự động tạo, bạn bật SSL trong Laragon |
| Tạo database, bảng, tài khoản quản trị | Trang `/setup` | Trang `/setup` |
| Cài chứng chỉ gốc lên điện thoại | **Bạn làm** (mục 4) | **Bạn làm** (mục 4) |
| Đặt Document Root trong Laragon | – | **Bạn làm** (1 thao tác) |

Yêu cầu chung: PHP 8.2 trở lên (khuyến nghị 8.4), MySQL/MariaDB (hoặc PostgreSQL/SQLite), extension `curl fileinfo gd intl mbstring openssl pdo_* zip xml`. Giao diện đã build sẵn trong `public/build`, chỉ cần Node.js 22 nếu bạn sửa giao diện.

---

## 1. Ubuntu 22.04 / 24.04

### 1.1. Cài tự động (khuyến nghị)

1. Chép mã nguồn lên máy chủ, ví dụ `/var/www/qlbh`:
   ```bash
   sudo mkdir -p /var/www && sudo unzip qlbh.zip -d /var/www/qlbh
   cd /var/www/qlbh
   ```
2. Chạy script (bằng root):
   ```bash
   sudo bash scripts/install-ubuntu.sh --with-mariadb --https
   ```
   Tuỳ chọn:
   - `--with-mariadb`: cài MariaDB và tạo user `qlbh` được quyền tạo database tên bắt đầu bằng `qlbh`. Mật khẩu lưu ở `/root/qlbh-db-credentials.txt`.
   - `--https`: tạo CA nội bộ và chứng chỉ cho IP máy chủ, nginx lắng nghe cổng 443 (cần cho camera điện thoại).
   - `--server-name qlbh.local`: dùng tên miền/tên máy thay vì chỉ IP.
   - `--php-version 8.4`: mặc định 8.4 (tự thêm PPA `ondrej/php` nếu Ubuntu chưa có).
   - `--no-nginx`: bỏ qua nginx (bạn tự cấu hình Apache/nginx).
   - `--no-build`: không build giao diện.
3. Mở `https://<IP-máy-chủ>/setup` (hoặc `http://` nếu không dùng `--https`) và làm theo mục 3.

Script chạy lại nhiều lần vẫn an toàn: không ghi đè `.env`, không tạo lại database.

### 1.2. Các bước thủ công tương ứng (khi cần hiểu hoặc tự làm)

```bash
# 1. PHP 8.4 + extension
sudo add-apt-repository -y ppa:ondrej/php && sudo apt update
sudo apt install -y php8.4-{cli,fpm,common,mbstring,xml,curl,zip,gd,intl,bcmath,sqlite3,mysql,pgsql} nginx mariadb-server unzip git cron
# 2. Composer: https://getcomposer.org/download/
# 3. Thư viện và .env
cd /var/www/qlbh
composer install --no-dev --optimize-autoloader
cp -n .env.example .env && php artisan key:generate
sed -i 's/^APP_ENV=.*/APP_ENV=production/; s/^APP_DEBUG=.*/APP_DEBUG=false/' .env
# 4. Quyền
sudo chown -R www-data:www-data storage bootstrap/cache database .env
sudo chmod -R ug+rwX storage bootstrap/cache database
php artisan storage:link
# 5. Cron cho user www-data
( sudo crontab -u www-data -l 2>/dev/null; echo "* * * * * cd /var/www/qlbh && /usr/bin/php8.4 artisan schedule:run >> /dev/null 2>&1" ) | sudo crontab -u www-data -
```

Cấu hình nginx cần lưu ý: **cổng vào của dự án là `public/setup.php`** (giống `public/.htaccess`), không phải `index.php`:
```nginx
index setup.php index.php;
location / { try_files $uri $uri/ /setup.php?$query_string; }
```
Nếu dùng Apache: bật `mod_rewrite`, đặt `DocumentRoot` là thư mục `public`, `AllowOverride All`.

---

## 2. Windows 10/11 (Laragon)

### 2.1. Cài tự động

1. Giải nén mã nguồn vào `C:\laragon\www\qlbh` (nếu chưa có Laragon, script sẽ tự cài).
2. Mở **PowerShell bằng Run as administrator**, chạy:
   ```powershell
   cd C:\laragon\www\qlbh
   powershell -ExecutionPolicy Bypass -File scripts\install-windows.ps1
   ```
   Script sẽ: cài Laragon (winget) nếu chưa có → kiểm tra PHP ≥ 8.2 → bật các extension thiếu trong `php.ini` → `composer install` → tạo `.env` + `APP_KEY` → `storage:link` → tạo Scheduled Task chạy `schedule:run` mỗi phút → mở cổng 80/443 cho mạng nội bộ.
3. Làm 2 việc cuối bằng tay (script cũng in nhắc):
   - Laragon → **Start All** (Apache + MySQL). Nếu script báo đã sửa `php.ini` thì Stop All rồi Start All.
   - Laragon → Menu → Preferences → General → **Document Root** = `C:\laragon\www\qlbh\public` (để mở được bằng IP máy, ví dụ `http://192.168.0.103`).
4. Mở `http://localhost/setup` và làm theo mục 3.

Nếu Laragon cài sẵn PHP thấp hơn 8.2: Laragon → Menu → PHP → Version → chọn bản 8.2+ (hoặc thêm PHP 8.4 vào `C:\laragon\bin\php`).

### 2.2. HTTPS cho camera (Windows)

Camera điện thoại chỉ chạy trên HTTPS. Chạy (PowerShell quản trị):
```powershell
powershell -ExecutionPolicy Bypass -File scripts\windows-https.ps1
```
Script tạo CA nội bộ, chứng chỉ cho IP máy, vhost Apache ở `etc\apache2\sites-enabled\00-qlbh-ssl.conf` và chép chứng chỉ gốc vào `public\certs\rootCA.crt`. Sau đó: Laragon → Menu → Apache → SSL → **Enabled** → Stop All / Start All. Script này chưa kiểm thử trên mọi phiên bản Laragon; nếu Apache không khởi động, xem `error.log` của Apache.

---

## 3. Hoàn tất bằng trang `/setup` (cả hai hệ điều hành)

1. **Bước kiểm tra máy chủ** (đầu trang): xem danh sách PHP, extension, quyền thư mục. Mục nào lỗi sẽ có lệnh sửa để copy. Bấm **Tự cấu hình** để tạo thư mục, `storage:link`… rồi xem lại.
2. **Kết nối cơ sở dữ liệu**: MySQL/MariaDB (`127.0.0.1`, cổng 3306), tên database mới, user/mật khẩu. Trang sẽ tự tạo database nếu user có quyền.
   - Ubuntu với `--with-mariadb`: dùng user `qlbh` và mật khẩu trong `/root/qlbh-db-credentials.txt`; tên database phải bắt đầu bằng `qlbh` (ví dụ `qlbh`).
   - Laragon: user `root`, mật khẩu để trống (mặc định).
   - Hoặc chọn SQLite nếu chỉ dùng thử.
3. **Tài khoản quản trị**: tên đăng nhập, họ tên, số điện thoại, email, mật khẩu (≥ 8 ký tự).
4. **Xác nhận**: trên Linux có ô *Tự cài lịch chạy nền (cron)*. Bấm **Khởi tạo website** → tự chuyển sang trang đăng nhập.

Trang setup chỉ chạy một lần: sau khi xong, `/setup` tự chuyển về đăng nhập.

---

## 4. Cài chứng chỉ gốc trên điện thoại (để camera quét mã hoạt động)

Tải `http://<IP>/certs/rootCA.crt` trên điện thoại, rồi:

- **Android**: Cài đặt → Bảo mật → Mã hoá & thông tin xác thực → Cài đặt chứng chỉ → **Chứng chỉ CA** → chọn file vừa tải. (Tên menu khác nhau tuỳ hãng.)
- **iPhone (Safari)**: mở link trên Safari → Cho phép tải hồ sơ → Cài đặt → *Đã tải hồ sơ* → Cài đặt. Sau đó Cài đặt → Cài đặt chung → Giới thiệu → **Cài đặt tin cậy chứng nhận** → bật cho "QLBH Local CA".
- **Máy tính Windows/Chrome**: mở file `.crt` → Install Certificate → Local Machine → *Trusted Root Certification Authorities*.

Sau khi cài, mở `https://<IP>` không còn cảnh báo và camera sẽ xin quyền truy cập.

Chứng chỉ máy chủ do script cấp có hạn 825 ngày. Hết hạn thì chạy lại script (`--https` trên Ubuntu, `windows-https.ps1` trên Windows); điện thoại không cần cài lại chứng chỉ gốc (CA hạn 10 năm).

---

## 5. Cập nhật, sao lưu, bảo trì

**Cập nhật phiên bản mới**
```bash
cd /var/www/qlbh            # hoặc C:\laragon\www\qlbh
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
```
Sau khi cập nhật giao diện, trên điện thoại có thể cần tải lại trang (hoặc xoá dữ liệu trang) để lấy bản JS mới do Service Worker lưu cache.

**Sao lưu**: dùng *Cài đặt → Sao lưu và khôi phục* trong ứng dụng (bật *Sao lưu tự động* thì cần cron/Scheduled Task ở trên). Máy phải đang bật vào giờ sao lưu.

**Camera cửa / nhận diện khuôn mặt** (tuỳ chọn): cấu hình trong Cài đặt. Trên Ubuntu nền tảng camera chạy bằng `docker compose up -d --build` theo thư mục `deploy/camera-platform` (không nằm trong gói này).

---

## 6. Xử lý lỗi thường gặp

| Hiện tượng | Nguyên nhân / cách xử lý |
|---|---|
| 500 ngay khi mở `/setup` (Ubuntu) | Thiếu quyền ghi: `sudo chown -R www-data:www-data storage bootstrap/cache .env`. Xem `storage/logs/laravel.log` và `/var/log/nginx/error.log`. |
| 502 Bad Gateway | PHP-FPM chưa chạy hoặc sai socket: `sudo systemctl status php8.4-fpm`; socket phải khớp `fastcgi_pass unix:/run/php/php8.4-fpm.sock`. |
| 404 ở mọi trang ngoài `/` | nginx chưa trỏ về `setup.php` (xem mục 1.2) hoặc Apache chưa bật `mod_rewrite`. |
| 419 Page Expired | Giờ máy chủ lệch, hoặc mở bằng 2 địa chỉ khác nhau (IP và tên miền). Dùng một địa chỉ nhất quán, xoá cookie. |
| Trang setup báo thiếu `pdo_mysql` | `sudo apt install php8.4-mysql && sudo systemctl restart php8.4-fpm` (Windows: bật `extension=pdo_mysql` trong `php.ini`). |
| "Database đã có bảng" | Trang setup không ghi đè dữ liệu. Dùng database trống hoặc tên khác. |
| Camera không mở / báo cần HTTPS | Dùng `https://`, đã cài chứng chỉ gốc (mục 4), cho phép quyền camera trong trình duyệt. |
| Sao lưu tự động không chạy | Kiểm tra cron (`sudo crontab -u www-data -l`) hoặc Task Scheduler "QLBH Laravel Scheduler"; bật *Sao lưu tự động* trong Cài đặt. |
| Điện thoại hiện trang offline sau khi máy chủ khởi động lại | Chờ máy chủ chạy xong rồi tải lại; nếu vẫn lỗi, xoá dữ liệu trang của trang web trong trình duyệt (Service Worker đang giữ bản cũ). |
