#!/usr/bin/env bash
# Cập nhật QLBH trên Ubuntu: lấy mã mới từ GitHub, cài thư viện, cập nhật database, BUILD giao diện ngay trên server, xóa cache.
#   sudo bash scripts/update.sh              # cập nhật đầy đủ (có build giao diện)
#   sudo bash scripts/update.sh --no-build   # không build (khi public/build đã được đẩy sẵn lên GitHub)
#   sudo bash scripts/update.sh --no-pull    # không git pull (đã chép mã tay), chỉ cài thư viện + build
set -euo pipefail
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WEB_USER="www-data"
DO_BUILD=1; DO_PULL=1
for arg in "$@"; do
    case "$arg" in
        --no-build) DO_BUILD=0 ;;
        --no-pull) DO_PULL=0 ;;
        -h|--help) sed -n '2,5p' "$0"; exit 0 ;;
        *) echo "Tham số không hợp lệ: $arg" >&2; exit 2 ;;
    esac
done
log() { printf '\n==> %s\n' "$*"; }
[[ $EUID -eq 0 ]] || { echo "Hãy chạy bằng root: sudo bash $0" >&2; exit 1; }
cd "$APP_DIR"
export COMPOSER_ALLOW_SUPERUSER=1
PHP_BIN="$(command -v php)"

if [[ $DO_PULL -eq 1 ]]; then
    log "Lấy mã mới từ GitHub"
    git config --global --add safe.directory "$APP_DIR" >/dev/null 2>&1 || true
    # Nếu public/build được Git theo dõi, bỏ thay đổi do lần build trước để git pull không bị xung đột.
    if git ls-files --error-unmatch public/build >/dev/null 2>&1; then git checkout -- public/build 2>/dev/null || true; fi
    git pull --ff-only
fi

log "Cài thư viện PHP"
composer install --no-dev --optimize-autoloader --no-interaction

log "Cập nhật database"
"$PHP_BIN" artisan migrate --force

if [[ $DO_BUILD -eq 1 && -f package.json ]]; then
    log "Build giao diện trên server (có thể mất 1–5 phút)"
    if ! command -v npm >/dev/null 2>&1; then
        curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
        apt-get install -y nodejs
    fi
    # Máy ít RAM: thêm swap tạm nếu dưới ~1.5 GB để build không bị kill
    MEM_MB="$(awk '/MemTotal/ {print int($2/1024)}' /proc/meminfo)"
    if [[ ${MEM_MB:-0} -lt 1500 && ! -f /swapfile && $(swapon --show --noheadings | wc -l) -eq 0 ]]; then
        echo "RAM ${MEM_MB} MB: tạo swap 2 GB tạm thời để build"
        fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile >/dev/null && swapon /swapfile || true
    fi
    if [[ -f package-lock.json ]]; then npm ci --no-audit --no-fund; else npm install --no-audit --no-fund; fi
    npm run build
fi

log "Phân quyền và xóa cache"
chown -R "$WEB_USER":"$WEB_USER" storage bootstrap/cache 2>/dev/null || true
[[ -d public/build ]] && chown -R "$WEB_USER":"$WEB_USER" public/build 2>/dev/null || true
"$PHP_BIN" artisan optimize:clear
for svc in $(systemctl list-units --type=service --no-legend 'php*-fpm.service' 2>/dev/null | awk '{print $1}'); do systemctl reload "$svc" 2>/dev/null || true; done

log "Xong. Trên điện thoại/trình duyệt hãy tải lại trang (Ctrl+Shift+R) hoặc xóa dữ liệu trang để lấy giao diện mới."
