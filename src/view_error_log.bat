@echo off
echo ==========================================
echo      VIEW PHP ERROR LOG
echo ==========================================
echo.
echo File: C:\xampp\apache\logs\error.log
echo.
echo ==========================================
echo.

if exist "C:\xampp\apache\logs\error.log" (
    echo Last 50 lines:
    echo.
    powershell -command "Get-Content 'C:\xampp\apache\logs\error.log' -Tail 50"
) else (
    echo ERROR LOG FILE NOT FOUND!
    echo File may not exist or no errors logged yet.
)

echo.
echo ==========================================
echo Press any key to exit...
pause >nul
