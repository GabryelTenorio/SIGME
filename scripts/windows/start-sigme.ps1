[CmdletBinding()]
param()

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'common.ps1')

$dockerDesktopPaths = @(
    'C:\Program Files\Docker\Docker\Docker Desktop.exe',
    (Join-Path $env:LOCALAPPDATA 'Docker\Docker Desktop.exe')
)
$dockerDesktopPath = $dockerDesktopPaths | Where-Object { Test-Path -LiteralPath $_ } | Select-Object -First 1

if ($dockerDesktopPath -and -not (Get-Process -Name 'Docker Desktop' -ErrorAction SilentlyContinue)) {
    Start-Process -FilePath $dockerDesktopPath -WindowStyle Hidden
}

& (Join-Path $PSScriptRoot 'check-prerequisites.ps1')
if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}

Invoke-SigmeWslScript -Name 'start.sh'
Start-Process 'http://localhost:8080'
