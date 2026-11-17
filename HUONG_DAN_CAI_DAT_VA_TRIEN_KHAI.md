# Hướng dẫn cài đặt và triển khai QLBH

Tài liệu này tổng hợp cách cài toàn bộ dự án Laravel/Vue, database, build giao diện, lịch tác vụ và worker camera cửa khi cài máy mới, chuyển máy hoặc đưa lên server.

## 1. Thành phần cần cài

| Thành phần | Dùng cho | Yêu cầu của dự án |
|---|---|---|
| PHP CLI và Composer 2 | Laravel, migration, lệnh quản trị | PHP 8.2 trở lên. Bật ctype, curl, fileinfo, gd, mbstring, openssl, PDO, tokenizer, xml, xmlreader, xmlwriter, zip. Bật pdo_mysql, pdo_sqlite hoặc pdo_pgsql theo database. |
| MySQL/MariaDB, PostgreSQL hoặc SQLite | Lưu dữ liệu | Trình cài đặt web hỗ trợ MySQL, PostgreSQL và SQLite. Production nên dùng database server. |
| Node.js và npm | Cài thư viện frontend, Vite, worker camera | Vite trong lockfile cần Node ^20.19.0 hoặc >=22.12.0. |
| Apache hoặc Nginx + PHP-FPM | Phục vụ website | Document root phải là thư mục public của repo. |
| FFmpeg (tuỳ chọn) | Camera RTSP | Chỉ cần nếu camera dùng rtsp:// hoặc rtsps://; ảnh chụp HTTP không cần. |

Dùng các lockfile có sẵn composer.lock, package-lock.json và door-worker/package-lock.json. Không chép vendor/ hay node_modules/ từ máy cũ; cài lại trên máy đích.

## 2. Cài dependency và build

### Windows / Laragon

Mở PowerShell:

    cd C:\laragon\www\QLBH
    composer install
    npm ci
    node scripts/copy-face-models.mjs
    npm run build

Kiểm tra PHP CLI, Composer và Node:

    php -v
    composer --version
    node -v
    npm -v

Laragon cần Apache, PHP CLI và MySQL/MariaDB. Bật các PHP extension ở bảng trên trong đúng php.ini của cả CLI và Apache.

### Ubuntu/Debian server

Cài PHP 8.2+, Composer 2, Node.js theo yêu cầu Vite, Apache hoặc Nginx, database và các PHP extension. Gói PHP thường dùng trên Debian/Ubuntu:

    sudo apt update
    sudo apt install php-cli php-fpm php-mysql php-sqlite3 php-pgsql \
      php-curl php-mbstring php-xml php-zip php-gd unzip git

Sau khi đưa mã nguồn vào /var/www/qlbh:

    cd /var/www/qlbh
    composer install --no-dev --optimize-autoloader
    npm ci
    node scripts/copy-face-models.mjs
    npm run build

Có thể build assets trên CI/máy build rồi chuyển public/build/ và public/models/human/ lên server. PHP production không cần node_modules/ để phục vụ assets đã build.

## 3. Cài đặt QLBH lần đầu

### Tạo môi trường

Tại thư mục gốc repo, tạo .env từ mẫu:

PowerShell:

    Copy-Item .env.example .env

Linux:

    cp .env.example .env

Tạo APP_KEY một lần:

    php artisan key:generate

Trước khi mở trình cài đặt /setup, đặt SESSION_DRIVER=file, CACHE_STORE=file và QUEUE_CONNECTION=sync trong .env. Điều này giúp trang cài đặt hoạt động trước khi các bảng session/cache/queue được tạo. Trình cài đặt cũng chuyển các giá trị này về file/file/sync sau khi lưu cấu hình database.

Đặt APP_URL theo địa chỉ mà người dùng sẽ mở, ví dụ https://pos.example.com. Production cần APP_ENV=production và APP_DEBUG=false.

### Database và tài khoản admin

Khởi động web server rồi mở:

    https://<địa-chỉ-QLBH>/setup

Chọn SQLite, MySQL hoặc PostgreSQL, nhập kết nối database và tạo tài khoản quản trị trong biểu mẫu. Chọn database trống. Nếu trình cài đặt cần tạo database mới, tài khoản database phải có quyền CREATE DATABASE; cách khác là tạo sẵn một database trống.

Trang cài đặt chạy migrations, tạo quyền, tạo tài khoản admin và ghi dấu hoàn tất vào storage/app/installed. Không chạy php artisan db:seed để tạo admin production: DatabaseSeeder gọi AdminSeeder có tài khoản mặc định dành cho môi trường cũ/dev.

### Cài thủ công và cập nhật schema

Nếu không dùng trình cài đặt web, cấu hình DB_* trong .env rồi chạy:

    php artisan migrate --force

Khi cập nhật máy đã có dữ liệu, sao lưu trước, triển khai mã mới rồi chạy migration. Không mở lại /setup, không chạy seed lại và không tạo APP_KEY mới trên dữ liệu cũ.

