param(
    [string]$Root,
    [string]$LogDir,
    [string]$PidDir
)

$Root = $Root.TrimEnd('\')

$services = @(
    @{
        Label   = "PHP web server    (port 8800)"
        Exe     = "php"
        Args    = "-S 0.0.0.0:8800 -t `"$Root`""
        WorkDir = $Root
        PidFile = "$PidDir\php.pid"
        LogFile = "$LogDir\php.log"
    }
    @{
        Label   = "AI service        (port 8001)"
        Exe     = "python"
        Args    = "-m uvicorn main:app --host 0.0.0.0 --port 8001"
        WorkDir = "$Root\ai-service"
        PidFile = "$PidDir\ai.pid"
        LogFile = "$LogDir\ai.log"
    }
    @{
        Label   = "Anti-cheat WS     (port 8002)"
        Exe     = "python"
        Args    = "-m uvicorn main:app --host 0.0.0.0 --port 8002"
        WorkDir = "$Root\anti-cheat-service"
        PidFile = "$PidDir\ac.pid"
        LogFile = "$LogDir\anticheat.log"
    }
    @{
        Label   = "ICP gateway       (port 3001)"
        Exe     = "node"
        Args    = "index.js"
        WorkDir = "$Root\icp-gateway"
        PidFile = "$PidDir\gateway.pid"
        LogFile = "$LogDir\gateway.log"
    }
    @{
        Label   = "Queue worker"
        Exe     = "php"
        Args    = "`"$Root\queue\worker.php`""
        WorkDir = $Root
        PidFile = "$PidDir\worker.pid"
        LogFile = "$LogDir\worker.log"
    }
)

foreach ($svc in $services) {
    $psi = New-Object System.Diagnostics.ProcessStartInfo
    $psi.FileName               = $svc.Exe
    $psi.Arguments              = $svc.Args
    $psi.WorkingDirectory       = $svc.WorkDir
    $psi.RedirectStandardOutput = $true
    $psi.RedirectStandardError  = $true
    $psi.UseShellExecute        = $false
    $psi.CreateNoWindow         = $true

    $proc = New-Object System.Diagnostics.Process
    $proc.StartInfo = $psi

    # Async drain so the output buffer never blocks the child
    $proc.EnableRaisingEvents = $false
    $stdoutLog = $svc.LogFile
    $stderrLog = $svc.LogFile + ".err"

    $proc.Start() | Out-Null
    $proc.Id | Set-Content $svc.PidFile

    # Hand off stdout/stderr to background jobs so the pipe doesn't block
    $null = Start-Job -ScriptBlock {
        param($stream, $path)
        while (-not $stream.EndOfStream) {
            $stream.ReadLine() | Add-Content $path
        }
    } -ArgumentList $proc.StandardOutput, $stdoutLog

    $null = Start-Job -ScriptBlock {
        param($stream, $path)
        while (-not $stream.EndOfStream) {
            $stream.ReadLine() | Add-Content $path
        }
    } -ArgumentList $proc.StandardError, $stderrLog

    Write-Host "  [+] $($svc.Label) (PID $($proc.Id))" -ForegroundColor Green
}
