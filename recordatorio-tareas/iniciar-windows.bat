@echo off
chcp 65001 >nul
title Recordatorio de Tareas

echo ============================================================
echo    Recordatorio de Tareas  -  inicio con Docker (Windows)
echo ============================================================
echo.

REM Comprobar que Docker esta disponible.
docker version >nul 2>&1
if errorlevel 1 (
    echo [ERROR] No se encuentra Docker en ejecucion.
    echo.
    echo Instala "Docker Desktop" desde https://www.docker.com/products/docker-desktop/
    echo y asegurate de que esta ABIERTO ^(icono de la ballena en la barra de tareas^).
    echo.
    pause
    exit /b 1
)

echo Construyendo y arrancando los contenedores...
echo La primera vez puede tardar varios minutos ^(descarga imagenes^).
echo.
echo   Aplicacion : http://localhost:8000
echo   phpMyAdmin : http://localhost:8080   ^(usuario root / clave root^)
echo.
echo Cuando veas el mensaje "Arrancando en http://0.0.0.0:8000",
echo abre tu navegador en  http://localhost:8000
echo.
echo Para DETENER: cierra esta ventana o pulsa Ctrl+C, y ejecuta detener-windows.bat
echo ------------------------------------------------------------
echo.

docker compose up --build

pause
