@echo off
setlocal
cd /d "%~dp0.."

echo.
echo WORKTRACK encrypted database backup
echo ------------------------------------
echo Make sure XAMPP MySQL is running.
echo.

"C:\xampp\php\php.exe" artisan config:clear
"C:\xampp\php\php.exe" artisan worktrack:backup

echo.
pause
