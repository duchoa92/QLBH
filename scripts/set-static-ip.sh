#!/usr/bin/env bash
# Cố định / đổi địa chỉ IP cho máy chủ Ubuntu (laptop/PC chạy QLBH) để các máy khác luôn vào được cùng một địa chỉ.
#   sudo bash scripts/set-static-ip.sh                       # hỏi IP: hiện dải IP hợp lệ, IP đang có người dùng, IP còn trống
#   sudo bash scripts/set-static-ip.sh --list                # chỉ quét và liệt kê IP (không đổi gì, không cần sudo)
#   sudo bash scripts/set-static-ip.sh --ip 192.168.100.124/24 --gateway 192.168.100.1 --dns 8.8.8.8,1.1.1.1
#   (--yes: không hỏi; --ip có thể chỉ là số cuối, vd --ip 210)
#   sudo bash scripts/set-static-ip.sh --revert              # quay lại tự động nhận IP (DHCP)
# Chạy lại được bất cứ lúc nào, kể cả khi hệ thống đang hoạt động: sau khi đổi IP script tự cập nhật APP_URL trong .env,
# chứng chỉ HTTPS, cấu hình camera rồi kiểm tra lại mạng + website. Nếu mất kết nối sẽ tự quay lại cấu hình cũ.
set -euo pipefail

IP_CIDR=""; GATEWAY=""; DNS=""; IFACE=""; REVERT=0; ASSUME_YES=0; LIST_ONLY=0
JSON_ONLY=0; STATUS_ONLY=0; CHECK_IP=""; APPLY_REQ=0; WEB_MODE=0; STATUS_DONE=0; QUIET=0   # JSON_ONLY/CHECK_IP/APPLY_REQ: dùng cho trang Cài đặt của website
while [[ $# -gt 0 ]]; do
    case "$1" in
        --ip) IP_CIDR="${2:?thiếu giá trị cho --ip}"; shift 2 ;;
        --gateway) GATEWAY="${2:?thiếu giá trị cho --gateway}"; shift 2 ;;
        --dns) DNS="${2:?thiếu giá trị cho --dns}"; shift 2 ;;
        --iface) IFACE="${2:?thiếu giá trị cho --iface}"; shift 2 ;;
        --revert) REVERT=1; shift ;;
        --list) LIST_ONLY=1; shift ;;
        --json) JSON_ONLY=1; QUIET=1; shift ;;
        --status) STATUS_ONLY=1; QUIET=1; shift ;;
        --check) CHECK_IP="${2:?thiếu giá trị cho --check}"; QUIET=1; shift 2 ;;
        --apply-request) APPLY_REQ=1; ASSUME_YES=1; shift ;;
        --yes|-y) ASSUME_YES=1; shift ;;
        -h|--help) sed -n '2,9p' "$0"; exit 0 ;;
        *) echo "Tham số không hợp lệ: $1" >&2; exit 2 ;;
    esac
done

# Bản cài ở /usr/local/sbin không nằm trong thư mục dự án: lấy đường dẫn dự án từ /etc/qlbh/app_dir (do root ghi).
if [[ -r /etc/qlbh/app_dir ]]; then APP_DIR="$(head -n1 /etc/qlbh/app_dir)"
else APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"; fi
WEB_USER="www-data"
log() { printf '\n==> %s\n' "$*"; }
ok()  { printf '  \033[32m✓\033[0m %s\n' "$*"; }
bad() { printf '  \033[31m✗\033[0m %s\n' "$*"; }
warn(){ printf '  \033[33m!\033[0m %s\n' "$*"; }
[[ $EUID -eq 0 || $LIST_ONLY -eq 1 || $JSON_ONLY -eq 1 || $STATUS_ONLY -eq 1 || -n $CHECK_IP ]] || { echo "Hãy chạy bằng root: sudo bash $0" >&2; exit 1; }
[[ -z $IFACE || $IFACE =~ ^[A-Za-z0-9._-]{1,15}$ ]] || { echo "Tên card mạng không hợp lệ." >&2; exit 2; }

# ---------- Yêu cầu đổi IP từ trang Cài đặt của website ----------
# Website (www-data) chỉ ghi file yêu cầu; dịch vụ systemd qlbh-ip-change chạy bằng root gọi script này với --apply-request.
# Mọi giá trị đọc từ file đều bị kiểm tra lại ở đây, không tin dữ liệu từ website.
STATE_DIR=/var/lib/qlbh; STATUS_FILE=$STATE_DIR/network-change.json; REQ_FILE=$STATE_DIR/inbox/ip-change.req; WEB_LOG=/var/log/qlbh-set-static-ip.log
json_escape() { sed -e 's/\x1b\[[0-9;]*m//g' -e 's/\\/\\\\/g' -e 's/"/\\"/g' | tr -d '\n\r\t'; }
write_status() {  # state message
    local tmp; mkdir -p "$STATE_DIR"; tmp="$(mktemp "$STATE_DIR/.status.XXXXXX")"
    printf '{"state":"%s","message":"%s","old_ip":"%s","new_ip":"%s","time":%s}\n' "$1" "$(printf '%s' "$2" | json_escape)" "${CUR_IP:-}" "${REQ_IP:-}" "$(date +%s)" > "$tmp"
    chmod 644 "$tmp"; mv -f "$tmp" "$STATUS_FILE"
}
finish_web() {
    local rc=$? msg=""
    [[ $WEB_MODE -eq 1 && $STATUS_DONE -eq 0 ]] || return 0
    if [[ $rc -eq 0 ]]; then write_status ok "Đã đổi IP xong."
    else
        msg="$(tail -n +$((LOG_START + 1)) "$WEB_LOG" 2>/dev/null | grep -v -E '^[[:space:]]*$|^(═|===|Chạy |----|NetworkManager|[0-9]+: |default |[0-9.]+/[0-9]+ dev|[[:space:]])' | tail -n 2 | tr '\n' ' ')"
        write_status failed "${msg:-Không đổi được IP, máy giữ nguyên cấu hình cũ.}"
    fi
}
if [[ $APPLY_REQ -eq 1 ]]; then
    WEB_MODE=1; REQ_IP=""
    exec >>"$WEB_LOG" 2>&1
    LOG_START="$(wc -l < "$WEB_LOG")"
    echo; echo "=== $(date '+%F %T') yêu cầu đổi IP từ website ==="
    trap finish_web EXIT
    [[ -f $REQ_FILE && ! -L $REQ_FILE && $(stat -c %s "$REQ_FILE") -lt 1024 ]] || { echo "Không có yêu cầu đổi IP hợp lệ."; exit 1; }
    REQ_PREFIX=""
    while IFS='=' read -r k v; do
        [[ $v =~ ^[0-9./,]{0,100}$ ]] || { echo "Yêu cầu có ký tự không hợp lệ."; rm -f "$REQ_FILE"; exit 1; }
        case "$k" in ip) REQ_IP="$v" ;; prefix) REQ_PREFIX="$v" ;; gateway) GATEWAY="$v" ;; dns) DNS="$v" ;; esac
    done < "$REQ_FILE"
    rm -f "$REQ_FILE"
    [[ -n $REQ_IP ]] || { echo "Yêu cầu thiếu IP."; exit 1; }
    IP_CIDR="$REQ_IP"; [[ -n $REQ_PREFIX ]] && IP_CIDR="${REQ_IP}/${REQ_PREFIX}"
    write_status running "Đang đổi IP..."
