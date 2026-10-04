@echo off
rem Orario Scuola - avvio su Windows: fai doppio clic su questo file.
rem Controlla che Docker Desktop sia installato e acceso, avvia l'applicazione e apre il browser.
setlocal EnableExtensions
chcp 65001 >nul
title Orario Scuola
cd /d "%~dp0"

set "PORTA=8080"
if exist ".env" for /f "usebackq tokens=1,* delims==" %%A in (".env") do if /i "%%A"=="APP_PORT" set "PORTA=%%B"
set "INDIRIZZO=http://localhost:%PORTA%"

echo ==============================================
echo    ORARIO SCUOLA - avvio
echo ==============================================
echo.

rem 1. Docker installato?
where docker >nul 2>&1
if errorlevel 1 goto senza_docker

rem 2. Docker acceso? Se no, lo avvio e aspetto (fino a 5 minuti).
docker info >nul 2>&1
if not errorlevel 1 goto docker_pronto
echo Docker non e' acceso: lo avvio, attendi qualche istante...
if exist "%ProgramFiles%\Docker\Docker\Docker Desktop.exe" start "" "%ProgramFiles%\Docker\Docker\Docker Desktop.exe"
set /a TENTATIVI=0
:attesa_docker
docker info >nul 2>&1
if not errorlevel 1 goto docker_pronto
set /a TENTATIVI+=1
if %TENTATIVI% GEQ 60 goto docker_non_parte
timeout /t 5 /nobreak >nul
goto attesa_docker

:docker_pronto
rem 3. Avvio dell'applicazione
echo Avvio dell'applicazione. La PRIMA volta puo' richiedere alcuni minuti: non chiudere la finestra.
echo.
docker compose up -d --build
if errorlevel 1 goto errore_avvio

rem 4. Attesa che risponda (fino a 3 minuti: migrazioni e dati di esempio al primo avvio)
echo.
echo Attendo che l'applicazione sia pronta...
set /a TENTATIVI=0
:attesa_app
powershell -NoProfile -Command "try { Invoke-WebRequest -UseBasicParsing -Uri '%INDIRIZZO%/login' -TimeoutSec 5 | Out-Null; exit 0 } catch { exit 1 }" >nul 2>&1
if not errorlevel 1 goto pronto
set /a TENTATIVI+=1
if %TENTATIVI% GEQ 90 goto app_lenta
timeout /t 2 /nobreak >nul
goto attesa_app

:pronto
start "" "%INDIRIZZO%"
echo.
echo ==============================================
echo    PRONTO!
echo ==============================================
echo.
echo L'applicazione e' aperta nel browser all'indirizzo:
echo     %INDIRIZZO%
echo.
echo Primo accesso:
echo     email:     amministratore@scuola.test
echo     password:  password
echo   Dopo l'accesso cambia la password dalla voce Utenze.
echo.
echo Dagli altri computer della scuola: http://INDIRIZZO-DI-QUESTO-COMPUTER:%PORTA%
echo.
echo Puoi chiudere questa finestra: l'applicazione resta attiva.
echo Per spegnerla usa il file "Ferma-Orario-Scuola.bat".
echo.
pause
exit /b 0

:senza_docker
echo Docker Desktop non e' installato su questo computer.
echo.
echo Che cosa fare:
echo   1. Si apre ora la pagina di download: scarica e installa Docker Desktop.
echo   2. Riavvia il computer se richiesto, poi apri Docker Desktop e attendi che sia pronto.
echo   3. Fai di nuovo doppio clic su questo file.
start "" "https://www.docker.com/products/docker-desktop/"
echo.
pause
exit /b 1

:docker_non_parte
echo.
echo Docker non si e' avviato. Apri Docker Desktop a mano, attendi che sia pronto
echo (l'icona della balena smette di muoversi) e rilancia questo file.
echo.
pause
exit /b 1

:errore_avvio
echo.
echo Qualcosa non ha funzionato nell'avvio. Se serve assistenza, fai una foto o copia
echo il testo qui sopra e mandalo a chi gestisce l'installazione.
echo.
pause
exit /b 1

:app_lenta
echo.
echo L'applicazione ci sta mettendo piu' del previsto. Prova ad aprire %INDIRIZZO% tra un minuto.
echo Se non si apre, rilancia questo file.
echo.
pause
exit /b 1
