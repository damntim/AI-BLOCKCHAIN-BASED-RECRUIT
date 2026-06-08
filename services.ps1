#Requires -Version 5.1
<#
.SYNOPSIS
    RecruitChain Service Manager
.DESCRIPTION
    Start, stop, restart and check status of all RecruitChain services.
    ICP canister must be managed separately (requires WSL/Linux + dfx).
.EXAMPLE
    .\services.ps1              # interactive menu
    .\services.ps1 start
    .\services.ps1 stop
    .\services.ps1 restart
    .\services.ps1 status
    .\services.ps1 logs php     # tail log for: php | ai | anticheat | gateway | worker
#>
param([string]$Action = "", [string]$Target = "")

$Root        = $PSScriptRoot
$LogDir      = "$Root\storage\logs"
$PidDir      = "$Root\storage\pids"

# Ensure dirs exist
foreach ($d in @($LogDir, $PidDir)) {
    if (-not (Test-Path $d)) { New-Item -ItemType Directory -Force $d | Out-Null }
}

# ── Service definitions ───────────────────────────────────────────────────────
$Services = [ordered]@{
    php       = @{
        Label   = "PHP web server"
        Port    = 8800
        Command = "php"
        Args    = @("-S", "0.0.0.0:8800", "-t", $Root)
        WorkDir = $Root
        Log     = "$LogDir\php.log"
        Pid     = "$PidDir\php.pid"
    }
    ai        = @{
        Label   = "AI service (FastAPI)"
        Port    = 8001
        Command = "python"
        Args    = @("-m", "uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8001")
        WorkDir = "$Root\ai-service"
        Log     = "$LogDir\ai.log"
        Pid     = "$PidDir\ai.pid"
    }
    anticheat = @{
        Label   = "Anti-cheat WS service"
        Port    = 8002
        Command = "python"
        Args    = @("-m", "uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8002")
        WorkDir = "$Root\anti-cheat-service"
        Log     = "$LogDir\anticheat.log"
        Pid     = "$PidDir\anticheat.pid"
    }
    gateway   = @{
        Label   = "ICP gateway (Node.js)"
        Port    = 3001
        Command = "node"
        Args    = @("index.js")
        WorkDir = "$Root\icp-gateway"
        Log     = "$LogDir\gateway.log"
        Pid     = "$PidDir\gateway.pid"
    }
    worker    = @{
        Label   = "Queue worker (PHP)"
        Port    = $null
        Command = "php"
        Args    = @("$Root\queue\worker.php")
        WorkDir = $Root
        Log     = "$LogDir\worker.log"
        Pid     = "$PidDir\worker.pid"
    }
}

# ── Helpers ───────────────────────────────────────────────────────────────────
function Get-ServicePid($svc) {
    if (Test-Path $svc.Pid) { return [int](Get-Content $svc.Pid -Raw).Trim() }
    return $null
}

function Test-ServiceRunning($svc) {
    $procId = Get-ServicePid $svc
    if ($null -eq $procId) { return $false }
    return [bool](Get-Process -Id $procId -ErrorAction SilentlyContinue)
}

