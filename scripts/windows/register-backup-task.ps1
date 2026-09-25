[CmdletBinding(SupportsShouldProcess, ConfirmImpact = 'Medium')]
param(
    [ValidatePattern('^(?:[01]\d|2[0-3]):[0-5]\d$')]
    [string] $At = '20:00',
    [switch] $Force
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'common.ps1')

$taskName = 'SIGME - Backup diario criptografado'
$location = Get-SigmeWslLocation
$linuxScript = "$($location.ProjectPath)/scripts/wsl/backup.sh"

& wsl.exe -d $location.Distro -- test -x $linuxScript
if ($LASTEXITCODE -ne 0) {
    throw "Script de backup ausente ou sem permissao de execucao no WSL: $linuxScript"
}

$existingTask = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
if ($existingTask -and -not $Force) {
    throw "A tarefa '$taskName' ja existe. Use -Force para substitui-la conscientemente."
}

$scheduleTime = [DateTime]::Today.Add([TimeSpan]::ParseExact($At, 'hh\:mm', [Globalization.CultureInfo]::InvariantCulture))
$wslExecutable = Join-Path $env:WINDIR 'System32\wsl.exe'
$wslArguments = "-d `"$($location.Distro)`" -- bash `"$linuxScript`" --label scheduled"

$action = New-ScheduledTaskAction -Execute $wslExecutable -Argument $wslArguments
$trigger = New-ScheduledTaskTrigger -Daily -At $scheduleTime
$settings = New-ScheduledTaskSettingsSet `
    -StartWhenAvailable `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -MultipleInstances IgnoreNew `
    -RestartCount 3 `
    -RestartInterval (New-TimeSpan -Minutes 10) `
    -ExecutionTimeLimit (New-TimeSpan -Hours 2)
$principal = New-ScheduledTaskPrincipal `
    -UserId ([Security.Principal.WindowsIdentity]::GetCurrent().Name) `
    -LogonType Interactive `
    -RunLevel Limited

$description = "Executa backup criptografado do SIGME no WSL '$($location.Distro)' em '$($location.ProjectPath)'. Docker deve estar disponivel. Nao contem senhas."

if ($Force) {
    $ConfirmPreference = 'None'
}

if ($PSCmdlet.ShouldProcess("$taskName ($At, WSL $($location.Distro))", 'Registrar tarefa diaria de backup')) {
    if ($existingTask) {
        Unregister-ScheduledTask -TaskName $taskName -Confirm:$false
    }

    Register-ScheduledTask `
        -TaskName $taskName `
        -Action $action `
        -Trigger $trigger `
        -Settings $settings `
        -Principal $principal `
        -Description $description | Out-Null

    Write-Host "Tarefa registrada: $taskName" -ForegroundColor Green
    Write-Host "Horario diario: $At (execucao perdida inicia quando o computador voltar)."
    Write-Host "Projeto WSL: $($location.Distro):$($location.ProjectPath)"
    Write-Host 'A tarefa nao foi iniciada agora. Use o Agendador ou Start-ScheduledTask para um teste consciente.'
}
