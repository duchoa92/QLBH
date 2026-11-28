<#
.SYNOPSIS
    Cài "trợ giúp" để trang Cài đặt → Mạng & máy chủ đổi được IP, khởi động lại, tắt máy Windows mà không cần gõ lệnh.
    Chạy PowerShell "Run as administrator":  powershell -ExecutionPolicy Bypass -File scripts\install-windows-helper.ps1
    (install-windows.ps1 tự chạy bước này.)

    Cách làm an toàn: bản script chạy quyền cao được chép vào %ProgramData%\QLBH\bin (người dùng thường chỉ đọc, không sửa được);
    website chỉ ghi file yêu cầu vào %ProgramData%\QLBH\inbox, tác vụ "QLBH Helper" (SYSTEM) kiểm tra lại rồi thực hiện.
#>
param([string] $LaragonRoot = 'C:\laragon')
$ErrorActionPreference = 'Stop'
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) { throw 'Hãy mở PowerShell bằng "Run as administrator" rồi chạy lại.' }

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$base = Join-Path $env:ProgramData 'QLBH'
$bin = Join-Path $base 'bin'
$inbox = Join-Path $base 'inbox'
New-Item -ItemType Directory -Force -Path $bin, $inbox | Out-Null

# Dừng tác vụ cũ (nếu có) trước khi ghi đè script.
Stop-ScheduledTask -TaskName 'QLBH Helper' -ErrorAction SilentlyContinue
Get-CimInstance Win32_Process -Filter "Name='powershell.exe'" -ErrorAction SilentlyContinue | Where-Object { $_.CommandLine -like '*QLBH\bin\windows-helper.ps1*' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }

foreach ($name in 'windows-network.ps1', 'windows-helper.ps1') {
    Copy-Item -LiteralPath (Join-Path $PSScriptRoot $name) -Destination (Join-Path $bin $name) -Force
}
[IO.File]::WriteAllText((Join-Path $base 'app_dir.txt'), $projectRoot, [Text.Encoding]::ASCII)
[IO.File]::WriteAllText((Join-Path $base 'laragon_root.txt'), $LaragonRoot, [Text.Encoding]::ASCII)

# Quyền: SYSTEM + Administrators toàn quyền; Users chỉ đọc, riêng thư mục inbox cho Users ghi file yêu cầu (dùng SID để chạy được trên Windows mọi ngôn ngữ).
& icacls.exe $base /inheritance:r /grant:r '*S-1-5-18:(OI)(CI)F' '*S-1-5-32-544:(OI)(CI)F' '*S-1-5-32-545:(OI)(CI)RX' | Out-Null
& icacls.exe $inbox /grant '*S-1-5-32-545:(OI)(CI)M' | Out-Null

$action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$bin\windows-helper.ps1`""
$trigger = New-ScheduledTaskTrigger -AtStartup
$principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -RestartCount 999 -RestartInterval (New-TimeSpan -Minutes 1) -ExecutionTimeLimit ([TimeSpan]::Zero) -MultipleInstances IgnoreNew
Register-ScheduledTask -TaskName 'QLBH Helper' -Action $action -Trigger $trigger -Principal $principal -Settings $settings -Force | Out-Null
Start-ScheduledTask -TaskName 'QLBH Helper'
Write-Host "  ✓ Đã cài tác vụ 'QLBH Helper'. Trang Cài đặt → 'Mạng & máy chủ' (đổi IP, khởi động lại, tắt máy) đã sẵn sàng." -ForegroundColor Green
