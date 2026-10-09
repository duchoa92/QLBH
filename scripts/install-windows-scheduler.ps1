param(
    [string] $TaskName = 'QLBH Laravel Scheduler',
    [string] $PhpPath
)

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$artisanPath = Join-Path $projectRoot 'artisan'

if (-not $PhpPath) {
    $phpCommand = Get-Command php -ErrorAction Stop
    $PhpPath = $phpCommand.Source
}

if (-not (Test-Path -LiteralPath $PhpPath -PathType Leaf)) {
    throw "PHP CLI not found: $PhpPath"
}

if (-not (Test-Path -LiteralPath $artisanPath -PathType Leaf)) {
    throw "Laravel artisan not found: $artisanPath"
}

$taskAction = '"{0}" "{1}" schedule:run' -f $PhpPath, $artisanPath
$result = & schtasks.exe /Create /SC MINUTE /MO 1 /TN $TaskName /TR $taskAction /F 2>&1
if ($LASTEXITCODE -ne 0) {
    throw "Could not install Scheduled Task: $($result -join ' ')"
}

Write-Output "Installed task '$TaskName' to run Laravel Scheduler every minute."
Write-Output 'Enable Automatic Backup in Settings > Backup and Restore to activate the backup schedule.'
Write-Output 'The computer must be on; backups use the timezone selected in the application.'