fi

IFACE="${IFACE:-$(ip -4 route show default 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i=="dev"){print $(i+1); exit}}')}"
[[ -n $IFACE ]] || { echo "Không tìm thấy card mạng đang kết nối (chưa có mạng?)." >&2; exit 1; }

NM=0
if command -v nmcli >/dev/null 2>&1 && systemctl is-active --quiet NetworkManager 2>/dev/null; then
    NM_STATE="$(nmcli -g GENERAL.STATE device show "$IFACE" 2>/dev/null || true)"
    [[ $NM_STATE == 100* || $NM_STATE == *connected* ]] && NM=1
fi
CON=""
[[ $NM -eq 1 ]] && CON="$(nmcli -g GENERAL.CONNECTION device show "$IFACE" 2>/dev/null || true)"

# ---------- Hàm tính toán địa chỉ IPv4 ----------
ip2int() { local a b c d; IFS=. read -r a b c d <<<"$1"; echo $(( (10#$a << 24) + (10#$b << 16) + (10#$c << 8) + 10#$d )); }
int2ip() { local n=$1; echo "$(( (n >> 24) & 255 )).$(( (n >> 16) & 255 )).$(( (n >> 8) & 255 )).$(( n & 255 ))"; }
mask_of() { echo $(( (0xFFFFFFFF << (32 - $1)) & 0xFFFFFFFF )); }

valid_ipv4() {  # a.b.c.d
    local o
    [[ $1 =~ ^([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})$ ]] || return 1
    for o in "${BASH_REMATCH[@]:1}"; do (( 10#$o <= 255 )) || return 1; done
}
valid_ip() {  # a.b.c.d hoặc a.b.c.d/nn
    local prefix=""
    valid_ipv4 "${1%/*}" || return 1
    [[ $1 == */* ]] && prefix="${1#*/}"
    [[ -z $prefix ]] || { [[ $prefix =~ ^[0-9]+$ ]] && (( prefix >= 8 && prefix <= 30 )); }
}

# Router có liên lạc được không? Thử ping, rồi ARP, rồi cổng TCP 80/443/53 (nhiều router chặn ping nhưng vẫn hoạt động bình thường).
ping_gateway() {
    local g="$1" p
    ip neigh flush to "$g" dev "$IFACE" >/dev/null 2>&1 || true   # bỏ bản ghi ARP cũ để không bị "đúng giả"
    ping -c 2 -W 2 "$g" >/dev/null 2>&1 && return 0
    ip -4 neigh show dev "$IFACE" 2>/dev/null | awk -v g="$g" '$1==g && /lladdr/ && $NF!="FAILED" && $NF!="INCOMPLETE" {f=1} END{exit !f}' && return 0
    for p in 80 443 53; do
        timeout 2 bash -c "exec 3<>/dev/tcp/${g}/${p}" >/dev/null 2>&1 && return 0
    done
    return 1
}
has_ip() { [[ "$(ip -4 addr show dev "$IFACE" 2>/dev/null)" == *"inet ${NEW_IP}/"* ]]; }

# Chờ tối đa ~40 giây cho đến khi card mạng có IP mới và liên lạc được với router.
wait_net() {
    local i
    for i in $(seq 1 20); do
        if has_ip && ping_gateway "$GATEWAY"; then return 0; fi
        sleep 2
    done
    return 1
}
show_diag() {
    echo "---- Thông tin chẩn đoán (gửi cho hỗ trợ kỹ thuật nếu cần) ----" >&2
    ip -4 addr show dev "$IFACE" >&2 || true
    ip -4 route >&2 || true
    echo "NetworkManager: $(systemctl is-active NetworkManager 2>&1)   systemd-networkd: $(systemctl is-active systemd-networkd 2>&1)" >&2
    ls /etc/netplan/ >&2 || true
    command -v ping >/dev/null 2>&1 || echo "!! Máy KHÔNG có lệnh ping (sudo apt install iputils-ping)" >&2
    echo "ping router ${GATEWAY}:" >&2; ping -c 2 -W 2 "$GATEWAY" 2>&1 | tail -n 3 >&2 || true
    echo "Bảng ARP:" >&2; ip -4 neigh show dev "$IFACE" >&2 || true
    echo "----------------------------------------------------------------" >&2
}

# ---------- Cập nhật phần mềm QLBH sau khi đổi IP ----------
# Thay đúng chuỗi IP cũ (không dính vào IP khác như .5 với .50) trong một file; bản gốc lưu ở /var/backups/qlbh.
replace_ip_in_file() {  # file old new
    local f="$1" old="${2//./\\.}" new="$3" bk=/var/backups/qlbh
    [[ -f $f ]] || return 1
    grep -qE "(^|[^0-9.])${old}([^0-9]|\$)" "$f" || return 1
    mkdir -p "$bk" && chmod 700 "$bk"
    cp -p "$f" "${bk}/$(basename "$f").$(date +%Y%m%d-%H%M%S)"
    sed -i -E "s/(^|[^0-9.])${old}([^0-9]|\$)/\1${new}\2/g; s/(^|[^0-9.])${old}([^0-9]|\$)/\1${new}\2/g" "$f"
}

update_app_for_new_ip() {  # old new
    local old="$1" new="$2" SSL_DIR=/etc/ssl/qlbh SITE=/etc/nginx/sites-available/qlbh
    [[ -n $old && $old != "$new" ]] || return 0
    log "Cập nhật QLBH theo IP mới (${old} → ${new})"

    if replace_ip_in_file "$APP_DIR/.env" "$old" "$new"; then
        ok ".env: đã đổi IP cũ sang IP mới (APP_URL, VITE_DEV_HOST...; bản cũ lưu ở /var/backups/qlbh)"
    else
        echo "  .env: không có IP cũ để đổi (APP_URL có thể đang dùng tên miền hoặc chưa tạo .env)."
    fi

    if replace_ip_in_file "$APP_DIR/camera-intelligence/config.yaml" "$old" "$new"; then
        ok "camera-intelligence/config.yaml: đã đổi IP trong cấu hình camera"
        if [[ -d $APP_DIR/deploy/camera-platform ]] && command -v docker >/dev/null 2>&1; then
            (cd "$APP_DIR/deploy/camera-platform" && { docker compose restart >/dev/null 2>&1 || docker-compose restart >/dev/null 2>&1; }) \
                && ok "Đã khởi động lại nền tảng camera" || warn "Chưa khởi động lại được camera: cd deploy/camera-platform && sudo docker compose restart"
        fi
    fi

    if [[ -x $(command -v php || true) && -f $APP_DIR/artisan ]]; then
        (cd "$APP_DIR" && sudo -u "$WEB_USER" php artisan optimize:clear >/dev/null 2>&1) && ok "Đã xóa cache cấu hình Laravel" || warn "Chưa xóa được cache: cd ${APP_DIR} && sudo -u ${WEB_USER} php artisan optimize:clear"
    fi

    # Chứng chỉ HTTPS gắn với IP: cấp lại (CA gốc giữ nguyên nên điện thoại không phải cài lại).
    if [[ -f $SSL_DIR/rootCA.crt && -f $SSL_DIR/rootCA.key ]]; then
        local sname san
        sname="$(awk '$1=="server_name"{gsub(";","",$2); print $2; exit}' "$SITE" 2>/dev/null || true)"; sname="${sname:-_}"
        san="IP:${new},IP:127.0.0.1,DNS:localhost"
        [[ $sname != "_" ]] && san="${san},DNS:${sname}"
        local T; T="$(mktemp -d)"
        if openssl genrsa -out "$T/server.key" 2048 2>/dev/null \
            && openssl req -new -key "$T/server.key" -subj "/CN=${sname/_/${new}}" -out "$T/server.csr" \
            && printf 'subjectAltName=%s\nbasicConstraints=CA:FALSE\nkeyUsage=digitalSignature,keyEncipherment\nextendedKeyUsage=serverAuth\n' "$san" > "$T/server.ext" \
            && openssl x509 -req -in "$T/server.csr" -CA "$SSL_DIR/rootCA.crt" -CAkey "$SSL_DIR/rootCA.key" -CAcreateserial \
                -out "$T/server.crt" -days 825 -sha256 -extfile "$T/server.ext" 2>/dev/null; then
            install -m 600 "$T/server.key" "$SSL_DIR/server.key"; install -m 644 "$T/server.crt" "$SSL_DIR/server.crt"
            printf '%s' "$san" > "$SSL_DIR/san.txt"
            ok "Đã cấp lại chứng chỉ HTTPS cho ${new}"
        else
            bad "Cấp lại chứng chỉ HTTPS thất bại (giữ chứng chỉ cũ): chạy sudo bash scripts/install-ubuntu.sh --https"
        fi
        rm -rf "$T"
    fi
    if command -v nginx >/dev/null 2>&1 && systemctl is-active --quiet nginx 2>/dev/null; then
        nginx -t >/dev/null 2>&1 && systemctl reload nginx && ok "Đã nạp lại nginx" || bad "nginx lỗi cấu hình: chạy nginx -t để xem chi tiết"
    fi
}

# Kiểm tra sau khi đổi IP.
verify_after_change() {
    local code scheme=http gw
    log "Kiểm tra sau khi đổi IP"
    has_ip && ok "Card ${IFACE} đang dùng IP ${NEW_IP}" || bad "Card ${IFACE} chưa có IP ${NEW_IP}"
    gw="$GATEWAY"; ping_gateway "$gw" && ok "Kết nối được router (${gw})" || bad "Không ping được router (${gw})"
    ping -c1 -W3 1.1.1.1 >/dev/null 2>&1 && ok "Ra được internet" || warn "Chưa ra được internet (kiểm tra DNS/gateway/router)"
    getent hosts github.com >/dev/null 2>&1 && ok "DNS hoạt động" || warn "DNS chưa phân giải được tên miền"
    if systemctl is-active --quiet nginx 2>/dev/null; then
        code="$(curl -ksS -o /dev/null -m 8 -w '%{http_code}' "https://${NEW_IP}/login" 2>/dev/null || true)"
        if [[ $code == 000 || -z $code ]]; then
            code="$(curl -sS -o /dev/null -m 8 -w '%{http_code}' "http://${NEW_IP}/login" 2>/dev/null || true)"
        else scheme=https; fi
        [[ $code == 200 || $code == 302 || $code == 301 ]] && ok "Website trả lời tại ${scheme}://${NEW_IP} (HTTP ${code})" || bad "Website không trả lời tại IP mới (HTTP ${code:-không có}): chạy sudo bash scripts/qlbh-doctor.sh"
    fi
}

# ---------- Quay lại DHCP ----------
if [[ $REVERT -eq 1 ]]; then
    log "Quay lại tự động nhận IP (DHCP)"
    OLD_IP_REV="$(ip -4 -o addr show dev "$IFACE" scope global | awk '{print $4; exit}')"; OLD_IP_REV="${OLD_IP_REV%/*}"
    if [[ $NM -eq 1 && -n $CON ]]; then
        nmcli con mod "$CON" ipv4.method auto ipv4.addresses "" ipv4.gateway "" ipv4.dns ""
        nmcli con up "$CON" >/dev/null
    else
        rm -f /etc/netplan/99-qlbh-static.yaml
        netplan apply
    fi
    sleep 5
    NEW_IP_REV="$(ip -4 -o addr show dev "$IFACE" scope global | awk '{print $4; exit}')"; NEW_IP_REV="${NEW_IP_REV%/*}"
    echo "Xong. IP hiện tại: ${NEW_IP_REV:-chưa có}"
    [[ -n $NEW_IP_REV ]] && update_app_for_new_ip "$OLD_IP_REV" "$NEW_IP_REV"
    exit 0
fi

# ---------- Thông tin hiện tại ----------
CUR_CIDR="$(ip -4 -o addr show dev "$IFACE" scope global | awk '{print $4; exit}')"
CUR_IP="${CUR_CIDR%/*}"
CUR_GW="$(ip -4 route show default dev "$IFACE" | awk '{print $3; exit}')"
CUR_PREFIX="${CUR_CIDR#*/}"; [[ $CUR_PREFIX =~ ^[0-9]+$ ]] || CUR_PREFIX=24
[[ -n $CUR_CIDR && -n $CUR_GW ]] || { echo "Không đọc được IP/gateway hiện tại. Nhập tay: --ip 192.168.x.y/24 --gateway 192.168.x.1" >&2; exit 1; }

IS_STATIC=0
if [[ -f /etc/netplan/99-qlbh-static.yaml ]]; then IS_STATIC=1
elif [[ $NM -eq 1 && -n $CON && "$(nmcli -g ipv4.method con show "$CON" 2>/dev/null)" == manual ]]; then IS_STATIC=1; fi

CUR_INT="$(ip2int "$CUR_IP")"; CUR_MASK="$(mask_of "$CUR_PREFIX")"
CUR_NET=$(( CUR_INT & CUR_MASK )); CUR_BCAST=$(( CUR_NET | (~CUR_MASK & 0xFFFFFFFF) ))
GW_INT="$(ip2int "$CUR_GW")"

# Tất cả IP của chính máy này (mọi card): không tính là "thiết bị khác đang dùng".
declare -A OWN_IP=()
while read -r a; do [[ -n $a ]] && OWN_IP["${a%/*}"]=1; done < <(ip -4 -o addr show 2>/dev/null | awk '{print $4}')

# ---------- Quét mạng: tìm IP đang có thiết bị dùng ----------
declare -A USED_MAC=()
SCAN_FIRST=0; SCAN_LAST=0
scan_network() {
    local i
    # Mạng lớn hơn /24 (vd /16): chỉ quét dải /24 chứa IP của máy này.
    if (( CUR_PREFIX < 24 )); then SCAN_FIRST=$(( CUR_INT & 0xFFFFFF00 )); SCAN_LAST=$(( SCAN_FIRST | 255 ))
    else SCAN_FIRST=$CUR_NET; SCAN_LAST=$CUR_BCAST; fi
    (( SCAN_FIRST <= CUR_NET )) && SCAN_FIRST=$CUR_NET
    (( SCAN_LAST >= CUR_BCAST )) && SCAN_LAST=$CUR_BCAST
    (( SCAN_FIRST += 1 )); (( SCAN_LAST -= 1 ))
    # Ping cả dải 2 lượt (song song). Ping còn khiến máy này gửi ARP: thiết bị chặn ping vẫn lộ ra qua bảng ARP.
    for pass in 1 2; do
        for (( i = SCAN_FIRST; i <= SCAN_LAST; i++ )); do echo "$(( (i >> 24) & 255 )).$(( (i >> 16) & 255 )).$(( (i >> 8) & 255 )).$(( i & 255 ))"; done \
            | xargs -P 64 -I{} sh -c 'ping -c 1 -W 1 {} >/dev/null 2>&1 || true'
    done
    read_neighbors
}
read_neighbors() {
    local ipa mac
    while read -r ipa mac; do
        [[ -n $ipa ]] || continue
        [[ -z ${OWN_IP[$ipa]:-} ]] && USED_MAC["$ipa"]="$mac"
    done < <(ip -4 neigh show dev "$IFACE" 2>/dev/null | awk '{m=""; for(i=1;i<=NF;i++) if($i=="lladdr") m=$(i+1); s=$NF; if(m!="" && s!="FAILED" && s!="INCOMPLETE") print $1, m}')
}

# Thiết bị có đang dùng IP này không? (ping + ARP, nếu có iputils-arping thì dò thêm trùng địa chỉ)
host_alive() {
    local target="$1" rc=0
    ping -c 1 -W 1 "$target" >/dev/null 2>&1 && return 0
    if command -v arping >/dev/null 2>&1 && arping -V 2>&1 | grep -qi iputils && [[ $EUID -eq 0 ]]; then
        arping -D -c 2 -w 3 -I "$IFACE" "$target" >/dev/null 2>&1 || rc=$?
        [[ $rc -eq 1 ]] && return 0
    fi
    read_neighbors
    [[ -n ${USED_MAC[$target]:-} ]]
}

# "210" hoặc "210/24" -> ghép với 3 số đầu của mạng hiện tại
expand_short_ip() {
    local last="${1%/*}" pre=""
    [[ $1 == */* ]] && pre="/${1#*/}"
    (( 10#$last <= 255 )) || { echo "$1"; return 0; }
    echo "$(int2ip $(( (CUR_INT & 0xFFFFFF00) + 10#$last )))${pre}"
}

# ---------- Hiển thị: dải hợp lệ, IP không thể chọn, IP còn trống ----------
compress_free_ranges() {  # in ra các dải IP còn trống liên tiếp: "start end"
    local i start=-1
    for (( i = SCAN_FIRST; i <= SCAN_LAST + 1; i++ )); do
        if (( i <= SCAN_LAST )) && is_free_int "$i"; then
            (( start < 0 )) && start=$i
        elif (( start >= 0 )); then
            echo "$start $(( i - 1 ))"; start=-1
        fi
    done
}
is_free_int() {  # chỉ xét trong phạm vi đã quét
    local v; v="$(int2ip "$1")"
    (( $1 >= SCAN_FIRST && $1 <= SCAN_LAST )) || return 1
    (( $1 == GW_INT )) && return 1
    [[ -n ${USED_MAC[$v]:-} ]] && return 1
    [[ $v == "$CUR_IP" ]] && return 1
    [[ -n ${OWN_IP[$v]:-} ]] && return 1
    return 0
}
short_range() {  # 192.168.1.5 192.168.1.9 -> 192.168.1.5 – .9 (nếu cùng /24)
    local a b; a="$(int2ip "$1")"; b="$(int2ip "$2")"
    if [[ ${a%.*} == "${b%.*}" ]]; then (( $1 == $2 )) && echo "$a" || echo "$a – ${b##*.}"; else echo "$a – $b"; fi
}

FREE_RANGES=(); SUGGEST_RANGE=""; SUGGEST_EXAMPLE=""; SUGGEST_FROM=""; SUGGEST_TO=""
sorted_used_ips() { printf '%s\n' "${!USED_MAC[@]}" | sort -t. -k1,1n -k2,2n -k3,3n -k4,4n; }

# Gom các dải trống và chọn dải gợi ý.
compute_plan() {
    local s e len best_len=0 best_s=0 best_e=0 dist score ex
    FREE_RANGES=()
    while read -r s e; do
        [[ -z $s ]] && continue
        FREE_RANGES+=("$s $e")
        len=$(( e - s + 1 )); dist=0
        # IP đang nhận tự động nằm trong dải DHCP của router nên ưu tiên dải XA IP hiện tại (chỉ xét dải >= 10 địa chỉ);
        # nếu đã là IP tĩnh thì chọn dải trống lớn nhất.
        if (( IS_STATIC == 0 )); then
            if (( CUR_INT < s )); then dist=$(( s - CUR_INT )); elif (( CUR_INT > e )); then dist=$(( CUR_INT - e )); fi
            (( len >= 10 )) && score=$(( dist * 1000 + len )) || score=$len
        else score=$len; fi
        (( score > best_len )) && { best_len=$score; best_s=$s; best_e=$e; }
    done < <(compress_free_ranges)
    if (( best_len > 0 )); then
        # Ví dụ nằm ở đầu xa IP hiện tại của dải, cách mép vài số; tránh số cuối mạng (.254 hay bị router chiếm).
        if (( CUR_INT > best_e )); then ex=$(( best_s + 3 )); else ex=$(( best_e - 3 )); fi
        (( ex > best_e )) && ex=$best_e; (( ex < best_s )) && ex=$best_s
        (( ex > CUR_BCAST - 5 )) && ex=$(( CUR_BCAST - 5 )); (( ex < best_s )) && ex=$best_s
        SUGGEST_FROM="$(int2ip "$best_s")"; SUGGEST_TO="$(int2ip "$best_e")"; SUGGEST_EXAMPLE="$(int2ip "$ex")"
        SUGGEST_RANGE="${SUGGEST_FROM} đến ${SUGGEST_TO}"
    fi
    return 0
}

show_ip_plan() {
    local first=$(( CUR_NET + 1 )) last=$(( CUR_BCAST - 1 )) n=0 ipa s e len cnt=0 r
    compute_plan
    echo
    echo "══════════ DẢI IP CỦA MẠNG ══════════"
    echo "  Mạng: $(int2ip "$CUR_NET")/${CUR_PREFIX}   (card ${IFACE}, router ${CUR_GW})"
    echo "  Máy chủ hiện dùng: ${CUR_IP}  ($([[ $IS_STATIC -eq 1 ]] && echo "IP tĩnh" || echo "tự động nhận từ router - DHCP"))"
    echo "  Có thể đặt IP trong khoảng: $(int2ip "$first") đến $(int2ip "$last")"
    (( CUR_PREFIX < 24 )) && echo "  (Mạng lớn: chỉ quét dải $(int2ip "$SCAN_FIRST") – $(int2ip "$SCAN_LAST"))"
    echo
    echo "  ✗ KHÔNG THỂ CHỌN các IP sau:"
    printf '      %-15s địa chỉ mạng (network)\n' "$(int2ip "$CUR_NET")"
    printf '      %-15s địa chỉ broadcast\n' "$(int2ip "$CUR_BCAST")"
    printf '      %-15s router / gateway\n' "$CUR_GW"
    for ipa in $(sorted_used_ips); do
        [[ -z $ipa || $ipa == "$CUR_GW" ]] && continue
        cnt=$(( cnt + 1 ))
        if (( cnt <= 40 )); then printf '      %-15s đang có thiết bị khác dùng (MAC %s)\n' "$ipa" "${USED_MAC[$ipa]}"; fi
    done
    (( cnt > 40 )) && echo "      ... và $(( cnt - 40 )) IP khác đang có thiết bị dùng"
    (( cnt == 0 )) && echo "      (không thấy thiết bị nào khác trong mạng)"
    echo "  ✓ IP của chính máy này (${CUR_IP}) vẫn chọn lại được (giữ nguyên)."
    echo
    echo "  ○ IP đang trống (chưa thấy thiết bị nào dùng):"
    for r in "${FREE_RANGES[@]}"; do
        read -r s e <<<"$r"; n=$(( n + 1 )); len=$(( e - s + 1 ))
        (( n <= 12 )) && echo "      $(short_range "$s" "$e")   ($len địa chỉ)"
    done
    (( n > 12 )) && echo "      ... và $(( n - 12 )) dải nhỏ khác"
    (( n == 0 )) && echo "      (không còn IP trống trong dải đã quét)"
    if [[ -n $SUGGEST_FROM ]]; then
        echo
        echo "  ★ GỢI Ý: chọn trong khoảng ${SUGGEST_RANGE}  (ví dụ ${SUGGEST_EXAMPLE})"
        (( IS_STATIC == 0 )) && echo "    (IP hiện tại ${CUR_IP} do router tự cấp nên nằm trong dải DHCP; dải gợi ý được chọn xa IP đó để ít bị trùng)"
    fi
    echo
    echo "  Lưu ý: máy đang TẮT hoặc ngủ sẽ không hiện ra ở trên. Để chắc chắn không trùng khi chúng bật lại, nên chọn IP"
    echo "  NGOÀI dải DHCP của router (xem trang quản trị router: DHCP Pool / Start IP – End IP) hoặc đặt DHCP reservation cho máy chủ."
    echo "═════════════════════════════════════"
}

# Xuất kết quả quét dạng JSON cho trang Cài đặt.
print_json() {
    local first=$(( CUR_NET + 1 )) last=$(( CUR_BCAST - 1 )) ipa sep="" r s e
    compute_plan
    printf '{"interface":"%s","network":"%s","prefix":%s,"gateway":"%s","current_ip":"%s","is_static":%s,' \
        "$IFACE" "$(int2ip "$CUR_NET")" "$CUR_PREFIX" "$CUR_GW" "$CUR_IP" "$([[ $IS_STATIC -eq 1 ]] && echo true || echo false)"
    printf '"range_first":"%s","range_last":"%s","scan_first":"%s","scan_last":"%s",' \
        "$(int2ip "$first")" "$(int2ip "$last")" "$(int2ip "$SCAN_FIRST")" "$(int2ip "$SCAN_LAST")"
    printf '"reserved":[{"ip":"%s","reason":"network"},{"ip":"%s","reason":"broadcast"},{"ip":"%s","reason":"gateway"}],' \
        "$(int2ip "$CUR_NET")" "$(int2ip "$CUR_BCAST")" "$CUR_GW"
    printf '"used":['
    for ipa in $(sorted_used_ips); do
        [[ -z $ipa || $ipa == "$CUR_GW" ]] && continue
        printf '%s{"ip":"%s","mac":"%s"}' "$sep" "$ipa" "${USED_MAC[$ipa]}"; sep=","
    done
    printf '],"free_ranges":['; sep=""
    for r in "${FREE_RANGES[@]}"; do
        read -r s e <<<"$r"
        printf '%s{"from":"%s","to":"%s","count":%s}' "$sep" "$(int2ip "$s")" "$(int2ip "$e")" "$(( e - s + 1 ))"; sep=","
    done
    printf '],"suggest":'
    if [[ -n $SUGGEST_FROM ]]; then printf '{"from":"%s","to":"%s","example":"%s"}' "$SUGGEST_FROM" "$SUGGEST_TO" "$SUGGEST_EXAMPLE"; else printf 'null'; fi
    printf '}\n'
}

# Kiểm tra một IP có chọn được không. In lý do ra REASON, trả 0 nếu chọn được.
REASON=""
check_candidate() {  # ip (không kèm /nn), prefix
    local v="$1" p="$2" n m net bc
    valid_ipv4 "$v" || { REASON="không đúng định dạng a.b.c.d"; return 1; }
    n="$(ip2int "$v")"; m="$(mask_of "$p")"; net=$(( n & m )); bc=$(( net | (~m & 0xFFFFFFFF) ))
    (( n == net )) && { REASON="là địa chỉ mạng (network), không dùng được"; return 1; }
    (( n == bc )) && { REASON="là địa chỉ broadcast, không dùng được"; return 1; }
    [[ $v == "$CUR_GW" ]] && { REASON="là IP của router (gateway)"; return 1; }
    [[ $v == "$CUR_IP" || -n ${OWN_IP[$v]:-} ]] && return 0
    if (( net == CUR_NET && p == CUR_PREFIX )); then
        if [[ -n ${USED_MAC[$v]:-} ]]; then REASON="đang có thiết bị khác dùng (MAC ${USED_MAC[$v]})"; return 1; fi
        host_alive "$v" && { REASON="đang có thiết bị khác trả lời tại IP này (trùng IP sẽ làm cả hai mất mạng)"; return 1; }
    else
        REASON="nằm ngoài mạng hiện tại $(int2ip "$CUR_NET")/${CUR_PREFIX} - không đủ thông tin để kiểm tra trùng IP"
        # Khác mạng vẫn cho phép nhưng chỉ khi người dùng chủ động (xử lý ở nơi gọi).
        return 2
    fi
    return 0
}

# --status: tình trạng nhanh (không quét) cho cảnh báo trong website: IP có đang cố định không, APP_URL có khớp IP máy không.
if [[ $STATUS_ONLY -eq 1 ]]; then
    APP_HOST="$(grep -E '^APP_URL=' "$APP_DIR/.env" 2>/dev/null | head -n1 | sed -E 's/^APP_URL=["'"'"']?[a-z]+:\/\/([^\/:"'"'"']+).*/\1/' || true)"
    printf '{"current_ip":"%s","is_static":%s,"app_url_host":"%s"}\n' "$CUR_IP" "$([[ $IS_STATIC -eq 1 ]] && echo true || echo false)" "$(printf '%s' "$APP_HOST" | json_escape)"
    exit 0
fi

# ---------- Quét & hiển thị ----------
if ! command -v ping >/dev/null 2>&1; then
    echo "Máy chưa có lệnh ping, đang cài iputils-ping..." >&2
    [[ $EUID -eq 0 ]] && apt-get install -y iputils-ping >/dev/null 2>&1 || true
    command -v ping >/dev/null 2>&1 || echo "CẢNH BÁO: không có ping nên không quét được IP đang dùng. Cài: sudo apt install iputils-ping" >&2
fi

# --check IP: kiểm tra nhanh một IP (cho trang Cài đặt), in JSON.
if [[ -n $CHECK_IP ]]; then
    SCAN_FIRST=$(( CUR_NET + 1 )); SCAN_LAST=$(( CUR_BCAST - 1 ))
    [[ $CHECK_IP =~ ^[0-9]{1,3}$ ]] && CHECK_IP="$(expand_short_ip "$CHECK_IP")"
    read_neighbors
    rc=0; check_candidate "${CHECK_IP%/*}" "$CUR_PREFIX" || rc=$?
    if [[ $rc -eq 0 ]]; then echo '{"ok":true,"reason":""}'
    else printf '{"ok":false,"reason":"%s"}\n' "$(printf '%s' "$REASON" | json_escape)"; fi
    exit 0
fi

[[ $QUIET -eq 1 ]] || echo "Đang quét mạng $(int2ip "$CUR_NET")/${CUR_PREFIX} để tìm IP đang được dùng (khoảng 5-15 giây)..."
scan_network
if [[ $JSON_ONLY -eq 1 ]]; then print_json; exit 0; fi
show_ip_plan
if [[ $LIST_ONLY -eq 1 ]]; then exit 0; fi

# ---------- Nhập IP, gateway, DNS ----------
INTERACTIVE=0
[[ -t 0 && $ASSUME_YES -eq 0 && -z $IP_CIDR && -z $GATEWAY && -z $DNS ]] && INTERACTIVE=1

if [[ $INTERACTIVE -eq 1 ]]; then
    echo
    [[ $IS_STATIC -eq 1 ]] && echo "Máy đang dùng IP tĩnh. Bạn có thể đổi sang IP khác ngay cả khi hệ thống đang hoạt động."
    echo "Nếu đang kết nối qua SSH, đổi IP sẽ làm mất kết nối hiện tại (kết nối lại bằng IP mới)."
    while :; do
        read -r -p "Nhập IP mới cho máy chủ (đủ 4 số, hoặc chỉ số cuối) [Enter = giữ ${CUR_IP}]: " IN_IP
        IN_IP="${IN_IP// /}"
        [[ -z $IN_IP ]] && break
        [[ $IN_IP =~ ^[0-9]{1,3}(/[0-9]+)?$ ]] && IN_IP="$(expand_short_ip "$IN_IP")"
        [[ $IN_IP == */* ]] || IN_IP="${IN_IP}/${CUR_PREFIX}"
        if ! valid_ip "$IN_IP"; then echo "  ✗ IP không hợp lệ. Ví dụ: ${SUGGEST_EXAMPLE:-192.168.100.124} (có thể kèm /24)."; continue; fi
        rc=0; check_candidate "${IN_IP%/*}" "${IN_IP#*/}" || rc=$?
        if [[ $rc -eq 0 ]]; then IP_CIDR="$IN_IP"; break
        elif [[ $rc -eq 2 ]]; then
            echo "  ! ${IN_IP%/*}: ${REASON}"
            read -r -p "    Vẫn dùng IP này (cần nhập gateway của mạng mới)? [y/N]: " FORCE_NET
            [[ $FORCE_NET =~ ^[YyCc] ]] && { IP_CIDR="$IN_IP"; break; }
        else echo "  ✗ ${IN_IP%/*}: ${REASON}. Chọn IP khác."; fi
    done
fi

IP_CIDR="${IP_CIDR:-$CUR_CIDR}"
# Cho phép --ip 210 (chỉ số cuối): ghép với 3 số đầu của mạng hiện tại
[[ $IP_CIDR =~ ^[0-9]{1,3}(/[0-9]+)?$ ]] && IP_CIDR="$(expand_short_ip "$IP_CIDR")"
[[ $IP_CIDR == */* ]] || IP_CIDR="${IP_CIDR}/${CUR_PREFIX}"
valid_ip "$IP_CIDR" || { echo "IP không hợp lệ: ${IP_CIDR}" >&2; exit 1; }
NEW_IP="${IP_CIDR%/*}"; NEW_PREFIX="${IP_CIDR#*/}"

# Kiểm tra IP khi chạy không hỏi (--ip ...): cũng phải hợp lệ và không trùng
if [[ $INTERACTIVE -eq 0 ]]; then
    rc=0; check_candidate "$NEW_IP" "$NEW_PREFIX" || rc=$?
    if [[ $rc -eq 1 ]]; then echo "Không thể dùng IP ${NEW_IP}: ${REASON}." >&2; echo "Chạy 'sudo bash $0 --list' để xem IP nào còn trống." >&2; exit 1
    elif [[ $rc -eq 2 && -z $GATEWAY ]]; then echo "IP ${NEW_IP} ${REASON}. Hãy truyền thêm --gateway của mạng mới." >&2; exit 1; fi
fi

# Gateway: mặc định là router hiện tại nếu cùng mạng với IP mới
NEW_MASK="$(mask_of "$NEW_PREFIX")"; NEW_NET=$(( $(ip2int "$NEW_IP") & NEW_MASK ))
in_new_net() { valid_ipv4 "$1" && (( ($(ip2int "$1") & NEW_MASK) == NEW_NET )); }
if [[ -z $GATEWAY ]] && in_new_net "$CUR_GW"; then GATEWAY="$CUR_GW"; fi
if [[ $INTERACTIVE -eq 1 ]]; then
    while :; do
        read -r -p "Router / gateway [Enter = ${GATEWAY:-nhập bắt buộc}]: " IN_GW
        IN_GW="${IN_GW// /}"
        [[ -z $IN_GW && -n $GATEWAY ]] && break
        if in_new_net "$IN_GW" && [[ $IN_GW != "$NEW_IP" ]]; then GATEWAY="$IN_GW"; break; fi
        echo "  ✗ Gateway phải là IP hợp lệ nằm cùng mạng với ${NEW_IP}/${NEW_PREFIX} và khác IP máy chủ."
    done
fi
[[ -n $GATEWAY ]] || { echo "Cần gateway: --gateway 192.168.x.1" >&2; exit 1; }
in_new_net "$GATEWAY" || { echo "Gateway ${GATEWAY} không cùng mạng với IP ${NEW_IP}/${NEW_PREFIX}." >&2; exit 1; }
[[ $GATEWAY != "$NEW_IP" ]] || { echo "Gateway không được trùng IP máy chủ." >&2; exit 1; }
if [[ $GATEWAY != "$CUR_GW" ]] && ! host_alive "$GATEWAY"; then
    echo "CẢNH BÁO: gateway ${GATEWAY} không trả lời." >&2
    if [[ $INTERACTIVE -eq 1 ]]; then
        read -r -p "Vẫn dùng gateway này? [y/N]: " C_GW; [[ $C_GW =~ ^[YyCc] ]] || { echo "Đã hủy, không đổi gì."; exit 0; }
    else exit 1; fi
fi

if [[ $INTERACTIVE -eq 1 ]]; then
    read -r -p "DNS, cách nhau bằng dấu phẩy [Enter = ${GATEWAY},8.8.8.8,1.1.1.1]: " IN_DNS
    [[ -n $IN_DNS ]] && DNS="${IN_DNS// /}"
fi
DNS="${DNS:-${GATEWAY},8.8.8.8,1.1.1.1}"
IFS=, read -r -a _dns_list <<<"$DNS"
for _d in "${_dns_list[@]}"; do valid_ipv4 "$_d" || { echo "DNS không hợp lệ: ${_d}" >&2; exit 1; }; done

log "Sẽ $([[ $NEW_IP == "$CUR_IP" ]] && echo "cố định (giữ nguyên)" || echo "đổi sang") IP ${NEW_IP} cho card mạng ${IFACE}"
echo "    IP: ${IP_CIDR}    Gateway: ${GATEWAY}    DNS: ${DNS}"
echo "    Cách áp dụng: $([[ $NM -eq 1 ]] && echo "NetworkManager (kết nối: ${CON})" || echo "netplan")"
if [[ -t 0 && $ASSUME_YES -eq 0 ]]; then
    read -r -p "Áp dụng cấu hình này? [Y/n]: " CONFIRM
    [[ -z $CONFIRM || $CONFIRM =~ ^[YyCc] ]] || { echo "Đã hủy, không đổi gì."; exit 0; }
fi

# Kiểm tra lần cuối ngay trước khi áp dụng (trong lúc nhập có thể có thiết bị mới vào mạng)
if [[ $NEW_IP != "$CUR_IP" && -z ${OWN_IP[$NEW_IP]:-} ]] && (( NEW_NET == CUR_NET && NEW_PREFIX == CUR_PREFIX )); then
    if host_alive "$NEW_IP"; then echo "CẢNH BÁO: địa chỉ ${NEW_IP} vừa có thiết bị khác dùng. Chọn IP khác." >&2; exit 1; fi
fi

# Đang SSH: đổi IP sẽ ngắt phiên. Bỏ qua tín hiệu ngắt và ghi kết quả ra file log để script chạy nốt (kiểm tra, cập nhật .env, hoàn tác nếu lỗi).
LOG=/var/log/qlbh-set-static-ip.log
trap '' HUP PIPE
if [[ -n ${SSH_CONNECTION:-} && $NEW_IP != "$CUR_IP" ]]; then
    echo "Đang chạy qua SSH: kết nối sẽ ngắt khi đổi IP, script vẫn tự chạy tiếp."
    echo "Sau ~1 phút kết nối lại tới ${NEW_IP} và xem kết quả: sudo cat ${LOG}"
    exec >>"$LOG" 2>&1
    echo; echo "=== $(date '+%F %T') đổi IP ${CUR_IP} -> ${NEW_IP} ==="
fi

# ---------- Áp dụng, tự quay lại nếu mất mạng ----------
if [[ $NM -eq 1 && -n $CON ]]; then
    OLD_METHOD="$(nmcli -g ipv4.method con show "$CON")"
    OLD_ADDR="$(nmcli -g ipv4.addresses con show "$CON")"
    OLD_GW="$(nmcli -g ipv4.gateway con show "$CON")"
    OLD_DNS="$(nmcli -g ipv4.dns con show "$CON")"
    nmcli con mod "$CON" ipv4.method manual ipv4.addresses "$IP_CIDR" ipv4.gateway "$GATEWAY" ipv4.dns "${DNS//,/ }" ipv4.ignore-auto-dns yes
    nmcli con up "$CON" >/dev/null 2>&1 || true
    if ! wait_net; then
        show_diag
        echo "Mất kết nối sau khi đổi IP, đang quay lại cấu hình cũ..." >&2
        nmcli con mod "$CON" ipv4.method "${OLD_METHOD:-auto}" ipv4.addresses "$OLD_ADDR" ipv4.gateway "$OLD_GW" ipv4.dns "$OLD_DNS" ipv4.ignore-auto-dns no
        nmcli con up "$CON" >/dev/null 2>&1 || true
        exit 1
    fi
else
    if [[ -d /sys/class/net/$IFACE/wireless ]]; then
        echo "Card ${IFACE} là Wi-Fi nhưng máy không dùng NetworkManager: hãy cố định IP trên router (DHCP reservation) hoặc cấu hình netplan wifis thủ công." >&2
        exit 1
    fi
    FILE=/etc/netplan/99-qlbh-static.yaml
    # Bản sao lưu cũ (nếu có) thuộc lần chạy trước, không được dùng để khôi phục lần này.
    rm -f "${FILE}.bak"
    if [[ -f $FILE ]]; then cp "$FILE" "${FILE}.bak"; fi
    # Dùng đúng tên khai báo của card mạng trong các file netplan hiện có (vd cloud-init dùng tên khác tên thật),
    # nếu không hai cấu hình trùng nhau và cấu hình DHCP cũ có thể thắng.
    NETPLAN_ID="$IFACE"
    if command -v python3 >/dev/null 2>&1; then
        FOUND_ID="$(python3 - "$IFACE" <<'PY' 2>/dev/null || true
import sys, glob
try:
    import yaml
except Exception:
    sys.exit(0)
iface = sys.argv[1]
try:
    mac = open('/sys/class/net/%s/address' % iface).read().strip().lower()
except Exception:
    mac = ''
for f in sorted(glob.glob('/etc/netplan/*.yaml')):
    if f.endswith('99-qlbh-static.yaml'):
        continue
    try:
        data = yaml.safe_load(open(f)) or {}
    except Exception:
        continue
    for key, cfg in ((data.get('network') or {}).get('ethernets') or {}).items():
        cfg = cfg or {}
        m = cfg.get('match') or {}
        if key == iface or cfg.get('set-name') == iface or m.get('name') == iface or (mac and str(m.get('macaddress', '')).lower() == mac):
            print(key)
            sys.exit(0)
PY
)"
        NETPLAN_ID="${FOUND_ID:-$IFACE}"
    fi
    echo "    Khai báo netplan dùng tên card: ${NETPLAN_ID}"
    {
        echo "network:"
        echo "  version: 2"
        echo "  ethernets:"
        echo "    ${NETPLAN_ID}:"
        echo "      dhcp4: false"
        echo "      addresses: [${IP_CIDR}]"
        echo "      routes:"
        echo "        - to: default"
        echo "          via: ${GATEWAY}"
        echo "      nameservers:"
        echo "        addresses: [${DNS}]"
    } > "$FILE"
    chmod 600 "$FILE"
    netplan generate
    netplan apply
    if ! wait_net; then
        show_diag
        echo "Mất kết nối sau khi đổi IP, đang quay lại cấu hình cũ..." >&2
        if [[ -f ${FILE}.bak ]]; then mv "${FILE}.bak" "$FILE"; else rm -f "$FILE"; fi
        netplan apply
        exit 1
    fi
fi

log "Xong. IP tĩnh: ${NEW_IP}"
update_app_for_new_ip "$CUR_IP" "$NEW_IP"
verify_after_change
echo
echo "    Truy cập: https://${NEW_IP}  (hoặc http://${NEW_IP})"
[[ $NEW_IP != "$CUR_IP" ]] && echo "    Các máy khác (điện thoại, máy tính tiền) cần mở lại bằng địa chỉ mới; đã lưu trong trình duyệt thì xóa/đổi lối tắt cũ ${CUR_IP}."
echo "    Nên đặt thêm 'DHCP reservation'/'Address reservation' cho máy này trên router để router không cấp IP ${NEW_IP} cho thiết bị khác."
if [[ $WEB_MODE -eq 1 ]]; then write_status ok "Đã đổi IP xong."; STATUS_DONE=1; fi
