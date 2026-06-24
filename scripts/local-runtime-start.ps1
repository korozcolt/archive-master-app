param(
    [string] $PhpPath = "$env:USERPROFILE\.config\herd\bin\php84\php.exe",
    [string] $QueueNames = "document-processing,notifications,default,ai-processing",
    [int] $ReverbPort = 0
)

$ErrorActionPreference = "Stop"

$Root = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$RunDir = Join-Path $Root "storage\app\local-runtime"
$LogDir = Join-Path $Root "storage\logs"

New-Item -ItemType Directory -Force -Path $RunDir, $LogDir | Out-Null

if (-not (Test-Path $PhpPath)) {
    throw "PHP was not found at $PhpPath"
}

function Get-DotEnvValue {
    param([string] $Name)

    $envFile = Join-Path $Root ".env"

    if (-not (Test-Path $envFile)) {
        return $null
    }

    $line = Get-Content $envFile | Where-Object { $_ -match "^$Name=" } | Select-Object -First 1

    if (-not $line) {
        return $null
    }

    return ($line -replace "^$Name=", "").Trim('"')
}

if ($ReverbPort -eq 0) {
    $envPort = Get-DotEnvValue "REVERB_SERVER_PORT"
    $ReverbPort = if ($envPort) { [int] $envPort } else { 8080 }
}

$services = @(
    @{
        Name = "queue"
        Arguments = "artisan queue:work --sleep=1 --tries=3 --timeout=120 --queue=$QueueNames"
    },
    @{
        Name = "scheduler"
        Arguments = "artisan schedule:work"
    },
    @{
        Name = "reverb"
        Arguments = "artisan reverb:start --host=0.0.0.0 --port=$ReverbPort"
    }
)

foreach ($service in $services) {
    $pidFile = Join-Path $RunDir "$($service.Name).pid"

    if (Test-Path $pidFile) {
        $existingPid = [int] (Get-Content $pidFile -Raw)
        $existingProcess = Get-Process -Id $existingPid -ErrorAction SilentlyContinue

        if ($existingProcess) {
            Write-Host "$($service.Name) is already running with PID $existingPid"
            continue
        }

        Remove-Item $pidFile -Force
    }

    $stdout = Join-Path $LogDir "local-$($service.Name).log"
    $stderr = Join-Path $LogDir "local-$($service.Name).error.log"

    $process = Start-Process `
        -FilePath $PhpPath `
        -ArgumentList $service.Arguments `
        -WorkingDirectory $Root `
        -WindowStyle Hidden `
        -RedirectStandardOutput $stdout `
        -RedirectStandardError $stderr `
        -PassThru

    Set-Content -Path $pidFile -Value $process.Id
    Write-Host "Started $($service.Name) with PID $($process.Id)"
}