## 4. Cấu hình production

Các giá trị tối thiểu trong .env production:

    APP_ENV=production
    APP_DEBUG=false
    APP_URL=https://pos.example.com
    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=qlbh
    DB_USERNAME=qlbh_app
    DB_PASSWORD=<mat-khau-manh>

Giữ APP_KEY ổn định. Không đưa .env, mật khẩu, API key hoặc khóa camera vào Git. Sau khi thay đổi .env, làm mới cache cấu hình:

    php artisan optimize:clear
    php artisan storage:link
    php artisan optimize

Apache/Nginx phải trỏ document root vào /var/www/qlbh/public, bật rewrite/front controller, và không phục vụ .env, vendor/ hoặc storage nội bộ. Cấp quyền ghi cho storage/ và bootstrap/cache/; code ứng dụng chỉ cần quyền đọc.

Nếu thiếu public/build/manifest.json, chạy npm ci và npm run build ở thư mục gốc.

## 5. Chạy ở máy phát triển

Sau khi đã cấu hình .env và database:

    composer run dev

Lệnh này chạy Laravel dev server, queue listener, log viewer và Vite. Chỉ dùng khi phát triển. Không dùng php artisan serve hoặc npm run dev làm web server production. Trên Laragon, có thể chạy Apache/Laragon và mở domain local đã cấu hình.

Trình duyệt chỉ cho dùng camera ở HTTPS hoặc localhost. Nếu dùng POS từ máy khác trong LAN, cấu hình HTTPS có chứng chỉ phù hợp.

## 6. Scheduler, backup và queue

Ứng dụng đăng ký lệnh sao lưu theo lịch trong routes/console.php. Scheduler cần được gọi mỗi phút.

Linux: thêm cron bằng user chạy ứng dụng:

    * * * * * cd /var/www/qlbh && php artisan schedule:run >> /dev/null 2>&1

Windows: chạy PowerShell từ thư mục gốc QLBH:

    powershell -ExecutionPolicy Bypass -File .\scripts\install-windows-scheduler.ps1 -PhpPath "C:\laragon\bin\php\php-8.x.x\php.exe"

Thay -PhpPath bằng đường dẫn PHP CLI thật. Máy phải bật để tác vụ chạy. Sau đó bật lịch sao lưu trong Cài đặt → Sao lưu và khôi phục.

.env.example đặt QUEUE_CONNECTION=database; trình cài đặt web đổi sang sync để cài lần đầu đơn giản. Nếu tự cấu hình database/redis queue, phải chạy worker liên tục bằng systemd/Supervisor hoặc Task Scheduler:

    php artisan queue:work --tries=3

Không chạy queue worker trong một SSH session tạm nếu cần nó tiếp tục sau khi đăng xuất.

## 7. Model nhận diện khuôn mặt

Browser POS và worker chia sẻ model Human. Sau khi npm ci ở thư mục gốc, tạo file model:

    node scripts/copy-face-models.mjs

Khi deploy, chuyển đủ public/models/human/ (các file .json và .bin) và chạy npm run build sau khi thay đổi frontend/model. Dự án hiện dùng @vladmandic/human.

## 8. Worker camera cửa

Worker là tiến trình Node riêng; máy chạy worker phải truy cập được cả API QLBH và camera. Lấy URL và lệnh có khóa từ Cài đặt → Khách hàng → Camera cửa. Ví dụ cài trên Windows:

    cd C:\laragon\www\QLBH\door-worker
    npm ci --ignore-scripts
    npm run setup -- --url https://pos.example.com/api --key <KHOA_CAMERA>
    npm run check

npm ci --ignore-scripts tránh tự chạy hook pin-cert trong postinstall. Backend mặc định auto sẽ dùng tfjs-node nếu native đã cài, nếu không thì dùng WASM; Windows không có toolchain C++ có thể chạy bằng WASM. Nếu package hiện tại không có hook postinstall hoặc bạn muốn chạy tất cả install scripts, có thể dùng npm ci rồi xác nhận pin-cert đúng máy chủ.

HTTPS tự ký: chỉ ghim sau khi xác minh máy chủ. Có thể dùng fingerprint SHA-256 đã biết:

    npm run pin-cert -- --fingerprint <SHA256_DA_XAC_MINH>

Không tắt kiểm tra TLS. Với HTTP trên mạng riêng, không chạy pin-cert; dùng firewall/VPN để giới hạn truy cập.

### Backend TensorFlow native

Mặc định TF_BACKEND=auto: thử tfjs-node, sau đó dùng WASM nếu native không khả dụng. Ép backend native:

    $env:TF_BACKEND="tfjs-node"
    npm start

Windows với @tensorflow/tfjs-node 4.22.0 có thể không tải được binary có sẵn và phải biên dịch. Cần Node.js x64, Python tương thích node-gyp, Visual Studio 2022 workload Desktop development with C++, MSVC x64/x86 build tools và Windows SDK. Node 20 riêng lẻ không khắc phục lỗi thiếu binary/toolset.

