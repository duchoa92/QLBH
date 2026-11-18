<?php

/*
| Ngưỡng so khớp khuôn mặt (độ giống 0..1, tính như hàm similarity của Human; càng cao càng giống).
| Có thể chỉnh trong .env mà không cần sửa code. Cần hiệu chỉnh trên khách thật của cửa hàng:
|  - Hay nhận nhầm người  → tăng FACE_MATCH_SIMILARITY.
|  - Hay không nhận ra khách quen → giảm FACE_MATCH_SIMILARITY.
| Đặt FACE_LOG_SCORES=true để ghi độ giống cao nhất của mỗi lần so khớp vào log (storage/logs) khi hiệu chỉnh.
*/
return [
    // Độ giống tối thiểu để coi là cùng người (cũng dùng để gộp các lần thấy cùng một khách lạ).
    'match_similarity' => (float) env('FACE_MATCH_SIMILARITY', 0.5),

    // Người giống nhất phải hơn người giống nhì ít nhất mức này, nếu không coi là chưa chắc chắn.
    'margin' => (float) env('FACE_MATCH_MARGIN', 0.03),

    // SFace emits a L2-normalized 128-dimension embedding. These are native cosine values, not Human scores.
    // Start conservatively and calibrate with the compare report before making SFace the primary engine.
    'sface_match_cosine' => (float) env('SFACE_MATCH_COSINE', 0.45),
    'sface_margin' => (float) env('SFACE_MATCH_MARGIN', 0.05),

    // Tự học sau khi nhân viên xác nhận "Đúng": vector mới phải đủ giống khách đó nhưng chưa trùng hẳn mẫu đã có.
    'feedback_min_similarity' => (float) env('FACE_FEEDBACK_MIN_SIMILARITY', 0.45),
    'feedback_duplicate_similarity' => (float) env('FACE_FEEDBACK_DUPLICATE_SIMILARITY', 0.95),

    'log_scores' => (bool) env('FACE_LOG_SCORES', false),
];
