# Nhận diện khách bằng khuôn mặt ở POS

## Cài đặt (làm một lần)
1. Chép file vào project, rồi:
   ```
   npm i @vladmandic/face-api
   node scripts/copy-face-models.mjs      # chép mô hình vào public/models/face
   php artisan migrate                    # tạo bảng customer_face_profiles
   npm run build                          # (hoặc npm run dev)
   ```
2. **Camera chỉ hoạt động khi mở bằng HTTPS hoặc localhost.** Nếu POS đang truy cập qua `http://192.168.x.x`
   thì trình duyệt sẽ chặn camera. Cần bật HTTPS cho máy chủ (hoặc dùng máy POS mở bằng `localhost`).

## Bật tính năng
Mặc định **tắt**. Vào **Thiết lập → Khách hàng → Nhận diện khách bằng khuôn mặt** và bật công tắc. Khi tắt, nút đăng ký và POS ngừng nhận diện
(dữ liệu đã đăng ký vẫn giữ, vẫn xóa được).

## Cách dùng
- **Đăng ký:** trang Khách hàng → mở khách (nút Sửa) → khung **Nhận diện khuôn mặt** → "Đăng ký khuôn mặt" (hoặc nút biểu tượng khuôn mặt ở cuối dòng) → tích xác nhận khách đồng ý → "Chụp & lưu"
  (chụp 3 mẫu). Có nút xóa dữ liệu bất cứ lúc nào.
- **Ở POS:** dưới ô Khách hàng có công tắc "Nhận diện khách bằng camera" (nhớ theo từng máy).
  Khi nhận ra khách đã đăng ký, POS hiện gợi ý "Có phải … ?" và nhân viên bấm **Đúng** để chọn khách.

## Quyền riêng tư
- Chỉ lưu vector đặc trưng 128 số, không lưu ảnh. Việc so khớp chạy ở máy chủ nên POS không nhận vector của khách khác.
- Chỉ nhận diện khách đã đăng ký và đồng ý. Người lạ không bị lưu lại gì.
