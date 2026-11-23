from __future__ import annotations

import json
import urllib.request
from datetime import datetime, timezone


class EventPublisher:
    def __init__(self, url: str, key: str, timeout: float) -> None:
        self.url, self.key, self.timeout = url, key, timeout
        # These events are sent only to the local QLBH API (or to its private
        # LAN address in a container deployment).  Never inherit a browser or
        # developer proxy here: a stale localhost proxy makes an otherwise
        # healthy worker appear disconnected and prevents overlays/visits from
        # reaching Laravel.
        self.opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))

    def post(self, path: str, payload: dict) -> dict:
        body = json.dumps(payload, separators=(",", ":")).encode()
        request = urllib.request.Request(self.url.rstrip('/') + path, data=body, method="POST", headers={
            "Content-Type": "application/json", "Accept": "application/json", "X-Camera-Key": self.key,
        })
        with self.opener.open(request, timeout=self.timeout) as response:
            return json.loads(response.read() or b"{}")

    @staticmethod
    def now() -> str:
        return datetime.now(timezone.utc).isoformat().replace("+00:00", "Z")
