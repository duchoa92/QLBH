# Chuyển nhận diện từ face-api sang Human

Giải nén đè lên thư mục dự án (các file giữ nguyên đường dẫn), rồi:

## Thư mục gốc dự án (Laravel + Vite)
```
npm i @vladmandic/human
npm uninstall @vladmandic/face-api
node scripts/copy-face-models.mjs
php artisan migrate        # xóa vector 128 số cũ (dữ liệu thử), khách cần đăng ký lại khuôn mặt
npm run build
```
Xóa thư mục mô hình cũ: `public/models/face` (Windows: `rmdir /s /q public\models\face`).
Nếu còn file `.env` của worker cũ, sửa `MODELS_DIR=../public/models/human` và `TF_BACKEND=auto`.

## Worker (door-worker) – Windows và Ubuntu dùng chung một cách
```
cd door-worker
rmdir /s /q node_modules    (Ubuntu: rm -rf node_modules)
npm install                 # Windows: cảnh báo tfjs-node là bình thường, npm vẫn thành công
npm run check
npm start
```
Windows tự dùng wasm, Ubuntu tự dùng tfjs-node, không cần đổi gì khi chuyển máy.

## Hiệu chỉnh ngưỡng (làm sau khi chạy được)
Ngưỡng nằm ở `config/face.php` (đặt trong `.env` của Laravel):
`FACE_MATCH_SIMILARITY` (mặc định 0.5), `FACE_MATCH_MARGIN` (0.03). Bật `FACE_LOG_SCORES=true` để xem độ giống ghi trong storage/logs.
Nhận nhầm người → tăng; không nhận ra khách quen → giảm.
