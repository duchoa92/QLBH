[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$ProjectRoot
)

$ErrorActionPreference = 'Stop'
$launcher = Join-Path $ProjectRoot 'camera-intelligence\run-windows.ps1'
$taskName = 'QLBH Camera Intelligence'

if (-not (Test-Path $launcher)) {
    throw "Missing Camera Intelligence launcher: $launcher"
}

$identity = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name
if ([string]::IsNullOrWhiteSpace($identity)) {
    throw 'Cannot determine the current Windows account for the Camera Intelligence startup task.'
}

$powerShell = "$env:WINDIR\System32\WindowsPowerShell\v1.0\powershell.exe"
$action = New-ScheduledTaskAction -Execute $powerShell -Argument ('-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File "{0}"' -f $launcher)
$trigger = New-ScheduledTaskTrigger -AtLogOn -User $identity
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1) -ExecutionTimeLimit (New-TimeSpan -Days 0)

Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings -Force | Out-Null
Write-Output "Registered $taskName for $identity."
