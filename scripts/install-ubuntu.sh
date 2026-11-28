#!/usr/bin/env bash
# Chuẩn bị máy chủ Ubuntu 22.04/24.04 cho QLBH. Chạy một lần bằng root, sau đó mở http://<ip-máy-chủ>/setup để hoàn tất.
#
#   sudo bash scripts/install-ubuntu.sh [--php-version 8.4] [--server-name qlbh.local] [--https] [--with-mariadb] [--db-user admin --db-pass 'matkhau'] [--no-camera] [--no-nginx] [--no-build]
#
#   --db-user / --db-pass: tạo sẵn tài khoản MySQL/MariaDB (quyền với database tên bắt đầu bằng qlbh) để nhập vào trang /setup.
#                          Bỏ --db-pass thì script sẽ hỏi mật khẩu. Không cần --with-mariadb nếu máy đã có MySQL.
#   (Khi chạy trong terminal, script tự hỏi có cố định/đổi IP không, quét mạng, hiện IP dùng được/không dùng được rồi cho nhập IP; --no-static-ip để bỏ qua.)
#   --static-ip [--ip 192.168.x.y/24 --gateway 192.168.x.1 --dns 8.8.8.8,1.1.1.1]: cố định IP máy chủ (mặc định giữ IP hiện tại).
#   --cloudflare-token <TOKEN>: cài Cloudflare Tunnel để truy cập từ internet (HTTPS hợp lệ, không cần mở cổng router).
#   --tailscale [--tailscale-key <KEY>]: cài Tailscale (mạng riêng) để chủ cửa hàng truy cập từ xa an toàn.
#   --rebuild: build lại giao diện ngay trên server (mặc định chỉ build khi chưa có public/build). Cập nhật sau này: sudo bash scripts/update.sh
#   --no-camera: KHÔNG cài Docker / nền tảng camera (mặc định script cài Docker và chạy deploy/camera-platform nếu thư mục này có trong dự án).
#   --https: tạo CA nội bộ + chứng chỉ cho IP/tên máy chủ (camera điện thoại bắt buộc HTTPS); CA để tải ở /certs/rootCA.crt
# Script chạy lại nhiều lần vẫn an toàn (không ghi đè .env, không tạo lại database).
set -euo pipefail

SERVER_NAME="_"
PHP_VERSION="8.4"
WITH_MARIADB=0
WITH_CAMERA=1
STATIC_IP=0
NO_STATIC_IP=0
STATIC_ARGS=()
CF_TOKEN=""
WITH_TAILSCALE=0
TS_KEY=""
DB_USER=""
DB_PASS=""
WITH_NGINX=1
WITH_HTTPS=0
BUILD_ASSETS=1
FORCE_BUILD=0
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WEB_USER="www-data"

while [[ $# -gt 0 ]]; do
    case "$1" in
        --server-name) SERVER_NAME="${2:?thiếu giá trị cho --server-name}"; shift 2 ;;
        --php-version) PHP_VERSION="${2:?thiếu giá trị cho --php-version}"; shift 2 ;;
        --with-mariadb) WITH_MARIADB=1; shift ;;
        --db-user) DB_USER="${2:?thiếu giá trị cho --db-user}"; shift 2 ;;
        --db-pass) DB_PASS="${2:?thiếu giá trị cho --db-pass}"; shift 2 ;;
        --with-camera) WITH_CAMERA=1; shift ;;
        --no-camera) WITH_CAMERA=0; shift ;;
        --static-ip) STATIC_IP=1; shift ;;
        --no-static-ip) NO_STATIC_IP=1; shift ;;
        --ip) STATIC_IP=1; STATIC_ARGS+=(--ip "${2:?thiếu giá trị cho --ip}"); shift 2 ;;
        --gateway) STATIC_ARGS+=(--gateway "${2:?thiếu giá trị cho --gateway}"); shift 2 ;;
        --dns) STATIC_ARGS+=(--dns "${2:?thiếu giá trị cho --dns}"); shift 2 ;;
        --cloudflare-token) CF_TOKEN="${2:?thiếu giá trị cho --cloudflare-token}"; shift 2 ;;
        --tailscale) WITH_TAILSCALE=1; shift ;;
        --tailscale-key) WITH_TAILSCALE=1; TS_KEY="${2:?thiếu giá trị cho --tailscale-key}"; shift 2 ;;
        --https) WITH_HTTPS=1; shift ;;
        --no-nginx) WITH_NGINX=0; shift ;;
        --no-build) BUILD_ASSETS=0; shift ;;
        --rebuild) FORCE_BUILD=1; shift ;;
        -h|--help) sed -n '2,18p' "$0"; exit 0 ;;
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

log() { printf '\n==> %s\n' "$*"; }

