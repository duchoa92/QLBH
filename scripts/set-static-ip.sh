#!/usr/bin/env bash
# Cố định địa chỉ IP cho máy chủ Ubuntu (laptop/PC chạy QLBH) để các máy khác luôn vào được cùng một địa chỉ.
#   sudo bash scripts/set-static-ip.sh                       # giữ nguyên IP hiện tại nhưng chuyển sang IP tĩnh
#   sudo bash scripts/set-static-ip.sh --ip 192.168.100.124/24 --gateway 192.168.100.1 --dns 8.8.8.8,1.1.1.1
#   (chạy trực tiếp trong terminal: script hỏi bạn nhập IP, gateway, DNS; Enter = giữ giá trị hiện tại; --yes để không hỏi)
#   sudo bash scripts/set-static-ip.sh --revert              # quay lại tự động nhận IP (DHCP)
# Tự kiểm tra mạng sau khi đổi; nếu mất kết nối sẽ tự quay lại cấu hình cũ.
set -euo pipefail

IP_CIDR=""; GATEWAY=""; DNS=""; IFACE=""; REVERT=0; ASSUME_YES=0
while [[ $# -gt 0 ]]; do
    case "$1" in
        --ip) IP_CIDR="${2:?thiếu giá trị cho --ip}"; shift 2 ;;
        --gateway) GATEWAY="${2:?thiếu giá trị cho --gateway}"; shift 2 ;;
        --dns) DNS="${2:?thiếu giá trị cho --dns}"; shift 2 ;;
        --iface) IFACE="${2:?thiếu giá trị cho --iface}"; shift 2 ;;
        --revert) REVERT=1; shift ;;
        --yes|-y) ASSUME_YES=1; shift ;;
        -h|--help) sed -n '2,8p' "$0"; exit 0 ;;
        *) echo "Tham số không hợp lệ: $1" >&2; exit 2 ;;
    esac
done

log() { printf '\n==> %s\n' "$*"; }
[[ $EUID -eq 0 ]] || { echo "Hãy chạy bằng root: sudo bash $0" >&2; exit 1; }

IFACE="${IFACE:-$(ip -4 route show default 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i=="dev"){print $(i+1); exit}}')}"
[[ -n $IFACE ]] || { echo "Không tìm thấy card mạng đang kết nối (chưa có mạng?)." >&2; exit 1; }

NM=0
if command -v nmcli >/dev/null 2>&1 && systemctl is-active --quiet NetworkManager 2>/dev/null; then
    NM_STATE="$(nmcli -g GENERAL.STATE device show "$IFACE" 2>/dev/null || true)"
    [[ $NM_STATE == 100* || $NM_STATE == *connected* ]] && NM=1
fi
CON=""
[[ $NM -eq 1 ]] && CON="$(nmcli -g GENERAL.CONNECTION device show "$IFACE" 2>/dev/null || true)"

ping_gateway() { ping -c 2 -W 2 "$1" >/dev/null 2>&1; }

# Chờ tối đa ~40 giây cho đến khi card mạng có IP mới và liên lạc được với router.
wait_net() {
    local i
    for i in $(seq 1 20); do
        if ip -4 addr show dev "$IFACE" | grep -q "inet ${NEW_IP}/" && ping_gateway "$GATEWAY"; then return 0; fi
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
    echo "----------------------------------------------------------------" >&2
}

# ---------- Quay lại DHCP ----------
if [[ $REVERT -eq 1 ]]; then
    log "Quay lại tự động nhận IP (DHCP)"
    if [[ $NM -eq 1 && -n $CON ]]; then
        nmcli con mod "$CON" ipv4.method auto ipv4.addresses "" ipv4.gateway "" ipv4.dns ""
        nmcli con up "$CON" >/dev/null
    else
        rm -f /etc/netplan/99-qlbh-static.yaml
        netplan apply
    fi
    echo "Xong. IP hiện tại: $(hostname -I | awk '{print $1}')"
    exit 0
fi

# ---------- Thông tin hiện tại làm mặc định ----------
CUR_CIDR="$(ip -4 -o addr show dev "$IFACE" scope global | awk '{print $4; exit}')"
CUR_GW="$(ip -4 route show default dev "$IFACE" | awk '{print $3; exit}')"
CUR_PREFIX="${CUR_CIDR#*/}"; [[ $CUR_PREFIX =~ ^[0-9]+$ ]] || CUR_PREFIX=24

