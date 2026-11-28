<#
.SYNOPSIS
    Chuẩn bị máy Windows (Laragon) để chạy QLBH. Chạy PowerShell bằng "Run as administrator":

        powershell -ExecutionPolicy Bypass -File scripts\install-windows.ps1

    Sau khi xong, bật Laragon (Start All) và mở http://localhost/setup (hoặc IP máy trong mạng LAN).

.PARAMETER SkipLaragon     Không tự cài Laragon (dùng PHP/Apache có sẵn).
.PARAMETER SkipFirewall    Không mở cổng 80/443 trong Windows Firewall.
.PARAMETER SkipScheduler   Không tạo Scheduled Task chạy schedule:run mỗi phút.
.PARAMETER SkipStaticIp    Không hỏi cố định IP máy chủ (quét mạng, hiện IP dùng được/không dùng được).
.PARAMETER SkipHelper      Không cài tác vụ nền cho trang Cài đặt (đổi IP, khởi động lại, tắt máy từ website).
#>
param(
    [switch] $SkipLaragon,
    [switch] $SkipFirewall,
    [switch] $SkipScheduler,
    [switch] $SkipStaticIp,
    [switch] $SkipHelper,
    [string] $LaragonRoot = 'C:\laragon'
)

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
Set-Location $projectRoot

function Step($text) { Write-Host "`n==> $text" -ForegroundColor Cyan }
function Warn($text) { Write-Host "    ! $text" -ForegroundColor Yellow }

$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) { throw 'Hãy mở PowerShell bằng "Run as administrator" rồi chạy lại.' }

function Find-Php {
    $cmd = Get-Command php -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }
    $found = Get-ChildItem (Join-Path $LaragonRoot 'bin\php') -Directory -ErrorAction SilentlyContinue |
        Sort-Object Name -Descending | ForEach-Object { Join-Path $_.FullName 'php.exe' } | Where-Object { Test-Path $_ } | Select-Object -First 1
    return $found
}

Step 'Kiểm tra PHP / Laragon'
$php = Find-Php
if (-not $php -and -not $SkipLaragon) {
    if (-not (Get-Command winget -ErrorAction SilentlyContinue)) {
        throw 'Không có winget. Hãy tải Laragon Full từ https://laragon.org/download rồi cài thủ công, sau đó chạy lại script.'
    }
    Write-Host '    Đang cài Laragon bằng winget (vài phút)...'
    winget install --id LeNgocKhoa.Laragon -e --silent --accept-package-agreements --accept-source-agreements
    $php = Find-Php
}
if (-not $php) { throw 'Không tìm thấy php.exe. Cài Laragon hoặc thêm PHP vào PATH rồi chạy lại.' }
$phpVersion = & $php -r 'echo PHP_VERSION;'
Write-Host "    PHP $phpVersion ($php)"
if ([version]($phpVersion -replace '[^0-9.].*$', '') -lt [version]'8.2') { throw "Cần PHP 8.2 trở lên (khuyến nghị 8.4). Hiện là $phpVersion. Trong Laragon: Menu > PHP > Version." }

Step 'Bật extension PHP cần thiết'
$iniPath = (& $php --ini | Select-String 'Loaded Configuration File:\s+(.+)$').Matches.Groups[1].Value.Trim()
if (-not $iniPath -or -not (Test-Path $iniPath)) {
    Warn 'Không tìm thấy php.ini đang dùng, bỏ qua bước này. Hãy tự bật: curl, fileinfo, gd, intl, mbstring, openssl, pdo_mysql, pdo_sqlite, zip.'
} else {
    $ini = Get-Content $iniPath -Raw
    $changed = $false
    foreach ($ext in 'curl', 'fileinfo', 'gd', 'intl', 'mbstring', 'openssl', 'pdo_mysql', 'pdo_sqlite', 'zip') {
        $loaded = (& $php -m) -contains $ext
        if ($loaded) { continue }
        if ($ini -match "(?m)^\s*;\s*extension\s*=\s*$ext\s*$") {
            $ini = [regex]::Replace($ini, "(?m)^\s*;\s*(extension\s*=\s*$ext)\s*$", '$1')
            $changed = $true
            Write-Host "    Đã bật $ext"
        } else {
            Warn "Không thấy dòng extension=$ext trong $iniPath."
        }
    }
    if ($changed) { Set-Content -Path $iniPath -Value $ini -Encoding ASCII; Warn 'Đã sửa php.ini: hãy Stop All rồi Start All trong Laragon.' }
}

Step 'Cài thư viện PHP (Composer)'
$composer = Get-Command composer -ErrorAction SilentlyContinue
if (-not $composer) {
    $laragonComposer = Join-Path $LaragonRoot 'bin\composer\composer.phar'
    $pharPath = if (Test-Path $laragonComposer) { $laragonComposer } else { Join-Path $env:TEMP 'composer.phar' }
    if (-not (Test-Path $pharPath)) {
        Invoke-WebRequest 'https://getcomposer.org/download/latest-stable/composer.phar' -OutFile $pharPath -UseBasicParsing
    }
    & $php $pharPath install --no-dev --optimize-autoloader --no-interaction
} else {
    & composer install --no-dev --optimize-autoloader --no-interaction
}
if ($LASTEXITCODE -ne 0) { throw 'composer install thất bại.' }

