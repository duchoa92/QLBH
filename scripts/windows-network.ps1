<#
.SYNOPSIS
    Quét mạng, hiện IP dùng được / không dùng được, cố định hoặc đổi IP máy Windows chạy QLBH (tương đương set-static-ip.sh trên Ubuntu).

    Chạy bằng PowerShell "Run as administrator" (hỏi từng bước, có quét mạng):
        powershell -ExecutionPolicy Bypass -File scripts\windows-network.ps1
    Chỉ xem danh sách IP (không đổi gì):      ... -List
    Đặt IP không hỏi:                          ... -Ip 192.168.100.210 -Gateway 192.168.100.1 -Dns 8.8.8.8,1.1.1.1 -Yes   (-Ip có thể chỉ là số cuối, vd 210)
    Quay lại tự động nhận IP (DHCP):           ... -Revert

    Các chế độ -Json, -Check, -Status, -ApplyRequest dành cho trang Cài đặt của website (không gọi tay).
    Đổi xong tự kiểm tra mạng; mất kết nối thì tự quay lại cấu hình cũ. Sau khi đổi cập nhật APP_URL trong .env, cấu hình camera, chứng chỉ HTTPS.

.NOTES
    Chưa kiểm thử trên mọi phiên bản Windows. Cần Windows 10/11 hoặc Windows Server 2016+ (module NetTCPIP).
#>
[CmdletBinding()]
param(
    [switch] $Json,
    [switch] $Status,
    [string] $Check = '',
    [switch] $List,
    [switch] $ApplyRequest,
    [switch] $Revert,
    [switch] $Yes,
    [string] $Ip = '',
    [string] $Gateway = '',
    [string] $Dns = '',
    [string] $InterfaceAlias = '',
    [string] $LaragonRoot = ''
)
$ErrorActionPreference = 'Stop'
try { [Console]::OutputEncoding = [System.Text.Encoding]::UTF8 } catch { }

$Base = Join-Path $env:ProgramData 'QLBH'
$StatusFile = Join-Path $Base 'network-change.json'
$ReqFile = Join-Path $Base 'inbox\ip-change.req'
$Utf8NoBom = New-Object System.Text.UTF8Encoding $false
$ALL = [uint64]4294967295

function Read-Setting([string] $name) {
    $f = Join-Path $Base $name
    if (Test-Path $f) { return ([IO.File]::ReadAllText($f)).Trim() }
    return ''
}
$ProjectRoot = Read-Setting 'app_dir.txt'
if (-not $ProjectRoot) { try { $ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path } catch { $ProjectRoot = '' } }
if (-not $LaragonRoot) { $LaragonRoot = Read-Setting 'laragon_root.txt' }
if (-not $LaragonRoot) { $LaragonRoot = 'C:\laragon' }

$script:OldIp = ''
$script:ReqIp = ''
$script:StatusDone = $false

# ---------- Tiện ích địa chỉ IPv4 ----------
function Test-Ipv4([string] $s) {
    if ($s -notmatch '^\d{1,3}(\.\d{1,3}){3}$') { return $false }
    foreach ($p in $s.Split('.')) { if ([int]$p -gt 255) { return $false } }
    return $true
}
function ConvertTo-UInt([string] $ip) {
    $b = $ip.Split('.') | ForEach-Object { [uint64]$_ }
    return [uint64]($b[0] * 16777216 + $b[1] * 65536 + $b[2] * 256 + $b[3])
}
function ConvertTo-Ip([uint64] $n) {
    return ('{0}.{1}.{2}.{3}' -f [int](($n -shr 24) -band 255), [int](($n -shr 16) -band 255), [int](($n -shr 8) -band 255), [int]($n -band 255))
}
function Get-Mask([int] $prefix) { return [uint64](($ALL -shl (32 - $prefix)) -band $ALL) }

function Write-Status([string] $state, [string] $message) {
    if (-not (Test-Path $Base)) { New-Item -ItemType Directory -Force -Path $Base | Out-Null }
    $obj = [ordered]@{ state = $state; message = $message; old_ip = $script:OldIp; new_ip = $script:ReqIp; time = [int][DateTimeOffset]::UtcNow.ToUnixTimeSeconds() }
    $tmp = Join-Path $Base ('.status.' + [guid]::NewGuid().ToString('N'))
    [IO.File]::WriteAllText($tmp, ($obj | ConvertTo-Json -Compress), $Utf8NoBom)
    Move-Item -LiteralPath $tmp -Destination $StatusFile -Force
}

