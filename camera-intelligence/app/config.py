from __future__ import annotations

from pathlib import Path
from typing import Literal

import yaml
from pydantic import BaseModel, Field, HttpUrl, field_validator


class ServerConfig(BaseModel):
    host: str = "127.0.0.1"
    port: int = Field(default=9070, ge=1, le=65535)
    event_url: HttpUrl
    camera_key: str = Field(min_length=24)
    event_timeout_seconds: float = Field(default=4, ge=1, le=15)


class InferenceConfig(BaseModel):
    device: str = "AUTO"
    detector_model: str
    recognizer_model: str
    min_face_px: int = Field(default=60, ge=24, le=1000)
    detect_fps: float = Field(default=8, ge=1, le=30)
    recognition_cooldown_seconds: float = Field(default=4, ge=1, le=60)
    match_threshold: float = Field(default=0.72, ge=0, le=1)
    match_margin: float = Field(default=0.08, ge=0, le=1)


class CameraConfig(BaseModel):
    id: str = Field(pattern=r"^[a-z][a-z0-9_-]{1,63}$")
    name: str = Field(min_length=1, max_length=120)
    enabled: bool = True
    ai_rtsp_url: str = Field(pattern=r"^rtsps?://")
    view_rtsp_url: str = Field(pattern=r"^rtsps?://")
    width: int = Field(default=1280, ge=160, le=7680)
    height: int = Field(default=720, ge=120, le=4320)
    zone: str = Field(default="default", max_length=80)


class AppConfig(BaseModel):
    server: ServerConfig
    inference: InferenceConfig
    cameras: list[CameraConfig] = []

    @field_validator("cameras")
    @classmethod
    def distinct_camera_ids(cls, cameras: list[CameraConfig]) -> list[CameraConfig]:
        ids = [camera.id for camera in cameras]
        if len(ids) != len(set(ids)):
            raise ValueError("Camera id must be unique")
        return cameras


def load_config(path: str | Path) -> AppConfig:
    source = Path(path)
    data = yaml.safe_load(source.read_text(encoding="utf-8")) or {}
    config = AppConfig.model_validate(data)

    # Model paths in config are deliberately relative to config.yaml. This keeps
    # the native Windows service and the Linux container on the same layout.
    base = source.resolve().parent
    config.inference.detector_model = str((base / config.inference.detector_model).resolve())
    config.inference.recognizer_model = str((base / config.inference.recognizer_model).resolve())

    return config
