[CmdletBinding()]
param(
    [string]$Version = '1.16.1'
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$project = Split-Path -Parent $root
$runtime = Join-Path $PSScriptRoot 'runtime'
$aiRoot = Join-Path $project 'camera-intelligence'
$venv = Join-Path $aiRoot '.venv'
$python = Join-Path $project 'camera-intelligence\.venv\Scripts\python.exe'
$config = Join-Path $project 'camera-intelligence\config.yaml'
$aiLauncher = Join-Path $project 'camera-intelligence\run-windows.ps1'
$mediaConfig = Join-Path $PSScriptRoot 'mediamtx.yml'
$taskRegistrar = Join-Path $PSScriptRoot 'register-windows-task.ps1'
# This is deliberately a one-command installer. It keeps the Python runtime,
# pip packages and model files inside camera-intelligence and never changes the
# system Python installation.
if (-not (Test-Path $aiRoot)) { throw 'Missing camera-intelligence directory.' }
if (-not (Test-Path $aiLauncher)) { throw 'Missing camera-intelligence/run-windows.ps1.' }
if (-not (Test-Path $taskRegistrar)) { throw 'Missing deploy/camera-platform/register-windows-task.ps1.' }
$py = Get-Command py -ErrorAction SilentlyContinue
if (-not $py) { throw 'Python Launcher (py.exe) was not found. Install 64-bit Python 3.10 to 3.14, then run this command again.' }
$pythonVersion = $null
foreach ($candidate in @('3.14', '3.13', '3.12', '3.11', '3.10')) {
    & $py.Source "-$candidate" -c 'import sys; print(sys.version)' 2>$null | Out-Null
    if ($LASTEXITCODE -eq 0) { $pythonVersion = $candidate; break }
}
if (-not $pythonVersion) { throw 'No supported 64-bit Python version was found. Install Python 3.10 to 3.14, then run this command again.' }
if (-not (Test-Path $python)) {
    & $py.Source "-$pythonVersion" -m venv $venv
    if ($LASTEXITCODE -ne 0) { throw 'Could not create the Camera Intelligence virtual environment.' }
}
& $python -m pip install --upgrade pip
if ($LASTEXITCODE -ne 0) { throw 'Could not update pip in the Camera Intelligence virtual environment.' }
& $python -m pip install --only-binary=:all: -r (Join-Path $aiRoot 'requirements.txt')
if ($LASTEXITCODE -ne 0) { throw 'Could not install Camera Intelligence packages.' }
& $python (Join-Path $aiRoot 'tools\fetch_models.py')
if ($LASTEXITCODE -ne 0) { throw 'Could not download or verify the approved face models.' }
New-Item -ItemType Directory -Force -Path $runtime | Out-Null
# Task Scheduler does not inherit the user's PATH, so always keep FFmpeg local
# to QLBH instead of relying on a version found only in an interactive shell.
$ffmpegRoot = Join-Path $runtime 'ffmpeg'
$ffmpegExe = Get-ChildItem -Path $ffmpegRoot -Filter 'ffmpeg.exe' -File -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1
if (-not $ffmpegExe) {
    $ffmpegZip = Join-Path $runtime 'ffmpeg-win64-lgpl-shared.zip'
    Invoke-WebRequest -Uri 'https://github.com/BtbN/FFmpeg-Builds/releases/download/latest/ffmpeg-master-latest-win64-lgpl-shared.zip' -OutFile $ffmpegZip
    if (-not (Test-Path $ffmpegZip)) { throw 'Could not download the local LGPL FFmpeg runtime.' }
    New-Item -ItemType Directory -Force -Path $ffmpegRoot | Out-Null
    Expand-Archive -LiteralPath $ffmpegZip -DestinationPath $ffmpegRoot -Force
    Remove-Item -LiteralPath $ffmpegZip -Force
    $ffmpegExe = Get-ChildItem -Path $ffmpegRoot -Filter 'ffmpeg.exe' -File -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1
}
if (-not $ffmpegExe) { throw 'The local LGPL FFmpeg runtime does not contain ffmpeg.exe.' }
$ffmpegDirectory = Split-Path -Parent $ffmpegExe.FullName
$env:PATH = "$ffmpegDirectory;$env:PATH"
& php (Join-Path $project 'artisan') camera-intelligence:configure
if ($LASTEXITCODE -ne 0) { throw 'Cannot generate camera configuration from QLBH Settings.' }
& $python (Join-Path $project 'camera-intelligence\tools\generate_mediamtx_config.py') --config $config --output $mediaConfig
$exe = Join-Path $runtime 'mediamtx.exe'
if (-not (Test-Path $exe)) {
    $zip = Join-Path $env:TEMP "mediamtx_v$Version`_windows_amd64.zip"
    Invoke-WebRequest -Uri "https://github.com/bluenviron/mediamtx/releases/download/v$Version/mediamtx_v$Version`_windows_amd64.zip" -OutFile $zip
    Expand-Archive -LiteralPath $zip -DestinationPath $runtime -Force
}
Get-Process mediamtx -ErrorAction SilentlyContinue | Stop-Process -Force
Start-Process -FilePath $exe -ArgumentList @($mediaConfig) -WorkingDirectory $runtime -WindowStyle Hidden

# A logon task keeps both MediaMTX and Camera Intelligence alive after every
# reboot. The launcher now starts MediaMTX first, so WebRTC also returns after
# Windows restarts instead of only the Python worker restarting.
& $taskRegistrar -ProjectRoot $project
Start-ScheduledTask -TaskName $taskName

# Start it now as well only when Task Scheduler did not bring the worker up.
# Waiting avoids racing a duplicate worker against the scheduled launch.
$workerReady = $false
for ($attempt = 0; $attempt -lt 8; $attempt++) {
    Start-Sleep -Milliseconds 500
    if (Get-NetTCPConnection -LocalPort 9070 -State Listen -ErrorAction SilentlyContinue) {
        $workerReady = $true
        break
    }
}
if (-not $workerReady) {
    $powerShell = "$env:WINDIR\System32\WindowsPowerShell\v1.0\powershell.exe"
    Start-Process -FilePath $powerShell -ArgumentList @('-NoProfile', '-WindowStyle', 'Hidden', '-ExecutionPolicy', 'Bypass', '-File', $aiLauncher) -WindowStyle Hidden
}
Write-Host 'MediaMTX va Camera Intelligence da duoc khoi dong; dich vu AI se tu chay lai khi dang nhap Windows.'
