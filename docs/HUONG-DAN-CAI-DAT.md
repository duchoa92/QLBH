# Hướng dẫn cài đặt QLBH

**3 việc:** (1) tải mã nguồn → (2) chạy script cài đặt → (3) mở trang `/setup` và điền thông tin.

> Phát triển bởi Đức Hòa Computer – Vạn Xuân, Hưng Yên. Hỗ trợ: ĐT/Zalo **0906064789**, fb.com/duchoait

**Lưu ý khi gõ lệnh:** dán **từng lệnh một**, chờ chạy xong rồi dán lệnh tiếp. Không thêm dấu `\` ở cuối dòng.

---

## A. Cài trên Ubuntu 22.04 / 24.04

### Bước 1. Tải mã nguồn từ GitHub
```bash
sudo apt update && sudo apt install -y git unzip
```
```bash
sudo git clone https://github.com/duchoa92/QLBH.git /var/www/qlbh
```
```bash
cd /var/www/qlbh
```
Gõ `ls`, phải thấy `app`, `public`, `scripts`, `artisan`.

> Kho riêng tư: khi hỏi *Password*, dán **Personal Access Token** (GitHub → Settings → Developer settings → Personal access tokens → tick quyền `repo`), không dùng mật khẩu GitHub.
> Không dùng Git: tải file zip, rồi `sudo unzip qlbh.zip -d /var/www/qlbh`.

### Bước 2. Chạy script cài đặt (kèm tạo tài khoản database)

Chạy **một lệnh duy nhất**, đặt luôn tên và mật khẩu database (đổi `admin` và `MatKhau123` theo ý bạn):
```bash
sudo bash scripts/install-ubuntu.sh --https --db-user admin --db-pass 'MatKhau123'
```
Muốn không để lộ mật khẩu trong lệnh, bỏ `--db-pass`, script sẽ hỏi bạn nhập (không hiện ký tự):
```bash
sudo bash scripts/install-ubuntu.sh --https --db-user admin
```
Script tự cài PHP, nginx, Composer, thư viện, quyền thư mục, lịch chạy nền, tường lửa **và tạo tài khoản database** đó. Chạy lại nhiều lần vẫn an toàn (chạy lại với mật khẩu mới sẽ đổi mật khẩu).

| Tùy chọn | Tác dụng |
|---|---|
| `--https` | Bật HTTPS (**cần để dùng camera điện thoại**) |
| `--db-user admin` | Tên tài khoản database (chữ, số, gạch dưới) |
| `--db-pass 'MatKhau123'` | Mật khẩu database. Để trong dấu nháy đơn `' '` |
| `--with-mariadb` | Cài thêm MariaDB nếu máy **chưa có** MySQL/MariaDB |
| `--server-name qlbh.local` | Dùng tên miền thay vì IP |

- Tài khoản này chỉ được tạo/xóa database có **tên bắt đầu bằng `qlbh`** (ví dụ `qlbh`), không đụng được database khác.
- Thông tin được lưu ở `/root/qlbh-db-credentials.txt` (xem lại bằng `sudo cat /root/qlbh-db-credentials.txt`).
- Trang `/setup` sẽ **tự điền sẵn** tên tài khoản này, và ô mật khẩu hiện `••••••••  (đã đặt sẵn)`: bạn **để trống** ô đó là được. Mật khẩu không bị gửi ra trình duyệt, và được xóa khỏi file cấu hình tạm sau khi cài xong.
- Không dùng `--db-user` và có `--with-mariadb`: script tự tạo user `qlbh` với mật khẩu ngẫu nhiên.

### Bước 3. Tạo tài khoản database bằng tay (chỉ khi không dùng `--db-user`)

Trên Ubuntu, user `root` của MySQL/MariaDB **không đăng nhập được từ trang web** (lỗi *Access denied for user 'root'@'localhost'*). Tạo user riêng:
```bash
sudo mysql
```
Khi thấy `mysql>`, dán (đổi `MatKhauManh123`):
```sql
CREATE USER 'admin'@'localhost' IDENTIFIED BY 'MatKhauManh123';
GRANT ALL PRIVILEGES ON `qlbh%`.* TO 'admin'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

