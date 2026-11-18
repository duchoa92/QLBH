from __future__ import annotations

import argparse
import base64
import hmac
import logging
import threading
from time import sleep
from contextlib import asynccontextmanager
from io import BytesIO

import numpy as np
import uvicorn
from fastapi import FastAPI, Header, HTTPException
from fastapi.responses import Response
from PIL import Image
from pydantic import BaseModel, Field

from .config import AppConfig, load_config
from .inference_yunet import YuNetSFaceEngine
from .worker import WorkerSupervisor


class EnrollmentRequest(BaseModel):
    images: list[str] = Field(min_length=1, max_length=5)


def decode_image(value: str) -> np.ndarray:
    encoded = value.split(',', 1)[1] if value.startswith('data:') and ',' in value else value
    raw = base64.b64decode(encoded, validate=True)
    if len(raw) > 8 * 1024 * 1024:
        raise ValueError('image is too large')
    with Image.open(BytesIO(raw)) as image:
        image = image.convert('RGB')
        if image.width < 80 or image.height < 80 or image.width * image.height > 12_000_000:
            raise ValueError('image dimensions are invalid')
        # Camera frames used by the worker are BGR, so keep enrolment identical.
        return np.ascontiguousarray(np.asarray(image)[:, :, ::-1])


def create_app(config_path: str) -> FastAPI:
    state: dict[str, AppConfig | WorkerSupervisor | YuNetSFaceEngine] = {}

    @asynccontextmanager
    async def lifespan(_: FastAPI):
        config = load_config(config_path)
        supervisor = WorkerSupervisor(config)
        enrollment_engine = YuNetSFaceEngine(
            config.inference.detector_model,
            config.inference.recognizer_model,
            config.inference.device,
            config.inference.min_face_px,
        )
        state["config"], state["supervisor"], state["enrollment_engine"] = config, supervisor, enrollment_engine
        supervisor.start()
        def report_heartbeat() -> None:
            while True:
                try:
                    # /reload replaces the supervisor. Always resolve the
                    # current instance so Laravel never receives the stopped
                    # worker state from before a camera/configuration change.
                    current = state.get("supervisor")
                    if isinstance(current, WorkerSupervisor):
                        current.heartbeat()
                except Exception as error:
                    logging.getLogger(__name__).warning('heartbeat failed: %s', error)
                sleep(5)
        threading.Thread(target=report_heartbeat, name='qlbh-heartbeat', daemon=True).start()
        yield
        supervisor.stop()

    app = FastAPI(title="QLBH Camera Intelligence", version="1.0", lifespan=lifespan)

    @app.get("/healthz")
    def healthz() -> dict:
        supervisor = state.get("supervisor")
        if not isinstance(supervisor, WorkerSupervisor):
            raise HTTPException(503, "service is starting")
        cameras = supervisor.status()
        return {"ok": all(item["running"] and item["inference_ready"] for item in cameras.values()), "cameras": cameras}

    @app.get("/metrics")
    def metrics() -> dict:
        supervisor = state.get("supervisor")
        return {"cameras": supervisor.status() if isinstance(supervisor, WorkerSupervisor) else {}}

    @app.post("/reload")
    def reload_config(x_camera_key: str = Header(default="")) -> dict:
        supervisor = state.get("supervisor")
        config = state.get("config")
        if not isinstance(supervisor, WorkerSupervisor) or not isinstance(config, AppConfig):
            raise HTTPException(503, "service is starting")
        if not hmac.compare_digest(x_camera_key, config.server.camera_key):
            raise HTTPException(401, "invalid camera key")
        config = load_config(config_path)
        # Recreate the publisher too: a regenerated camera key must take effect
        # immediately, not after the next Windows/Linux service restart.
        supervisor.stop()
        replacement = WorkerSupervisor(config)
        replacement.start()
        state["config"], state["supervisor"], state["enrollment_engine"] = config, replacement, YuNetSFaceEngine(
            config.inference.detector_model, config.inference.recognizer_model, config.inference.device, config.inference.min_face_px,
        )
        return {"ok": True, "cameras": list(replacement.workers)}

    @app.get("/snapshot")
    def snapshot(camera: str = 'door-1', x_camera_key: str = Header(default="")) -> Response:
        """Return the current in-memory RTSP frame for controlled enrolment.

        Laravel proxies this endpoint to an authenticated operator.  Requiring
        the same shared key as /enroll means this local endpoint cannot become
        an unauthenticated camera feed on the LAN.
        """
        config = state.get("config")
        supervisor = state.get("supervisor")
        if not isinstance(config, AppConfig) or not isinstance(supervisor, WorkerSupervisor):
            raise HTTPException(503, "service is starting")
        if not hmac.compare_digest(x_camera_key, config.server.camera_key):
            raise HTTPException(401, "invalid camera key")
        camera_state = supervisor.states.get(camera)
        if camera_state is None:
            raise HTTPException(404, "camera is not configured")
        snapshot = camera_state.snapshot_jpeg()
        if snapshot is None:
            raise HTTPException(503, "camera has not received a frame yet")
        image, face_count = snapshot
        return Response(content=image, media_type='image/jpeg', headers={
            'Cache-Control': 'no-store',
            # Laravel forwards these two non-sensitive facts to the enrolment
            # modal. It then trusts this YuNet result instead of the retired
            # browser-only Human detector, which missed distant CCTV faces.
            'X-Face-Engine': 'yunet-sface',
            'X-Face-Count': str(face_count),
        })

    @app.post("/enroll")
    def enroll(payload: EnrollmentRequest, x_camera_key: str = Header(default="")) -> dict:
        config = state.get("config")
        engine = state.get("enrollment_engine")
        if not isinstance(config, AppConfig) or not isinstance(engine, YuNetSFaceEngine):
            raise HTTPException(503, "service is starting")
        if not hmac.compare_digest(x_camera_key, config.server.camera_key):
            raise HTTPException(401, "invalid camera key")
        descriptors = []
        for index, encoded in enumerate(payload.images):
            try:
                frame = decode_image(encoded)
                boxes = engine.detect(frame)
                if len(boxes) != 1:
                    raise ValueError("exactly one clear face is required")
                result = engine.recognize(frame, boxes[0])
                if result is None or result.quality < 0.55:
                    raise ValueError("face is not clear enough")
                descriptors.append(result.descriptor)
            # Bad uploads must be a client validation error; never let malformed
            # image bytes turn the internal enrolment endpoint into a 500.
            except Exception as error:
                raise HTTPException(422, f"Sample {index + 1}: {error}") from error
        return {"descriptors": descriptors}

    return app


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--config", default="config.yaml")
    args = parser.parse_args()
    config = load_config(args.config)
    logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(name)s %(message)s")
    uvicorn.run(create_app(args.config), host=config.server.host, port=config.server.port)


if __name__ == "__main__":
    main()
