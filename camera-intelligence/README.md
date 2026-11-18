# QLBH Camera Intelligence

Dich vu AI chay nen doc lap cho QLBH. Dich vu nay chi phat hien, theo doi va nhan dien khuon mat; MediaMTX phat video WebRTC va Laravel luu nghiep vu.

## Nguyen tac

- Mot pipeline doc RTSP cho moi camera, bo frame cu khi may cham de khong bao gio suy luan tren hinh da tre.
- Detector va tracker chay lien tuc; nhan dien chi chay voi track moi co anh ro.
- Ket qua gui ve QLBH la event, khong gui video hay thu vien model ra trinh duyet.
- Model va cau hinh khong nam trong Git. Dung dung hai file ONNX da kiem thu cua QLBH: YuNet va SFace.

## Chay native (Windows / Ubuntu)

```powershell
# Kiem tra cac Python dang co: py -0p
py -3.14 -m venv .venv
.\.venv\Scripts\python.exe -m pip install --upgrade pip
.\.venv\Scripts\python.exe -m pip install -r requirements.txt
.\.venv\Scripts\python.exe tools\fetch_models.py
if (-not (Test-Path config.yaml)) { Copy-Item config.example.yaml config.yaml }
```

Dung Python 64-bit tu 3.10 den 3.14. Lenh tren dung 3.14; neu may da cai mot ban khac trong khoang nay, thay `3.14` bang so ban do. Tren Ubuntu, dung `python3` thay cho `py` va kich hoat `.venv/bin/activate`.

Khong can chay `app.main` thu cong neu dung script `deploy/camera-platform/install-windows.ps1`: script se tao tac vu nen va tu khoi dong dich vu. Script cung tu tai FFmpeg ban LGPL vao `deploy/camera-platform/runtime/ffmpeg/`; khong can sua PATH he thong.

`config.yaml` khong duoc commit: chua camera key va RTSP URL.

## Model

Chay `tools/fetch_models.py` sau khi cai dependency de tai hai model chinh thuc tu OpenCV Zoo va kiem tra SHA-256. Script tu bo qua file da dung hash va tu choi ghi de file sai hash. Hai file can co trong `models/`:

- `face_detection_yunet_2023mar.onnx`
- `face_recognition_sface_2021dec.onnx`

Khi chuyen may, chep nguyen thu muc `camera-intelligence/models/` tu may cu qua kenh noi bo an toan. Khong tai model tuy y tu Internet: embedding khac phien ban se khong so khop voi mau khuon mat da dang ky. Neu thieu model, dich vu bao `inference_ready=false` va khong gui ket qua nham.

## Dang ky khuon mat

Tai **Khach hang → Dang ky khuon mat**, chon anh hoac camera tai quay va luu mau. Anh chi duoc gui tam thoi den dich vu noi bo de tao SFace embedding; QLBH chi luu vector, khong luu anh. Camera nen va man hinh dang ky dung cung YuNet + SFace, vi vay mau vua dang ky co the duoc nhan ra ngay ma khong can mo trinh duyet lien tuc.

## API noi bo

- `GET /healthz`: trang thai dich vu va tung camera.
- `GET /metrics`: metric JSON de QLBH hien thi.
- `POST /reload`: nap lai cau hinh camera sau khi QLBH cap nhat.

Do khong mo cong ra Internet. Laravel va MediaMTX giao tiep voi dich vu qua localhost/container network.