# ---------- Thông tin mạng hiện tại ----------
function Get-Context {
    $routes = @(Get-NetRoute -DestinationPrefix '0.0.0.0/0' -AddressFamily IPv4 -ErrorAction SilentlyContinue | Where-Object { $_.NextHop -ne '0.0.0.0' })
    if ($InterfaceAlias) { $routes = @($routes | Where-Object { $_.InterfaceAlias -eq $InterfaceAlias }) }
    $route = $routes | Sort-Object RouteMetric | Select-Object -First 1
    if (-not $route) { throw 'Không tìm thấy card mạng đang kết nối (chưa có mạng?).' }
    $idx = $route.InterfaceIndex
    $addr = Get-NetIPAddress -InterfaceIndex $idx -AddressFamily IPv4 -ErrorAction SilentlyContinue | Where-Object { $_.IPAddress -notmatch '^169\.254\.' } | Select-Object -First 1
    if (-not $addr) { throw 'Card mạng chưa có địa chỉ IP.' }
    $iface = Get-NetIPInterface -InterfaceIndex $idx -AddressFamily IPv4
    $own = @(Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue | ForEach-Object { $_.IPAddress })
    $dns = @((Get-DnsClientServerAddress -InterfaceIndex $idx -AddressFamily IPv4 -ErrorAction SilentlyContinue).ServerAddresses)
    $ip = $addr.IPAddress; $prefix = [int]$addr.PrefixLength
    $int = ConvertTo-UInt $ip; $mask = Get-Mask $prefix
    $net = [uint64]($int -band $mask); $bcast = [uint64]($net -bor ((-bnot $mask) -band $ALL))
    return [pscustomobject]@{
        Index = $idx; Alias = $route.InterfaceAlias; Ip = $ip; Prefix = $prefix; Gateway = $route.NextHop
        IsStatic = ($iface.Dhcp -eq 'Disabled'); Own = $own; Dns = $dns
        Int = $int; Net = $net; Bcast = $bcast; GwInt = (ConvertTo-UInt $route.NextHop)
    }
}

# ---------- Quét mạng ----------
$script:Used = @{}   # ip -> mac
function Read-Neighbors($ctx) {
    $nb = Get-NetNeighbor -InterfaceIndex $ctx.Index -AddressFamily IPv4 -ErrorAction SilentlyContinue |
        Where-Object { ([string]$_.State) -in @('Reachable', 'Stale', 'Delay', 'Probe') -and $_.LinkLayerAddress -and $_.LinkLayerAddress -notmatch '^(00-00-00-00-00-00|FF-FF-FF-FF-FF-FF)$' }
    foreach ($n in $nb) {
        if ($ctx.Own -notcontains $n.IPAddress) { $script:Used[$n.IPAddress] = ($n.LinkLayerAddress -replace '-', ':').ToLower() }
    }
}
function Get-ScanRange($ctx) {
    # Mạng lớn hơn /24: chỉ quét dải /24 chứa IP của máy này.
    if ($ctx.Prefix -lt 24) { $first = [uint64]($ctx.Int -band [uint64]4294967040); $last = [uint64]($first -bor 255) } else { $first = $ctx.Net; $last = $ctx.Bcast }
    if ($first -le $ctx.Net) { $first = $ctx.Net }
    if ($last -ge $ctx.Bcast) { $last = $ctx.Bcast }
    return @(($first + 1), ($last - 1))
}
function Invoke-Scan($ctx) {
    $range = Get-ScanRange $ctx
    for ($pass = 1; $pass -le 2; $pass++) {
        $tasks = New-Object System.Collections.ArrayList
        for ($i = [int64]$range[0]; $i -le [int64]$range[1]; $i++) {
            $p = New-Object System.Net.NetworkInformation.Ping
            [void]$tasks.Add($p.SendPingAsync((ConvertTo-Ip ([uint64]$i)), 1000))
        }
        try { [System.Threading.Tasks.Task]::WaitAll([System.Threading.Tasks.Task[]]$tasks.ToArray()) } catch { }
    }
    Start-Sleep -Milliseconds 500
    Read-Neighbors $ctx
}
function Test-HostAlive($ctx, [string] $target) {
    $p = New-Object System.Net.NetworkInformation.Ping
    try { if ($p.Send($target, 1000).Status -eq 'Success') { return $true } } catch { }
    Start-Sleep -Milliseconds 400
    Read-Neighbors $ctx
    return $script:Used.ContainsKey($target)
}
# Router có liên lạc được không? ping, ARP, rồi cổng TCP 80/443/53 (nhiều router chặn ping).
function Test-GatewayReachable($ctx, [string] $gw) {
    Remove-NetNeighbor -InterfaceIndex $ctx.Index -IPAddress $gw -Confirm:$false -ErrorAction SilentlyContinue
    $p = New-Object System.Net.NetworkInformation.Ping
    try { if ($p.Send($gw, 1500).Status -eq 'Success') { return $true } } catch { }
    $nb = Get-NetNeighbor -InterfaceIndex $ctx.Index -IPAddress $gw -ErrorAction SilentlyContinue | Where-Object { ([string]$_.State) -in @('Reachable', 'Stale', 'Delay', 'Probe') -and $_.LinkLayerAddress }
    if ($nb) { return $true }
    foreach ($port in 80, 443, 53) {
        try {
            $c = New-Object System.Net.Sockets.TcpClient
            $ar = $c.BeginConnect($gw, $port, $null, $null)
            $ok = $ar.AsyncWaitHandle.WaitOne(1500) -and $c.Connected
            $c.Close()
            if ($ok) { return $true }
        } catch { }
    }
    return $false
}

