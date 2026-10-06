@echo off
REM Orario Scuola - aggiornamento su Windows: scarica da GitHub lo ZIP dell'ultima versione, lo sovrascrive sui file
REM del programma e ricostruisce l'applicazione. Non serve Git. I dati (nel database Docker) non si perdono.
cd /d "%~dp0"
echo ORARIO SCUOLA - aggiornamento
echo.
docker info >nul 2>nul || (echo Docker non e acceso: avvia Docker Desktop e riprova. & goto fine)
set T=%TEMP%\orario-agg
rmdir /s /q "%T%" 2>nul
echo Scarico l'ultima versione...
powershell -NoProfile -Command "$ProgressPreference='SilentlyContinue'; Invoke-WebRequest 'https://github.com/johnnybaida/orario-scuola/archive/refs/heads/main.zip' -OutFile \"$env:TEMP\orario-agg.zip\"; Expand-Archive \"$env:TEMP\orario-agg.zip\" \"$env:TEMP\orario-agg\" -Force" || (echo Download non riuscito: controlla la connessione. & goto fine)
REM questo file non si sovrascrive mentre gira; i file tolti dal progetto restano dove sono
for /d %%D in ("%T%\orario-scuola-*") do robocopy "%%D" . /E /XF Aggiorna-Orario-Scuola.bat /NFL /NDL /NJH /NJS /NP >nul
if errorlevel 8 (echo Copia dei file non riuscita. & goto fine)
rmdir /s /q "%T%" 2>nul
docker compose up -d --build || (echo Ricostruzione non riuscita. & goto fine)
echo.
set /p V=<VERSION
echo Aggiornato alla versione %V%.
:fine
echo.
pause
