#!/usr/bin/env bash
# Chẩn đoán nhanh QLBH trên Ubuntu: dịch vụ, lịch chạy nền, sao lưu tự động, ổ đĩa, mạng/internet, truy cập từ xa.
#   sudo bash scripts/qlbh-doctor.sh
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WEB_USER="www-data"
PHP_BIN="$(command -v php || true)"
ok()   { printf '  \033[32m✓\033[0m %s\n' "$*"; }
bad()  { printf '  \033[31m✗\033[0m %s\n' "$*"; PROBLEMS=$((PROBLEMS+1)); }
warn() { printf '  \033[33m!\033[0m %s\n' "$*"; }
head_() { printf '\n== %s\n' "$*"; }
PROBLEMS=0
artisan() { (cd "$APP_DIR" && sudo -u "$WEB_USER" "$PHP_BIN" artisan "$@" 2>/dev/null); }
tinker() { (cd "$APP_DIR" && sudo -u "$WEB_USER" "$PHP_BIN" artisan tinker --execute="$1" 2>/dev/null | tail -n1); }

head_ "Mạng và internet"
IFACE="$(ip -4 route show default 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i=="dev"){print $(i+1); exit}}')"
IPV4="$(hostname -I 2>/dev/null | awk '{print $1}')"
[[ -n $IPV4 ]] && ok "Địa chỉ IP: ${IPV4} (card ${IFACE:-?})" || bad "Chưa có địa chỉ IP"
if command -v nmcli >/dev/null 2>&1 && [[ -n $IFACE ]]; then
    M="$(nmcli -g IP4.ADDRESS,ipv4.method con show "$(nmcli -g GENERAL.CONNECTION device show "$IFACE" 2>/dev/null)" 2>/dev/null | tr '\n' ' ')"
    [[ $M == *manual* ]] && ok "IP đã được cố định (tĩnh)" || warn "IP đang nhận tự động (DHCP), có thể đổi khi khởi động lại router: chạy sudo bash scripts/set-static-ip.sh"
elif ls /etc/netplan/99-qlbh-static.yaml >/dev/null 2>&1; then ok "IP đã được cố định (netplan)"; else warn "Chưa cố định IP: chạy sudo bash scripts/set-static-ip.sh"; fi
GW="$(ip -4 route show default 2>/dev/null | awk '{print $3; exit}')"
# IP hiện tại có khớp với cấu hình QLBH (APP_URL) và chứng chỉ HTTPS không? (hay lệch sau khi đổi IP)
APP_HOST="$(grep -E '^APP_URL=' "$APP_DIR/.env" 2>/dev/null | head -n1 | sed -E 's/^APP_URL=["'"'"']?[a-z]+:\/\/([^\/:"'"'"']+).*/\1/')"
if [[ $APP_HOST =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ && -n $IPV4 ]]; then
    ALL_IPS=" $(hostname -I 2>/dev/null) "
    [[ $ALL_IPS == *" ${APP_HOST} "* ]] && ok "APP_URL trong .env khớp IP máy (${APP_HOST})" || bad "APP_URL trong .env là ${APP_HOST} nhưng IP máy là ${IPV4}: chạy sudo bash scripts/set-static-ip.sh (chọn giữ/đổi IP) để cập nhật"
    if [[ -f /etc/ssl/qlbh/san.txt ]]; then
        grep -q "IP:${IPV4}" /etc/ssl/qlbh/san.txt && ok "Chứng chỉ HTTPS có IP ${IPV4}" || bad "Chứng chỉ HTTPS không chứa IP ${IPV4}: chạy sudo bash scripts/install-ubuntu.sh --https"
    fi
fi
[[ -n $GW ]] && { ping -c1 -W2 "$GW" >/dev/null 2>&1 && ok "Kết nối được router (${GW})" || bad "Không ping được router (${GW})"; }
ping -c1 -W3 1.1.1.1 >/dev/null 2>&1 && ok "Ra được internet (ping 1.1.1.1)" || bad "Không ra được internet"
getent hosts github.com >/dev/null 2>&1 && ok "DNS hoạt động (phân giải được github.com)" || bad "DNS lỗi: không phân giải được tên miền"
if systemctl is-active --quiet cloudflared 2>/dev/null; then ok "Truy cập từ xa: Cloudflare Tunnel đang chạy"; fi
if command -v tailscale >/dev/null 2>&1; then tailscale status >/dev/null 2>&1 && ok "Truy cập từ xa: Tailscale đang chạy ($(tailscale ip -4 2>/dev/null | head -n1))" || warn "Tailscale đã cài nhưng chưa đăng nhập (chạy: sudo tailscale up)"; fi
systemctl is-active --quiet cloudflared 2>/dev/null || command -v tailscale >/dev/null 2>&1 || warn "Chưa cấu hình truy cập từ xa qua internet (xem --cloudflare-token / --tailscale trong install-ubuntu.sh)"

