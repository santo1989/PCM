@echo off
cd /d "E:\Programming\www\www\PCM"


start /B cmd /C "php artisan ser --host=192.168.0.105:1000"
start /B cmd /C "php artisan ser"