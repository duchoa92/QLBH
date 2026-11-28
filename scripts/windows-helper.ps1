<#
.SYNOPSIS
    Tác vụ nền "QLBH Helper" (chạy bằng SYSTEM, tự khởi động cùng Windows). Theo dõi thư mục %ProgramData%\QLBH\inbox:
      - ip-change.req : yêu cầu đổi IP từ trang Cài đặt  -> gọi windows-network.ps1 -ApplyRequest (tự kiểm tra lại mọi giá trị)
      - power.req     : "restart" hoặc "shutdown"         -> khởi động lại / tắt máy sau 5 giây
    Website (không có quyền quản trị) chỉ ghi các file nhỏ này; bản script này nằm ở ProgramData\QLBH\bin mà người dùng thường không sửa được.
    Cài bằng scripts\install-windows-helper.ps1 (install-windows.ps1 tự chạy).
#>
$ErrorActionPreference = 'Continue'
$base = Join-Path $env:ProgramData 'QLBH'
$inbox = Join-Path $base 'inbox'
$network = Join-Path $base 'bin\windows-network.ps1'
New-Item -ItemType Directory -Force -Path $inbox | Out-Null

while ($true) {
    try {
        $req = Join-Path $inbox 'ip-change.req'
        if (Test-Path -LiteralPath $req) {
            Start-Sleep -Milliseconds 300   # đợi website ghi xong
            & powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -File $network -ApplyRequest *>> (Join-Path $base 'network-change.log')
        }
        $power = Join-Path $inbox 'power.req'
        if (Test-Path -LiteralPath $power) {
            $action = ''
            if ((Get-Item -LiteralPath $power).Length -lt 64) { $action = ([IO.File]::ReadAllText($power)).Trim() }
            Remove-Item -LiteralPath $power -Force -ErrorAction SilentlyContinue
            if ($action -eq 'restart') { & shutdown.exe /r /t 5 /c "QLBH: khởi động lại theo yêu cầu từ trang Cài đặt" }
            elseif ($action -eq 'shutdown') { & shutdown.exe /s /t 5 /c "QLBH: tắt máy theo yêu cầu từ trang Cài đặt" }
        }
    } catch { }
    Start-Sleep -Seconds 2
}
