[CmdletBinding()]
param(
    [ValidateSet('Install', 'Start', 'Stop', 'Status', 'Admin', 'Logs')]
    [string] $Action = 'Start',
    [ValidateRange(1024, 65535)]
    [int] $Port = 8080,
    [ValidateRange(1024, 65535)]
    [int] $MailpitPort = 8025,
    [switch] $NoBrowser
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$sigmeRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).ProviderPath
$sigmeEnv = Join-Path $sigmeRoot '.env.windows'
$sigmeCompose = Join-Path $sigmeRoot 'compose.windows.yaml'

function Find-DockerCli {
    $command = Get-Command docker.exe -ErrorAction SilentlyContinue
    if ($command) { return $command.Source }
    $candidates = @(
        "$env:ProgramFiles\Docker\Docker\resources\bin\docker.exe",
        "$env:LOCALAPPDATA\Programs\DockerDesktop\resources\bin\docker.exe",
        "$env:LOCALAPPDATA\Docker\resources\bin\docker.exe"
    )
    foreach ($candidate in $candidates) {
        if (Test-Path -LiteralPath $candidate) { return $candidate }
    }
    throw 'Docker Desktop nao encontrado. Instale-o, abra-o e selecione Linux containers. Consulte README.md.'
}

function New-RandomSecret {
    $bytes = New-Object byte[] 32
    $generator = [Security.Cryptography.RandomNumberGenerator]::Create()
    try { $generator.GetBytes($bytes) } finally { $generator.Dispose() }
    return [Convert]::ToBase64String($bytes)
}

function Read-SigmeSetting([string] $Key) {
    foreach ($line in Get-Content -LiteralPath $sigmeEnv) {
        if ($line.StartsWith("${Key}=")) { return $line.Substring($Key.Length + 1).Trim() }
    }
    throw "Configuracao $Key ausente em .env.windows. Preserve as chaves existentes e consulte .env.windows.example."
}

function Invoke-SigmeDocker([string[]] $DockerArguments) {
    & $script:dockerCli @DockerArguments
    if ($LASTEXITCODE -ne 0) { throw "Docker terminou com codigo $LASTEXITCODE. Veja as mensagens acima." }
}

function Invoke-SigmeCompose([string[]] $ComposeArguments) {
    Invoke-SigmeDocker -DockerArguments (@(
        'compose', '--project-name', $script:sigmeProject,
        '--project-directory', $sigmeRoot, '--env-file', $sigmeEnv,
        '-f', $sigmeCompose
    ) + $ComposeArguments)
}

function Assert-AvailablePorts {
    foreach ($portNumber in @($script:appPort, $script:mailPort)) {
        $existing = @(Get-NetTCPConnection -State Listen -LocalPort $portNumber -ErrorAction SilentlyContinue)
        if ($existing.Count -eq 0) { continue }
        $owned = @(& $script:dockerCli ps --filter "label=com.docker.compose.project=$script:sigmeProject" --format '{{.Ports}}')
        if ($LASTEXITCODE -ne 0) { throw 'Nao foi possivel consultar os containers.' }
        if (($owned -join ' ') -notmatch "127\.0\.0\.1:${portNumber}->") {
            throw "Porta $portNumber ocupada por outro programa. Escolha portas livres em .env.windows."
        }
    }
}

