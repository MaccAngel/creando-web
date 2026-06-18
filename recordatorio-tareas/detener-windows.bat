@echo off
chcp 65001 >nul
title Recordatorio de Tareas - detener

echo Deteniendo los contenedores de Recordatorio de Tareas...
echo.
echo  - Para detener conservando los datos:   docker compose down
echo  - Para detener Y BORRAR los datos:       docker compose down -v
echo.

docker compose down

echo.
echo Contenedores detenidos. Los datos de la base de datos se conservan.
echo (Si quieres borrarlos por completo, ejecuta: docker compose down -v)
pause