# ---------- Kế hoạch: IP không chọn được, IP trống, gợi ý ----------
function Get-FreeRanges($ctx) {
    $range = Get-ScanRange $ctx
    $result = @(); $start = [int64]-1
    for ($i = [int64]$range[0]; $i -le [int64]$range[1] + 1; $i++) {
        $isFree = $false
        if ($i -le [int64]$range[1]) {
            $v = ConvertTo-Ip ([uint64]$i)
            $isFree = ($i -ne [int64]$ctx.GwInt) -and (-not $script:Used.ContainsKey($v)) -and ($v -ne $ctx.Ip) -and ($ctx.Own -notcontains $v)
        }
        if ($isFree) { if ($start -lt 0) { $start = $i } }
        elseif ($start -ge 0) { $result += , @($start, ($i - 1)); $start = -1 }
    }
    return , $result   # dấu phẩy: giữ nguyên mảng-của-mảng kể cả khi chỉ có 1 dải
}
function Get-Suggestion($ctx, $free) {
    $best = $null; $bestScore = 0
    foreach ($r in $free) {
        $len = $r[1] - $r[0] + 1; $dist = 0
        if (-not $ctx.IsStatic) {
            # IP đang nhận tự động nằm trong dải DHCP của router: ưu tiên dải xa IP hiện tại (dải >= 10 địa chỉ).
            if ([int64]$ctx.Int -lt $r[0]) { $dist = $r[0] - [int64]$ctx.Int } elseif ([int64]$ctx.Int -gt $r[1]) { $dist = [int64]$ctx.Int - $r[1] }
            if ($len -ge 10) { $score = $dist * 1000 + $len } else { $score = $len }
        } else { $score = $len }
        if ($score -gt $bestScore) { $bestScore = $score; $best = $r }
    }
    if (-not $best) { return $null }
    if ([int64]$ctx.Int -gt $best[1]) { $ex = $best[0] + 3 } else { $ex = $best[1] - 3 }
    if ($ex -gt $best[1]) { $ex = $best[1] }; if ($ex -lt $best[0]) { $ex = $best[0] }
    $cap = [int64]$ctx.Bcast - 5; if ($ex -gt $cap) { $ex = $cap }; if ($ex -lt $best[0]) { $ex = $best[0] }
    return [pscustomobject]@{ from = (ConvertTo-Ip ([uint64]$best[0])); to = (ConvertTo-Ip ([uint64]$best[1])); example = (ConvertTo-Ip ([uint64]$ex)) }
}
function Get-UsedList($ctx) {
    return @($script:Used.Keys | Where-Object { $_ -ne $ctx.Gateway } | Sort-Object { ConvertTo-UInt $_ } | ForEach-Object { [ordered]@{ ip = $_; mac = $script:Used[$_] } })
}

