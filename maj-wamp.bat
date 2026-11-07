@echo off
title Gestion Credits - Mise a jour WAMP
setlocal

echo.
echo  Reconstruction de l'interface et copie vers WAMP...
echo.

cd /d "%~dp0frontend"
set VITE_API_BASE=/gestion-credits-api/api/v1
call npm.cmd run build -- --base=/gestion-credits/
if errorlevel 1 goto erreur

if not exist "C:\wamp64\www\gestion-credits" mkdir "C:\wamp64\www\gestion-credits"
xcopy "dist\*" "C:\wamp64\www\gestion-credits\" /E /Y /I >nul
if errorlevel 1 goto erreur

echo.
echo  Termine. Ouvrez : http://localhost/gestion-credits/
echo.
start "" http://localhost/gestion-credits/
pause
exit /b 0

:erreur
echo.
echo  ERREUR pendant la mise a jour.
pause
exit /b 1
