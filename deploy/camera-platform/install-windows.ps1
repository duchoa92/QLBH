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
$taskName = 'QLBH Camera Intelligence'
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
# Do not contact PyPI merely because the installer is run again.  The worker
# can start fully offline when its existing venv is complete, which is common
# after a temporary DNS or Internet outage at the shop.
$dependencyProbe = 'import fastapi, uvicorn, pydantic, yaml, numpy, openvino, PIL, cv2'
$baseDependencyProbe = 'import fastapi, uvicorn, pydantic, yaml, numpy, openvino, PIL'
# A missing import deliberately exits Python with code 1.  Temporarily avoid
# $ErrorActionPreference=Stop turning that expected probe result into a
# terminating PowerShell error before the installer can repair it.
$previousErrorAction = $ErrorActionPreference
$ErrorActionPreference = 'Continue'
& $python -c $dependencyProbe 2>$null
$dependencyExitCode = $LASTEXITCODE
$ErrorActionPreference = $previousErrorAction
if ($dependencyExitCode -ne 0) {
    Write-Host 'Some Camera Intelligence Python packages are missing; installing them now...'
    $ErrorActionPreference = 'Continue'
    & $python -c $baseDependencyProbe 2>$null
    $baseDependencyExitCode = $LASTEXITCODE
    $ErrorActionPreference = $previousErrorAction
    # --isolated deliberately ignores a machine-level PIP_NO_INDEX setting.
    # Such a setting is useful for locked-down builds, but otherwise leaves a
    # new Camera Intelligence environment unable to fetch its first OpenCV
    # wheel even though the Internet connection itself is healthy.
    # A stale developer proxy at 127.0.0.1:9 is not a usable corporate proxy;
    # it causes pip to fail even when direct HTTPS works. Remove only that
    # exact known-invalid value for this child process and retain any real
    # proxy configured by the machine.
    $clearedProxy = @{}
    foreach ($proxyName in @('HTTP_PROXY', 'HTTPS_PROXY', 'ALL_PROXY', 'http_proxy', 'https_proxy', 'all_proxy')) {
        $proxyValue = [Environment]::GetEnvironmentVariable($proxyName, 'Process')
        if ($proxyValue -match '^https?://127\.0\.0\.1:9/?$') {
            $clearedProxy[$proxyName] = $proxyValue
            [Environment]::SetEnvironmentVariable($proxyName, $null, 'Process')
        }
    }
    try {
        if ($baseDependencyExitCode -eq 0) {
            # This is the normal upgrade path for an existing QLBH install:
            # all AI packages are already compatible, only OpenCV is absent.
            # Do not replace a proven OpenVINO/Pydantic stack just to add cv2.
            & $python -m pip --isolated install --index-url 'https://pypi.org/simple' --only-binary=:all: --retries 5 --timeout 30 'opencv-python-headless==4.14.0.94'
        } else {
            # A newly created or incomplete environment needs the complete
            # versioned set from requirements.txt.
            & $python -m pip --isolated install --index-url 'https://pypi.org/simple' --only-binary=:all: --retries 5 --timeout 30 -r (Join-Path $aiRoot 'requirements.txt')
        }
        $installExitCode = $LASTEXITCODE
    } finally {
        foreach ($proxyName in $clearedProxy.Keys) {
            [Environment]::SetEnvironmentVariable($proxyName, $clearedProxy[$proxyName], 'Process')
        }
    }
    if ($installExitCode -ne 0) {
        throw 'Could not install Camera Intelligence packages. Check Internet/DNS access to files.pythonhosted.org (and any configured proxy), then run this command again. If this is an offline machine, copy the verified camera-intelligence/.venv folder from a working machine with the same Python version.'
    }
    & $python -c $dependencyProbe
    if ($LASTEXITCODE -ne 0) { throw 'Camera Intelligence packages were installed but the Python dependency check still failed.' }
} else {
    Write-Host 'Reusing the existing Camera Intelligence Python environment (no Internet download needed).'
}
& $python (Join-Path $aiRoot 'tools\fetch_models.py')
if ($LASTEXITCODE -ne 0) { throw 'Could not download or verify the approved face models.' }
New-Item -ItemType Directory -Force -Path $runtime | Out-Null
# Task Scheduler does not inherit the user's PATH, so always keep FFmpeg local
# to QLBH instead of relying on a version found only in an interactive shell.
$ffmpegRoot = Join-Path $runtime 'ffmpeg'
$ffmpegExe = Get-ChildItem -Path $ffmpegRoot -Filter 'ffmpeg.exe' -File -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1
if (-not $ffmpegExe) {
    # Reuse a verified FFmpeg already available to this Windows account. This
    # keeps a repeat install working during an Internet outage; the launcher
    # supports the same fallback for the scheduled background worker.
    $systemFfmpeg = Get-Command ffmpeg -ErrorAction SilentlyContinue
    if ($systemFfmpeg) {
        $ffmpegExe = [PSCustomObject]@{ FullName = $systemFfmpeg.Source }
    } else {
        $ffmpegZip = Join-Path $runtime 'ffmpeg-win64-lgpl-shared.zip'
        Invoke-WebRequest -Uri 'https://github.com/BtbN/FFmpeg-Builds/releases/download/latest/ffmpeg-master-latest-win64-lgpl-shared.zip' -OutFile $ffmpegZip
        if (-not (Test-Path $ffmpegZip)) { throw 'Could not download the local LGPL FFmpeg runtime.' }
        New-Item -ItemType Directory -Force -Path $ffmpegRoot | Out-Null
        Expand-Archive -LiteralPath $ffmpegZip -DestinationPath $ffmpegRoot -Force
        Remove-Item -LiteralPath $ffmpegZip -Force
        $ffmpegExe = Get-ChildItem -Path $ffmpegRoot -Filter 'ffmpeg.exe' -File -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1
    }
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
$taskRegistered = $false
try {
    & $taskRegistrar -ProjectRoot $project
    Start-ScheduledTask -TaskName $taskName
    $taskRegistered = $true
} catch {
    # A manually started worker remains valid if a non-administrator account
    # cannot create a Task Scheduler entry.  Keep installation usable and tell
    # the operator exactly what is missing for automatic startup after reboot.
    Write-Warning "Could not register the Windows startup task: $($_.Exception.Message)"
    Write-Warning 'Camera Intelligence will start now, but run this installer from an Administrator PowerShell later to enable automatic startup after Windows logon.'
}

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
if ($taskRegistered) {
    Write-Host 'MediaMTX va Camera Intelligence da duoc khoi dong; dich vu AI se tu chay lai khi dang nhap Windows.'
} else {
    Write-Host 'MediaMTX va Camera Intelligence da duoc khoi dong cho phien lam viec hien tai.'
}
