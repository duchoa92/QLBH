"""Generate MediaMTX paths from the one canonical camera-intelligence config."""
from __future__ import annotations

import argparse
from pathlib import Path
import sys

import yaml

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from app.config import load_config

HEADER = {
    'api': True,
    'apiAddress': '127.0.0.1:9997',
    # Metrics are not consumed by QLBH.  Disabling the optional listener
    # avoids a stale prior MediaMTX process on port 9998 preventing the video
    # server itself from starting after an update or Windows logon.
    'metrics': False,
    # Camera Intelligence deliberately reads the same MediaMTX *view* path
    # over local RTSP. Keep that listener local and TCP-only: it needs no UDP
    # RTP/RTCP ports (which are often occupied by a former camera stack), yet
    # it still gives AI and WebRTC one canonical low-resolution stream.
    'rtsp': True,
    'rtspAddress': '127.0.0.1:8554',
    'rtspTransports': ['tcp'],
    # QLBH doesn't use these public protocols; disable their unused listeners
    # so an unrelated local service cannot prevent WebRTC from starting.
    'rtmp': False,
    'hls': False,
    'srt': False,
    'webrtc': True,
    'webrtcAddress': ':8889',
    'webrtcLocalUDPAddress': ':8189',
    # TCP is a LAN fallback when UDP is filtered by a Windows firewall/router.
    # Browsers still prefer UDP when it is available.
    'webrtcLocalTCPAddress': ':8189',
    # Windows may include VPN/virtual adapters in automatic discovery. Use the
    # explicit, validated hosts below instead so local browsers can always use
    # loopback and LAN browsers receive the actual Laragon host address.
    'webrtcIPsFromInterfaces': False,
    # Kept empty because interface discovery is intentionally disabled.
    'webrtcIPsFromInterfacesList': [],
    'pathDefaults': {'rtspTransport': 'tcp', 'sourceOnDemand': False},
}

parser = argparse.ArgumentParser()
parser.add_argument('--config', default='config.yaml')
parser.add_argument('--output', required=True)
args = parser.parse_args()
config = load_config(args.config)
project_root = Path(args.config).resolve().parent.parent
certificate = project_root / 'storage' / 'app' / 'ssl' / 'qlbh-lan.crt'
private_key = project_root / 'storage' / 'app' / 'ssl' / 'qlbh-lan.key'
paths = {}
for camera in config.cameras:
    if camera.enabled:
        paths[camera.id] = {'source': camera.ai_rtsp_url}
        paths[f'{camera.id}-view'] = {'source': camera.view_rtsp_url}
document = HEADER | {'paths': paths}
document['webrtcAdditionalHosts'] = config.server.webrtc_additional_hosts
# The Laravel UI is HTTPS on Windows/Laragon. Use its already-trusted LAN
# certificate for MediaMTX too, otherwise the browser blocks direct WHEP as
# mixed content and PHP has to proxy a slow signalling request.
if certificate.is_file() and private_key.is_file():
    document['webrtcEncryption'] = True
    document['webrtcServerCert'] = str(certificate).replace('\\', '/')
    document['webrtcServerKey'] = str(private_key).replace('\\', '/')
document['webrtcAllowOrigins'] = ['*']
Path(args.output).write_text(yaml.safe_dump(document, sort_keys=False, allow_unicode=True), encoding='utf-8')
print(f'Generated {args.output} for {len(paths) // 2} camera(s)')