> Không muốn tạo user: ở trang setup chọn **SQLite** (không cần user, mật khẩu). Hợp với cửa hàng nhỏ, ít máy cùng dùng.

### Bước 4. Mở trang setup
Mở `https://<IP-máy-chủ>/setup` (hoặc `http://` nếu không dùng `--https`). Lần đầu trình duyệt báo "Không bảo mật": bấm **Nâng cao → Tiếp tục**. Sang phần **C**.

---

## B. Cài trên Windows 10/11 (Laragon)

1. Cài Git: mở PowerShell, chạy `winget install --id Git.Git -e`, rồi **đóng và mở lại** PowerShell.
2. Tải mã nguồn (hoặc giải nén `qlbh.zip` vào `C:\laragon\www\qlbh`):
   ```powershell
   git clone https://github.com/duchoa92/QLBH.git C:\laragon\www\qlbh
   ```
3. Mở **PowerShell bằng Run as administrator**, chạy:
   ```powershell
   cd C:\laragon\www\qlbh
   powershell -ExecutionPolicy Bypass -File scripts\install-windows.ps1
   ```
4. Mở **Laragon** → **Start All**. Nếu script báo đã sửa `php.ini` thì Stop All rồi Start All.
5. Laragon → Menu → **Preferences** → **General** → **Document Root** = `C:\laragon\www\qlbh\public` → Stop All → Start All.
6. Mở `http://localhost/setup`. Sang phần **C**. Với Laragon, database dùng user `root`, mật khẩu để trống.

**HTTPS cho camera:** chạy `powershell -ExecutionPolicy Bypass -File scripts\windows-https.ps1`, rồi Laragon → Menu → Apache → SSL → **Enabled** → Stop All → Start All.

---

## C. Điền trang `/setup`

Có 3 bước. Trang chỉ chạy một lần, xong sẽ chuyển về trang đăng nhập.

**1. Cơ sở dữ liệu**

| Môi trường | Máy chủ | Cổng | Tài khoản | Mật khẩu | Tên database |
|---|---|---|---|---|---|
| Ubuntu (MySQL/MariaDB) | `127.0.0.1` | `3306` | tên bạn đặt ở `--db-user` (ví dụ `admin`) | mật khẩu ở `--db-pass` | `qlbh` |
| Laragon (Windows) | `127.0.0.1` | `3306` (hoặc `3307` nếu MySQL dùng cổng đó) | `root` | để trống | `qlbh` |
| SQLite | – | – | – | – | `qlbh` |

Nếu đã chạy script với `--db-user`, hai ô **Tên người dùng** và **Mật khẩu** đã có sẵn, bạn không cần gõ lại. Bấm **Kiểm tra kết nối** (phải báo xanh "Kết nối thành công") → **Tiếp tục**. Database phải **trống**; trang không ghi đè dữ liệu đã có.

**2. Quản trị viên:** tên đăng nhập, họ tên, số điện thoại, email, mật khẩu (từ 8 ký tự). Các ô họ tên, số điện thoại, email có chữ gợi ý mờ (Đức Hòa, 0906064789, duchoa.92@gmail.com): bạn nhập thông tin thật của mình. Ghi nhớ tài khoản này.

**3. Xác nhận:** bấm **Khởi tạo**. Khi thấy "Thiết lập thành công" bấm **Đăng nhập**.

---

## D. Cài chứng chỉ trên điện thoại (để dùng camera)

Tải `http://<IP>/certs/rootCA.crt` trên điện thoại, rồi:

