@echo off
title RabegNet Billing

cd /d C:\laragon\www\e-billing

start "" http://127.0.0.1:8000

echo ========================================
echo  RabegNet Billing sedang dijalankan
echo ========================================
echo.
echo  URL: http://127.0.0.1:8000
echo.
echo  Jangan tutup jendela ini selama aplikasi digunakan.
echo  Tekan CTRL + C untuk menghentikan server.
echo.

"C:\laragon\bin\php\php-8.2.31-Win32-vs16-x64\php.exe" artisan serve --host=127.0.0.1 --port=8000

pause
