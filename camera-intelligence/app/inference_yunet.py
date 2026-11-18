from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path

import cv2
import numpy as np
from openvino import Core
from PIL import Image

from .tracker import Box


INPUT, EMBED = 640, 112
SFACE_TEMPLATE = np.asarray(
    ((38.2946, 51.6963), (73.5318, 51.5014), (56.0252, 71.7366), (41.5493, 92.3655), (70.7299, 92.2041)),
    dtype=np.float32,
)


@dataclass
class Recognition:
    descriptor: list[float]
    quality: float


def nchw(image: np.ndarray, *, swap_rb: bool = False) -> np.ndarray:
    """Build an OpenVINO tensor from a BGR image, matching OpenCV's blobs."""
    if swap_rb:
        image = image[..., ::-1]
    return np.ascontiguousarray(image.transpose(2, 0, 1)[None].astype(np.float32))


def yunet_tensor(image: np.ndarray) -> np.ndarray:
    """Match OpenCV Zoo YuNet preprocessing: RGB image, then RGB mean removal."""
    rgb = image[..., ::-1].astype(np.float32)
    rgb -= np.asarray((104.0, 117.0, 123.0), dtype=np.float32)
    return np.ascontiguousarray(rgb.transpose(2, 0, 1)[None])


def letterbox(image: np.ndarray, size: int = INPUT) -> tuple[np.ndarray, float, int, int]:
    """Preserve face geometry while fitting the fixed-size YuNet input."""
    height, width = image.shape[:2]
    scale = min(size / width, size / height)
    resized_width, resized_height = max(1, round(width * scale)), max(1, round(height * scale))
    resized = np.asarray(Image.fromarray(image).resize((resized_width, resized_height), Image.Resampling.BILINEAR))
    canvas = np.zeros((size, size, 3), dtype=np.uint8)
    left, top = (size - resized_width) // 2, (size - resized_height) // 2
    canvas[top:top + resized_height, left:left + resized_width] = resized
    return canvas, scale, left, top


def similarity_matrix(source: np.ndarray, target: np.ndarray = SFACE_TEMPLATE) -> np.ndarray | None:
    """Return the 2x3 similarity transform used by OpenCV FaceRecognizerSF."""
    if source.shape != (5, 2):
        return None
    source_mean, target_mean = source.mean(axis=0), target.mean(axis=0)
    source_zero, target_zero = source - source_mean, target - target_mean
    variance = float(np.mean(np.sum(source_zero * source_zero, axis=1)))
    if variance <= 1e-7:
        return None
    covariance = (target_zero.T @ source_zero) / len(source)
    u, singular, vt = np.linalg.svd(covariance)
    diagonal = np.ones(2, dtype=np.float32)
    if np.linalg.det(covariance) < 0:
        diagonal[-1] = -1
    rotation = u @ np.diag(diagonal) @ vt
    scale = float(np.sum(singular * diagonal) / variance)
    translation = target_mean - scale * (rotation @ source_mean)
    return np.column_stack((scale * rotation, translation)).astype(np.float32)


def align_sface(image: np.ndarray, landmarks: tuple[tuple[float, float], ...]) -> np.ndarray | None:
    """Warp five YuNet landmarks into the 112px SFace canonical pose."""
    matrix = similarity_matrix(np.asarray(landmarks, dtype=np.float32))
    if matrix is None:
        return None
    linear, translation = matrix[:, :2], matrix[:, 2]
    try:
        inverse = np.linalg.inv(linear)
    except np.linalg.LinAlgError:
        return None
    yy, xx = np.indices((EMBED, EMBED), dtype=np.float32)
    destination = np.stack((xx.ravel(), yy.ravel()), axis=0)
    source = inverse @ (destination - translation[:, None])
    sx, sy = source[0], source[1]
    height, width = image.shape[:2]
    valid = (sx >= 0) & (sy >= 0) & (sx <= width - 1) & (sy <= height - 1)
    x0, y0 = np.floor(sx).astype(np.int32), np.floor(sy).astype(np.int32)
    x0, y0 = np.clip(x0, 0, width - 1), np.clip(y0, 0, height - 1)
    x1, y1 = np.clip(x0 + 1, 0, width - 1), np.clip(y0 + 1, 0, height - 1)
    wx, wy = (sx - x0)[:, None], (sy - y0)[:, None]
    top = image[y0, x0].astype(np.float32) * (1 - wx) + image[y0, x1].astype(np.float32) * wx
    bottom = image[y1, x0].astype(np.float32) * (1 - wx) + image[y1, x1].astype(np.float32) * wx
    pixels = top * (1 - wy) + bottom * wy
    pixels[~valid] = 0
    return np.clip(pixels.reshape(EMBED, EMBED, 3), 0, 255).astype(np.uint8)


class YuNetSFaceEngine:
    """YuNet detector + aligned RGB SFace descriptor using native OpenVINO."""

    def __init__(self, detector_path: str, recognizer_path: str, device: str, min_face_px: int) -> None:
        for filename in (detector_path, recognizer_path):
            if not Path(filename).is_file():
                raise FileNotFoundError(f"Missing approved model: {filename}")
        core = Core()
        # FaceDetectorYN owns YuNet's decode + NMS implementation. Keeping this
        # in OpenCV avoids subtly wrong output-tensor ordering/coordinates in a
        # hand-written decoder (which can silently miss even a close face).
        self.face_detector = cv2.FaceDetectorYN.create(
            # 0.65 removes weak texture/door-frame false positives while the
            # validated door-camera face scores around 0.9.  Track stability
            # below supplies the second guard against a one-frame false hit.
            detector_path, '', (INPUT, INPUT), 0.65, 0.3, 5000,
        )
        self.recognizer = core.compile_model(recognizer_path, device)
        self.recognizer_input = self.recognizer.input(0)
        self.min_face_px = min_face_px

    def detect(self, frame: np.ndarray) -> list[Box]:
        model_frame, scale, left, top = letterbox(frame)
        _, rows = self.face_detector.detect(model_frame)
        if rows is None:
            return []
        faces: list[Box] = []
        for row in rows:
            x, y, width, height = (float(value) for value in row[:4])
            if min(width, height) / scale < self.min_face_px:
                continue
            points = tuple(
                ((float(row[4 + point * 2]) - left) / scale,
                 (float(row[5 + point * 2]) - top) / scale)
                for point in range(5)
            )
            faces.append(Box(
                (x - left) / scale, (y - top) / scale, width / scale, height / scale,
                float(row[14]), points,
            ))
        return faces[:12]

    def recognize(self, frame: np.ndarray, box: Box) -> Recognition | None:
        aligned = align_sface(frame, box.landmarks)
        if aligned is None:
            return None
        result = self.recognizer({self.recognizer_input: nchw(aligned, swap_rb=True)})
        raw = next(iter(result.values())).reshape(-1).astype(np.float32)
        norm = np.linalg.norm(raw)
        if norm <= 1e-8:
            return None
        quality = min(1.0, box.score * min(box.width, box.height) / max(1, self.min_face_px * 2))
        return Recognition(descriptor=(raw / norm).tolist(), quality=float(quality))