- **Android:** Cài đặt → Bảo mật → Cài đặt chứng chỉ → **Chứng chỉ CA** → chọn file vừa tải.
- **iPhone (Safari):** mở link → Cho phép → Cài đặt → *Đã tải hồ sơ* → Cài đặt. Sau đó Cài đặt → Cài đặt chung → Giới thiệu → **Cài đặt tin cậy chứng nhận** → bật *QLBH Local CA*.
- **Windows/Chrome:** mở file `.crt` → Install Certificate → Local Machine → *Trusted Root Certification Authorities*.

Xong mở `https://<IP>`, bấm **Cho phép** khi trình duyệt xin quyền camera.

---

## E. Cập nhật phiên bản mới

Sao lưu trước (Cài đặt → Sao lưu và khôi phục). Ubuntu, chạy từng lệnh:
```bash
cd /var/www/qlbh
```
```bash
sudo git pull
```
```bash
sudo composer install --no-dev --optimize-autoloader
```
```bash
sudo php artisan migrate --force
```
```bash
sudo php artisan optimize:clear
```
```bash
sudo chown -R www-data:www-data storage bootstrap/cache
```
Windows: bỏ `sudo`, dùng thư mục `C:\laragon\www\qlbh`.

Sau khi cập nhật, trên điện thoại tải lại trang (hoặc xóa dữ liệu trang) để lấy giao diện mới.

> `git pull` chỉ lấy được những gì đã được **push** lên GitHub. Giao diện nằm trong `public/build`, nên sau khi sửa file `.vue` phải chạy `npm run build` rồi đưa cả `public/build` lên GitHub.

---

## F. Lỗi thường gặp

| Hiện tượng | Cách xử lý |
|---|---|
| **Access denied for user 'root'@'localhost'** | Không dùng `root` trên Ubuntu. Chạy lại script với `--db-user` và `--db-pass` (bước A2) hoặc tạo user theo bước A3. |
| **Connection refused** | MySQL chưa chạy hoặc sai cổng. Ubuntu: `sudo systemctl start mysql` (hoặc `mariadb`). Laragon: Start All, nhập đúng cổng. |
| "Database đã có bảng" | Dùng database trống hoặc đặt tên khác. |
| Thiếu `pdo_mysql` | Ubuntu: `sudo apt install php8.4-mysql && sudo systemctl restart php8.4-fpm`. Windows: bật `extension=pdo_mysql` trong `php.ini`. |
| 500 khi mở `/setup` | `sudo chown -R www-data:www-data storage bootstrap/cache .env`; xem `storage/logs/laravel.log`. |
| 500 sau khi bấm Khởi tạo | Tải lại trang; vào được trang đăng nhập là cài đã xong. Gửi vài dòng cuối `laravel.log` cho hỗ trợ. |
| 502 Bad Gateway | `sudo systemctl status php8.4-fpm` (PHP-FPM chưa chạy). |
| 404 ở mọi trang ngoài `/` | nginx phải trỏ về `public/setup.php` (Apache: bật `mod_rewrite`). |
| 419 Page Expired | Chỉ dùng một địa chỉ (IP **hoặc** tên miền), xóa cookie. |
| `git clone`: "destination path already exists" | Thư mục đã có nội dung. Đã clone rồi thì dùng `git pull` (mục E). Muốn clone lại: `sudo rm -rf /var/www/qlbh`. |
| `git clone`: "Authentication failed" | Kho riêng tư, dùng token (bước A1). |
| Camera không mở | Dùng `https://`, đã cài chứng chỉ (mục D), cho phép quyền camera. |
| Sao lưu tự động không chạy | Kiểm tra `sudo crontab -u www-data -l` (Ubuntu) hoặc Task Scheduler *QLBH Laravel Scheduler* (Windows); máy phải đang bật. |
| Điện thoại hiện trang offline | Chờ máy chủ chạy xong rồi tải lại; nếu vẫn lỗi, xóa dữ liệu trang web. |