function Show-Plan($ctx) {
    $free = Get-FreeRanges $ctx; $sug = Get-Suggestion $ctx $free
    Write-Host ''
    Write-Host '══════════ DẢI IP CỦA MẠNG ══════════'
    Write-Host ("  Mạng: {0}/{1}   (card {2}, router {3})" -f (ConvertTo-Ip $ctx.Net), $ctx.Prefix, $ctx.Alias, $ctx.Gateway)
    Write-Host ("  Máy chủ hiện dùng: {0}  ({1})" -f $ctx.Ip, $(if ($ctx.IsStatic) { 'IP tĩnh' } else { 'tự động nhận từ router - DHCP' }))
    Write-Host ("  Có thể đặt IP trong khoảng: {0} đến {1}" -f (ConvertTo-Ip ($ctx.Net + 1)), (ConvertTo-Ip ($ctx.Bcast - 1)))
    Write-Host ''
    Write-Host '  ✗ KHÔNG THỂ CHỌN các IP sau:' -ForegroundColor Red
    Write-Host ("      {0,-15} địa chỉ mạng (network)" -f (ConvertTo-Ip $ctx.Net))
    Write-Host ("      {0,-15} địa chỉ broadcast" -f (ConvertTo-Ip $ctx.Bcast))
    Write-Host ("      {0,-15} router / gateway" -f $ctx.Gateway)
    $usedList = @(Get-UsedList $ctx)
    foreach ($u in ($usedList | Select-Object -First 40)) { Write-Host ("      {0,-15} đang có thiết bị khác dùng (MAC {1})" -f $u.ip, $u.mac) }
    if ($usedList.Count -gt 40) { Write-Host ("      ... và {0} IP khác đang có thiết bị dùng" -f ($usedList.Count - 40)) }
    if ($usedList.Count -eq 0) { Write-Host '      (không thấy thiết bị nào khác trong mạng)' }
    Write-Host ("  ✓ IP của chính máy này ({0}) vẫn chọn lại được (giữ nguyên)." -f $ctx.Ip) -ForegroundColor Green
    Write-Host ''
    Write-Host '  ○ IP đang trống (chưa thấy thiết bị nào dùng):'
    $n = 0
    foreach ($r in $free) {
        $n++
        if ($n -le 12) {
            $a = ConvertTo-Ip ([uint64]$r[0]); $b = ConvertTo-Ip ([uint64]$r[1])
            if ($r[0] -eq $r[1]) { $txt = $a } else { $txt = "$a – $($b.Split('.')[3])" }
            Write-Host ("      {0}   ({1} địa chỉ)" -f $txt, ($r[1] - $r[0] + 1))
        }
    }
    if ($n -gt 12) { Write-Host ("      ... và {0} dải nhỏ khác" -f ($n - 12)) }
    if ($n -eq 0) { Write-Host '      (không còn IP trống trong dải đã quét)' }
    if ($sug) {
        Write-Host ''
        Write-Host ("  ★ GỢI Ý: chọn trong khoảng {0} đến {1}  (ví dụ {2})" -f $sug.from, $sug.to, $sug.example) -ForegroundColor Cyan
        if (-not $ctx.IsStatic) { Write-Host ("    (IP hiện tại {0} do router tự cấp nên nằm trong dải DHCP; dải gợi ý chọn xa IP đó để ít bị trùng)" -f $ctx.Ip) }
    }
    Write-Host ''
    Write-Host '  Lưu ý: máy đang TẮT hoặc ngủ sẽ không hiện ra ở trên. Để chắc chắn không trùng, nên chọn IP NGOÀI dải DHCP của router'
    Write-Host '  (xem trang quản trị router: DHCP Pool / Start IP – End IP) hoặc đặt DHCP reservation cho máy chủ.'
    Write-Host '═════════════════════════════════════'
}

function Write-JsonReport($ctx) {
    $free = Get-FreeRanges $ctx; $sug = Get-Suggestion $ctx $free; $range = Get-ScanRange $ctx
    $freeObjs = @($free | ForEach-Object { [ordered]@{ from = (ConvertTo-Ip ([uint64]$_[0])); to = (ConvertTo-Ip ([uint64]$_[1])); count = [int]($_[1] - $_[0] + 1) } })
    $obj = [ordered]@{
        interface = $ctx.Alias; network = (ConvertTo-Ip $ctx.Net); prefix = $ctx.Prefix; gateway = $ctx.Gateway
        current_ip = $ctx.Ip; is_static = [bool]$ctx.IsStatic
        range_first = (ConvertTo-Ip ($ctx.Net + 1)); range_last = (ConvertTo-Ip ($ctx.Bcast - 1))
        scan_first = (ConvertTo-Ip ([uint64]$range[0])); scan_last = (ConvertTo-Ip ([uint64]$range[1]))
        reserved = @([ordered]@{ ip = (ConvertTo-Ip $ctx.Net); reason = 'network' }, [ordered]@{ ip = (ConvertTo-Ip $ctx.Bcast); reason = 'broadcast' }, [ordered]@{ ip = $ctx.Gateway; reason = 'gateway' })
        used = @(Get-UsedList $ctx); free_ranges = $freeObjs; suggest = $sug
    }
    $obj | ConvertTo-Json -Depth 6 -Compress
}

