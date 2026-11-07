@echo off
title Gestion Credits - Lancement
setlocal

echo.
echo  ============================================
echo   GESTION CREDITS - Suivi des credits clients
echo  ============================================
echo.

powershell -NoProfile -Command "if (Test-NetConnection -ComputerName 127.0.0.1 -Port 8000 -InformationLevel Quiet -WarningAction SilentlyContinue) { exit 0 } else { exit 1 }"
if %errorlevel%==0 (
  echo  API deja en marche ^(port 8000^).
) else (
  echo  Demarrage de l'API ^(port 8000^)...
  start "Gestion Credits - API" cmd /k "cd /d "%~dp0backend" && php artisan serve --host=127.0.0.1 --port=8000"
)

powershell -NoProfile -Command "if (Test-NetConnection -ComputerName 127.0.0.1 -Port 5173 -InformationLevel Quiet -WarningAction SilentlyContinue) { exit 0 } else { exit 1 }"
if %errorlevel%==0 (
  echo  Interface deja en marche ^(port 5173^).
) else (
  echo  Demarrage de l'interface ^(port 5173^)...
  start "Gestion Credits - Interface" cmd /k "cd /d "%~dp0frontend" && npm run dev"
)

echo  Ouverture du navigateur dans quelques secondes...
ping -n 7 127.0.0.1 >nul
start "" http://127.0.0.1:5173

echo.
echo  C'est pret !
echo  - Interface : http://127.0.0.1:5173
echo  - API       : http://127.0.0.1:8000/api/v1
echo  - Login     : admin / admin123
echo.
echo  Gardez les fenetres noires ouvertes : les fermer coupe le site.
ping -n 9 127.0.0.1 >nul
