from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path

import numpy as np
from openvino import Core

from .tracker import Box

INPUT, EMBED = 640, 112
STRIDES = (8, 16, 32)


@dataclass
class Recognition:
    descriptor: list[float]
    quality: float


class YuNetSFaceEngine:
    """YuNet + SFace loaded with OpenVINO; no Node, TensorFlow or browser runtime."""

    def __init__(self, detector_path: str, recognizer_path: str, device: str, min_face_px: int) -> None:
        for filename in (detector_path, recognizer_path):
            if not Path(filename).is_file():
                raise FileNotFoundError(f"Missing approved model: {filename}")
        core = Core()
        self.detector = core.compile_model(detector_path, device)
        self.recognizer = core.compile_model(recognizer_path, device)
        self.detector_input, self.recognizer_input = self.detector.input(0), self.recognizer.input(0)
        self.min_face_px = min_face_px

    @staticmethod
    def _resize(image: np.ndarray, width: int, height: int) -> np.ndarray:
        y = np.linspace(0, image.shape[0] - 1, height).astype(np.int32)
        x = np.linspace(0, image.shape[1] - 1, width).astype(np.int32)
        return image[y][:, x]

    @staticmethod
    def _nchw(image: np.ndarray) -> np.ndarray:
        return np.ascontiguousarray(image.transpose(2, 0, 1)[None].astype(np.float32))

    @staticmethod
    def _iou(a: Box, b: Box) -> float:
        left, top = max(a.x, b.x), max(a.y, b.y)
        right, bottom = min(a.x + a.width, b.x + b.width), min(a.y + a.height, b.y + b.height)
        intersection = max(0.0, right - left) * max(0.0, bottom - top)
        union = a.width * a.height + b.width * b.height - intersection
        return intersection / union if union else 0.0

    def detect(self, frame: np.ndarray) -> list[Box]:
        values = list(self.detector({self.detector_input: self._nchw(self._resize(frame, INPUT, INPUT))}).values())
        classes, objects, bboxes = values[:3], values[3:6], values[6:9]
        candidates: list[Box] = []
        for part, stride in enumerate(STRIDES):
            cells, cls = INPUT // stride, classes[part].reshape(-1)
            obj, bbox = objects[part].reshape(-1), bboxes[part].reshape(-1, 4)
            for index, (class_score, object_score) in enumerate(zip(cls, obj)):
                score = float(np.sqrt(max(0, class_score) * max(0, object_score)))
                if score < 0.65:
                    continue
                col, row = index % cells, index // cells
                cx = (col + bbox[index][0]) * stride * frame.shape[1] / INPUT
                cy = (row + bbox[index][1]) * stride * frame.shape[0] / INPUT
                width = min(float(np.exp(np.clip(bbox[index][2], -8, 8)) * stride * frame.shape[1] / INPUT), frame.shape[1])
                height = min(float(np.exp(np.clip(bbox[index][3], -8, 8)) * stride * frame.shape[0] / INPUT), frame.shape[0])
                box = Box(max(0, cx - width / 2), max(0, cy - height / 2), width, height, score)
                if min(width, height) >= self.min_face_px:
                    candidates.append(box)
        kept: list[Box] = []
        for box in sorted(candidates, key=lambda item: item.score, reverse=True):
            if all(self._iou(box, other) < 0.3 for other in kept):
                kept.append(box)
            if len(kept) == 12:
                break
        return kept

    def recognize(self, frame: np.ndarray, box: Box) -> Recognition | None:
        crop = frame[int(box.y):int(box.y + box.height), int(box.x):int(box.x + box.width)]
        if crop.size == 0:
            return None
        raw = next(iter(self.recognizer({self.recognizer_input: self._nchw(self._resize(crop, EMBED, EMBED))}).values())).reshape(-1).astype(np.float32)
        norm = np.linalg.norm(raw)
        if norm <= 1e-8:
            return None
        quality = min(1.0, box.score * min(box.width, box.height) / max(1, self.min_face_px * 2))
        return Recognition(descriptor=(raw / norm).tolist(), quality=float(quality))