# Trả về lý do không chọn được (chuỗi), hoặc $null nếu chọn được.
function Test-Candidate($ctx, [string] $ip, [int] $prefix) {
    if (-not (Test-Ipv4 $ip)) { return 'không đúng định dạng a.b.c.d' }
    $n = ConvertTo-UInt $ip; $m = Get-Mask $prefix; $net = [uint64]($n -band $m); $bc = [uint64]($net -bor ((-bnot $m) -band $ALL))
    if ($n -eq $net) { return 'là địa chỉ mạng (network), không dùng được' }
    if ($n -eq $bc) { return 'là địa chỉ broadcast, không dùng được' }
    if ($ip -eq $ctx.Gateway) { return 'là IP của router (gateway)' }
    if ($ip -eq $ctx.Ip -or $ctx.Own -contains $ip) { return $null }
    if ($net -ne $ctx.Net -or $prefix -ne $ctx.Prefix) { return ('nằm ngoài mạng hiện tại {0}/{1} - không đủ thông tin để kiểm tra trùng IP' -f (ConvertTo-Ip $ctx.Net), $ctx.Prefix) }
    if ($script:Used.ContainsKey($ip)) { return ('đang có thiết bị khác dùng (MAC {0})' -f $script:Used[$ip]) }
    if (Test-HostAlive $ctx $ip) { return 'đang có thiết bị khác trả lời tại IP này (trùng IP sẽ làm cả hai mất mạng)' }
    return $null
}
function Expand-ShortIp($ctx, [string] $text) {
    if ($text -match '^\d{1,3}$' -and [int]$text -le 255) {
        $p = (ConvertTo-Ip ($ctx.Int -band [uint64]4294967040)).Split('.')
        return ('{0}.{1}.{2}.{3}' -f $p[0], $p[1], $p[2], $text)
    }
    return $text
}

# ---------- Cập nhật phần mềm QLBH theo IP mới ----------
function Replace-IpInFile([string] $file, [string] $old, [string] $new) {
    if (-not (Test-Path $file)) { return $false }
    $txt = [IO.File]::ReadAllText($file)
    $pat = '(?m)(^|[^0-9.])' + [regex]::Escape($old) + '([^0-9]|$)'
    if ($txt -notmatch $pat) { return $false }
    $bk = Join-Path $Base 'backup'
    New-Item -ItemType Directory -Force -Path $bk | Out-Null
    Copy-Item -LiteralPath $file -Destination (Join-Path $bk ((Split-Path $file -Leaf) + '.' + (Get-Date -Format 'yyyyMMdd-HHmmss'))) -Force
    $out = [regex]::Replace($txt, $pat, ('${1}' + $new + '${2}'))
    $out = [regex]::Replace($out, $pat, ('${1}' + $new + '${2}'))
    [IO.File]::WriteAllText($file, $out, $Utf8NoBom)
    return $true
}
function Invoke-Native([string] $exe, [string[]] $arguments) {
    $psi = New-Object System.Diagnostics.ProcessStartInfo
    $psi.FileName = $exe; $psi.Arguments = ($arguments | ForEach-Object { '"' + $_ + '"' }) -join ' '
    $psi.RedirectStandardError = $true; $psi.RedirectStandardOutput = $true; $psi.UseShellExecute = $false; $psi.CreateNoWindow = $true
    $p = [System.Diagnostics.Process]::Start($psi); [void]$p.StandardOutput.ReadToEnd(); [void]$p.StandardError.ReadToEnd(); $p.WaitForExit()
    if ($p.ExitCode -ne 0) { throw "$exe thất bại (mã $($p.ExitCode))" }
}
function Update-App([string] $old, [string] $new) {
    if (-not $old -or $old -eq $new) { return }
    Write-Host "`n==> Cập nhật QLBH theo IP mới ($old → $new)" -ForegroundColor Cyan
    if ($ProjectRoot) {
        if (Replace-IpInFile (Join-Path $ProjectRoot '.env') $old $new) { Write-Host '  ✓ .env: đã đổi IP cũ sang IP mới (bản cũ lưu ở ProgramData\QLBH\backup)' -ForegroundColor Green }
        else { Write-Host '  .env: không có IP cũ để đổi (APP_URL có thể dùng tên miền).' }
        if (Replace-IpInFile (Join-Path $ProjectRoot 'camera-intelligence\config.yaml') $old $new) { Write-Host '  ✓ Đã đổi IP trong cấu hình camera (khởi động lại dịch vụ camera nếu đang chạy)' -ForegroundColor Green }
        # Bỏ cache cấu hình để Laravel đọc lại .env (không chạy mã PHP của dự án bằng quyền cao).
        Remove-Item -LiteralPath (Join-Path $ProjectRoot 'bootstrap\cache\config.php') -Force -ErrorAction SilentlyContinue
    }
    # Chứng chỉ HTTPS gắn với IP: cấp lại (CA gốc giữ nguyên nên điện thoại không phải cài lại).
    $sslDir = Join-Path $LaragonRoot 'etc\ssl\qlbh'
    if ((Test-Path (Join-Path $sslDir 'rootCA.key')) -and (Test-Path (Join-Path $sslDir 'rootCA.crt'))) {
        try {
            $openssl = (Get-Command openssl -ErrorAction SilentlyContinue).Source
            if (-not $openssl) { $openssl = Get-ChildItem (Join-Path $LaragonRoot 'bin') -Recurse -Filter openssl.exe -ErrorAction SilentlyContinue | Select-Object -First 1 -ExpandProperty FullName }
            if (-not $openssl) { throw 'không tìm thấy openssl.exe' }
            $tmp = Join-Path $env:TEMP ('qlbh-cert-' + [guid]::NewGuid().ToString('N')); New-Item -ItemType Directory -Force -Path $tmp | Out-Null
            Invoke-Native $openssl @('genrsa', '-out', "$tmp\server.key", '2048')
            Invoke-Native $openssl @('req', '-new', '-key', "$tmp\server.key", '-subj', "/CN=$new", '-out', "$tmp\server.csr")
            [IO.File]::WriteAllText("$tmp\server.ext", "subjectAltName=IP:$new,IP:127.0.0.1,DNS:localhost`nbasicConstraints=CA:FALSE`nkeyUsage=digitalSignature,keyEncipherment`nextendedKeyUsage=serverAuth`n", [Text.Encoding]::ASCII)
            Invoke-Native $openssl @('x509', '-req', '-in', "$tmp\server.csr", '-CA', "$sslDir\rootCA.crt", '-CAkey', "$sslDir\rootCA.key", '-CAcreateserial', '-out', "$tmp\server.crt", '-days', '825', '-sha256', '-extfile', "$tmp\server.ext")
            Copy-Item "$tmp\server.key" "$sslDir\server.key" -Force; Copy-Item "$tmp\server.crt" "$sslDir\server.crt" -Force
            Remove-Item $tmp -Recurse -Force -ErrorAction SilentlyContinue
            [void](Replace-IpInFile (Join-Path $LaragonRoot 'etc\apache2\sites-enabled\00-qlbh-ssl.conf') $old $new)
            Write-Host "  ✓ Đã cấp lại chứng chỉ HTTPS cho $new. Trong Laragon bấm Stop All rồi Start All để Apache nạp chứng chỉ mới." -ForegroundColor Green
        } catch { Write-Host ('  ✗ Cấp lại chứng chỉ HTTPS thất bại (giữ chứng chỉ cũ): ' + $_.Exception.Message) -ForegroundColor Red }
    }
}

