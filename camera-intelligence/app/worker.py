from __future__ import annotations

import base64
import logging
import threading
from dataclasses import dataclass, field
from time import monotonic, sleep

import cv2
import numpy as np

from .config import AppConfig, CameraConfig
from .events import EventPublisher
from .inference_yunet import YuNetSFaceEngine
from .stream import RtspFrames
from .tracker import MultiFaceTracker

LOG = logging.getLogger(__name__)
STABLE_TRACK_FRAMES = 3
THUMB_MAX_CHARACTERS = 29_000


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


@dataclass
class CameraState:
    camera_id: str
    running: bool = False
    frames: int = 0
    detected_faces: int = 0
    last_error: str | None = None
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

    def stop(self) -> None:
        self.stop_event.set()

    def run(self) -> None:
        try:
            engine = YuNetSFaceEngine(
                self.config.inference.detector_model,
                self.config.inference.recognizer_model,
                self.config.inference.device,
                self.config.inference.min_face_px,
            )
            self.state.inference_ready = True
        except Exception as error:
            self.state.last_error = str(error)
            LOG.exception("camera=%s cannot load approved inference models", self.camera.id)
            return

        interval = 1 / self.config.inference.detect_fps
        # Analyse the same H.264 sub-stream that MediaMTX sends to the web
        # page.  A camera main-stream and sub-stream are frequently different
        # encodes (and, on some devices, can drift), which made a person visible
        # in WebRTC while the worker was inspecting a different frame.  The
        # configured main stream remains available as a fallback for old/manual
        # configs that do not provide a view stream.
        analysis_url = self.camera.view_rtsp_url or self.camera.ai_rtsp_url
        reader = RtspFrames(analysis_url, self.camera.width, self.camera.height)
        self.state.running = True
        try:
            for frame in reader.frames():
                if self.stop_event.is_set():
                    break
                started = monotonic()
                boxes = engine.detect(frame)
                self.state.update_snapshot(frame, len(boxes))
                tracks = self.tracker.update(boxes, started)
                # Only recognise a stable track; the descriptor is matched by QLBH
                # so customer profiles never have to be copied to this service.
                overlay = []
                for track in tracks:
                    # A single detector hit is not enough to publish a box,
                    # create a visit or attach a customer's identity.  Requiring
                    # three adjacent frames prevents a door edge/texture from
                    # briefly becoming a named person in the UI.
                    if track.frames < STABLE_TRACK_FRAMES:
                        continue
                    if track.box and started - track.last_recognized_at >= self.config.inference.recognition_cooldown_seconds:
                        result = engine.recognize(frame, track.box)
                        if result and result.quality >= 0.55:
                            # Keep the crop from the exact accepted frame. A
                            # later frame may already show another person or
                            # no face at all by the time QLBH handles the POST.
                            thumb = make_face_thumb(frame, track.box)
                            track.last_recognized_at = started
                            try:
                                match = self.publisher.post('/camera-intelligence/match', {'descriptors': [result.descriptor]})
                                track.identity = match.get('customer') if match.get('matched') else None
                                self.publisher.post('/camera-intelligence/visits', {
                                    'customer_id': track.identity.get('id') if track.identity else None,
                                    'engine': 'sface-v1',
                                    'descriptor': result.descriptor,
                                    'thumb': thumb,
                                })
                                self.state.last_error = None
                            except Exception as error:
                                self.state.last_error = f'Cannot match with QLBH: {error}'
                    box = track.box
                    if box:
                        overlay.append({
                            'id': str(track.id),
                            # NumPy scalars are not JSON serialisable by urllib.
                            'x': float(round(float(box.x) / frame.shape[1], 5)),
                            'y': float(round(float(box.y) / frame.shape[0], 5)),
                            'w': float(round(float(box.width) / frame.shape[1], 5)),
                            'h': float(round(float(box.height) / frame.shape[0], 5)),
                            'kind': 'known' if track.identity else 'unknown',
                            'label': track.identity.get('full_name') if track.identity else 'Khách chưa có tên',
                        })
                try:
                    self.publisher.post('/camera-intelligence/faces', {'camera_id': self.camera.id, 'faces': overlay})
                    if self.state.last_error and self.state.last_error.startswith('Cannot update overlays:'):
                        self.state.last_error = None
                except Exception as error:
                    self.state.last_error = f'Cannot update overlays: {error}'
                self.state.frames += 1
                self.state.detected_faces += len(boxes)
                self.state.last_frame_at = monotonic()
                delay = interval - (monotonic() - started)
                if delay > 0:
                    sleep(delay)
        except Exception as error:
            self.state.last_error = str(error)
            LOG.exception("camera=%s pipeline failed", self.camera.id)
        finally:
            reader.stop()
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
                "last_error": state.last_error,
            }
            for camera_id, state in self.states.items()
        }

    def heartbeat(self) -> None:
        self.publisher.post('/camera-intelligence/heartbeat', {'cameras': [
            {'id': camera_id, 'running': state.running, 'inference_ready': state.inference_ready, 'frames': state.frames,
             'detected_faces': state.detected_faces, 'last_error': state.last_error}
            for camera_id, state in self.states.items()
        ]})