Step 'Chuẩn bị .env, APP_KEY, thư mục'
if (-not (Test-Path '.env')) {
    Copy-Item '.env.example' '.env'
    (Get-Content '.env') -replace '^APP_ENV=.*', 'APP_ENV=production' -replace '^APP_DEBUG=.*', 'APP_DEBUG=false' | Set-Content '.env' -Encoding UTF8
}
if (-not (Select-String -Path '.env' -Pattern '^APP_KEY=base64:' -Quiet)) { & $php artisan key:generate --force --no-interaction }
foreach ($dir in 'storage\app\public', 'storage\framework\cache\data', 'storage\framework\sessions', 'storage\framework\views', 'storage\logs', 'bootstrap\cache') {
    New-Item -ItemType Directory -Force -Path (Join-Path $projectRoot $dir) | Out-Null
}
if (-not (Test-Path 'public\storage')) { & $php artisan storage:link --no-interaction }

if (-not (Test-Path 'public\build') -and (Test-Path 'package.json')) {
    Step 'Build giao diện (cần Node.js)'
    if (-not (Get-Command npm -ErrorAction SilentlyContinue)) {
        if (Get-Command winget -ErrorAction SilentlyContinue) {
            winget install --id OpenJS.NodeJS.LTS -e --silent --accept-package-agreements --accept-source-agreements
            $env:Path = [Environment]::GetEnvironmentVariable('Path', 'Machine') + ';' + [Environment]::GetEnvironmentVariable('Path', 'User')
        } else { throw 'Cần Node.js để build giao diện. Cài từ https://nodejs.org rồi chạy lại.' }
    }
    if (Test-Path 'package-lock.json') { npm ci --no-audit --no-fund } else { npm install --no-audit --no-fund }
    npm run build
}

if (-not $SkipScheduler) {
    Step 'Tạo Scheduled Task chạy lịch nền mỗi phút'
    & (Join-Path $PSScriptRoot 'install-windows-scheduler.ps1') -PhpPath $php
}

if (-not $SkipFirewall) {
    Step 'Mở cổng 80/443 cho mạng nội bộ'
    foreach ($port in 80, 443) {
        $name = "QLBH port $port"
        if (-not (Get-NetFirewallRule -DisplayName $name -ErrorAction SilentlyContinue)) {
            New-NetFirewallRule -DisplayName $name -Direction Inbound -Protocol TCP -LocalPort $port -Action Allow -Profile Private,Domain | Out-Null
            Write-Host "    Đã mở cổng $port (Private/Domain)"
        }
    }
}

Step 'Tự chạy Laragon khi đăng nhập Windows'
$laragonExe = Join-Path $LaragonRoot 'laragon.exe'
if (Test-Path $laragonExe) {
    $taskName = 'QLBH Laragon Autostart'
    $action = New-ScheduledTaskAction -Execute $laragonExe -WorkingDirectory $LaragonRoot
    $trigger = New-ScheduledTaskTrigger -AtLogOn
    $settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -ExecutionTimeLimit ([TimeSpan]::Zero)
    Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings -RunLevel Highest -Force | Out-Null
    Write-Host "    Đã tạo tác vụ '$taskName': Laragon tự mở khi đăng nhập Windows."
    Warn "Để Apache + MySQL tự chạy theo: Laragon > Menu > Preferences > General, tick 'Start All automatically' (và nên tick 'Run Laragon when Windows starts')."
} else {
    Warn "Không thấy $laragonExe, bỏ qua bước tự chạy Laragon."
}

if (-not $SkipHelper) {
    Step 'Cài trợ giúp cho trang Cài đặt (đổi IP, khởi động lại, tắt máy từ website)'
    & (Join-Path $PSScriptRoot 'install-windows-helper.ps1') -LaragonRoot $LaragonRoot
}

if (-not $SkipStaticIp -and [Environment]::UserInteractive) {
    Step 'Mạng: cố định IP máy chủ'
    $answer = (Read-Host 'Quét mạng và cố định/đổi IP máy chủ bây giờ? (script hiện IP dùng được và không dùng được) [Y/n]').Trim()
    if (-not $answer -or $answer -match '^[YyCc]') {
        & (Join-Path $PSScriptRoot 'windows-network.ps1') -LaragonRoot $LaragonRoot
        if ($LASTEXITCODE -ne 0) { Warn 'Chưa cố định được IP. Đặt lại sau trong phần mềm: Cài đặt > Mạng & máy chủ, hoặc chạy scripts\windows-network.ps1.' }
    }
}

$ip = (Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue | Where-Object { $_.IPAddress -notmatch '^(127|169\.254)\.' -and $_.PrefixOrigin -ne 'WellKnown' } | Select-Object -First 1).IPAddress
Step 'Hoàn tất phần tự động'
Write-Host "    1) Mở Laragon > Start All (Apache + MySQL)."
Write-Host "    2) Laragon > Menu > Preferences > General > Document Root = $projectRoot\public  (để truy cập được bằng IP máy, không chỉ qlbh.test)."
Write-Host "    3) Mở http://localhost/setup  hoặc  http://$ip/setup  để tạo database và tài khoản quản trị."
Write-Host "    4) Camera điện thoại cần HTTPS: xem docs\HUONG-DAN-CAI-DAT.md mục 'HTTPS cho camera'."
Write-Host "    5) Đổi IP, khởi động lại, tắt máy sau này: trong phần mềm vào Cài đặt > Mạng & máy chủ."
