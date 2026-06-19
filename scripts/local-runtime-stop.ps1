$ErrorActionPreference = "Stop"

$Root = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$RunDir = Join-Path $Root "storage\app\local-runtime"

if (-not (Test-Path $RunDir)) {
    Write-Host "No local runtime processes are registered."
    exit 0
}

Get-ChildItem $RunDir -Filter "*.pid" | ForEach-Object {
    $serviceName = $_.BaseName
    $pidValue = [int] (Get-Content $_.FullName -Raw)
    $process = Get-Process -Id $pidValue -ErrorAction SilentlyContinue

    if ($process) {
        Stop-Process -Id $pidValue -Force
        Write-Host "Stopped $serviceName with PID $pidValue"
    } else {
        Write-Host "$serviceName was not running"
    }

    Remove-Item $_.FullName -Force
}
