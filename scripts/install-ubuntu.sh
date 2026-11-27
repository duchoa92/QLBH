#!/usr/bin/env bash
# Chuẩn bị máy chủ Ubuntu 22.04/24.04 cho QLBH. Chạy một lần bằng root, sau đó mở http://<ip-máy-chủ>/setup để hoàn tất.
#
#   sudo bash scripts/install-ubuntu.sh [--php-version 8.4] [--server-name qlbh.local] [--https] [--with-mariadb] [--db-user admin --db-pass 'matkhau'] [--no-nginx] [--no-build]
#
#   --db-user / --db-pass: tạo sẵn tài khoản MySQL/MariaDB (quyền với database tên bắt đầu bằng qlbh) để nhập vào trang /setup.
#                          Bỏ --db-pass thì script sẽ hỏi mật khẩu. Không cần --with-mariadb nếu máy đã có MySQL.
#   --https: tạo CA nội bộ + chứng chỉ cho IP/tên máy chủ (camera điện thoại bắt buộc HTTPS); CA để tải ở /certs/rootCA.crt
# Script chạy lại nhiều lần vẫn an toàn (không ghi đè .env, không tạo lại database).
set -euo pipefail

SERVER_NAME="_"
PHP_VERSION="8.4"
WITH_MARIADB=0
DB_USER=""
DB_PASS=""
WITH_NGINX=1
WITH_HTTPS=0
BUILD_ASSETS=1
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WEB_USER="www-data"

while [[ $# -gt 0 ]]; do
    case "$1" in
        --server-name) SERVER_NAME="${2:?thiếu giá trị cho --server-name}"; shift 2 ;;
        --php-version) PHP_VERSION="${2:?thiếu giá trị cho --php-version}"; shift 2 ;;
        --with-mariadb) WITH_MARIADB=1; shift ;;
        --db-user) DB_USER="${2:?thiếu giá trị cho --db-user}"; shift 2 ;;
        --db-pass) DB_PASS="${2:?thiếu giá trị cho --db-pass}"; shift 2 ;;
        --https) WITH_HTTPS=1; shift ;;
        --no-nginx) WITH_NGINX=0; shift ;;
        --no-build) BUILD_ASSETS=0; shift ;;
        -h|--help) sed -n '2,11p' "$0"; exit 0 ;;
        *) echo "Tham số không hợp lệ: $1" >&2; exit 2 ;;
    esac
done

if [[ $EUID -ne 0 ]]; then
    echo "Hãy chạy bằng root: sudo bash $0" >&2
    exit 1
fi
if ! grep -qi 'ubuntu' /etc/os-release; then
    echo "Script chỉ hỗ trợ Ubuntu." >&2
    exit 1
fi

export DEBIAN_FRONTEND=noninteractive
log() { printf '\n==> %s\n' "$*"; }

log "Cài gói hệ thống"
apt-get update -y
apt-get install -y software-properties-common ca-certificates curl unzip git cron openssl

# Ubuntu 24.04 mặc định là PHP 8.3; PHP 8.4 lấy từ PPA ondrej/php nếu kho chính chưa có.
if ! apt-cache show "php${PHP_VERSION}-cli" >/dev/null 2>&1; then
    log "Thêm PPA ondrej/php để có PHP ${PHP_VERSION}"
    add-apt-repository -y ppa:ondrej/php
    apt-get update -y
fi
PHP_PKGS=()
for ext in cli fpm common mbstring xml curl zip gd intl bcmath sqlite3 mysql pgsql; do PHP_PKGS+=("php${PHP_VERSION}-${ext}"); done
apt-get install -y "${PHP_PKGS[@]}"
if [[ $WITH_NGINX -eq 1 ]]; then apt-get install -y nginx; fi
if [[ $WITH_MARIADB -eq 1 ]]; then apt-get install -y mariadb-server; fi
systemctl enable --now cron

