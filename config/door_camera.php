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
];
