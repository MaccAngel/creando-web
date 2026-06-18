@echo off
setlocal enabledelayedexpansion
chcp 65001 >nul
title Recordatorio de Tareas (servidor)

REM Situarse en la raiz del proyecto (carpeta padre de windows\).
cd /d "%~dp0.."

REM Localizar PHP (igual que el instalador).
set "PHP="
where php >nul 2>&1 && set "PHP=php"
if not defined PHP if exist "C:\xampp\php\php.exe" set "PHP=C:\xampp\php\php.exe"
if not defined PHP for /d %%D in ("C:\laragon\bin\php\php*") do set "PHP=%%D\php.exe"
if not defined PHP (
    echo [ERROR] No se encontro php.exe. Ejecuta antes windows\instalar.bat
    pause & exit /b 1
)

if not exist "config.php" (
    echo [ERROR] No existe config.php. Ejecuta primero  windows\instalar.bat
    pause & exit /b 1
)

echo ============================================================
echo    Recordatorio de Tareas  -  servidor local
echo ============================================================
echo.
echo Aplicacion en:  http://localhost:8000
echo.
echo Recuerda: MySQL/MariaDB debe estar ARRANCADO en XAMPP.
echo Para DETENER el servidor: cierra esta ventana.
echo ------------------------------------------------------------
echo.

REM Abrir el navegador tras un breve margen y arrancar el servidor.
start "" cmd /c "timeout /t 2 >nul & start http://localhost:8000"
"%PHP%" -S localhost:8000 -t public

pause