head_ "Dịch vụ (đang chạy và tự chạy khi bật máy)"
PHPFPM="php$("$PHP_BIN" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null)-fpm"
for svc in nginx "$PHPFPM" mariadb mysql cron docker; do
    systemctl list-unit-files "${svc}.service" 2>/dev/null | grep -q "^${svc}.service" || continue
    A="$(systemctl is-active "$svc" 2>/dev/null)"; E="$(systemctl is-enabled "$svc" 2>/dev/null)"
    if [[ $A == active && $E == enabled ]]; then ok "$svc: đang chạy, tự chạy khi bật máy"
    elif [[ $A == active ]]; then warn "$svc: đang chạy nhưng KHÔNG tự chạy khi bật máy (sudo systemctl enable $svc)"
    else bad "$svc: không chạy (sudo systemctl start $svc)"; fi
done
CODE="$(curl -ksS -o /dev/null -m 8 -w '%{http_code}' "https://127.0.0.1/login" 2>/dev/null)"; [[ $CODE == 000 || -z $CODE ]] && CODE="$(curl -sS -o /dev/null -m 8 -w '%{http_code}' "http://127.0.0.1/login" 2>/dev/null)"
[[ $CODE == 200 || $CODE == 302 ]] && ok "Trang web trả lời (HTTP ${CODE})" || bad "Trang web không trả lời đúng (HTTP ${CODE:-không có})"

head_ "Lịch chạy nền (cron)"
if crontab -u "$WEB_USER" -l 2>/dev/null | grep -q "schedule:run"; then ok "Có dòng cron schedule:run cho ${WEB_USER}"; else bad "Chưa có cron cho ${WEB_USER}: chạy lại sudo bash scripts/install-ubuntu.sh"; fi
HB="$(tinker 'echo (string) Cache::get("scheduler.heartbeat");')"
if [[ $HB =~ ^[0-9]+$ ]]; then
    AGE=$(( ( $(date +%s) - HB ) / 60 ))
    [[ $AGE -lt 5 ]] && ok "Lịch chạy nền vừa chạy ${AGE} phút trước" || bad "Lịch chạy nền đã dừng ${AGE} phút (cron không chạy?)"
else warn "Chưa ghi nhận lần chạy nào của lịch chạy nền (đợi 1–2 phút sau khi cài rồi chạy lại lệnh này)"; fi

head_ "Sao lưu tự động"
EN="$(tinker 'echo filter_var(setting("backup_enabled", false), FILTER_VALIDATE_BOOLEAN) ? "1" : "0";')"
if [[ $EN == 1 ]]; then
    ok "Sao lưu tự động: BẬT (lúc $(tinker 'echo setting("backup_time", "02:00");'), $(tinker 'echo setting("backup_frequency", "daily");'))"
else bad "Sao lưu tự động đang TẮT: bật trong Cài đặt → Sao lưu"; fi
LAST="$(tinker 'echo (string) setting("backup_last_run");')"; [[ -n $LAST ]] && ok "Lần sao lưu gần nhất: ${LAST}" || warn "Chưa có lần sao lưu tự động nào"
ERR="$(tinker 'echo (string) setting("backup_last_error");')"; [[ -n $ERR && $ERR != null ]] && bad "Lỗi sao lưu gần nhất: ${ERR}"
FILE="$(find "$APP_DIR/storage" -name 'backup_*.zip' -printf '%T@ %p\n' 2>/dev/null | sort -nr | head -n1 | cut -d' ' -f2-)"
[[ -n $FILE ]] && ok "File sao lưu mới nhất: ${FILE} ($(du -h "$FILE" | cut -f1))" || warn "Chưa thấy file sao lưu nào"
[[ -w "$APP_DIR/storage" || $(stat -c %U "$APP_DIR/storage") == "$WEB_USER" ]] && ok "Thư mục storage thuộc ${WEB_USER}" || bad "storage không thuộc ${WEB_USER}: sudo chown -R ${WEB_USER}:${WEB_USER} ${APP_DIR}/storage"
"$PHP_BIN" -m 2>/dev/null | grep -qi '^zip$' && ok "PHP có extension zip" || bad "PHP thiếu extension zip: sudo apt install php-zip"

head_ "Ổ đĩa"
USE="$(df --output=pcent "$APP_DIR" | tail -n1 | tr -dc '0-9')"
[[ ${USE:-0} -ge 95 ]] && bad "Ổ đĩa đã dùng ${USE}% (sắp hết chỗ)" || { [[ ${USE:-0} -ge 85 ]] && warn "Ổ đĩa đã dùng ${USE}% (gần đầy)" || ok "Ổ đĩa đã dùng ${USE}%"; }
[[ -f "$APP_DIR/storage/app/disk-health.json" ]] && ok "Giám sát lỗi ổ đĩa: $(cat "$APP_DIR/storage/app/disk-health.json")" || warn "Chưa có dữ liệu giám sát ổ đĩa (cron /etc/cron.d/qlbh-disk-health)"

echo
[[ $PROBLEMS -eq 0 ]] && echo "Mọi thứ đều ổn." || echo "Có ${PROBLEMS} vấn đề cần xử lý (các dòng ✗ ở trên)."
exit 0
