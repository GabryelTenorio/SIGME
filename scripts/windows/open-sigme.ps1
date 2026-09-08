[CmdletBinding()]
param()

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

try {
    $response = Invoke-WebRequest -Uri 'http://localhost:8080/up' -UseBasicParsing -TimeoutSec 5
    if ($response.StatusCode -ne 200) {
        throw "Status HTTP inesperado: $($response.StatusCode)"
    }
} catch {
    throw 'O SIGME ainda não está saudável. Execute start-sigme.ps1 antes de abrir o navegador.'
}

Start-Process 'http://localhost:8080'
