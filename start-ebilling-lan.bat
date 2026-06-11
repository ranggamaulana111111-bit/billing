@echo off
title RabegNet Billing LAN

cd /d C:\laragon\www\e-billing

echo ========================================
echo  RabegNet Billing Mode LAN
echo ========================================
echo.
echo  Aplikasi akan berjalan di port 8000.
echo.
echo  Untuk buka dari HP:
echo  1. Pastikan HP dan PC satu WiFi.
echo  2. Cari IP PC di daftar IPv4 Address di bawah.
echo  3. Buka di HP: http://IP-PC:8000
echo.
echo  Contoh: http://192.168.1.25:8000
echo.
echo ========================================
echo  IP komputer ini:
echo ========================================
ipconfig | findstr /R /C:"IPv4"
echo ========================================
echo.
echo  Jangan tutup jendela ini selama aplikasi digunakan.
echo  Tekan CTRL + C untuk menghentikan server.
echo.

"C:\laragon\bin\php\php-8.2.31-Win32-vs16-x64\php.exe" artisan serve --host=0.0.0.0 --port=8000

pause
