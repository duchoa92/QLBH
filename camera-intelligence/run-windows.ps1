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

# The worker must not inherit a config.yaml generated on another computer.
# Rebuild it from the local QLBH Settings every time the logon task starts:
# this renews the loopback API path/key and the LAN WebRTC hosts after an IP
# change, a restore, or a copied project.  If PHP is temporarily unavailable,
# retain a valid existing config so an already working camera is not stopped.
$phpCommand = Get-Command php -ErrorAction SilentlyContinue
$phpExecutable = if ($phpCommand) { $phpCommand.Source } else { $null }
if (-not $phpExecutable) {
    # Scheduled Task often has a smaller PATH than an interactive Laragon
    # terminal. Resolve Laragon's bundled PHP relative to a normal
    # C:\laragon\www\<project> installation before falling back.
    $laragonRoot = Split-Path -Parent (Split-Path -Parent $project)
    $laragonPhp = Join-Path $laragonRoot 'bin\php'
    if (Test-Path $laragonPhp) {
        $phpExecutable = (Get-ChildItem -Path $laragonPhp -Filter 'php.exe' -File -Recurse -ErrorAction SilentlyContinue |
            Sort-Object FullName -Descending |
            Select-Object -First 1 -ExpandProperty FullName)
    }
}
if ($phpExecutable) {
    Push-Location $project
    try {
        & $phpExecutable (Join-Path $project 'artisan') camera-intelligence:configure
        if ($LASTEXITCODE -ne 0) {
            Write-Warning 'Khong the tao lai cau hinh Camera Intelligence tu Cai dat; dang dung config.yaml hien co.'
        }
    } finally {
        Pop-Location
    }
} else {
    Write-Warning 'Khong tim thay PHP trong PATH cua tac vu nen; dang dung config.yaml hien co.'
}

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