# Dùng đúng PHP đã chọn cho dòng lệnh (artisan, composer, cron).
PHP_BIN="/usr/bin/php${PHP_VERSION}"
update-alternatives --set php "$PHP_BIN" >/dev/null 2>&1 || true
PHP_FPM_SERVICE="php${PHP_VERSION}-fpm"
log "PHP: $("$PHP_BIN" -r 'echo PHP_VERSION;')"

if ! command -v composer >/dev/null 2>&1; then
    log "Cài Composer"
    EXPECTED="$(curl -fsSL https://composer.github.io/installer.sig)"
    "$PHP_BIN" -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"
    ACTUAL="$("$PHP_BIN" -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
    if [[ "$EXPECTED" != "$ACTUAL" ]]; then echo "Composer installer không hợp lệ." >&2; rm -f /tmp/composer-setup.php; exit 1; fi
    "$PHP_BIN" /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
fi

cd "$APP_DIR"

log "Cài thư viện PHP"
COMPOSER_ALLOW_SUPERUSER=1 "$PHP_BIN" "$(command -v composer)" install --no-dev --optimize-autoloader --no-interaction

if [[ ! -f .env ]]; then
    log "Tạo .env"
    if [[ -f .env.example ]]; then cp .env.example .env; else touch .env; fi
    # Máy chủ thật: tắt debug. (public/setup.php vẫn tự bật chế độ an toàn trong lúc cài đặt.)
    sed -i -E 's/^APP_ENV=.*/APP_ENV=production/; s/^APP_DEBUG=.*/APP_DEBUG=false/' .env
fi
if ! grep -qE '^APP_KEY=base64:' .env; then
    log "Sinh APP_KEY"
    "$PHP_BIN" artisan key:generate --force --no-interaction
fi

if [[ $BUILD_ASSETS -eq 1 && ! -d public/build && -f package.json ]]; then
    log "Build giao diện (cần Node.js)"
    if ! command -v npm >/dev/null 2>&1; then
        curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
        apt-get install -y nodejs
    fi
    if [[ -f package-lock.json ]]; then npm ci --no-audit --no-fund; else npm install --no-audit --no-fund; fi
    npm run build
fi

log "Phân quyền cho user ${WEB_USER}"
mkdir -p storage/app/public storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache database
chown -R "$WEB_USER":"$WEB_USER" storage bootstrap/cache database .env
chmod -R u+rwX,g+rwX storage bootstrap/cache database
chmod 640 .env
[[ -e public/storage ]] || sudo -u "$WEB_USER" "$PHP_BIN" artisan storage:link --no-interaction || true

log "Cài cron cho ${WEB_USER} (Laravel scheduler, mỗi phút)"
CRON_LINE="* * * * * cd '${APP_DIR}' && ${PHP_BIN} artisan schedule:run >> /dev/null 2>&1"
CURRENT="$(crontab -u "$WEB_USER" -l 2>/dev/null || true)"
if ! grep -qF "${APP_DIR}" <<<"$CURRENT" || ! grep -qF 'schedule:run' <<<"$CURRENT"; then
    printf '%s\n%s\n' "$CURRENT" "$CRON_LINE" | sed '/^$/d' | crontab -u "$WEB_USER" -
fi

IP="$(hostname -I 2>/dev/null | awk '{print $1}')"
SCHEME="http"

