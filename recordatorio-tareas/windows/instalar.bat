@echo off
setlocal enabledelayedexpansion
chcp 65001 >nul
title Instalador nativo - Recordatorio de Tareas

REM Situarse en la raiz del proyecto (carpeta padre de windows\).
cd /d "%~dp0.."

echo ============================================================
echo    Instalador NATIVO (sin Docker) - Recordatorio de Tareas
echo ============================================================
echo.
echo Requisitos: XAMPP o Laragon instalado, y MySQL/MariaDB ARRANCADO.
echo (En XAMPP: abre el "Control Panel" y pulsa Start en MySQL.)
echo.
pause

REM ---------------------------------------------------------------
REM 1. Localizar PHP
REM ---------------------------------------------------------------
set "PHP="
where php >nul 2>&1 && set "PHP=php"
if not defined PHP if exist "C:\xampp\php\php.exe" set "PHP=C:\xampp\php\php.exe"
if not defined PHP for /d %%D in ("C:\laragon\bin\php\php*") do set "PHP=%%D\php.exe"
if not defined PHP (
    echo [ERROR] No se encontro php.exe.
    echo Instala XAMPP ^(https://www.apachefriends.org^) o Laragon, o anade PHP al PATH.
    pause & exit /b 1
)
echo PHP detectado:   %PHP%

REM ---------------------------------------------------------------
REM 2. Localizar el cliente MySQL
REM ---------------------------------------------------------------
set "MYSQL="
where mysql >nul 2>&1 && set "MYSQL=mysql"
if not defined MYSQL if exist "C:\xampp\mysql\bin\mysql.exe" set "MYSQL=C:\xampp\mysql\bin\mysql.exe"
if not defined MYSQL for /d %%D in ("C:\laragon\bin\mysql\mysql*") do set "MYSQL=%%D\bin\mysql.exe"
if not defined MYSQL (
    echo [ERROR] No se encontro mysql.exe ^(cliente de MySQL/MariaDB^).
    echo Asegurate de tener XAMPP/Laragon instalado.
    pause & exit /b 1
)
echo MySQL detectado: %MYSQL%
echo.

REM ---------------------------------------------------------------
REM 3. Comprobar extensiones de PHP necesarias
REM ---------------------------------------------------------------
"%PHP%" -r "exit((extension_loaded('sodium')&&extension_loaded('pdo_mysql')&&extension_loaded('curl'))?0:1);"
if errorlevel 1 (
    echo [ERROR] Faltan extensiones de PHP. Abre tu php.ini y quita el punto y coma
    echo         de estas lineas, guarda y vuelve a ejecutar:
    echo            extension=sodium
    echo            extension=pdo_mysql
    echo            extension=curl
    echo.
    echo Para saber que php.ini usas:  "%PHP%" --ini
    pause & exit /b 1
)
echo Extensiones de PHP: OK ^(sodium, pdo_mysql, curl^)
echo.

REM ---------------------------------------------------------------
REM 4. Pedir credenciales de MySQL
REM ---------------------------------------------------------------
set "DBUSER=root"
set /p "DBUSER=Usuario de MySQL [root]: "
if "%DBUSER%"=="" set "DBUSER=root"
set "DBPASS="
set /p "DBPASS=Password de MySQL (en XAMPP suele estar vacia, pulsa Enter): "

set "PWDARG="
if not "%DBPASS%"=="" set "PWDARG=-p%DBPASS%"

REM ---------------------------------------------------------------
REM 5. Generar/actualizar config.php
REM ---------------------------------------------------------------
echo.
echo Preparando config.php ...
set "RT_APPKEY="
set "RT_DBUSER=%DBUSER%"
set "RT_DBPASS=%DBPASS%"
"%PHP%" windows\_configurar.php
if errorlevel 1 ( echo [ERROR] No se pudo preparar config.php & pause & exit /b 1 )

REM ---------------------------------------------------------------
REM 6. Dependencias (vendor). El ZIP ya lo incluye; si falta, composer.
REM ---------------------------------------------------------------
if not exist "vendor\autoload.php" (
    echo.
    echo No se encontro vendor\. Intentando 'composer install' ...
    where composer >nul 2>&1
    if errorlevel 1 (
        echo [AVISO] No hay 'composer' y falta la carpeta vendor\.
        echo         Usa el ZIP del proyecto ^(ya incluye vendor^) o instala Composer.
        pause & exit /b 1
    )
    call composer install --no-dev --no-interaction
)

REM ---------------------------------------------------------------
REM 7. Crear la base de datos y cargar esquema + datos de ejemplo
REM ---------------------------------------------------------------
echo.
echo Creando la base de datos y cargando el esquema ...
"%MYSQL%" -u %DBUSER% %PWDARG% < sql\esquema.sql
if errorlevel 1 (
    echo [ERROR] No se pudo conectar a MySQL o crear la base de datos.
    echo         Comprueba que MySQL esta ARRANCADO en XAMPP y que el usuario/clave son correctos.
    pause & exit /b 1
)

echo Cargando datos de ejemplo ...
"%MYSQL%" -u %DBUSER% %PWDARG% recordatorio_tareas < sql\datos_ejemplo.sql

echo.
echo ============================================================
echo    Instalacion COMPLETADA correctamente.
echo ============================================================
echo.
echo Para arrancar la aplicacion: doble clic en  windows\iniciar.bat
echo Se abrira en  http://localhost:8000
echo.
set /p "ARRANCAR=Quieres arrancarla ahora? [S/n]: "
if /i "%ARRANCAR%"=="n" goto :fin
call "%~dp0iniciar.bat"

:fin
pause