# ---------- Đặt IP tĩnh / quay lại DHCP ----------
function Clear-IpConfig($idx) {
    Get-NetRoute -InterfaceIndex $idx -DestinationPrefix '0.0.0.0/0' -ErrorAction SilentlyContinue | Remove-NetRoute -Confirm:$false -ErrorAction SilentlyContinue
    Get-NetIPAddress -InterfaceIndex $idx -AddressFamily IPv4 -ErrorAction SilentlyContinue | Remove-NetIPAddress -Confirm:$false -ErrorAction SilentlyContinue
}
function Set-StaticIp($idx, [string] $ip, [int] $prefix, [string] $gw, [string[]] $dns) {
    Clear-IpConfig $idx
    Set-NetIPInterface -InterfaceIndex $idx -AddressFamily IPv4 -Dhcp Disabled
    New-NetIPAddress -InterfaceIndex $idx -AddressFamily IPv4 -IPAddress $ip -PrefixLength $prefix -DefaultGateway $gw | Out-Null
    if ($dns.Count -gt 0) { Set-DnsClientServerAddress -InterfaceIndex $idx -ServerAddresses $dns }
}
function Set-DhcpIp($idx) {
    Clear-IpConfig $idx
    Set-NetIPInterface -InterfaceIndex $idx -AddressFamily IPv4 -Dhcp Enabled
    Set-DnsClientServerAddress -InterfaceIndex $idx -ResetServerAddresses
    & ipconfig.exe /renew | Out-Null
}
function Wait-Network($ctx, [string] $ip, [string] $gw) {
    for ($i = 0; $i -lt 20; $i++) {
        $a = Get-NetIPAddress -InterfaceIndex $ctx.Index -AddressFamily IPv4 -ErrorAction SilentlyContinue | Where-Object { $_.IPAddress -eq $ip -and ([string]$_.AddressState) -eq 'Preferred' }
        if ($a -and (Test-GatewayReachable $ctx $gw)) { return $true }
        Start-Sleep -Seconds 2
    }
    return $false
}

function Assert-Admin {
    $isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
    if (-not $isAdmin) { throw 'Hãy mở PowerShell bằng "Run as administrator" rồi chạy lại.' }
}

