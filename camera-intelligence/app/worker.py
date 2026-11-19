from __future__ import annotations

import base64
import logging
import threading
from dataclasses import dataclass, field
from time import monotonic, sleep, time

import cv2
import numpy as np

from .config import AppConfig, CameraConfig
from .events import EventPublisher
from .inference_yunet import YuNetSFaceEngine
from .stream import RtspFrames
from .tracker import Box, MultiFaceTracker, Track

LOG = logging.getLogger(__name__)
DISPLAY_TRACK_FRAMES = 2
RECOGNITION_SAMPLE_COUNT = 3
THUMB_MAX_CHARACTERS = 29_000


def analysis_stream_url(camera: CameraConfig) -> str:
    """Choose a current, low-latency stream for inference.

    ``view_rtsp_url`` is the camera's H.264 sub-stream.  It is also the
    source MediaMTX publishes to WebRTC, but reading it directly avoids the
    replay server's TCP backlog becoming an unintended inference queue.
    """
    return camera.view_rtsp_url or camera.analysis_rtsp_url or camera.ai_rtsp_url


def make_face_thumb(frame: np.ndarray, box: "Box") -> str | None:
    """Return a small, bounded JPEG crop for the customer-visit card.

    This is intentionally made only after YuNet and SFace both accept a stable
    face.  The worker keeps no file: Laravel stores the compact data URL under
    its existing consent/retention rules.
    """
    padding = max(box.width, box.height) * 0.30
    left = max(0, int(round(box.x - padding)))
    top = max(0, int(round(box.y - padding)))
    right = min(frame.shape[1], int(round(box.x + box.width + padding)))
    bottom = min(frame.shape[0], int(round(box.y + box.height + padding)))
    if right - left < 8 or bottom - top < 8:
        return None

    crop = frame[top:bottom, left:right]
    side = min(160, max(crop.shape[:2]))
    if side < 8:
        return None
    scale = side / max(crop.shape[:2])
    if scale != 1:
        crop = cv2.resize(crop, (max(1, round(crop.shape[1] * scale)), max(1, round(crop.shape[0] * scale))), interpolation=cv2.INTER_AREA)

    for quality in (84, 72, 60):
        encoded, jpeg = cv2.imencode('.jpg', crop, [cv2.IMWRITE_JPEG_QUALITY, quality])
        if not encoded:
            continue
        value = 'data:image/jpeg;base64,' + base64.b64encode(jpeg).decode('ascii')
        if len(value) <= THUMB_MAX_CHARACTERS:
            return value
    return None


def overlay_face(track: Track, frame: np.ndarray) -> dict[str, float | str] | None:
    """Describe one confirmed face for the WebRTC overlay.

    A box becomes visible after two adjacent YuNet hits.  It remains cyan while
    samples are collected or the server compares SFace vectors, emerald only
    after a confident known-customer match, and amber only after an explicit
    no-match.  This prevents the UI from implying that a single detector hit
    is a recognised customer.
    """
    box = track.box
    if box is None or track.frames < DISPLAY_TRACK_FRAMES:
        return None

    if track.identity:
        kind, label = 'known', str(track.identity.get('full_name') or 'Khách quen đã nhận ra')
    elif track.matching:
        kind, label = 'scanning', 'Đã thấy khuôn mặt · đang đối chiếu…'
    elif track.match_checked:
        kind, label = 'unknown', 'Đã thấy khuôn mặt · chưa đăng ký'
    elif track.quality_note:
        kind, label = 'scanning', track.quality_note
    elif len(track.samples) < RECOGNITION_SAMPLE_COUNT:
        kind, label = 'scanning', f'Đã thấy khuôn mặt · lấy mẫu {len(track.samples)}/{RECOGNITION_SAMPLE_COUNT}'
    else:
        kind, label = 'scanning', 'Đã thấy khuôn mặt · đang phân tích…'

    return {
        'id': str(track.id),
        'x': float(round(float(box.x) / frame.shape[1], 5)),
        'y': float(round(float(box.y) / frame.shape[0], 5)),
        'w': float(round(float(box.width) / frame.shape[1], 5)),
        'h': float(round(float(box.height) / frame.shape[0], 5)),
        'kind': kind,
        'label': label,
    }


@dataclass
class CameraState:
    camera_id: str
    running: bool = False
    frames: int = 0
    detected_faces: int = 0
    last_error: str | None = None
    stream_error: str | None = None
    last_frame_at: float | None = None
    inference_ready: bool = False
    # The frame is held only in RAM so the enrollment screen can take a
    # snapshot from the very same RTSP stream that is being analysed.  It is
    # intentionally never written to disk by the worker.
    latest_frame: np.ndarray | None = field(default=None, repr=False)
    latest_face_count: int | None = field(default=None, repr=False)
    frame_lock: threading.Lock = field(default_factory=threading.Lock, repr=False)

    def update_snapshot(self, frame: np.ndarray, face_count: int) -> None:
        # RtspFrames yields an independent ndarray for every decoded image.
        # Keep the matching YuNet result too: enrolment must not ask the old
        # browser detector to second-guess the exact frame its worker saw.
        # snapshot_jpeg makes its own copy while holding the lock.
        with self.frame_lock:
            self.latest_frame = frame
            self.latest_face_count = face_count
            self.last_frame_at = time()

    def snapshot_jpeg(self) -> tuple[bytes, int] | None:
        with self.frame_lock:
            frame = None if self.latest_frame is None else self.latest_frame.copy()
            face_count = self.latest_face_count
        if frame is None:
            return None
        encoded, image = cv2.imencode('.jpg', frame, [cv2.IMWRITE_JPEG_QUALITY, 88])
        return (image.tobytes(), max(0, int(face_count or 0))) if encoded else None


