@echo off
REM Orario Scuola - aggiornamento su Windows: scarica da GitHub lo ZIP dell'ultima versione, allinea la cartella del
REM programma (i file che non esistono piu vengono cancellati) e ricostruisce l'applicazione. Non serve Git.
REM I dati (nel database Docker) non si perdono. Restano intatti: i file .env, i due script di aggiornamento,
REM .git e, per chi sviluppa, web\vendor, web\node_modules, web\storage e web\solver\.venv.
cd /d "%~dp0"
echo ORARIO SCUOLA - aggiornamento
echo.
REM La cartella viene allineata con cancellazione: ci si ferma se non e quella del programma.
if not exist VERSION goto sbagliata
if not exist compose.yaml goto sbagliata
if not exist web\ goto sbagliata
docker info >nul 2>nul || (echo Docker non e acceso: avvia Docker Desktop e riprova. & goto fine)
set T=%TEMP%\orario-agg
rmdir /s /q "%T%" 2>nul
echo Scarico l'ultima versione...
powershell -NoProfile -Command "$ProgressPreference='SilentlyContinue'; Invoke-WebRequest 'https://github.com/johnnybaida/orario-scuola/archive/refs/heads/main.zip' -OutFile \"$env:TEMP\orario-agg.zip\"; Expand-Archive \"$env:TEMP\orario-agg.zip\" \"$env:TEMP\orario-agg\" -Force" || (echo Download non riuscito: controlla la connessione. & goto fine)
REM Prima si scarica e si scompatta tutto, solo poi si allinea la cartella (/MIR toglie i file non piu nel progetto).
for /d %%D in ("%T%\orario-scuola-*") do robocopy "%%D" . /MIR /XD "%CD%\.git" "%CD%\web\vendor" "%CD%\web\node_modules" "%CD%\web\storage" "%CD%\web\solver\.venv" /XF .env Aggiorna-Orario-Scuola-Windows.bat Aggiorna-Orario-Scuola-Mac.command /NFL /NDL /NJH /NJS /NP >nul
if errorlevel 8 (echo Copia dei file non riuscita. & goto fine)
rmdir /s /q "%T%" 2>nul
docker compose up -d --build || (echo Ricostruzione non riuscita. & goto fine)
echo.
set /p V=<VERSION
echo Aggiornato alla versione %V%.
:fine
echo.
pause
exit /b

:sbagliata
echo Questa non sembra la cartella di Orario Scuola: non cambio nulla.
goto fine
