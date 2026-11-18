from __future__ import annotations

import json
import urllib.request
from datetime import datetime, timezone


class EventPublisher:
    def __init__(self, url: str, key: str, timeout: float) -> None:
        self.url, self.key, self.timeout = url, key, timeout

    def publish(self, event: dict) -> None:
        body = json.dumps({"events": [event]}, separators=(",", ":")).encode()
        request = urllib.request.Request(self.url, data=body, method="POST", headers={
            "Content-Type": "application/json", "Accept": "application/json", "X-Camera-Key": self.key,
        })
        with urllib.request.urlopen(request, timeout=self.timeout):
            pass

    def post(self, path: str, payload: dict) -> dict:
        body = json.dumps(payload, separators=(",", ":")).encode()
        request = urllib.request.Request(self.url.rstrip('/') + path, data=body, method="POST", headers={
            "Content-Type": "application/json", "Accept": "application/json", "X-Camera-Key": self.key,
        })
        with urllib.request.urlopen(request, timeout=self.timeout) as response:
            return json.loads(response.read() or b"{}")

    @staticmethod
    def now() -> str:
        return datetime.now(timezone.utc).isoformat().replace("+00:00", "Z")
