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
Script tự cài PHP, nginx, Composer, thư viện, quyền thư mục, lịch chạy nền, tường lửa, **Docker (camera)**, công cụ giám sát ổ đĩa **và tạo tài khoản database** đó. Các dịch vụ (nginx, PHP, MySQL, cron, Docker) được bật **tự chạy khi bật máy**. Nếu là **laptop**, script còn tắt chế độ ngủ và bỏ qua thao tác gập màn hình để website luôn chạy (nên cắm sạc thường xuyên). Chạy lại nhiều lần vẫn an toàn (chạy lại với mật khẩu mới sẽ đổi mật khẩu).

| Tùy chọn | Tác dụng |
|---|---|
| `--https` | Bật HTTPS (**cần để dùng camera điện thoại**) |
| `--db-user admin` | Tên tài khoản database (chữ, số, gạch dưới) |
| `--db-pass 'MatKhau123'` | Mật khẩu database. Để trong dấu nháy đơn `' '` |
| `--static-ip` | **Cố định IP** máy chủ (mặc định giữ IP hiện tại). Đặt IP khác: `--ip 192.168.100.124/24 --gateway 192.168.100.1` |
| `--cloudflare-token <TOKEN>` | Cài **Cloudflare Tunnel** để truy cập từ internet (xem mục G) |
| `--tailscale` | Cài **Tailscale** để chủ cửa hàng truy cập từ xa an toàn (xem mục G) |
| `--no-camera` | **Không** cài Docker / nền tảng camera. Mặc định script **cài Docker** và chạy `deploy/camera-platform` (nếu thư mục này có trong dự án) |
| `--with-mariadb` | Cài thêm MariaDB nếu máy **chưa có** MySQL/MariaDB |
| `--server-name qlbh.local` | Dùng tên miền thay vì IP |

- Tài khoản này chỉ được tạo/xóa database có **tên bắt đầu bằng `qlbh`** (ví dụ `qlbh`), không đụng được database khác.
- Thông tin được lưu ở `/root/qlbh-db-credentials.txt` (xem lại bằng `sudo cat /root/qlbh-db-credentials.txt`).
- Trang `/setup` sẽ **tự điền sẵn** tên tài khoản này, và ô mật khẩu hiện `••••••••  (đã đặt sẵn)`: bạn **để trống** ô đó là được. Mật khẩu không bị gửi ra trình duyệt, và được xóa khỏi file cấu hình tạm sau khi cài xong.
- Không dùng `--db-user` và có `--with-mariadb`: script tự tạo user `qlbh` với mật khẩu ngẫu nhiên.

### Tự chạy khi bật máy và cảnh báo ổ đĩa

- **Tự chạy khi bật máy:** script bật khởi động cùng hệ thống cho nginx, PHP-FPM, MySQL/MariaDB, cron và Docker (các container camera được đặt tự chạy lại). Bật máy lên là website dùng được, không cần gõ lệnh.
- **Cảnh báo ổ đĩa ngay trên màn hình:** cứ 15 phút máy chủ kiểm tra ổ đĩa một lần. Thanh cảnh báo hiện ở đầu các trang quản trị (người có quyền *Quản lý sao lưu* mới thấy), **nội dung lỗi hiện trước** (SMART báo hỏng, lỗi đọc/ghi ổ đĩa, ổ bị chuyển sang chỉ đọc), **dung lượng hiện bên dưới dạng thanh đồ họa** (xanh / vàng từ 85% / đỏ từ 95% hoặc còn dưới 2 GB) kèm nút **Sao lưu ngay**. Bấm **X** để thu gọn vào nút chức năng nổi. Máy ảo thường không đọc được SMART, khi đó vẫn cảnh báo theo dung lượng và lỗi hệ thống.
- **Cảnh báo lịch chạy nền:** nếu cron ngừng chạy hơn 5 phút, website hiện thanh đỏ "Lịch chạy nền chưa hoạt động: sao lưu tự động KHÔNG chạy".

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

