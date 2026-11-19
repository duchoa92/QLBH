<?php

return [
    // Kept for the legacy same-machine WHEP proxy.
    'mediamtx_webrtc_url' => env('MEDIAMTX_WEBRTC_URL', 'http://127.0.0.1:8889'),
    // Browsers signal directly to the local MediaMTX HTTPS listener. This
    // avoids tying a long-lived WebRTC negotiation to a PHP/Laravel worker.
    'mediamtx_browser_webrtc_url' => env('MEDIAMTX_BROWSER_WEBRTC_URL', 'https://'.(parse_url((string) env('APP_URL', 'https://127.0.0.1'), PHP_URL_HOST) ?: '127.0.0.1').':8889'),
    // Laravel calls this loopback-only endpoint to convert enrolment images to
    // the same SFace embedding used by the background camera service.
    'service_url' => env('CAMERA_INTELLIGENCE_URL', 'http://127.0.0.1:9070'),
    'default_view_path' => env('MEDIAMTX_DEFAULT_VIEW_PATH', 'door-1-view'),
];
