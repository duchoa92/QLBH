"""Fetch the two approved OpenCV Zoo face models and verify their SHA-256 sums.

The models intentionally stay out of Git because SFace is large.  This script
is the single reproducible Windows/Linux setup path instead of asking an
operator to find binaries from a third-party mirror.
"""

from __future__ import annotations

import hashlib
import os
import tempfile
import urllib.request
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
MODELS = (
    {
        "filename": "face_detection_yunet_2023mar.onnx",
        "sha256": "8f2383e4dd3cfbb4553ea8718107fc0423210dc964f9f4280604804ed2552fa4",
        "url": "https://media.githubusercontent.com/media/opencv/opencv_zoo/main/models/face_detection_yunet/face_detection_yunet_2023mar.onnx",
    },
    {
        "filename": "face_recognition_sface_2021dec.onnx",
        "sha256": "0ba9fbfa01b5270c96627c4ef784da859931e02f04419c829e83484087c34e79",
        "url": "https://media.githubusercontent.com/media/opencv/opencv_zoo/main/models/face_recognition_sface/face_recognition_sface_2021dec.onnx",
    },
)


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as source:
        for chunk in iter(lambda: source.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def fetch(model: dict[str, str]) -> None:
    target = ROOT / "models" / model["filename"]
    target.parent.mkdir(parents=True, exist_ok=True)
    if target.is_file() and sha256(target) == model["sha256"]:
        print(f"OK {target.name}")
        return
    if target.exists():
        raise RuntimeError(f"Refusing to overwrite {target}: SHA-256 does not match the approved model.")

    print(f"Downloading {target.name} from OpenCV Zoo...")
    descriptor, raw_path = tempfile.mkstemp(dir=target.parent, prefix=target.name + ".", suffix=".part")
    temp_path = Path(raw_path)
    try:
        # NamedTemporaryFile remains locked on Windows. Open and close the file
        # explicitly so checksum verification and os.replace both work there.
        with os.fdopen(descriptor, "wb") as temporary:
            request = urllib.request.Request(model["url"], headers={"User-Agent": "QLBH-Camera-Intelligence/1.0"})
            with urllib.request.urlopen(request, timeout=90) as response:
                while chunk := response.read(1024 * 1024):
                    temporary.write(chunk)
        actual = sha256(temp_path)
        if actual != model["sha256"]:
            raise RuntimeError(f"Checksum mismatch for {target.name}: {actual}")
        os.replace(temp_path, target)
        print(f"Saved {target.name}")
    finally:
        temp_path.unlink(missing_ok=True)


if __name__ == "__main__":
    for item in MODELS:
        fetch(item)