# Cần internet để tải PHP, thư viện, Docker...
if ! getent hosts archive.ubuntu.com >/dev/null 2>&1 || ! (curl -fsS -m 8 -o /dev/null https://archive.ubuntu.com 2>/dev/null || ping -c1 -W3 1.1.1.1 >/dev/null 2>&1); then
    echo "CẢNH BÁO: máy chủ chưa ra được internet (hoặc DNS lỗi). Kiểm tra dây mạng/Wi-Fi/router rồi chạy lại; bước tải gói sẽ thất bại." >&2
fi

# IP đã được cố định chưa? (netplan do script tạo, hoặc NetworkManager đang để manual)
ip_is_static() {
    [[ -f /etc/netplan/99-qlbh-static.yaml ]] && return 0
    command -v nmcli >/dev/null 2>&1 || return 1
    local dev con
    dev="$(ip -4 route show default 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i=="dev"){print $(i+1); exit}}')"
    con="$(nmcli -g GENERAL.CONNECTION device show "$dev" 2>/dev/null)"
    [[ -n $con && "$(nmcli -g ipv4.method con show "$con" 2>/dev/null)" == manual ]]
}

# Chạy trong terminal và chưa chọn --no-static-ip: hỏi có cố định IP không.
if [[ $STATIC_IP -eq 0 && $NO_STATIC_IP -eq 0 && -t 0 ]]; then
    if ip_is_static; then
        echo "IP máy chủ đã được cố định: $(hostname -I | awk '{print $1}')."
        read -r -p "Đổi sang IP khác? (script sẽ hiện IP nào dùng được/không) [y/N]: " ANS_IP
        [[ $ANS_IP =~ ^[YyCc] ]] && STATIC_IP=1
    else
        echo
        echo "Địa chỉ IP hiện tại của máy chủ: $(hostname -I | awk '{print $1}')"
        echo "Cố định IP giúp các máy khác luôn vào được cùng một địa chỉ (nên làm)."
        read -r -p "Cố định IP bây giờ? [Y/n]: " ANS_IP
        [[ -z $ANS_IP || $ANS_IP =~ ^[YyCc] ]] && STATIC_IP=1
    fi
fi

if [[ $STATIC_IP -eq 1 ]]; then
    log "Cố định địa chỉ IP"
    bash "$APP_DIR/scripts/set-static-ip.sh" "${STATIC_ARGS[@]}" || echo "Chưa cố định được IP (xem lỗi ở trên). Chạy riêng sau: sudo bash scripts/set-static-ip.sh" >&2
fi

export DEBIAN_FRONTEND=noninteractive

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

if [[ $BUILD_ASSETS -eq 1 && ( $FORCE_BUILD -eq 1 || ! -d public/build ) && -f package.json ]]; then
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
# Thư mục public thuộc root (do clone bằng sudo) nên www-data không tạo được liên kết: tạo bằng root rồi trao quyền.
if [[ ! -e public/storage && ! -L public/storage ]]; then
    "$PHP_BIN" artisan storage:link --no-interaction && chown -h "$WEB_USER":"$WEB_USER" public/storage || true
fi

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
        fastcgi_read_timeout 600;
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

log "Giám sát ổ đĩa (cảnh báo gần đầy / lỗi ổ đĩa)"
apt-get install -y smartmontools || echo "Không cài được smartmontools: vẫn cảnh báo được dung lượng, nhưng không đọc được SMART." >&2
cat > /etc/cron.d/qlbh-disk-health <<CRON
# QLBH: kiểm tra sức khỏe ổ đĩa mỗi 15 phút, kết quả hiển thị trên website
*/15 * * * * root /bin/bash ${APP_DIR}/scripts/disk-health.sh >/dev/null 2>&1
CRON
chmod 644 /etc/cron.d/qlbh-disk-health
bash "$APP_DIR/scripts/disk-health.sh" || true

if [[ $WITH_CAMERA -eq 1 ]]; then
    log "Nền tảng camera (Docker)"
    CAM_DIR="$APP_DIR/deploy/camera-platform"
    if [[ ! -d $CAM_DIR ]]; then
        echo "Không thấy thư mục deploy/camera-platform trong dự án, bỏ qua bước camera." >&2
    else
        if ! command -v docker >/dev/null 2>&1; then
            apt-get install -y docker.io
        fi
        if ! docker compose version >/dev/null 2>&1; then
            apt-get install -y docker-compose-v2 || apt-get install -y docker-compose-plugin || apt-get install -y docker-compose
        fi
        systemctl enable --now docker
        if docker compose version >/dev/null 2>&1; then
            (cd "$CAM_DIR" && docker compose up -d --build) || echo "Chạy docker compose chưa thành công, xem lỗi ở trên." >&2
            # Tự chạy lại các container khi bật máy / khi Docker khởi động lại.
            (cd "$CAM_DIR" && docker compose ps -q | xargs -r docker update --restart unless-stopped >/dev/null) || true
        elif command -v docker-compose >/dev/null 2>&1; then
            (cd "$CAM_DIR" && docker-compose up -d --build) || echo "Chạy docker-compose chưa thành công, xem lỗi ở trên." >&2
        else
            echo "Không cài được Docker Compose. Cài thủ công rồi chạy: cd $CAM_DIR && sudo docker compose up -d --build" >&2
        fi
    fi
fi

if ls /sys/class/power_supply/BAT* >/dev/null 2>&1; then
    log "Máy xách tay: không tự ngủ khi gập màn hình (để website luôn chạy)"
    mkdir -p /etc/systemd/logind.conf.d
    cat > /etc/systemd/logind.conf.d/qlbh-laptop.conf <<'LOGIND'
[Login]
HandleLidSwitch=ignore
HandleLidSwitchExternalPower=ignore
HandleLidSwitchDocked=ignore
IdleAction=ignore
LOGIND
    systemctl mask sleep.target suspend.target hibernate.target hybrid-sleep.target >/dev/null 2>&1 || true
    systemctl kill -s HUP systemd-logind >/dev/null 2>&1 || true
    echo "  ✓ Đã tắt ngủ/ngủ đông và bỏ qua thao tác gập màn hình. Nên cắm sạc thường xuyên."
fi

if [[ -n $CF_TOKEN ]]; then
    log "Truy cập từ internet qua Cloudflare Tunnel"
    if ! command -v cloudflared >/dev/null 2>&1; then
        mkdir -p --mode=0755 /usr/share/keyrings
        if curl -fsSL https://pkg.cloudflare.com/cloudflare-main.gpg -o /usr/share/keyrings/cloudflare-main.gpg 2>/dev/null; then
            echo 'deb [signed-by=/usr/share/keyrings/cloudflare-main.gpg] https://pkg.cloudflare.com/cloudflared any main' > /etc/apt/sources.list.d/cloudflared.list
            apt-get update && apt-get install -y cloudflared || true
        fi
        if ! command -v cloudflared >/dev/null 2>&1; then
            ARCH="$(dpkg --print-architecture)"
            curl -fsSL "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-${ARCH}.deb" -o /tmp/cloudflared.deb && dpkg -i /tmp/cloudflared.deb || true
        fi
    fi
    if command -v cloudflared >/dev/null 2>&1; then
        cloudflared service uninstall >/dev/null 2>&1 || true
        cloudflared service install "$CF_TOKEN" && systemctl enable --now cloudflared >/dev/null 2>&1
        echo "  ✓ Cloudflare Tunnel đã cài và tự chạy khi bật máy."
        echo "    Trên trang Cloudflare Zero Trust → Networks → Tunnels → tunnel này → Public hostname:"
        echo "    đặt tên miền, Service = HTTPS, URL = localhost:443, bật 'No TLS Verify' (nếu dùng --https) hoặc HTTP + localhost:80 (nếu không dùng --https)."
    else
        echo "Không cài được cloudflared. Cài thủ công theo hướng dẫn của Cloudflare rồi chạy: sudo cloudflared service install <TOKEN>" >&2
    fi
fi

if [[ $WITH_TAILSCALE -eq 1 ]]; then
    log "Truy cập từ xa qua Tailscale"
    command -v tailscale >/dev/null 2>&1 || curl -fsSL https://tailscale.com/install.sh | sh || true
    if command -v tailscale >/dev/null 2>&1; then
        systemctl enable --now tailscaled >/dev/null 2>&1 || true
        if [[ -n $TS_KEY ]]; then tailscale up --authkey "$TS_KEY" || true; else echo "  Mở đường link hiện ra bên dưới trên điện thoại/máy tính để đăng nhập Tailscale:"; tailscale up || true; fi
        echo "  ✓ Địa chỉ Tailscale của máy chủ: $(tailscale ip -4 2>/dev/null | head -n1) (cài Tailscale trên điện thoại/máy khác rồi mở https://<địa chỉ này>)"
    else
        echo "Không cài được Tailscale. Xem https://tailscale.com/download/linux" >&2
    fi
fi

log "Tự chạy khi bật máy"
for svc in cron nginx "$PHP_FPM_SERVICE" mariadb mysql docker; do
    if systemctl list-unit-files "${svc}.service" 2>/dev/null | grep -q "^${svc}.service"; then
        systemctl enable "$svc" >/dev/null 2>&1 && echo "  ✓ ${svc}: tự chạy khi bật máy"
    fi
done

log "Xong. Mở ${SCHEME}://${IP:-<ip-máy-chủ>}/setup để tạo database và tài khoản quản trị."
if [[ $WITH_HTTPS -eq 1 ]]; then
    echo "Điện thoại/máy khác cần cài chứng chỉ gốc để tin cậy HTTPS: mở http://${IP}/certs/rootCA.crt và cài đặt (xem docs/HUONG-DAN-CAI-DAT.md)."
else
    echo "Lưu ý: camera điện thoại cần HTTPS. Chạy lại script với --https để tạo chứng chỉ nội bộ."
fi
