<?php

return [
    // Laravel proxies WHEP signalling to keep MediaMTX private and enforce QLBH permissions.
    'mediamtx_webrtc_url' => env('MEDIAMTX_WEBRTC_URL', 'http://127.0.0.1:8889'),
    // Laravel calls this loopback-only endpoint to convert enrolment images to
    // the same SFace embedding used by the background camera service.
    'service_url' => env('CAMERA_INTELLIGENCE_URL', 'http://127.0.0.1:9070'),
    'default_view_path' => env('MEDIAMTX_DEFAULT_VIEW_PATH', 'door-1-view'),
];
