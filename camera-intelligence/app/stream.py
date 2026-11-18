from __future__ import annotations

import subprocess
from collections.abc import Iterator

import numpy as np


class RtspFrames:
    """FFmpeg reader with a one-frame buffer. It always discards stale video rather than building latency."""

    def __init__(self, url: str, width: int, height: int) -> None:
        self.url, self.width, self.height = url, width, height
        self.process: subprocess.Popen[bytes] | None = None

    def start(self) -> None:
        self.stop()
        self.process = subprocess.Popen([
            "ffmpeg", "-hide_banner", "-loglevel", "error", "-rtsp_transport", "tcp",
            "-fflags", "nobuffer", "-flags", "low_delay", "-analyzeduration", "0", "-probesize", "32",
            "-i", self.url, "-an", "-vf", f"scale={self.width}:{self.height}", "-pix_fmt", "bgr24",
            "-f", "rawvideo", "pipe:1",
        ], stdin=subprocess.DEVNULL, stdout=subprocess.PIPE, stderr=subprocess.PIPE, bufsize=0)

    def stop(self) -> None:
        if self.process and self.process.poll() is None:
            self.process.kill()
            self.process.wait(timeout=3)
        self.process = None

    def frames(self) -> Iterator[np.ndarray]:
        required = self.width * self.height * 3
        while True:
            if self.process is None or self.process.poll() is not None:
                self.start()
            assert self.process and self.process.stdout
            # Windows pipes commonly return partial reads (often 64 KiB). A
            # raw 1280x720 BGR frame is 2.7 MiB, so treating the first partial
            # read as a failed frame caused a permanent restart loop and zero
            # inference frames even though RTSP itself was healthy.
            parts: list[bytes] = []
            remaining = required
            while remaining:
                chunk = self.process.stdout.read(remaining)
                if not chunk:
                    break
                parts.append(chunk)
                remaining -= len(chunk)
            data = b''.join(parts)
            if len(data) != required:
                self.stop()
                continue
            yield np.frombuffer(data, dtype=np.uint8).reshape((self.height, self.width, 3))
