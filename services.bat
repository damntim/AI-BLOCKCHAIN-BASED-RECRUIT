@echo off
setlocal enabledelayedexpansion
title RecruitChain Service Manager

:: --- Config -----------------------------------------------------------------
set "ROOT=%~dp0"
set "PHP_PORT=8800"
set "AI_PORT=8001"
set "ANTICHEAT_PORT=8002"
set "ICP_GATEWAY_PORT=3001"
set "LOG_DIR=%ROOT%storage\logs"
set "PID_DIR=%ROOT%storage\pids"

set "PID_PHP=%PID_DIR%\php.pid"
set "PID_AI=%PID_DIR%\ai.pid"
set "PID_AC=%PID_DIR%\ac.pid"
set "PID_GW=%PID_DIR%\gateway.pid"
set "PID_WK=%PID_DIR%\worker.pid"

:: Ensure directories exist
if not exist "%LOG_DIR%" mkdir "%LOG_DIR%"
if not exist "%PID_DIR%" mkdir "%PID_DIR%"

:: --- Entry point ------------------------------------------------------------
if "%~1"=="" goto :menu

if /i "%~1"=="start"       call :launch_all & call :print_urls & call :tail_logs & goto :eof
if /i "%~1"=="-background" call :launch_all & call :print_urls & goto :eof
if /i "%~1"=="stop"        call :stop_all & goto :eof
if /i "%~1"=="restart"     call :stop_all & timeout /t 2 /nobreak >nul & call :launch_all & call :print_urls & call :tail_logs & goto :eof
if /i "%~1"=="status"      call :status & goto :eof

echo Unknown command: %~1
goto :usage

:: --- Interactive menu -------------------------------------------------------
:menu
cls
echo.
echo  +----------------------------------------------+
echo  ^|       RecruitChain  --  Service Manager       ^|
echo  +----------------------------------------------+
echo.
echo   [1] Start all services  (+ live logs)
echo   [2] Stop  all services
echo   [3] Restart all services  (+ live logs)
echo   [4] Show service status
echo   [Q] Quit
echo.
set /p "choice=  Choose: "

if "%choice%"=="1" call :launch_all & call :print_urls & call :tail_logs & goto :menu
if "%choice%"=="2" call :stop_all & pause & goto :menu
if "%choice%"=="3" call :stop_all & timeout /t 2 /nobreak >nul & call :launch_all & call :print_urls & call :tail_logs & goto :menu
if "%choice%"=="4" call :status & pause & goto :menu
if /i "%choice%"=="q" exit /b 0
goto :menu

:: --- Usage ------------------------------------------------------------------
:usage
echo.
echo  Usage: services.bat [start ^| stop ^| restart ^| status ^| -background]
echo.
goto :eof

:: ===========================================================================
:: Subroutines
:: ===========================================================================

:launch_all
echo.
echo  Starting RecruitChain services...
echo.
powershell -NoProfile -ExecutionPolicy Bypass -File "%ROOT%svc-launch.ps1" -Root "%ROOT:~0,-1%" -LogDir "%LOG_DIR%" -PidDir "%PID_DIR%"
echo.
goto :eof

:print_urls
echo  All services started.
echo.
echo    Web App   : http://localhost:%PHP_PORT%
echo    AI Svc    : http://localhost:%AI_PORT%
echo    Anti-Cheat: ws://localhost:%ANTICHEAT_PORT%
echo    ICP GW    : http://localhost:%ICP_GATEWAY_PORT%
echo.
goto :eof

:stop_all
echo.
echo  Stopping RecruitChain services...
echo.
call :kill_pid "%PID_PHP%"  "PHP web server"
call :kill_pid "%PID_AI%"   "AI service"
call :kill_pid "%PID_AC%"   "Anti-cheat service"
call :kill_pid "%PID_GW%"   "ICP gateway"
call :kill_pid "%PID_WK%"   "Queue worker"
echo.
echo  All services stopped.
echo.
goto :eof

:status
echo.
echo  Service Status:
echo  -----------------------------------------------
call :check_pid "%PID_PHP%"  "PHP web server     (:%PHP_PORT%)"
call :check_pid "%PID_AI%"   "AI service         (:%AI_PORT%)"
call :check_pid "%PID_AC%"   "Anti-cheat service (:%ANTICHEAT_PORT%)"
call :check_pid "%PID_GW%"   "ICP gateway        (:%ICP_GATEWAY_PORT%)"
call :check_pid "%PID_WK%"   "Queue worker"
echo  -----------------------------------------------
echo.
goto :eof

:tail_logs
echo  Showing live logs -- press Ctrl+C to return to menu
echo.
powershell -NoProfile -ExecutionPolicy Bypass -File "%ROOT%svc-tail.ps1" -LogDir "%LOG_DIR%"
goto :eof

:: ---------------------------------------------------------------------------
:kill_pid
set "_pidfile=%~1"
set "_name=%~2"
if not exist "%_pidfile%" (
    echo   [-] %_name%: not running (no PID file)
    goto :eof
)
set /p _pid=<"%_pidfile%"
if "%_pid%"=="" goto :eof
taskkill /PID %_pid% /F >nul 2>&1
if errorlevel 1 (
    echo   [-] %_name%: already stopped
) else (
    echo   [x] %_name% stopped (PID %_pid%)
)
del "%_pidfile%" >nul 2>&1
goto :eof

:check_pid
set "_pidfile=%~1"
set "_name=%~2"
if not exist "%_pidfile%" (
    echo   [ ] %_name%: STOPPED
    goto :eof
)
set /p _pid=<"%_pidfile%"
tasklist /FI "PID eq %_pid%" 2>nul | find "%_pid%" >nul
if errorlevel 1 (
    echo   [ ] %_name%: STOPPED ^(stale PID %_pid%^)
    del "%_pidfile%" >nul 2>&1
) else (
    echo   [*] %_name%: RUNNING ^(PID %_pid%^)
)
goto :eof