if [[ $WITH_NGINX -eq 1 ]]; then
    log "Cấu hình nginx"
    SSL_DIR=/etc/ssl/qlbh
    if [[ $WITH_HTTPS -eq 1 ]]; then
        SCHEME="https"
        mkdir -p "$SSL_DIR" "$APP_DIR/public/certs"
        if [[ ! -f $SSL_DIR/rootCA.key ]]; then
            log "Tạo CA nội bộ (hiệu lực 10 năm)"
            openssl genrsa -out "$SSL_DIR/rootCA.key" 4096
            openssl req -x509 -new -nodes -key "$SSL_DIR/rootCA.key" -sha256 -days 3650 -subj "/CN=QLBH Local CA" -out "$SSL_DIR/rootCA.crt"
        fi
        # iOS chỉ chấp nhận chứng chỉ máy chủ hiệu lực tối đa 825 ngày; cấp lại khi chạy script lần sau.
        SAN="IP:${IP:-127.0.0.1},IP:127.0.0.1,DNS:localhost"
        [[ "$SERVER_NAME" != "_" ]] && SAN="${SAN},DNS:${SERVER_NAME}"
        if [[ ! -f $SSL_DIR/server.crt || "$(cat "$SSL_DIR/san.txt" 2>/dev/null)" != "$SAN" ]]; then
            log "Cấp chứng chỉ máy chủ cho: $SAN"
            openssl genrsa -out "$SSL_DIR/server.key" 2048
            openssl req -new -key "$SSL_DIR/server.key" -subj "/CN=${SERVER_NAME/_/${IP:-localhost}}" -out "$SSL_DIR/server.csr"
            printf 'subjectAltName=%s\nbasicConstraints=CA:FALSE\nkeyUsage=digitalSignature,keyEncipherment\nextendedKeyUsage=serverAuth\n' "$SAN" > "$SSL_DIR/server.ext"
            openssl x509 -req -in "$SSL_DIR/server.csr" -CA "$SSL_DIR/rootCA.crt" -CAkey "$SSL_DIR/rootCA.key" -CAcreateserial \
                -out "$SSL_DIR/server.crt" -days 825 -sha256 -extfile "$SSL_DIR/server.ext"
            printf '%s' "$SAN" > "$SSL_DIR/san.txt"
        fi
        chmod 600 "$SSL_DIR"/*.key
        cp "$SSL_DIR/rootCA.crt" "$APP_DIR/public/certs/rootCA.crt"
        chown "$WEB_USER":"$WEB_USER" "$APP_DIR/public/certs/rootCA.crt"
    fi

    # Giống public/.htaccess: mọi yêu cầu không phải file thật đều đi qua setup.php (nó nạp index.php sau khi kiểm tra cài đặt).
    APP_BLOCK="    root ${APP_DIR}/public;
    index setup.php index.php;
    client_max_body_size 64M;
    location / { try_files \$uri \$uri/ /setup.php?\$query_string; }
    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
    }
    location ~ /\.(?!well-known).* { deny all; }
    location = /service-worker.js { add_header Cache-Control \"no-cache\"; try_files \$uri =404; }"

    SITE=/etc/nginx/sites-available/qlbh
    if [[ $WITH_HTTPS -eq 1 ]]; then
        cat > "$SITE" <<NGINX
server {
    listen 80;
    server_name ${SERVER_NAME};
    # Cho phép tải chứng chỉ gốc qua HTTP để điện thoại cài đặt lần đầu.
    location = /certs/rootCA.crt { root ${APP_DIR}/public; }
    location / { return 301 https://\$host\$request_uri; }
}
server {
    listen 443 ssl http2;
    server_name ${SERVER_NAME};
    ssl_certificate ${SSL_DIR}/server.crt;
    ssl_certificate_key ${SSL_DIR}/server.key;
${APP_BLOCK}
}
NGINX
    else
        cat > "$SITE" <<NGINX
server {
    listen 80;
    server_name ${SERVER_NAME};
${APP_BLOCK}
}
NGINX
    fi
    ln -sf "$SITE" /etc/nginx/sites-enabled/qlbh
    rm -f /etc/nginx/sites-enabled/default
    nginx -t
    systemctl enable --now "$PHP_FPM_SERVICE" nginx
    systemctl reload nginx
    systemctl restart "$PHP_FPM_SERVICE"
    if command -v ufw >/dev/null 2>&1 && ufw status | grep -q "Status: active"; then
        ufw allow 80/tcp >/dev/null; ufw allow 443/tcp >/dev/null
    fi
fi

if [[ $WITH_MARIADB -eq 1 || -n $DB_USER ]]; then
    log "Tạo tài khoản database cho trang setup"
    for svc in mariadb mysql; do
        if systemctl list-unit-files "${svc}.service" >/dev/null 2>&1 && systemctl enable --now "$svc" >/dev/null 2>&1; then break; fi
    done
    CRED_FILE=/root/qlbh-db-credentials.txt

    if [[ -n $DB_USER ]]; then
        if [[ ! $DB_USER =~ ^[A-Za-z0-9_]{1,32}$ ]]; then
            echo "--db-user chỉ gồm chữ, số, gạch dưới (tối đa 32 ký tự)." >&2; exit 2
        fi
        if [[ -z $DB_PASS ]]; then
            read -r -s -p "Nhập mật khẩu cho tài khoản database '${DB_USER}': " DB_PASS; echo
            read -r -s -p "Nhập lại mật khẩu: " DB_PASS2; echo
            [[ $DB_PASS == "$DB_PASS2" ]] || { echo "Mật khẩu không khớp." >&2; exit 2; }
        fi
        [[ -n $DB_PASS ]] || { echo "Mật khẩu không được để trống." >&2; exit 2; }
    elif [[ ! -f $CRED_FILE ]]; then
        DB_USER="qlbh"
        DB_PASS="$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-24)"
    fi

    if [[ -n $DB_USER ]]; then
        SQL_PASS="${DB_PASS//\\/\\\\}"; SQL_PASS="${SQL_PASS//\'/\\\'}"
        # Quyền tạo database có tên bắt đầu bằng qlbh (trang setup sẽ tự tạo database).
        mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${SQL_PASS}'; ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${SQL_PASS}'; GRANT ALL PRIVILEGES ON \`qlbh%\`.* TO '${DB_USER}'@'localhost'; FLUSH PRIVILEGES;"
        umask 077
        printf 'Host: 127.0.0.1\nCổng: 3306\nNgười dùng: %s\nMật khẩu: %s\nTên database: qlbh (hoặc tên bắt đầu bằng qlbh)\n' "$DB_USER" "$DB_PASS" > "$CRED_FILE"
        # Ghi tạm vào .env để trang /setup tự điền sẵn (trang xóa 2 dòng này sau khi cài xong).
        if [[ -f "$APP_DIR/.env" ]]; then
            ENV_TMP="$(mktemp)"
            grep -vE '^(SETUP_DB_USERNAME|SETUP_DB_PASSWORD)=' "$APP_DIR/.env" > "$ENV_TMP" || true
            ENV_PASS="${DB_PASS//\\/\\\\}"; ENV_PASS="${ENV_PASS//\"/\\\"}"
            printf 'SETUP_DB_USERNAME="%s"\nSETUP_DB_PASSWORD="%s"\n' "$DB_USER" "$ENV_PASS" >> "$ENV_TMP"
            cat "$ENV_TMP" > "$APP_DIR/.env"   # ghi đè nội dung, giữ nguyên chủ sở hữu và quyền của .env
            rm -f "$ENV_TMP"
        fi
    fi
    echo "Thông tin kết nối database lưu ở ${CRED_FILE}"
fi

log "Xong. Mở ${SCHEME}://${IP:-<ip-máy-chủ>}/setup để tạo database và tài khoản quản trị."
if [[ $WITH_HTTPS -eq 1 ]]; then
    echo "Điện thoại/máy khác cần cài chứng chỉ gốc để tin cậy HTTPS: mở http://${IP}/certs/rootCA.crt và cài đặt (xem docs/HUONG-DAN-CAI-DAT.md)."
else
    echo "Lưu ý: camera điện thoại cần HTTPS. Chạy lại script với --https để tạo chứng chỉ nội bộ."
fi
