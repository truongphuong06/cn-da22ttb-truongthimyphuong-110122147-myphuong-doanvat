@echo off
cls
echo ==========================================
echo   MONITORING PHP ERROR LOG (Live)
echo ==========================================
echo.
echo Watching: C:\xampp\apache\logs\error.log
echo Press Ctrl+C to stop
echo.
echo Waiting for new entries...
echo ==========================================
echo.

powershell -command "Get-Content 'C:\xampp\apache\logs\error.log' -Wait -Tail 30"
