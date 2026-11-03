@echo off
setlocal
cd /d "%~dp0.."

echo.
echo WORKTRACK database setup
echo ------------------------
echo Make sure XAMPP MySQL is running before continuing.
echo.

"C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS worktrack CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
"C:\xampp\php\php.exe" artisan config:clear
"C:\xampp\php\php.exe" artisan migrate --force

echo.
echo Database setup complete.
pause
