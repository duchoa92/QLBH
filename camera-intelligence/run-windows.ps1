[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot
$python = Join-Path $root '.venv\Scripts\python.exe'
$config = Join-Path $root 'config.yaml'

if (-not (Test-Path $python)) { throw "Khong tim thay Python environment: $python" }
if (-not (Test-Path $config)) { throw "Khong tim thay cau hinh: $config" }

Set-Location $root
& $python -m app.main --config $config
exit $LASTEXITCODE
