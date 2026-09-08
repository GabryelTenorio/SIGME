[CmdletBinding()]
param()

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'common.ps1')

$problems = [System.Collections.Generic.List[string]]::new()
$sigmePublishedPorts = ''

try {
    $windows = Get-CimInstance Win32_OperatingSystem
    if ([Environment]::OSVersion.Version.Build -lt 22000) {
        $problems.Add('Windows 11 não detectado. Atualize para Windows 11 e execute novamente.')
    }

    if (-not (Get-Command wsl.exe -ErrorAction SilentlyContinue)) {
        $problems.Add('WSL ausente. Instale com "wsl --install -d Ubuntu-24.04" em um PowerShell administrativo e reinicie.')
    } else {
        $location = Get-SigmeWslLocation
        $wslMode = ((& wsl.exe --list --verbose | Out-String) -replace "`0", '')
        if ($wslMode -notmatch [regex]::Escape($location.Distro)) {
            $problems.Add("Distribuição '$($location.Distro)' não encontrada no WSL.")
        }

        $osRelease = ((& wsl.exe -d $location.Distro -- cat /etc/os-release | Out-String) -replace "`0", '')
        $ubuntuVersionMatch = [regex]::Match($osRelease, '(?m)^VERSION_ID="?([^"\r\n]+)')
        $ubuntuVersion = if ($ubuntuVersionMatch.Success) {
            $ubuntuVersionMatch.Groups[1].Value
        } else {
            'desconhecida'
        }
        if ($ubuntuVersion -ne '24.04') {
            $problems.Add("Ubuntu 24.04 LTS é obrigatório; versão detectada: '$ubuntuVersion'. Instale a distribuição Ubuntu-24.04.")
        }

        $linuxProjectPath = $location.ProjectPath
        if ($linuxProjectPath -like '/mnt/*') {
            $problems.Add('O projeto está em /mnt. Clone-o novamente em ~/projetos/sigme dentro do Ubuntu.')
        }

        & wsl.exe -d $location.Distro -- docker info *> $null
        if ($LASTEXITCODE -ne 0) {
            $problems.Add('Docker não responde no Ubuntu. Inicie o Docker Desktop e habilite Settings > Resources > WSL Integration.')
        } else {
            $sigmePublishedPorts = ((
                & wsl.exe -d $location.Distro -- docker ps --filter 'label=com.docker.compose.project=sigme' --format '{{.Ports}}' |
                    Out-String
            ) -replace "`0", '')
        }
    }

    $dockerDesktopPaths = @(
        'C:\Program Files\Docker\Docker\Docker Desktop.exe',
        (Join-Path $env:LOCALAPPDATA 'Docker\Docker Desktop.exe')
    )
    $dockerDesktopPath = $dockerDesktopPaths | Where-Object { Test-Path -LiteralPath $_ } | Select-Object -First 1
    if (-not $dockerDesktopPath) {
        $problems.Add('Docker Desktop não está instalado. Instale-o com backend WSL 2 e habilite a integração da distribuição Ubuntu-24.04.')
    } elseif (-not (Get-Process -Name 'Docker Desktop' -ErrorAction SilentlyContinue)) {
        $problems.Add('Docker Desktop está instalado, mas não está em execução. Inicie-o e aguarde o status operacional.')
    }

    $computerSystem = Get-CimInstance Win32_ComputerSystem
    if (-not $computerSystem.HypervisorPresent) {
        $problems.Add('Virtualização/hipervisor não detectado. Habilite a virtualização no firmware e os recursos necessários do WSL 2.')
    }

    $systemDrive = Get-CimInstance Win32_LogicalDisk -Filter "DeviceID='C:'"
    if ($systemDrive.FreeSpace -lt 20GB) {
        $problems.Add('Há menos de 20 GB livres em C:. Libere espaço antes de baixar imagens e manter volumes do Docker.')
    }

    foreach ($port in 8080, 8025, 1025, 5173) {
        $listener = Get-NetTCPConnection -State Listen -LocalPort $port -ErrorAction SilentlyContinue
        $ownedBySigme = $sigmePublishedPorts -match "127\.0\.0\.1:${port}->"

        if ($listener -and -not $ownedBySigme) {
            $problems.Add("A porta $port já está ocupada. Encerre o processo conflitante antes de iniciar o SIGME.")
        }
    }
} catch {
    $problems.Add("Falha durante a verificação: $($_.Exception.Message)")
}

if ($problems.Count -gt 0) {
    Write-Host 'Pré-requisitos do SIGME não atendidos:' -ForegroundColor Red
    foreach ($problem in $problems) {
        Write-Host " - $problem" -ForegroundColor Yellow
    }
    Write-Host 'Corrija os itens e execute novamente scripts\windows\check-prerequisites.ps1.'
    exit 1
}

Write-Host 'Pré-requisitos verificados com sucesso.' -ForegroundColor Green
