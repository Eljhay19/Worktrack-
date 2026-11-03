@echo off
setlocal
cd /d "%~dp0.."

echo.
echo Clearing WORKTRACK Laravel cache...
echo.

"C:\xampp\php\php.exe" artisan optimize:clear

echo.
echo Cache cleared.
pause
