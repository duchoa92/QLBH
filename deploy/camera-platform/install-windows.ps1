[CmdletBinding()]
param(
    [string]$Version = '1.16.1'
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$project = Split-Path -Parent $root
$runtime = Join-Path $PSScriptRoot 'runtime'
$python = Join-Path $project 'camera-intelligence\.venv\Scripts\python.exe'
$config = Join-Path $project 'camera-intelligence\config.yaml'
$aiLauncher = Join-Path $project 'camera-intelligence\run-windows.ps1'
$mediaConfig = Join-Path $PSScriptRoot 'mediamtx.yml'
$taskName = 'QLBH Camera Intelligence'
& php (Join-Path $project 'artisan') camera-intelligence:configure
if ($LASTEXITCODE -ne 0) { throw 'Không tạo được cấu hình camera từ Cài đặt QLBH.' }
if (-not (Test-Path $python)) { throw 'Cài Camera Intelligence trước: xem Cài đặt → Khách hàng → Camera Platform.' }
if (-not (Test-Path $config)) { throw 'Chưa có camera-intelligence/config.yaml.' }
if (-not (Test-Path $aiLauncher)) { throw 'Thiếu camera-intelligence/run-windows.ps1.' }
& $python (Join-Path $project 'camera-intelligence\tools\generate_mediamtx_config.py') --config $config --output $mediaConfig
New-Item -ItemType Directory -Force -Path $runtime | Out-Null
$exe = Join-Path $runtime 'mediamtx.exe'
if (-not (Test-Path $exe)) {
    $zip = Join-Path $env:TEMP "mediamtx_v$Version`_windows_amd64.zip"
    Invoke-WebRequest -Uri "https://github.com/bluenviron/mediamtx/releases/download/v$Version/mediamtx_v$Version`_windows_amd64.zip" -OutFile $zip
    Expand-Archive -LiteralPath $zip -DestinationPath $runtime -Force
}
Get-Process mediamtx -ErrorAction SilentlyContinue | Stop-Process -Force
Start-Process -FilePath $exe -ArgumentList @($mediaConfig) -WorkingDirectory $runtime -WindowStyle Hidden

# A task at user logon is enough for Laragon-style Windows installations and
# avoids asking users to keep a second console window open. Relative model paths
# are resolved from config.yaml by the Python service.
$powerShell = "$env:WINDIR\System32\WindowsPowerShell\v1.0\powershell.exe"
$action = New-ScheduledTaskAction -Execute $powerShell -Argument ('-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File "{0}"' -f $aiLauncher)
$trigger = New-ScheduledTaskTrigger -AtLogOn -User "$env:USERDOMAIN\$env:USERNAME"
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1) -ExecutionTimeLimit (New-TimeSpan -Days 0)
Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue | Stop-ScheduledTask -ErrorAction SilentlyContinue
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings -Force | Out-Null
Start-ScheduledTask -TaskName $taskName

# Start it now as well. Task Scheduler owns the next-logon restart, whereas a
# direct hidden launcher makes an install/update effective immediately.
Start-Sleep -Milliseconds 500
if (-not (Get-NetTCPConnection -LocalPort 9070 -State Listen -ErrorAction SilentlyContinue)) {
    Start-Process -FilePath $powerShell -ArgumentList @('-NoProfile', '-WindowStyle', 'Hidden', '-ExecutionPolicy', 'Bypass', '-File', $aiLauncher) -WindowStyle Hidden
}
Write-Host 'MediaMTX va Camera Intelligence da duoc khoi dong; dich vu AI se tu chay lai khi dang nhap Windows.'
