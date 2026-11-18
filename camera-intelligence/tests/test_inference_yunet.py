from __future__ import annotations

import unittest

import numpy as np

from app.inference_yunet import SFACE_TEMPLATE, align_sface, nchw, similarity_matrix
from app.tracker import Box
from app.worker import THUMB_MAX_CHARACTERS, make_face_thumb


class InferenceTests(unittest.TestCase):
    def test_similarity_matrix_keeps_canonical_landmarks_stable(self) -> None:
        matrix = similarity_matrix(SFACE_TEMPLATE)
        self.assertIsNotNone(matrix)
        assert matrix is not None
        np.testing.assert_allclose(matrix[:, :2], np.eye(2), atol=1e-4)
        np.testing.assert_allclose(matrix[:, 2], np.zeros(2), atol=1e-4)

    def test_sface_tensor_swaps_bgr_to_rgb(self) -> None:
        tensor = nchw(np.array([[[10, 20, 30]]], dtype=np.uint8), swap_rb=True)
        np.testing.assert_array_equal(tensor[0, :, 0, 0], np.array([30, 20, 10], dtype=np.float32))

    def test_alignment_returns_canonical_sface_size(self) -> None:
        image = np.full((128, 128, 3), 127, dtype=np.uint8)
        aligned = align_sface(image, tuple(map(tuple, SFACE_TEMPLATE)))
        self.assertIsNotNone(aligned)
        assert aligned is not None
        self.assertEqual(aligned.shape, (112, 112, 3))

    def test_visit_thumbnail_is_a_bounded_jpeg_crop(self) -> None:
        frame = np.random.default_rng(42).integers(0, 256, size=(720, 1280, 3), dtype=np.uint8)
        thumb = make_face_thumb(frame, Box(520, 180, 180, 180, 0.95))
        self.assertIsNotNone(thumb)
        assert thumb is not None
        self.assertTrue(thumb.startswith('data:image/jpeg;base64,'))
        self.assertLessEqual(len(thumb), THUMB_MAX_CHARACTERS)


if __name__ == "__main__":
    unittest.main()
