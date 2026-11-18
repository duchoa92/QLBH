from __future__ import annotations

import logging
import threading
from dataclasses import dataclass, field
from time import monotonic, sleep

from .config import AppConfig, CameraConfig
from .events import EventPublisher
from .inference_yunet import YuNetSFaceEngine
from .stream import RtspFrames
from .tracker import MultiFaceTracker

LOG = logging.getLogger(__name__)


@dataclass
class CameraState:
    camera_id: str
    running: bool = False
    frames: int = 0
    detected_faces: int = 0
    last_error: str | None = None
    last_frame_at: float | None = None
    inference_ready: bool = False


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
        reader = RtspFrames(self.camera.ai_rtsp_url, self.camera.width, self.camera.height)
        self.state.running = True
        try:
            for frame in reader.frames():
                if self.stop_event.is_set():
                    break
                started = monotonic()
                boxes = engine.detect(frame)
                tracks = self.tracker.update(boxes, started)
                # Only recognise a stable track; the descriptor is matched by QLBH
                # so customer profiles never have to be copied to this service.
                overlay = []
                for track in tracks:
                    if track.box and started - track.last_recognized_at >= self.config.inference.recognition_cooldown_seconds:
                        result = engine.recognize(frame, track.box)
                        if result and result.quality >= 0.55:
                            track.last_recognized_at = started
                            try:
                                match = self.publisher.post('/camera-intelligence/match', {'descriptors': [result.descriptor]})
                                track.identity = match.get('customer') if match.get('matched') else None
                                self.publisher.post('/camera-intelligence/visits', {
                                    'customer_id': track.identity.get('id') if track.identity else None,
                                    'engine': 'sface-v1',
                                    'descriptor': result.descriptor,
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
