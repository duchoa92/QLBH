FROM python:3.12-slim
WORKDIR /opt/qlbh-camera-intelligence
RUN apt-get update && apt-get install -y --no-install-recommends ffmpeg && rm -rf /var/lib/apt/lists/*
COPY requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt
COPY app ./app
ENTRYPOINT ["python", "-m", "app.main", "--config", "/config/config.yaml"]