Có 4 bước (Database → Quản trị → Cửa hàng → Xác nhận). Trang chỉ chạy một lần, xong sẽ chuyển về trang đăng nhập.

**1. Cơ sở dữ liệu**

| Môi trường | Máy chủ | Cổng | Tài khoản | Mật khẩu | Tên database |
|---|---|---|---|---|---|
| Ubuntu (MySQL/MariaDB) | `127.0.0.1` | `3306` | tên bạn đặt ở `--db-user` (ví dụ `admin`) | mật khẩu ở `--db-pass` | `qlbh` |
| Laragon (Windows) | `127.0.0.1` | `3306` (hoặc `3307` nếu MySQL dùng cổng đó) | `root` | để trống | `qlbh` |
| SQLite | – | – | – | – | `qlbh` |

Nếu đã chạy script với `--db-user`, hai ô **Tên người dùng** và **Mật khẩu** đã có sẵn, bạn không cần gõ lại. Bấm **Kiểm tra kết nối** (phải báo xanh "Kết nối thành công") → **Tiếp tục**. Database phải **trống**; trang không ghi đè dữ liệu đã có.

**2. Quản trị viên:** tên đăng nhập, họ tên, số điện thoại, email, mật khẩu (từ 8 ký tự). Các ô họ tên, số điện thoại, email có chữ gợi ý mờ (Đức Hòa, 0906064789, duchoa.92@gmail.com): bạn nhập thông tin thật của mình. Ghi nhớ tài khoản này.

**3. Cửa hàng và hệ thống** (cài đặt luôn, sau này vẫn sửa được trong *Cài đặt*): **tên cửa hàng**, số điện thoại, địa chỉ (in trên hóa đơn), **ngôn ngữ** (Tiếng Việt / English), **múi giờ**, **định dạng ngày** (có hiện ví dụ ngày hôm nay), **định dạng số tiền** và **ký hiệu tiền tệ** (mặc định ₫). Cũng ở bước này có công tắc **Sao lưu tự động hằng ngày** (mặc định BẬT, 02:00): bật ngay từ khi cài, không phải vào Cài đặt.

**4. Xác nhận:**
- Ô **Lịch chạy nền (cron)** là công tắc có hiện rõ **✓ BẬT** (xanh) hoặc **✗ TẮT** (đỏ). Cron là "đồng hồ" chạy mỗi phút để website tự làm: **sao lưu tự động** theo giờ hẹn, **nhắc việc** lúc 08:00 mỗi ngày, **nhắc công nợ** sáng thứ Hai, **dọn dữ liệu cũ** hằng đêm. **Nên để BẬT**; nếu TẮT, các việc đó sẽ không tự chạy. Nếu script đã cài sẵn thì trang hiện "ĐÃ BẬT SẴN".
- Bấm **Khởi tạo**. Khi thấy **"Thiết lập thành công"**, trang hiện đầy đủ: địa chỉ đăng nhập, tài khoản và mật khẩu quản trị, thông tin cửa hàng và hệ thống, cấu hình database (máy chủ, tên, tài khoản, mật khẩu), tình trạng lịch chạy nền, các dịch vụ tự chạy khi bật máy và dung lượng ổ đĩa. **Thông tin này chỉ hiện một lần**: bấm **Tải file (.txt)** hoặc **Sao chép** để lưu lại, rồi bấm **Đăng nhập**.
- Cuối trang có mục **"Cài chứng chỉ tin cậy"** (khi dùng HTTPS) kèm nút tải và hướng dẫn cho Android, iPhone, Windows.

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
Windows: bỏ `sudo`, dùng thư mục `C:\laragon\www\qlbh`. Windows: script cũng tạo tác vụ để Laragon tự mở khi đăng nhập Windows; trong Laragon → Preferences → General hãy tick *Start All automatically*.

Sau khi cập nhật, trên điện thoại tải lại trang (hoặc xóa dữ liệu trang) để lấy giao diện mới.

