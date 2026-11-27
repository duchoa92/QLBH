<#
.SYNOPSIS
    Tạo chứng chỉ HTTPS nội bộ (CA riêng + chứng chỉ cho IP máy) và vhost Apache của Laragon, để camera điện thoại hoạt động.
    Chạy bằng PowerShell "Run as administrator":  powershell -ExecutionPolicy Bypass -File scripts\windows-https.ps1

    Sau khi chạy: Laragon > Menu > Apache > SSL > Enabled, rồi Stop All / Start All.
    Điện thoại tải chứng chỉ gốc ở  http://<IP>/certs/rootCA.crt  và cài đặt (xem docs\HUONG-DAN-CAI-DAT.md).

.NOTES
    Cần openssl.exe (Laragon có sẵn trong bin\apache hoặc bin\git). Chưa kiểm thử trên mọi phiên bản Laragon:
    nếu Apache không nạp được vhost, xem logs trong C:\laragon\bin\apache\...\logs\error.log.
#>
param(
    [string] $LaragonRoot = 'C:\laragon',
    [string] $IpAddress
)
$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path

if (-not $IpAddress) {
    $IpAddress = (Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.IPAddress -notmatch '^(127|169\.254)\.' -and $_.PrefixOrigin -ne 'WellKnown' } | Select-Object -First 1).IPAddress
}
if (-not $IpAddress) { throw 'Không xác định được IP máy. Truyền -IpAddress 192.168.x.x.' }

$openssl = (Get-Command openssl -ErrorAction SilentlyContinue).Source
if (-not $openssl) {
    $openssl = Get-ChildItem $LaragonRoot -Recurse -Filter openssl.exe -ErrorAction SilentlyContinue | Select-Object -First 1 -ExpandProperty FullName
}
if (-not $openssl) { throw 'Không tìm thấy openssl.exe. Cài Git for Windows hoặc dùng Laragon Full rồi chạy lại.' }

$sslDir = Join-Path $LaragonRoot 'etc\ssl\qlbh'
New-Item -ItemType Directory -Force -Path $sslDir, (Join-Path $projectRoot 'public\certs') | Out-Null
$ca = Join-Path $sslDir 'rootCA'
$srv = Join-Path $sslDir 'server'

if (-not (Test-Path "$ca.key")) {
    & $openssl genrsa -out "$ca.key" 4096
    & $openssl req -x509 -new -nodes -key "$ca.key" -sha256 -days 3650 -subj '/CN=QLBH Local CA' -out "$ca.crt"
}
$san = "IP:$IpAddress,IP:127.0.0.1,DNS:localhost"
& $openssl genrsa -out "$srv.key" 2048
& $openssl req -new -key "$srv.key" -subj "/CN=$IpAddress" -out "$srv.csr"
"subjectAltName=$san`nbasicConstraints=CA:FALSE`nkeyUsage=digitalSignature,keyEncipherment`nextendedKeyUsage=serverAuth" | Set-Content "$srv.ext" -Encoding ASCII
# iOS chỉ chấp nhận chứng chỉ máy chủ hiệu lực tối đa 825 ngày.
& $openssl x509 -req -in "$srv.csr" -CA "$ca.crt" -CAkey "$ca.key" -CAcreateserial -out "$srv.crt" -days 825 -sha256 -extfile "$srv.ext"
Copy-Item "$ca.crt" (Join-Path $projectRoot 'public\certs\rootCA.crt') -Force

$publicDir = (Join-Path $projectRoot 'public') -replace '\\', '/'
$crt = "$srv.crt" -replace '\\', '/'
$key = "$srv.key" -replace '\\', '/'
$sites = Join-Path $LaragonRoot 'etc\apache2\sites-enabled'
New-Item -ItemType Directory -Force -Path $sites | Out-Null
@"
<IfModule ssl_module>
<VirtualHost *:443>
    ServerName $IpAddress
    DocumentRoot "$publicDir"
    SSLEngine on
    SSLCertificateFile "$crt"
    SSLCertificateKeyFile "$key"
    <Directory "$publicDir">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
</IfModule>
"@ | Set-Content (Join-Path $sites '00-qlbh-ssl.conf') -Encoding ASCII

Write-Host "Đã tạo chứng chỉ cho $IpAddress và vhost Apache."
Write-Host "Tiếp theo: Laragon > Menu > Apache > SSL > Enabled, rồi Stop All / Start All."
Write-Host "Chứng chỉ gốc cho điện thoại: $projectRoot\public\certs\rootCA.crt  (tải qua http://$IpAddress/certs/rootCA.crt)"
