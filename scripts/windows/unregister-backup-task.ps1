[CmdletBinding(SupportsShouldProcess, ConfirmImpact = 'High')]
param(
    [switch] $Force
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$taskName = 'SIGME - Backup diario criptografado'
$existingTask = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue

if (-not $existingTask) {
    Write-Host "A tarefa '$taskName' nao esta registrada. Nenhuma alteracao foi feita."
    exit 0
}

if ($Force) {
    $ConfirmPreference = 'None'
}

if ($PSCmdlet.ShouldProcess($taskName, 'Remover somente a tarefa agendada de backup')) {
    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false
    Write-Host "Tarefa removida: $taskName" -ForegroundColor Green
    Write-Host 'Backups existentes, chave criptografica, banco e uploads foram preservados.'
}