> `git pull` chỉ lấy được những gì đã được **push** lên GitHub. Giao diện nằm trong `public/build`, nên sau khi sửa file `.vue` phải chạy `npm run build` rồi đưa cả `public/build` lên GitHub.

---


### Build giao diện ngay trên server (không cần build ở máy phát triển)

Cập nhật bằng **một lệnh**, server tự lấy mã mới, cài thư viện, cập nhật database, **build giao diện** và xóa cache:
```bash
cd /var/www/qlbh
sudo bash scripts/update.sh
```
- Lần đầu server tự cài Node.js 22. Build mất khoảng 1–5 phút; máy ít RAM (dưới 1,5 GB) script tạo thêm swap 2 GB tạm thời.
- Đã đẩy sẵn `public/build` lên GitHub thì dùng `sudo bash scripts/update.sh --no-build` cho nhanh.
- Chọn **một** cách để tránh xung đột: (a) build trên server và để `/public/build` trong `.gitignore`, hoặc (b) build ở máy phát triển và đẩy `public/build` lên GitHub. Script xử lý được cả hai.
- Muốn build lại khi cài: `sudo bash scripts/install-ubuntu.sh --rebuild`.

## G. Mạng: IP tĩnh và truy cập từ internet

### G1. Cố định IP cho laptop làm máy chủ

Nếu IP đổi (khi khởi động lại máy hoặc router), các máy khác sẽ không vào được. Cố định IP bằng **một** trong hai cách:

- **Cách 1 (khuyến nghị): dùng script.** Giữ nguyên IP hiện tại nhưng chuyển sang IP tĩnh:
  ```bash
  cd /var/www/qlbh
  sudo bash scripts/set-static-ip.sh
  ```
  Muốn đặt IP khác (cùng dải mạng, chưa có thiết bị nào dùng):
  ```bash
  sudo bash scripts/set-static-ip.sh --ip 192.168.100.124/24 --gateway 192.168.100.1 --dns 8.8.8.8,1.1.1.1
  ```
  Script tự kiểm tra mạng sau khi đổi và **tự quay lại cấu hình cũ nếu mất kết nối**. Muốn quay về tự động nhận IP: `sudo bash scripts/set-static-ip.sh --revert`.
- **Cách 2: trên router** đặt "DHCP reservation" / "Gắn IP theo địa chỉ MAC" cho laptop. Nên làm thêm cả khi đã dùng cách 1, để router không cấp IP đó cho thiết bị khác.

**Ngay khi chạy script cài đặt**, nếu IP chưa cố định thì script hỏi:
```
Cố định IP bây giờ? [Y/n]:
Nhập IP mới cho máy chủ [Enter = giữ 192.168.100.124]:
Router / gateway [Enter = 192.168.100.1]:
DNS, cách nhau bằng dấu phẩy [Enter = 192.168.100.1,8.8.8.8,1.1.1.1]:
Áp dụng cấu hình này? [Y/n]:
```
Bạn chỉ cần nhập **IP** (ví dụ `192.168.100.168`, không cần gõ `/24`); các mục còn lại bấm **Enter** để dùng giá trị hiện tại. Script kiểm tra IP hợp lệ, đổi xong tự kiểm tra mạng và **tự quay lại cấu hình cũ nếu mất kết nối**. Nếu đang điều khiển máy qua SSH, đổi IP sẽ ngắt kết nối: kết nối lại bằng IP mới. Muốn bỏ qua bước này: thêm `--no-static-ip`. Chạy không hỏi: `--static-ip --ip 192.168.100.168/24 --gateway 192.168.100.1`.
**Sau khi đổi IP**, nếu dùng HTTPS hãy chạy lại `sudo bash scripts/install-ubuntu.sh --https` để cấp lại chứng chỉ cho IP mới (điện thoại không cần cài lại chứng chỉ gốc).

