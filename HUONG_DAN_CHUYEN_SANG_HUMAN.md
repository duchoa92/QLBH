# Ghi chú chuyển mô hình nhận diện sang Human

Tài liệu này chỉ ghi lại thay đổi mô hình trước đây. Hướng dẫn cài đặt và chạy hệ thống hiện tại nằm ở `HUONG_DAN_CAI_DAT_VA_TRIEN_KHAI.md` và `camera-ai/README.md`.

Trình duyệt và Camera AI dùng chung mô hình Human trong `public/models/human`. Nếu cần tạo lại các tệp model sau khi cài dependency ở thư mục gốc, chạy:

```bash
node scripts/copy-face-models.mjs
```

Camera AI hiện nằm trong `camera-ai` và chạy cùng go2rtc qua camera stack. Các bước vận hành hiện tại được mô tả trong README và hướng dẫn triển khai.

Ngưỡng so khớp được tinh chỉnh trong Cài đặt → Khách hàng → Tinh chỉnh nhận diện.