# Thực hiện đổi IP. Ném lỗi (throw) với thông báo dễ hiểu nếu không đổi được.
function Invoke-Change($ctx, [string] $ipText, [int] $prefix, [string] $gw, [string] $dnsText) {
    Assert-Admin
    if (-not (Test-Ipv4 $ipText)) { throw "IP không hợp lệ: $ipText" }
    if ($prefix -lt 8 -or $prefix -gt 30) { $prefix = $ctx.Prefix }
    if (-not $gw) { $gw = $ctx.Gateway }
    if (-not (Test-Ipv4 $gw)) { throw "Gateway không hợp lệ: $gw" }
    $mask = Get-Mask $prefix
    if (([uint64]((ConvertTo-UInt $gw) -band $mask)) -ne ([uint64]((ConvertTo-UInt $ipText) -band $mask))) { throw "Gateway $gw không cùng mạng với IP $ipText/$prefix." }
    if ($gw -eq $ipText) { throw 'Gateway không được trùng IP máy chủ.' }
    $dnsList = @()
    if ($dnsText) { $dnsList = @($dnsText.Split(',') | ForEach-Object { $_.Trim() } | Where-Object { $_ }) } else { $dnsList = @($gw, '8.8.8.8', '1.1.1.1') }
    foreach ($d in $dnsList) { if (-not (Test-Ipv4 $d)) { throw "DNS không hợp lệ: $d" } }
    $reason = Test-Candidate $ctx $ipText $prefix
    if ($reason -and -not ($reason -like 'nằm ngoài mạng hiện tại*')) { throw "Không thể dùng IP ${ipText}: $reason." }
    if ($gw -ne $ctx.Gateway -and -not (Test-HostAlive $ctx $gw)) { throw "Gateway $gw không trả lời." }

    $old = [pscustomobject]@{ IsStatic = $ctx.IsStatic; Ip = $ctx.Ip; Prefix = $ctx.Prefix; Gateway = $ctx.Gateway; Dns = $ctx.Dns }
    Write-Host "`n==> Đổi IP: $($old.Ip) → $ipText/$prefix  (gateway $gw, DNS $($dnsList -join ', '))" -ForegroundColor Cyan
    try {
        Set-StaticIp $ctx.Index $ipText $prefix $gw $dnsList
        if (-not (Wait-Network $ctx $ipText $gw)) { throw 'mất kết nối' }
    } catch {
        Write-Host 'Mất kết nối sau khi đổi IP, đang quay lại cấu hình cũ...' -ForegroundColor Yellow
        try {
            if ($old.IsStatic) { Set-StaticIp $ctx.Index $old.Ip $old.Prefix $old.Gateway @($old.Dns) } else { Set-DhcpIp $ctx.Index }
        } catch { Write-Host ('Không khôi phục được tự động: ' + $_.Exception.Message) -ForegroundColor Red }
        throw 'Mất kết nối sau khi đổi IP, máy đã quay lại cấu hình cũ.'
    }
    Write-Host "  ✓ Card mạng đang dùng IP $ipText, liên lạc được với router $gw" -ForegroundColor Green
    Update-App $old.Ip $ipText
    return $ipText
}