### G2. Truy cập từ internet (ngoài cửa hàng)

Có 2 cách, **không cần mở cổng trên router** (an toàn hơn mở cổng trực tiếp):

**Cách A. Cloudflare Tunnel** (có tên miền riêng, HTTPS hợp lệ, điện thoại **không cần cài chứng chỉ**):
1. Có tài khoản Cloudflare (miễn phí) và một tên miền đã thêm vào Cloudflare.
2. Zero Trust → **Networks → Tunnels → Create a tunnel** → chọn *Cloudflared* → đặt tên → sao chép **token** (chuỗi dài sau `service install`).
3. Trên server chạy (thay `<TOKEN>`):
   ```bash
   cd /var/www/qlbh
   sudo bash scripts/install-ubuntu.sh --https --db-user admin --cloudflare-token '<TOKEN>'
   ```
4. Trong tunnel, tab **Public hostname** → thêm: tên miền (ví dụ `shop.tenmien.com`), **Service = HTTPS**, **URL = `localhost:443`**, mở *Additional settings → TLS* bật **No TLS Verify**. (Không dùng `--https` thì chọn HTTP và `localhost:80`.)
5. Mở `https://shop.tenmien.com` từ bất kỳ đâu. Tunnel tự chạy khi bật máy.

**Cách B. Tailscale** (mạng riêng, chỉ thiết bị của bạn vào được, không cần tên miền):
```bash
sudo bash scripts/install-ubuntu.sh --https --db-user admin --tailscale
```
Mở đường link hiện ra để đăng nhập, rồi cài ứng dụng Tailscale trên điện thoại/máy tính cùng tài khoản, truy cập `https://<địa chỉ Tailscale của máy chủ>`.

> **An toàn:** khi để website ra internet, hãy đặt mật khẩu quản trị mạnh, không dùng tên `admin` với mật khẩu dễ đoán, và nên bật **Cloudflare Access** (đăng nhập bằng email) trước website nếu dùng Cloudflare.

## H. Kiểm tra nhanh khi có sự cố (sao lưu tự động, mạng…)

```bash
cd /var/www/qlbh
sudo bash scripts/qlbh-doctor.sh
```
Lệnh này kiểm tra và báo ✓ / ✗ cho: IP tĩnh, internet và DNS, truy cập từ xa, các dịch vụ (đang chạy và tự chạy khi bật máy), **lịch chạy nền (cron)**, **sao lưu tự động** (đang bật không, lần gần nhất, lỗi gần nhất, file mới nhất), ổ đĩa.

**Sao lưu tự động không chạy thì kiểm tra theo thứ tự:**
1. Công tắc **Sao lưu tự động** trong *Cài đặt → Sao lưu* phải **BẬT** (cài mới đã bật sẵn).
2. **Lịch chạy nền (cron) phải đang chạy**: nếu không, website hiện thanh đỏ "Lịch chạy nền chưa hoạt động". Chạy lại `sudo bash scripts/install-ubuntu.sh`.
3. Máy phải **đang bật** vào giờ sao lưu (mặc định 02:00). Máy tắt/ngủ vào giờ đó thì bản sao lưu được tạo ở lần chạy kế tiếp sau giờ hẹn trong ngày.
4. Thư mục `storage` phải thuộc `www-data`: `sudo chown -R www-data:www-data /var/www/qlbh/storage`.
5. Xem lỗi gần nhất ở trang *Sao lưu* hoặc trong `storage/logs/laravel.log`.

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
| Sao lưu tự động không chạy | Chạy `sudo bash scripts/qlbh-doctor.sh` và xem mục H. |
| IP máy chủ bị đổi, không vào được | Cố định IP: mục G1. |
| Camera không mở | Dùng `https://`, đã cài chứng chỉ (mục D), cho phép quyền camera. |
| Điện thoại hiện trang offline | Chờ máy chủ chạy xong rồi tải lại; nếu vẫn lỗi, xóa dữ liệu trang web. |