Kiểm tra native backend:

    node -e "const tf=require('@tensorflow/tfjs-node'); const x=tf.tensor1d([1,2]); console.log('backend='+tf.getBackend(), 'sum='+x.sum().dataSync()[0])"

Kết quả mong đợi: backend=cpu sum=3. Nếu báo thiếu tfjs_binding.node, trên Windows chạy rebuild sau khi đã cài workload C++:

    npm rebuild @tensorflow/tfjs-node --build-from-source

Trên Linux, nếu gói không có binary phù hợp, cài compiler toolchain (build-essential, Python 3 và make), rồi chạy npm rebuild @tensorflow/tfjs-node. Chỉ đặt TF_BACKEND=tfjs-node sau khi phép tính kiểm tra ở trên thành công.

### Chạy worker liên tục

Linux có file mẫu door-worker/door-worker.service. Sửa User, WorkingDirectory và ExecStart theo máy đích; đường dẫn Node phải đúng, nhất là nếu Node cài qua nvm:

    sudo cp door-worker/door-worker.service /etc/systemd/system/door-worker.service
    sudo systemctl daemon-reload
    sudo systemctl enable --now door-worker
    sudo systemctl status door-worker
    journalctl -u door-worker -f

Mẫu dùng user www-data, thư mục /var/www/qlbh/door-worker và /usr/bin/node. Chỉnh nếu máy khác. Cài ffmpeg nếu camera dùng RTSP:

    sudo apt install ffmpeg
    ffmpeg -version

Windows có thể chạy npm start trong PowerShell để kiểm tra; nếu cần tự chạy sau reboot, cấu hình Task Scheduler hoặc process manager chạy đúng user, thư mục và Node PATH.

Camera snapshot HTTP không cần ffmpeg. RTSP cần ffmpeg. Nhập URL, kiểu đăng nhập và tài khoản camera ở Cài đặt → Khách hàng → Camera cửa; worker tự đọc và cập nhật cấu hình từ server. Dùng npm run check để kiểm tra API, camera và dò mặt.

## 9. Chuyển máy hoặc nâng cấp server có dữ liệu

1. Backup database và file người dùng. Công cụ backup QLBH có thể gói database và file; bản cục bộ nằm trong storage/app/backups/.
2. Cài dependency bằng composer install và npm ci; build assets/model như trên.
3. Chuyển .env an toàn, giữ APP_KEY hiện tại, cấu hình DB_* theo server mới, và giữ các dữ liệu storage/ cần thiết, gồm storage/app/installed.
4. Import database backup, kiểm tra APP_URL, HTTPS, quyền file và kết nối DB.
5. Chạy php artisan migrate --force, php artisan storage:link, php artisan optimize:clear, rồi php artisan optimize.
6. Cài lại scheduler, queue worker nếu dùng queue bất đồng bộ, và door-worker trên máy được chọn.
7. Kiểm tra đăng nhập, tải ảnh/file, camera, backup và log.

Không chạy /setup trên database có dữ liệu. Không tạo APP_KEY mới khi chuyển app cũ vì dữ liệu mã hóa có thể không đọc được.

## 10. Kiểm tra nhanh

    php artisan about
    php artisan migrate:status
    php artisan route:list
    npm run build

| Lỗi | Kiểm tra |
|---|---|
| could not find driver | Bật đúng pdo_mysql, pdo_sqlite hoặc pdo_pgsql cho cả PHP CLI và Apache/PHP-FPM. |
| SQLSTATE / không kết nối DB | Kiểm tra DB_*, host/port, quyền user và chọn database trống khi cài mới. |
| Thiếu Vite manifest | Chạy npm ci, npm run build; kiểm tra public/build/manifest.json. |
| Lỗi ghi cache/session/log/upload | Cấp quyền ghi đúng cho storage/ và bootstrap/cache/. |
| Browser không thấy camera | Dùng HTTPS hoặc localhost và kiểm tra quyền camera. |
| Worker báo sai khóa hoặc không kết nối | Lấy lại lệnh cài từ QLBH; kiểm tra URL API từ máy worker và TLS certificate. |
| tfjs_binding.node không có | Build lại tfjs-node; Windows cần MSVC C++ workload và Windows SDK. |
| RTSP lỗi | Cài ffmpeg, kiểm tra URL/luồng, tài khoản và thử bằng VLC. |

### Hai file môi trường

- .env ở thư mục gốc: Laravel, APP_KEY, database, mail và tích hợp dịch vụ.
- door-worker/.env: API_URL, DOOR_CAMERA_KEY, TF_BACKEND, đường dẫn model và TLS pin.
- Không ghi đè .env đang chạy bằng .env.example; rà lại cấu hình sau mỗi lần chuyển máy.

