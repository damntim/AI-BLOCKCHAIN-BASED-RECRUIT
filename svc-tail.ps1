param([string]$LogDir)

$logs = [ordered]@{
    PHP       = "$LogDir\php.log"
    AI        = "$LogDir\ai.log"
    AntiCheat = "$LogDir\anticheat.log"
    Gateway   = "$LogDir\gateway.log"
    Worker    = "$LogDir\worker.log"
}

$colors = @{
    PHP       = "Cyan"
    AI        = "Green"
    AntiCheat = "Yellow"
    Gateway   = "Magenta"
    Worker    = "White"
}

$pos = @{}
foreach ($k in $logs.Keys) { $pos[$k] = 0 }

while ($true) {
    foreach ($svc in $logs.Keys) {
        $f = $logs[$svc]
        if (Test-Path $f) {
            $lines = Get-Content $f -ErrorAction SilentlyContinue
            if ($lines -and $lines.Count -gt $pos[$svc]) {
                $new = $lines[$pos[$svc]..($lines.Count - 1)]
                foreach ($l in $new) {
                    Write-Host "[$svc] " -ForegroundColor $colors[$svc] -NoNewline
                    Write-Host $l
                }
                $pos[$svc] = $lines.Count
            }
        }
    }
    Start-Sleep -Milliseconds 400
}