class CameraWorker(threading.Thread):
    def __init__(self, camera: CameraConfig, config: AppConfig, state: CameraState, publisher: EventPublisher) -> None:
        super().__init__(name=f"camera-{camera.id}", daemon=True)
        self.camera, self.config, self.state = camera, config, state
        self.publisher = publisher
        self.stop_event = threading.Event()
        self.tracker = MultiFaceTracker()
        # Posting overlay metadata must never make the detector wait for a
        # PHP/HTTP round-trip.  Keep one latest payload; when the server is
        # briefly busy the publisher skips stale boxes and sends the newest.
        self.overlay_event = threading.Event()
        self.overlay_lock = threading.Lock()
        self.latest_overlay: list[dict[str, float | str]] = []
        self.overlay_thread: threading.Thread | None = None
        self.stream_monitor: threading.Thread | None = None

    def stop(self) -> None:
        self.stop_event.set()
        self.overlay_event.set()

    def queue_overlay(self, faces: list[dict[str, float | str]]) -> None:
        with self.overlay_lock:
            self.latest_overlay = faces
        self.overlay_event.set()

    def publish_overlays(self) -> None:
        """Send only the newest boxes in a separate, bounded-latency thread."""
        while not self.stop_event.is_set():
            self.overlay_event.wait(timeout=0.5)
            self.overlay_event.clear()
            if self.stop_event.is_set():
                break
            with self.overlay_lock:
                faces = self.latest_overlay
            try:
                self.publisher.post('/camera-intelligence/faces', {'camera_id': self.camera.id, 'faces': faces})
                if self.state.last_error and self.state.last_error.startswith('Cannot update overlays:'):
                    self.state.last_error = None
            except Exception as error:
                self.state.last_error = f'Cannot update overlays: {error}'
            self.stop_event.wait(0.2)

    def monitor_stream(self, started_at: float) -> None:
        """Surface an RTSP source that starts a worker but never yields a frame."""
        while not self.stop_event.wait(1):
            last_frame_at = self.state.last_frame_at
            no_initial_frame = last_frame_at is None and monotonic() - started_at >= 8
            stale_frame = last_frame_at is not None and time() - last_frame_at >= 12
            if no_initial_frame or stale_frame:
                self.state.stream_error = 'Chưa nhận được khung hình từ RTSP. Kiểm tra địa chỉ, tài khoản hoặc luồng H.264 của camera.'

    def match_and_record(self, track: Track, samples: list[tuple[float, list[float], str | None]]) -> None:
        """Resolve one SFace sample set without stalling live face boxes."""
        try:
            # Keep the sharpest sample first and submit several independent
            # frames. Laravel aggregates them per person, which is markedly
            # less prone to an accidental one-frame identity than one vector.
            ordered = sorted(samples, key=lambda sample: sample[0], reverse=True)
            descriptors = [sample[1] for sample in ordered]
            thumb = next((sample[2] for sample in ordered if sample[2]), None)
            match = self.publisher.post('/camera-intelligence/match', {'descriptors': descriptors})
            matched_identity = match.get('customer') if match.get('matched') else None
            # A known person stays known while an occasional blurred probe is
            # below the server threshold.  Only a new positive match can
            # replace that identity; a transient no-match cannot turn a green
            # box into an anonymous amber one.
            identity = matched_identity or track.identity
            self.publisher.post('/camera-intelligence/visits', {
                'customer_id': identity.get('id') if identity else None,
                'camera_id': self.camera.id,
                'engine': 'sface-v1',
                # Retain the sharpest normalized vector for an unknown-visit
                # grouping. The other probes were only for robust matching.
                'descriptor': descriptors[0],
                'thumb': thumb,
            })
            if matched_identity:
                track.identity = matched_identity
            track.match_checked = True
            self.state.last_error = None
        except Exception as error:
            self.state.last_error = f'Cannot match with QLBH: {error}'
            LOG.warning('camera=%s cannot resolve face track=%s: %s', self.camera.id, track.id, error)
        finally:
            track.matching = False

    def run(self) -> None:
        try:
            engine = YuNetSFaceEngine(
                self.config.inference.detector_model,
                self.config.inference.recognizer_model,
                self.config.inference.device,
                self.config.inference.min_face_px,
            )
            self.state.inference_ready = True
            self.overlay_thread = threading.Thread(
                target=self.publish_overlays,
                name=f'camera-overlay-{self.camera.id}',
                daemon=True,
            )
            self.overlay_thread.start()
            self.stream_monitor = threading.Thread(
                target=self.monitor_stream,
                args=(monotonic(),),
                name=f'camera-stream-monitor-{self.camera.id}',
                daemon=True,
            )
            self.stream_monitor.start()
        except Exception as error:
            self.state.last_error = str(error)
            LOG.exception("camera=%s cannot load approved inference models", self.camera.id)
            return

        interval = 1 / self.config.inference.detect_fps
        # Read the H.264 sub-stream directly from the camera.  MediaMTX still
        # owns WebRTC playback, but its local RTSP restream can retain a large
        # TCP buffer after a brief camera/network stall.  That made YuNet see a
        # face from tens of seconds ago while the browser was already showing
        # the current person.  The direct sub-stream is the same low-resolution
        # encode as WebRTC, starts from its newest keyframe and keeps the
        # recognition overlay in real time.  ``analysis_rtsp_url`` is retained
        # only to read older config files; it is a fallback for a legacy config
        # that has no view stream.
        analysis_url = analysis_stream_url(self.camera)
        reader = RtspFrames(analysis_url, self.camera.width, self.camera.height)
        self.state.running = True
        try:
            for frame in reader.frames():
                if self.stop_event.is_set():
                    break
                started = monotonic()
                boxes = engine.detect(frame)
                self.state.update_snapshot(frame, len(boxes))
                self.state.stream_error = None
                tracks = self.tracker.update(boxes, started)
                # Do not wait for a server round trip before publishing a box.
                # The visual stage is real-time; identity work is dispatched
                # separately after several high-quality SFace samples.
                overlay = []
                for track in tracks:
                    if track.box and not track.matching and started - track.last_recognized_at >= self.config.inference.recognition_cooldown_seconds:
                        result = engine.recognize(frame, track.box)
                        if result and result.identity_ready:
                            track.quality_note = None
                            # Keep crops from exact accepted frames. Three
                            # samples take only a few live frames, but prevent
                            # a moving/blinking face from deciding identity.
                            track.samples.append((result.quality, result.descriptor, make_face_thumb(frame, track.box)))
                            track.samples = sorted(track.samples, key=lambda sample: sample[0], reverse=True)[:RECOGNITION_SAMPLE_COUNT]
                            if len(track.samples) >= RECOGNITION_SAMPLE_COUNT:
                                samples = track.samples
                                track.samples = []
                                track.matching = True
                                track.last_recognized_at = started
                                threading.Thread(
                                    target=self.match_and_record,
                                    args=(track, samples),
                                    name=f'camera-match-{track.id[:8]}',
                                    daemon=True,
                                ).start()
                        elif result:
                            track.quality_note = 'Đã thấy khuôn mặt · chờ mặt chính diện, đủ nét'

                    face = overlay_face(track, frame)
                    if face:
                        overlay.append(face)
                self.queue_overlay(overlay)
                self.state.frames += 1
                self.state.detected_faces += len(boxes)
                delay = interval - (monotonic() - started)
                if delay > 0:
                    sleep(delay)
        except Exception as error:
            self.state.last_error = str(error)
            LOG.exception("camera=%s pipeline failed", self.camera.id)
        finally:
            reader.stop()
            self.overlay_event.set()
            if self.overlay_thread:
                self.overlay_thread.join(timeout=0.2)
            if self.stream_monitor:
                self.stream_monitor.join(timeout=0.2)
            self.state.running = False


