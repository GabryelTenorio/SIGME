Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$utf8NoBom = [System.Text.UTF8Encoding]::new($false)
[Console]::OutputEncoding = $utf8NoBom
$global:OutputEncoding = $utf8NoBom

function Get-SigmeWslLocation {
    $projectWindowsPath = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).ProviderPath
    $match = [regex]::Match($projectWindowsPath, '^\\\\wsl(?:\.localhost|\$)\\(?<Distro>[^\\]+)(?<LinuxPath>\\.*)$')

    if (-not $match.Success) {
        throw 'O projeto deve ser executado diretamente do filesystem WSL, por exemplo \\wsl.localhost\Ubuntu-24.04\home\usuario\projetos\sigme.'
    }

    [pscustomobject]@{
        Distro = $match.Groups['Distro'].Value
        ProjectPath = $match.Groups['LinuxPath'].Value.Replace('\', '/')
    }
}

function Invoke-SigmeWslScript {
    param(
        [Parameter(Mandatory)]
        [string] $Name
    )

    $location = Get-SigmeWslLocation
    $scriptPath = "$($location.ProjectPath)/scripts/wsl/$Name"

    & wsl.exe -d $location.Distro -- bash $scriptPath

    if ($LASTEXITCODE -ne 0) {
        throw "O script WSL '$Name' terminou com código $LASTEXITCODE."
    }
}
