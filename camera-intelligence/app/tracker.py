from __future__ import annotations

from dataclasses import dataclass, field
from time import monotonic
from uuid import uuid4


@dataclass(frozen=True)
class Box:
    x: float
    y: float
    width: float
    height: float
    score: float
    # YuNet returns right eye, left eye, nose, right mouth, left mouth. Keep
    # them with the detection so SFace can receive a pose-normalised crop.
    landmarks: tuple[tuple[float, float], ...] = ()

    def iou(self, other: "Box") -> float:
        left, top = max(self.x, other.x), max(self.y, other.y)
        right, bottom = min(self.x + self.width, other.x + other.width), min(self.y + self.height, other.y + other.height)
        intersection = max(0.0, right - left) * max(0.0, bottom - top)
        union = self.width * self.height + other.width * other.height - intersection
        return intersection / union if union else 0.0


@dataclass
class Track:
    id: str = field(default_factory=lambda: uuid4().hex)
    box: Box | None = None
    created_at: float = field(default_factory=monotonic)
    last_seen_at: float = field(default_factory=monotonic)
    last_recognized_at: float = 0.0
    frames: int = 0
    identity: dict | None = None


class MultiFaceTracker:
    """Deterministic IoU tracker. It is intentionally small and has no global state across cameras."""

    def __init__(self, expire_seconds: float = 2.0, min_iou: float = 0.25) -> None:
        self.expire_seconds = expire_seconds
        self.min_iou = min_iou
        self.tracks: dict[str, Track] = {}

    def update(self, boxes: list[Box], now: float | None = None) -> list[Track]:
        now = now or monotonic()
        candidates = sorted(
            ((track, box, track.box.iou(box)) for track in self.tracks.values() if track.box for box in boxes),
            key=lambda item: item[2], reverse=True,
        )
        used_tracks: set[str] = set()
        used_boxes: set[int] = set()
        indexed = {id(box): index for index, box in enumerate(boxes)}
        touched: list[Track] = []

        for track, box, iou in candidates:
            index = indexed[id(box)]
            if iou < self.min_iou or track.id in used_tracks or index in used_boxes:
                continue
            track.box, track.last_seen_at, track.frames = box, now, track.frames + 1
            used_tracks.add(track.id)
            used_boxes.add(index)
            touched.append(track)

        for index, box in enumerate(boxes):
            if index in used_boxes:
                continue
            track = Track(box=box, last_seen_at=now, frames=1)
            self.tracks[track.id] = track
            touched.append(track)

        self.tracks = {track_id: track for track_id, track in self.tracks.items() if now - track.last_seen_at <= self.expire_seconds}
        return touched
