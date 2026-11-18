# QLBH Camera Intelligence

Dich vu AI chay nen doc lap cho QLBH. Dich vu nay chi phat hien, theo doi va nhan dien khuon mat; MediaMTX phat video WebRTC va Laravel luu nghiep vu.

## Nguyen tac

- Mot pipeline doc RTSP cho moi camera, bo frame cu khi may cham de khong bao gio suy luan tren hinh da tre.
- Detector va tracker chay lien tuc; nhan dien chi chay voi track moi co anh ro.
- Ket qua gui ve QLBH la event, khong gui video hay thu vien model ra trinh duyet.
- Model va cau hinh khong nam trong Git. Dung dung hai file ONNX da kiem thu cua QLBH: YuNet va SFace.

## Chay native (Windows / Ubuntu)

```powershell
py -3.10 -m venv .venv
.\.venv\Scripts\python -m pip install -r requirements.txt
Copy-Item config.example.yaml config.yaml
.\.venv\Scripts\python -m app.main --config config.yaml
```

Tren Ubuntu, dung `python3` thay cho `py` va kich hoat `.venv/bin/activate`.

`config.yaml` khong duoc commit: chua camera key va RTSP URL.

## Model

Dat hai file da duoc QLBH kiem thu vao `models/`:

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
