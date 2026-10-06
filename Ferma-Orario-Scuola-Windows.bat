@echo off
rem Orario Scuola - spegnimento su Windows: fai doppio clic su questo file.
rem I dati (orari, docenti, classi, ...) NON vengono cancellati.
setlocal EnableExtensions
chcp 65001 >nul
title Orario Scuola - spegnimento
cd /d "%~dp0"

echo ==============================================
echo    ORARIO SCUOLA - spegnimento
echo ==============================================
echo.

where docker >nul 2>&1
if errorlevel 1 goto gia_spenta
docker info >nul 2>&1
if errorlevel 1 goto gia_spenta

docker compose down
if errorlevel 1 goto errore
echo.
echo Applicazione spenta. I dati sono al sicuro e ci saranno al prossimo avvio.
goto fine

:gia_spenta
echo Docker non e' acceso: l'applicazione risulta gia' spenta.
goto fine

:errore
echo.
echo Non sono riuscito a spegnere l'applicazione. Chiudi Docker Desktop per fermarla.

:fine
echo.
pause
