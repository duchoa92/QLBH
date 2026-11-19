from __future__ import annotations

import subprocess
import threading
from collections.abc import Iterator
from time import monotonic

import numpy as np


STREAM_REFRESH_SECONDS = 4.0


class RtspFrames:
    """FFmpeg reader that exposes only the newest decoded frame.

    Recognition makes HTTP calls to QLBH.  Those calls must never let raw
    frames accumulate in the FFmpeg pipe: consuming that backlog made a box
    appear seconds after the person had moved.  A dedicated reader therefore
    continuously drains FFmpeg and replaces the one in-memory frame.
    """

    def __init__(self, url: str, width: int, height: int) -> None:
        self.url, self.width, self.height = url, width, height
        # TCP is reliable but several CCTV firmware versions retain decoded
        # frames in a long TCP queue.  For local cameras UDP receives the most
        # recent RTP packets instead, which removes the 20–30 second lag seen
        # by the detector.  A camera/network that blocks UDP automatically
        # falls back to the previous TCP behaviour below.
        self._transport = 'udp'
        self._attempted_udp = False
        self.process: subprocess.Popen[bytes] | None = None
        self._latest: np.ndarray | None = None
        self._sequence = 0
        self._last_frame_at = 0.0
        self._session_started_at = 0.0
        self._stopping = threading.Event()
        self._frame_ready = threading.Condition()
        self._reader: threading.Thread | None = None

    def start(self) -> None:
        self.stop()
        self._stopping.clear()
        self.process = subprocess.Popen([
            "ffmpeg", "-hide_banner", "-loglevel", "error", "-rtsp_transport", self._transport,
            "-fflags", "nobuffer+discardcorrupt", "-flags", "low_delay", "-avioflags", "direct",
            "-analyzeduration", "0", "-probesize", "32", "-max_delay", "0",
            "-i", self.url, "-an", "-vf", f"scale={self.width}:{self.height}", "-pix_fmt", "bgr24",
            "-f", "rawvideo", "pipe:1",
        ], stdin=subprocess.DEVNULL, stdout=subprocess.PIPE, stderr=subprocess.PIPE, bufsize=0)
        with self._frame_ready:
            self._latest = None
            self._sequence = 0
            self._last_frame_at = monotonic()
            self._session_started_at = self._last_frame_at
        self._reader = threading.Thread(target=self._read_frames, name='rtsp-frame-reader', daemon=True)
        self._reader.start()

    def stop(self) -> None:
        self._stopping.set()
        if self.process and self.process.poll() is None:
            self.process.kill()
            try:
                self.process.wait(timeout=3)
            except subprocess.TimeoutExpired:
                pass
        self.process = None
        with self._frame_ready:
            self._latest = None
            self._frame_ready.notify_all()
        reader, self._reader = self._reader, None
        if reader and reader is not threading.current_thread():
            reader.join(timeout=1)

    def _read_frames(self) -> None:
        required = self.width * self.height * 3
        process = self.process
        if process is None or process.stdout is None:
            return

        while not self._stopping.is_set() and process.poll() is None:
            parts: list[bytes] = []
            remaining = required
            while remaining and not self._stopping.is_set():
                chunk = process.stdout.read(remaining)
                if not chunk:
                    break
                parts.append(chunk)
                remaining -= len(chunk)
            if remaining:
                break
            frame = np.frombuffer(b''.join(parts), dtype=np.uint8).reshape((self.height, self.width, 3))
            with self._frame_ready:
                self._latest = frame
                self._sequence += 1
                self._last_frame_at = monotonic()
                self._frame_ready.notify_all()

        with self._frame_ready:
            self._frame_ready.notify_all()

    def frames(self) -> Iterator[np.ndarray]:
        sequence = 0
        while True:
            # Some DVR/camera firmware starts a new RTSP client near live but
            # then steadily serves an internal replay queue.  No FFmpeg flag
            # can discard frames the camera has not marked stale.  Reopening
            # this inexpensive H.264 sub-stream before that queue grows keeps
            # face boxes within a few seconds of the browser's WebRTC view.
            # The worker retains only the newest decoded frame, so the brief
            # reconnect never creates a backlog in Python either.
            if self.process is not None and monotonic() - self._session_started_at >= STREAM_REFRESH_SECONDS:
                self.stop()
                sequence = 0
                continue
            if self.process is None or self.process.poll() is not None:
                # A UDP-less camera normally exits before delivering a full
                # frame.  Try TCP once so a restrictive router cannot make a
                # camera disappear merely because low-latency transport is
                # unavailable.
                if self._transport == 'udp' and self._attempted_udp and self._sequence == 0:
                    self._transport = 'tcp'
                if self._transport == 'udp':
                    self._attempted_udp = True
                self.start()
                sequence = 0

            with self._frame_ready:
                self._frame_ready.wait_for(
                    lambda: self._sequence != sequence or self.process is None or self.process.poll() is not None,
                    timeout=3,
                )
                if self._sequence == sequence or self._latest is None:
                    # An RTSP TCP connection can remain open after a camera
                    # or MediaMTX restart while never returning another RTP
                    # packet. Treat eight seconds without a complete frame
                    # as a dead reader, then reopen it. This keeps AI alive
                    # without requiring the operator to restart Python.
                    stalled = monotonic() - self._last_frame_at >= 8
                    if self.process is None or self.process.poll() is not None or stalled:
                        if self._transport == 'udp' and self._sequence == 0:
                            self._transport = 'tcp'
                        self.stop()
                    continue
                sequence = self._sequence
                frame = self._latest

            if frame is None:
                continue
            yield frame