# ---------- Các chế độ chạy ----------
try {
    if ($ApplyRequest) {
        # Yêu cầu từ website: file nhỏ do trang Cài đặt ghi, tác vụ nền "QLBH Helper" (SYSTEM) gọi script này. Mọi giá trị đều kiểm tra lại.
        Assert-Admin
        try {
            if (-not (Test-Path $ReqFile) -or (Get-Item $ReqFile).Length -gt 1024) { throw 'Không có yêu cầu đổi IP hợp lệ.' }
            $req = @{}
            foreach ($line in [IO.File]::ReadAllLines($ReqFile)) {
                if ($line -match '^(ip|prefix|gateway|dns)=([0-9./,]{0,100})$') { $req[$Matches[1]] = $Matches[2] }
                elseif ($line.Trim()) { throw 'Yêu cầu có ký tự không hợp lệ.' }
            }
            Remove-Item -LiteralPath $ReqFile -Force
            $script:ReqIp = [string]$req['ip']
            if (-not $script:ReqIp) { throw 'Yêu cầu thiếu IP.' }
            $ctx = Get-Context; $script:OldIp = $ctx.Ip
            Write-Status 'running' 'Đang đổi IP...'
            $prefix = 0; if ($req['prefix']) { $prefix = [int]$req['prefix'] }
            [void](Invoke-Change $ctx $script:ReqIp $prefix ([string]$req['gateway']) ([string]$req['dns']))
            Write-Status 'ok' 'Đã đổi IP xong.'
        } catch {
            Remove-Item -LiteralPath $ReqFile -Force -ErrorAction SilentlyContinue
            Write-Status 'failed' $_.Exception.Message
            exit 1
        }
        exit 0
    }

    $ctx = Get-Context

    if ($Status) {
        $appHost = ''
        $envFile = Join-Path $ProjectRoot '.env'
        if ($ProjectRoot -and (Test-Path $envFile)) {
            $m = [regex]::Match([IO.File]::ReadAllText($envFile), '(?m)^APP_URL=["'']?[a-z]+://([^/:"''\r\n]+)')
            if ($m.Success) { $appHost = $m.Groups[1].Value }
        }
        ([ordered]@{ current_ip = $ctx.Ip; is_static = [bool]$ctx.IsStatic; app_url_host = $appHost } | ConvertTo-Json -Compress)
        exit 0
    }

    if ($Check) {
        Read-Neighbors $ctx
        $c = $Check
        $c = Expand-ShortIp $ctx $c
        $reason = Test-Candidate $ctx $c $ctx.Prefix
        if ($reason) { ([ordered]@{ ok = $false; reason = $reason } | ConvertTo-Json -Compress) } else { '{"ok":true,"reason":""}' }
        exit 0
    }

    if ($Revert) {
        Assert-Admin
        $old = $ctx.Ip
        Write-Host '==> Quay lại tự động nhận IP (DHCP)' -ForegroundColor Cyan
        Set-DhcpIp $ctx.Index
        Start-Sleep -Seconds 6
        $now = (Get-NetIPAddress -InterfaceIndex $ctx.Index -AddressFamily IPv4 -ErrorAction SilentlyContinue | Where-Object { $_.IPAddress -notmatch '^169\.254\.' } | Select-Object -First 1).IPAddress
        Write-Host "Xong. IP hiện tại: $now"
        if ($now) { Update-App $old $now }
        exit 0
    }

    if (-not $Json) { Write-Host ("Đang quét mạng {0}/{1} để tìm IP đang được dùng (khoảng 5-15 giây)..." -f (ConvertTo-Ip $ctx.Net), $ctx.Prefix) }
    Invoke-Scan $ctx

    if ($Json) { Write-JsonReport $ctx; exit 0 }
    # Chế độ máy đọc (-Json) in JSON thuần; các chế độ còn lại in bảng cho người xem.
    Show-Plan $ctx
    if ($List) { exit 0 }

    $interactive = (-not $Yes) -and (-not $Ip) -and [Environment]::UserInteractive
    $newIp = $Ip
    if ($interactive) {
        Write-Host ''
        if ($ctx.IsStatic) { Write-Host 'Máy đang dùng IP tĩnh. Bạn có thể đổi sang IP khác ngay cả khi hệ thống đang hoạt động.' }
        Write-Host 'Nếu đang điều khiển máy từ xa (Remote Desktop), đổi IP sẽ làm mất kết nối hiện tại: kết nối lại bằng IP mới.'
        while ($true) {
            $typed = (Read-Host "Nhập IP mới (đủ 4 số, hoặc chỉ số cuối) [Enter = giữ $($ctx.Ip)]").Trim()
            if (-not $typed) { $newIp = $ctx.Ip; break }
            $typed = Expand-ShortIp $ctx $typed
            $reason = Test-Candidate $ctx $typed $ctx.Prefix
            if (-not $reason) { $newIp = $typed; break }
            Write-Host "  ✗ ${typed}: $reason. Chọn IP khác." -ForegroundColor Red
        }
        $g = (Read-Host "Router / gateway [Enter = $($ctx.Gateway)]").Trim(); if ($g) { $Gateway = $g }
        $d = (Read-Host "DNS, cách nhau bằng dấu phẩy [Enter = gateway,8.8.8.8,1.1.1.1]").Trim(); if ($d) { $Dns = $d }
        $ok = (Read-Host "Áp dụng IP $newIp? [Y/n]").Trim()
        if ($ok -and $ok -notmatch '^[YyCc]') { Write-Host 'Đã hủy, không đổi gì.'; exit 0 }
    } else {
        if (-not $newIp) { $newIp = $ctx.Ip }
        $newIp = Expand-ShortIp $ctx $newIp
    }
    $script:OldIp = $ctx.Ip; $script:ReqIp = $newIp
    [void](Invoke-Change $ctx $newIp 0 $Gateway $Dns)
    Write-Host "`n==> Xong. IP tĩnh: $newIp" -ForegroundColor Green
    Write-Host "    Truy cập: https://$newIp  (hoặc http://$newIp)"
    Write-Host "    Nên đặt thêm 'DHCP reservation' cho máy này trên router để router không cấp IP $newIp cho thiết bị khác."
} catch {
    Write-Host ('Lỗi: ' + $_.Exception.Message) -ForegroundColor Red
    exit 1
}