try {
    $script:dockerCli = Find-DockerCli
    $engine = & $script:dockerCli info --format '{{.OSType}}'
    if ($LASTEXITCODE -ne 0) { throw 'Abra o Docker Desktop e aguarde o mecanismo ficar pronto.' }
    if (($engine -join '').Trim() -ne 'linux') { throw 'No Docker Desktop, selecione Switch to Linux containers.' }
    Invoke-SigmeDocker -DockerArguments @('compose', 'version')

    if (-not (Test-Path -LiteralPath $sigmeEnv)) {
        if ($Action -ne 'Install') { throw 'Execute Instalar SIGME Docker.cmd primeiro.' }
        if ($Port -eq $MailpitPort) { throw 'As portas do SIGME e do Mailpit devem ser diferentes.' }
        $settings = @(
            'SIGME_WINDOWS_PROJECT=sigme-windows',
            "APP_PORT=$Port", "MAILPIT_PORT=$MailpitPort", 'DB_DATABASE=sigme',
            "APP_KEY=base64:$(New-RandomSecret)",
            "DB_PASSWORD=$(New-RandomSecret)",
            "DB_ROOT_PASSWORD=$(New-RandomSecret)"
        )
        [IO.File]::WriteAllText($sigmeEnv, ($settings -join "`n") + "`n", [Text.UTF8Encoding]::new($false))
        Write-Host 'Configuracao criada em .env.windows. Guarde este arquivo junto com seus backups.'
    }

    $script:sigmeProject = Read-SigmeSetting 'SIGME_WINDOWS_PROJECT'
    if ($script:sigmeProject -notmatch '^sigme-windows(?:-[a-z0-9-]+)?$') {
        throw 'SIGME_WINDOWS_PROJECT deve ser sigme-windows ou iniciar com sigme-windows-.'
    }
    $script:appPort = [int](Read-SigmeSetting 'APP_PORT')
    $script:mailPort = [int](Read-SigmeSetting 'MAILPIT_PORT')
    if ($script:appPort -lt 1024 -or $script:appPort -gt 65535 -or $script:mailPort -lt 1024 -or $script:mailPort -gt 65535 -or $script:appPort -eq $script:mailPort) {
        throw 'Defina duas portas diferentes entre 1024 e 65535 em .env.windows.'
    }
    Invoke-SigmeCompose -ComposeArguments @('config', '--quiet')

    switch ($Action) {
        'Install' {
            Assert-AvailablePorts
            Write-Host 'Construindo o SIGME. A primeira instalacao baixa as dependencias e pode levar varios minutos.'
            Invoke-SigmeCompose -ComposeArguments @('build', 'app')
            Invoke-SigmeCompose -ComposeArguments @('stop', 'app', 'queue', 'scheduler')
            Invoke-SigmeCompose -ComposeArguments @('up', '-d', '--wait', '--wait-timeout', '240', 'mysql', 'mailpit')
            Invoke-SigmeCompose -ComposeArguments @('run', '--rm', '--no-deps', 'app', 'php', 'artisan', 'migrate', '--force', '--no-interaction')
            Invoke-SigmeCompose -ComposeArguments @('up', '-d', '--wait', '--wait-timeout', '180')
            Write-Host 'Instalacao concluida. Use Criar administrador SIGME Docker.cmd para criar seu acesso.'
        }
        'Start' {
            Assert-AvailablePorts
            Invoke-SigmeCompose -ComposeArguments @('up', '-d', '--no-build', '--wait', '--wait-timeout', '180')
        }
        'Stop' { Invoke-SigmeCompose -ComposeArguments @('stop') }
        'Status' { Invoke-SigmeCompose -ComposeArguments @('ps', '--all') }
        'Admin' { Invoke-SigmeCompose -ComposeArguments @('exec', 'app', 'php', 'artisan', 'sigme:create-admin', '--generate') }
        'Logs' { Invoke-SigmeCompose -ComposeArguments @('logs', '--tail=100', 'app', 'queue', 'scheduler', 'mysql') }
    }

    if ($Action -in @('Install', 'Start', 'Status')) {
        $url = "http://localhost:$script:appPort"
        $health = Invoke-WebRequest -Uri "$url/up" -UseBasicParsing -TimeoutSec 15
        if ($health.StatusCode -ne 200 -or $health.Headers['X-Sigme-Service'] -ne 'SIGME') {
            throw 'O endereco respondeu, mas nao confirmou a saude do SIGME.'
        }
        Write-Host "SIGME disponivel: $url" -ForegroundColor Green
        Write-Host "E-mails locais: http://localhost:$script:mailPort"
        if (-not $NoBrowser -and $Action -ne 'Status') { Start-Process $url }
    }
} catch {
    Write-Host "ERRO: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}
