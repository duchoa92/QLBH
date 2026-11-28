#!/usr/bin/env bash
# Cài "trợ giúp đổi IP" để trang Cài đặt của website đổi được IP máy chủ mà không cần gõ lệnh. Tự chạy trong install-ubuntu.sh / update.sh.
#   sudo bash scripts/install-network-helper.sh
# Cách làm an toàn: website (www-data) KHÔNG chạy lệnh root tùy ý. Nó chỉ ghi một file yêu cầu rồi khởi động dịch vụ
# qlbh-ip-change (sudoers cho phép đúng một lệnh này). Dịch vụ chạy bản script thuộc root ở /usr/local/sbin (www-data không sửa được)
# và tự kiểm tra lại mọi giá trị.
set -euo pipefail
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WEB_USER="www-data"
[[ $EUID -eq 0 ]] || { echo "Hãy chạy bằng root: sudo bash $0" >&2; exit 1; }
[[ -f $APP_DIR/scripts/set-static-ip.sh ]] || { echo "Không thấy scripts/set-static-ip.sh" >&2; exit 1; }
SYSTEMCTL="$(command -v systemctl || true)"
[[ -n $SYSTEMCTL ]] || { echo "Máy không có systemd, bỏ qua." >&2; exit 0; }

install -d -m 755 -o root -g root /etc/qlbh /var/lib/qlbh
install -d -m 770 -o root -g "$WEB_USER" /var/lib/qlbh/inbox
install -m 755 -o root -g root "$APP_DIR/scripts/set-static-ip.sh" /usr/local/sbin/qlbh-set-static-ip
printf '%s\n' "$APP_DIR" > /etc/qlbh/app_dir
chmod 644 /etc/qlbh/app_dir

cat > /etc/systemd/system/qlbh-ip-change.service <<'UNIT'
[Unit]
Description=QLBH: đổi IP máy chủ theo yêu cầu từ trang Cài đặt
After=network-online.target

[Service]
Type=oneshot
ExecStart=/usr/local/sbin/qlbh-set-static-ip --apply-request
TimeoutStartSec=300
UNIT

SUDOERS=/etc/sudoers.d/qlbh-network
TMP="$(mktemp)"
printf '%s ALL=(root) NOPASSWD: %s start --no-block qlbh-ip-change.service\n' "$WEB_USER" "$SYSTEMCTL" > "$TMP"
if visudo -cf "$TMP" >/dev/null 2>&1; then install -m 440 -o root -g root "$TMP" "$SUDOERS"; else echo "File sudoers không hợp lệ, không cài." >&2; rm -f "$TMP"; exit 1; fi
rm -f "$TMP"
"$SYSTEMCTL" daemon-reload
echo "  ✓ Trang Cài đặt → 'Mạng & địa chỉ IP' đã sẵn sàng."
