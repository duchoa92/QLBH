[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$deploy = Split-Path -Parent $PSScriptRoot
$project = Split-Path -Parent $deploy
$runtime = Join-Path $PSScriptRoot 'runtime'
$python = Join-Path $project 'camera-intelligence\.venv\Scripts\python.exe'
$config = Join-Path $project 'camera-intelligence\config.yaml'
$generator = Join-Path $project 'camera-intelligence\tools\generate_mediamtx_config.py'
$mediaConfig = Join-Path $PSScriptRoot 'mediamtx.yml'
$mediaMtx = Join-Path $runtime 'mediamtx.exe'
$taskRegistrar = Join-Path $PSScriptRoot 'register-windows-task.ps1'

if (-not (Test-Path $python) -or -not (Test-Path $config) -or -not (Test-Path $generator) -or -not (Test-Path $mediaMtx) -or -not (Test-Path $taskRegistrar)) {
    throw 'Camera Platform is not installed completely. Run deploy/camera-platform/install-windows.ps1 once.'
}

# Saving camera settings also repairs the logon task after the source has been
# copied to another Windows machine, where Task Scheduler data is not in Git.
& $taskRegistrar -ProjectRoot $project

& $python $generator --config $config --output $mediaConfig
if ($LASTEXITCODE -ne 0) { throw 'Could not generate the MediaMTX configuration.' }

Get-Process mediamtx -ErrorAction SilentlyContinue | Stop-Process -Force
Start-Process -FilePath $mediaMtx -ArgumentList @($mediaConfig) -WorkingDirectory $runtime -WindowStyle Hidden

# Camera Intelligence is reloaded through its authenticated local HTTP endpoint
# before this script is called. Do not stop/restart its Scheduled Task here:
# doing so races with that reload and was the reason a saved camera address
# could leave the worker without a heartbeat for several seconds.