class WorkerSupervisor:
    def __init__(self, config: AppConfig) -> None:
        self.config = config
        self.workers: dict[str, CameraWorker] = {}
        self.states: dict[str, CameraState] = {}
        self.publisher = EventPublisher(str(config.server.event_url).rsplit('/camera-intelligence/', 1)[0], config.server.camera_key, config.server.event_timeout_seconds)

    def start(self) -> None:
        self.stop()
        for camera in self.config.cameras:
            if not camera.enabled:
                continue
            state = CameraState(camera_id=camera.id)
            worker = CameraWorker(camera, self.config, state, self.publisher)
            self.states[camera.id] = state
            self.workers[camera.id] = worker
            worker.start()

    def stop(self) -> None:
        for worker in self.workers.values():
            worker.stop()
        for worker in self.workers.values():
            worker.join(timeout=5)
        self.workers.clear()

    def status(self) -> dict:
        return {
            camera_id: {
                "running": state.running,
                "inference_ready": state.inference_ready,
                "frames": state.frames,
                "detected_faces": state.detected_faces,
                "last_error": state.last_error or state.stream_error,
                "last_frame_at": state.last_frame_at,
            }
            for camera_id, state in self.states.items()
        }

    def heartbeat(self) -> None:
        self.publisher.post('/camera-intelligence/heartbeat', {'cameras': [
            {'id': camera_id, 'running': state.running, 'inference_ready': state.inference_ready, 'frames': state.frames,
             'detected_faces': state.detected_faces, 'last_error': state.last_error or state.stream_error,
             'last_frame_at': state.last_frame_at}
            for camera_id, state in self.states.items()
        ]})
