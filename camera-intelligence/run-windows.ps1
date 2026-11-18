[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot
$python = Join-Path $root '.venv\Scripts\python.exe'
$config = Join-Path $root 'config.yaml'
$project = Split-Path -Parent $root
$platform = Join-Path $project 'deploy\camera-platform'
$runtime = Join-Path $platform 'runtime'
$ffmpegRoot = Join-Path $runtime 'ffmpeg'
$mediaMtx = Join-Path $runtime 'mediamtx.exe'
$mediaConfig = Join-Path $platform 'mediamtx.yml'
$mediaGenerator = Join-Path $root 'tools\generate_mediamtx_config.py'
$ffmpeg = Get-ChildItem -Path $ffmpegRoot -Filter 'ffmpeg.exe' -File -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1
$ffmpegDirectory = $null

if ($ffmpeg) {
    $ffmpegDirectory = Split-Path -Parent $ffmpeg.FullName
} else {
    # The installer may use an existing system FFmpeg instead of downloading
    # the local runtime. Keep the launcher compatible with that valid setup.
    $systemFfmpeg = Get-Command ffmpeg -ErrorAction SilentlyContinue
    if ($systemFfmpeg) {
        $ffmpegDirectory = Split-Path -Parent $systemFfmpeg.Source
    }
}

if (-not (Test-Path $python)) { throw "Khong tim thay Python environment: $python" }
if (-not (Test-Path $config)) { throw "Khong tim thay cau hinh: $config" }
if (-not $ffmpegDirectory) { throw "Khong tim thay FFmpeg. Chay lai deploy/camera-platform/install-windows.ps1." }
if (-not (Test-Path $mediaMtx)) { throw "Khong tim thay MediaMTX. Chay lai deploy/camera-platform/install-windows.ps1." }
if (-not (Test-Path $mediaGenerator)) { throw "Khong tim thay MediaMTX config generator: $mediaGenerator" }

# The scheduled task starts this launcher after every Windows logon. Generate
# the ignored, machine-local MediaMTX config and ensure its video server exists
# before starting the AI worker; otherwise the worker would recover without a
# WebRTC source after a reboot.
& $python $mediaGenerator --config $config --output $mediaConfig
if ($LASTEXITCODE -ne 0) { throw 'Khong the tao cau hinh MediaMTX tu Camera Intelligence.' }

if (-not (Get-Process mediamtx -ErrorAction SilentlyContinue)) {
    Start-Process -FilePath $mediaMtx -ArgumentList @($mediaConfig) -WorkingDirectory $runtime -WindowStyle Hidden
}

Set-Location $root
$env:PATH = "$ffmpegDirectory;$env:PATH"
& $python -m app.main --config $config
exit $LASTEXITCODE
