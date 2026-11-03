@echo off
setlocal
cd /d "%~dp0.."

echo.
echo WORKTRACK local server
echo ----------------------
echo Make sure XAMPP MySQL is running.
echo URL: http://127.0.0.1:8000
echo.

"C:\xampp\php\php.exe" artisan config:clear
"C:\xampp\php\php.exe" artisan serve --host=127.0.0.1 --port=8000

echo.
echo Server stopped.
pause
