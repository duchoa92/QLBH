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
    'metrics': True,
    'metricsAddress': '127.0.0.1:9998',
    'webrtc': True,
    'webrtcAddress': ':8889',
    'webrtcLocalUDPAddress': ':8189',
    # TCP is a LAN fallback when UDP is filtered by a Windows firewall/router.
    # Browsers still prefer UDP when it is available.
    'webrtcLocalTCPAddress': ':8189',
    'webrtcIPsFromInterfaces': True,
    # MediaMTX 1.16 accepts an allow-list, not the removed exclude-list.
    # An empty list means it advertises usable host interfaces automatically.
    'webrtcIPsFromInterfacesList': [],
    'pathDefaults': {'rtspTransport': 'tcp', 'sourceOnDemand': False},
}

parser = argparse.ArgumentParser()
parser.add_argument('--config', default='config.yaml')
parser.add_argument('--output', required=True)
args = parser.parse_args()
config = load_config(args.config)
paths = {}
for camera in config.cameras:
    if camera.enabled:
        paths[camera.id] = {'source': camera.ai_rtsp_url}
        paths[f'{camera.id}-view'] = {'source': camera.view_rtsp_url}
document = HEADER | {'paths': paths}
Path(args.output).write_text(yaml.safe_dump(document, sort_keys=False, allow_unicode=True), encoding='utf-8')
print(f'Generated {args.output} for {len(paths) // 2} camera(s)')