function Start-Svc($name) {
    $svc = $Services[$name]
    if (Test-ServiceRunning $svc) {
        Write-Host "  [~] $($svc.Label): already running" -ForegroundColor Yellow
        return
    }
    $proc = Start-Process -FilePath $svc.Command `
        -ArgumentList $svc.Args `
        -WorkingDirectory $svc.WorkDir `
        -RedirectStandardOutput $svc.Log `
        -RedirectStandardError  "$($svc.Log).err" `
        -WindowStyle Hidden -PassThru
    $proc.Id | Set-Content $svc.Pid
    Write-Host "  [+] $($svc.Label) started (PID $($proc.Id))" -ForegroundColor Green
}

function Stop-Svc($name) {
    $svc = $Services[$name]
    $procId = Get-ServicePid $svc
    if ($null -eq $procId) {
        Write-Host "  [-] $($svc.Label): not running" -ForegroundColor DarkGray
        return
    }
    $proc = Get-Process -Id $procId -ErrorAction SilentlyContinue
    if ($proc) {
        Stop-Process -Id $procId -Force -ErrorAction SilentlyContinue
        Write-Host "  [x] $($svc.Label) stopped (PID $procId)" -ForegroundColor Red
    } else {
        Write-Host "  [-] $($svc.Label): stale PID $procId (already gone)" -ForegroundColor DarkGray
    }
    Remove-Item $svc.Pid -Force -ErrorAction SilentlyContinue
}

function Show-Status {
    Write-Host ""
    Write-Host "  Service Status" -ForegroundColor Cyan
    Write-Host "  ─────────────────────────────────────────────────"
    foreach ($name in $Services.Keys) {
        $svc = $Services[$name]
        if (Test-ServiceRunning $svc) {
            $procId = Get-ServicePid $svc
            $port   = if ($svc.Port) { " [:$($svc.Port)]" } else { "" }
            Write-Host "  [*] $($svc.Label)$port  PID $procId" -ForegroundColor Green
        } else {
            Write-Host "  [ ] $($svc.Label)" -ForegroundColor DarkGray
        }
    }
    Write-Host "  ─────────────────────────────────────────────────"
    Write-Host ""
    Write-Host "  App URL: http://localhost:8800" -ForegroundColor Cyan
}

function Show-Logs($target) {
    $map = @{
        php       = "$LogDir\php.log"
        ai        = "$LogDir\ai.log"
        anticheat = "$LogDir\anticheat.log"
        gateway   = "$LogDir\gateway.log"
        worker    = "$LogDir\worker.log"
    }
    if (-not $map.ContainsKey($target)) {
        Write-Host "  Unknown service. Choose: php, ai, anticheat, gateway, worker" -ForegroundColor Red
        return
    }
    $logFile = $map[$target]
    if (-not (Test-Path $logFile)) {
        Write-Host "  No log yet: $logFile" -ForegroundColor Yellow
        return
    }
    Write-Host "  Tailing $logFile  (Ctrl+C to stop)" -ForegroundColor Cyan
    Get-Content $logFile -Wait -Tail 40
}

function Show-Menu {
    do {
        Clear-Host
        Write-Host ""
        Write-Host "  ╔══════════════════════════════════════════════╗" -ForegroundColor Cyan
        Write-Host "  ║       RecruitChain  —  Service Manager       ║" -ForegroundColor Cyan
        Write-Host "  ╚══════════════════════════════════════════════╝" -ForegroundColor Cyan
        Write-Host ""
        Write-Host "   [1] Start all services"
        Write-Host "   [2] Stop  all services"
        Write-Host "   [3] Restart all services"
        Write-Host "   [4] Show status"
        Write-Host "   [5] Tail logs  (php/ai/anticheat/gateway/worker)"
        Write-Host "   [Q] Quit"
        Write-Host ""
        $ch = Read-Host "  Choose"

        switch ($ch) {
            "1" { Start-All;    Read-Host "  Press Enter to continue" }
            "2" { Stop-All;     Read-Host "  Press Enter to continue" }
            "3" { Restart-All;  Read-Host "  Press Enter to continue" }
            "4" { Show-Status;  Read-Host "  Press Enter to continue" }
            "5" {
                $t = Read-Host "  Which service (php/ai/anticheat/gateway/worker)"
                Show-Logs $t
            }
        }
    } while ($ch -notmatch "^[qQ]$")
}

function Start-All {
    Write-Host ""
    Write-Host "  Starting all services..." -ForegroundColor Cyan
    foreach ($name in $Services.Keys) { Start-Svc $name }
    Write-Host ""
    Write-Host "  App  : http://localhost:8800" -ForegroundColor Green
    Write-Host "  AI   : http://localhost:8001/health" -ForegroundColor Green
    Write-Host "  WS   : ws://localhost:8002" -ForegroundColor Green
    Write-Host "  GW   : http://localhost:3001" -ForegroundColor Green
    Write-Host ""
}

function Stop-All {
    Write-Host ""
    Write-Host "  Stopping all services..." -ForegroundColor Cyan
    foreach ($name in $Services.Keys) { Stop-Svc $name }
    Write-Host ""
}

function Restart-All {
    Stop-All
    Start-Sleep -Seconds 2
    Start-All
}

# ── Entry ─────────────────────────────────────────────────────────────────────
switch ($Action.ToLower()) {
    "start"   { Start-All }
    "stop"    { Stop-All }
    "restart" { Restart-All }
    "status"  { Show-Status }
    "logs"    { Show-Logs $Target }
    default   { Show-Menu }
}
