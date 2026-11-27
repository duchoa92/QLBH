#!/usr/bin/env bash
# Kiểm tra sức khỏe ổ đĩa chứa QLBH và ghi kết quả vào storage/app/disk-health.json để website hiển thị cảnh báo.
# Chạy bằng root (cron /etc/cron.d/qlbh-disk-health, mỗi 15 phút) vì đọc SMART và nhật ký nhân cần quyền root.
set -u
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT="$APP_DIR/storage/app/disk-health.json"
mkdir -p "$(dirname "$OUT")"

SRC="$(df --output=source "$APP_DIR" 2>/dev/null | tail -n1 | tr -d ' ')"
MOUNT="$(df --output=target "$APP_DIR" 2>/dev/null | tail -n1 | tr -d ' ')"

# Ổ vật lý chứa phân vùng (vd /dev/sda1 -> sda, /dev/nvme0n1p2 -> nvme0n1, LVM -> ổ gốc đầu tiên)
DISK=""
if [[ -n $SRC && -b $SRC ]] && command -v lsblk >/dev/null 2>&1; then
    DISK="$(lsblk -no pkname "$SRC" 2>/dev/null | head -n1)"
    [[ -z $DISK ]] && DISK="$(basename "$SRC")"
    # LVM/crypt: đi ngược lên ổ vật lý
    ROOTDISK="$(lsblk -ndo NAME,TYPE -s "$SRC" 2>/dev/null | awk '$2=="disk"{print $1; exit}')"
    [[ -n $ROOTDISK ]] && DISK="$ROOTDISK"
fi

# 1) SMART (cần smartmontools; máy ảo thường không hỗ trợ -> unknown)
SMART="unknown"
if [[ -n $DISK ]] && command -v smartctl >/dev/null 2>&1; then
    RESULT="$(smartctl -H "/dev/$DISK" 2>/dev/null)"
    if grep -qiE 'PASSED|: OK' <<<"$RESULT"; then SMART="passed"
    elif grep -qiE 'FAILED' <<<"$RESULT"; then SMART="failed"; fi
fi

# 2) Lỗi đọc/ghi ổ đĩa hoặc hệ thống tệp trong 24 giờ gần đây
IO_ERRORS=0
PATTERN='I/O error|Buffer I/O error|EXT4-fs error|XFS.*(error|corruption)|BTRFS.*(error|corrupt)|blk_update_request|ata[0-9.]+.*(failed|error)|Remounting filesystem read-only|nvme.*(timeout|error)'
if command -v journalctl >/dev/null 2>&1; then
    IO_ERRORS="$(journalctl -k --since '24 hours ago' --no-pager 2>/dev/null | grep -Ecai "$PATTERN" || true)"
elif command -v dmesg >/dev/null 2>&1; then
    IO_ERRORS="$(dmesg 2>/dev/null | grep -Eci "$PATTERN" || true)"
fi
IO_ERRORS="${IO_ERRORS:-0}"; [[ $IO_ERRORS =~ ^[0-9]+$ ]] || IO_ERRORS=0

# 3) Ổ đĩa bị chuyển sang chỉ đọc (dấu hiệu lỗi nghiêm trọng)
READ_ONLY=false
if [[ -n $MOUNT ]] && awk -v m="$MOUNT" '$2==m {print $4}' /proc/mounts | tail -n1 | grep -qE '(^|,)ro(,|$)'; then READ_ONLY=true; fi

TMP="$(mktemp)"
printf '{"checked_at":"%s","disk":"%s","smart":"%s","io_errors":%s,"read_only":%s}\n' \
    "$(date -Iseconds)" "${DISK:-}" "$SMART" "$IO_ERRORS" "$READ_ONLY" > "$TMP"
cat "$TMP" > "$OUT"; rm -f "$TMP"
OWNER="$(stat -c '%U:%G' "$APP_DIR/storage" 2>/dev/null || echo www-data:www-data)"
chown "$OWNER" "$OUT" 2>/dev/null || true
chmod 644 "$OUT"
