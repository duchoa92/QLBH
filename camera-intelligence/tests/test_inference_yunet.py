from __future__ import annotations

import unittest

import numpy as np

from app.inference_yunet import SFACE_TEMPLATE, align_sface, nchw, similarity_matrix
from app.config import CameraConfig
from app.stream import STREAM_REFRESH_SECONDS, RtspFrames
from app.tracker import Box, Track
from app.worker import DISPLAY_TRACK_FRAMES, THUMB_MAX_CHARACTERS, analysis_stream_url, make_face_thumb, overlay_face


class InferenceTests(unittest.TestCase):
    def test_rtsp_reader_prefers_udp_for_low_latency(self) -> None:
        reader = RtspFrames('rtsp://camera/sub', 1280, 720)
        self.assertEqual(reader._transport, 'udp')
        self.assertLessEqual(STREAM_REFRESH_SECONDS, 5)

    def test_analysis_uses_direct_view_stream_not_buffered_mediamtx_restream(self) -> None:
        camera = CameraConfig(
            id='door-1', name='Door', ai_rtsp_url='rtsp://camera/main',
            view_rtsp_url='rtsp://camera/sub', analysis_rtsp_url='rtsp://127.0.0.1:8554/door-1-view',
        )
        self.assertEqual(analysis_stream_url(camera), 'rtsp://camera/sub')

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

    def test_overlay_explains_confirmed_face_before_identity_is_known(self) -> None:
        frame = np.zeros((720, 1280, 3), dtype=np.uint8)
        track = Track(box=Box(400, 180, 180, 180, 0.95), frames=DISPLAY_TRACK_FRAMES)
        scanning = overlay_face(track, frame)
        self.assertEqual(scanning['kind'], 'scanning')
        self.assertIn('Đã thấy khuôn mặt', scanning['label'])

        track.matching = True
        resolving = overlay_face(track, frame)
        self.assertEqual(resolving['kind'], 'scanning')
        self.assertIn('đối chiếu', resolving['label'])

        track.matching = False
        track.match_checked = True
        unknown = overlay_face(track, frame)
        self.assertEqual(unknown['kind'], 'unknown')
        self.assertIn('chưa đăng ký', unknown['label'])


if __name__ == "__main__":
    unittest.main()