valid_ip() {  # a.b.c.d hoặc a.b.c.d/nn
    local v="${1%/*}" prefix="" o
    [[ $1 == */* ]] && prefix="${1#*/}"
    [[ $v =~ ^([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})$ ]] || return 1
    for o in "${BASH_REMATCH[@]:1}"; do (( 10#$o <= 255 )) || return 1; done
    [[ -z $prefix ]] || { [[ $prefix =~ ^[0-9]+$ ]] && (( prefix >= 8 && prefix <= 30 )); }
}

# Chạy trong terminal và chưa truyền tham số: hỏi người dùng nhập IP.
if [[ -t 0 && $ASSUME_YES -eq 0 && -z $IP_CIDR && -z $GATEWAY && -z $DNS ]]; then
    echo
    echo "Địa chỉ IP hiện tại của máy chủ: ${CUR_CIDR:-không rõ}   Router (gateway): ${CUR_GW:-không rõ}"
    echo "Nếu đang kết nối qua SSH, đổi IP sẽ làm mất kết nối hiện tại (kết nối lại bằng IP mới)."
    while :; do
        read -r -p "Nhập IP mới cho máy chủ [Enter = giữ ${CUR_CIDR%/*}]: " IN_IP
        [[ -z $IN_IP ]] && break
        if valid_ip "$IN_IP"; then
            [[ $IN_IP == */* ]] || IN_IP="${IN_IP}/${CUR_PREFIX}"
            IP_CIDR="$IN_IP"; break
        fi
        echo "  IP không hợp lệ. Ví dụ: 192.168.100.124 (có thể kèm /24)."
    done
    read -r -p "Router / gateway [Enter = ${CUR_GW}]: " IN_GW
    if [[ -n $IN_GW ]]; then
        valid_ip "$IN_GW" && [[ $IN_GW != */* ]] && GATEWAY="$IN_GW" || echo "  Gateway không hợp lệ, dùng ${CUR_GW}."
    fi
    read -r -p "DNS, cách nhau bằng dấu phẩy [Enter = ${CUR_GW},8.8.8.8,1.1.1.1]: " IN_DNS
    [[ -n $IN_DNS ]] && DNS="${IN_DNS// /}"
fi

IP_CIDR="${IP_CIDR:-$CUR_CIDR}"
GATEWAY="${GATEWAY:-$CUR_GW}"
DNS="${DNS:-${GATEWAY},8.8.8.8,1.1.1.1}"
[[ $IP_CIDR == */* ]] || IP_CIDR="${IP_CIDR}/${CUR_PREFIX}"
valid_ip "$IP_CIDR" || { echo "IP không hợp lệ: ${IP_CIDR}" >&2; exit 1; }
[[ -n $IP_CIDR && -n $GATEWAY ]] || { echo "Không đọc được IP/gateway hiện tại. Nhập tay: --ip 192.168.x.y/24 --gateway 192.168.x.1" >&2; exit 1; }

NEW_IP="${IP_CIDR%/*}"
log "Sẽ cố định IP cho card mạng ${IFACE}"
echo "    IP: ${IP_CIDR}    Gateway: ${GATEWAY}    DNS: ${DNS}"
echo "    Cách áp dụng: $([[ $NM -eq 1 ]] && echo "NetworkManager (kết nối: ${CON})" || echo "netplan")"
if [[ -t 0 && $ASSUME_YES -eq 0 ]]; then
    read -r -p "Áp dụng cấu hình này? [Y/n]: " CONFIRM
    [[ -z $CONFIRM || $CONFIRM =~ ^[YyCc] ]] || { echo "Đã hủy, không đổi gì."; exit 0; }
fi

# IP mới khác IP hiện tại: kiểm tra chưa có máy nào đang dùng
if [[ $NEW_IP != "${CUR_CIDR%/*}" ]] && command -v arping >/dev/null 2>&1; then
    if arping -D -c 2 -I "$IFACE" "$NEW_IP" >/dev/null 2>&1; then :; else
        echo "CẢNH BÁO: địa chỉ ${NEW_IP} có vẻ đã có thiết bị khác dùng. Chọn IP khác (--ip)." >&2; exit 1
    fi
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
    [[ -f $FILE ]] && cp "$FILE" "${FILE}.bak"
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
echo "    Truy cập: https://${NEW_IP}  (hoặc http://${NEW_IP})"
echo "    Nên đặt thêm 'DHCP reservation'/'Address reservation' cho máy này trên router để router không cấp IP ${NEW_IP} cho thiết bị khác."
echo "    Nếu đã dùng HTTPS: chạy lại 'sudo bash scripts/install-ubuntu.sh --https' để cấp lại chứng chỉ cho IP mới."
