<?php

return [
    /*
    | Khóa cho worker camera cửa. Mặc định KHÔNG cần đặt: hệ thống tự sinh khóa và lưu (mã hóa) trong Cài đặt.
    | Biến DOOR_CAMERA_KEY trong .env chỉ còn là tùy chọn (dùng khi muốn tự đặt khóa cố định).
    */
    'key' => env('DOOR_CAMERA_KEY', ''),

    // Worker không gửi nhịp quá số giây này thì coi là ngừng chạy.
    'online_seconds' => 30,

    // Lượt khách có nhịp trong khoảng này được coi là "đang đứng ở cửa".
    'present_seconds' => 20,

    // go2rtc API phải chỉ mở nội bộ; Laravel quản lý stream và làm proxy cho WebRTC signaling.
    'go2rtc_api_url' => env('GO2RTC_API_URL', 'http://127.0.0.1:1984'),
    'go2rtc_api_username' => env('GO2RTC_API_USERNAME', ''),
    'go2rtc_api_password' => env('GO2RTC_API_PASSWORD', ''),
    'go2rtc_stream_name' => env('GO2RTC_STREAM_NAME', 'qlbh_door_camera'),
    'go2rtc_webrtc_stream_name' => env('GO2RTC_WEBRTC_STREAM_NAME', 'qlbh_door_camera_webrtc'),
    'go2rtc_worker_frame_url' => env('GO2RTC_WORKER_FRAME_URL', 'http://127.0.0.1:1984/api/frame.jpeg'),
];
