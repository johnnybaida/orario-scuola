@echo off
rem Orario Scuola - avvio, aggiornamento e spegnimento su Windows: fai doppio clic su questo file.
rem Si apre una finestra con tre pulsanti (Avvia, Aggiorna, Ferma); in quella finestra, "Crea icona sul Desktop"
rem crea un'icona con cui non vedrai piu' questo file. I dati (nel database Docker) non si perdono mai.
rem Il programma e' il codice PowerShell che segue la riga con il marcatore: questo file lo legge e lo esegue.
start "" /min powershell -NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -Command "$bat='%~f0'; iex ((Get-Content -Raw -Encoding UTF8 -LiteralPath $bat) -split ('#'+'PS#'))[-1]" & exit /b
#PS#
Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing
[System.Windows.Forms.Application]::EnableVisualStyles()

$progetto = Split-Path -Parent $bat
$zipUrl = 'https://github.com/johnnybaida/orario-scuola/archive/refs/heads/main.zip'
$icona = Join-Path $progetto 'risorse\icona.ico'
$Titolo = 'Orario Scuola'

function Messaggio([string]$testo, [string]$tipo = 'Information') {
    [System.Windows.Forms.MessageBox]::Show($testo, $Titolo, 'OK', $tipo) | Out-Null
}

if (-not (Test-Path -LiteralPath (Join-Path $progetto 'compose.yaml'))) {
    Messaggio "Questo file deve stare nella cartella di Orario Scuola (quella con il file compose.yaml). Spostalo li' e riprova." 'Error'
    exit
}
$versione = ''
$fileVersione = Join-Path $progetto 'VERSION'
if (Test-Path -LiteralPath $fileVersione) { $versione = (Get-Content -LiteralPath $fileVersione -TotalCount 1).Trim() }

# ---------- il lavoro vero e proprio, eseguito in un processo a parte; le righe che iniziano con ## sono l'esito ----------
$logica = {
    param($p, $azione, $zip)
    $ErrorActionPreference = 'Continue'
    Set-Location -LiteralPath $p
    $global:esito = 0

    # Esegue un comando mostrandone l'output riga per riga.
    function Lancia([string]$comando) {
        cmd /c "$comando 2>&1" | ForEach-Object { Write-Output $_ }
        $global:esito = $LASTEXITCODE
    }
    function DockerAcceso {
        cmd /c "docker info >nul 2>&1"
        return ($LASTEXITCODE -eq 0)
    }
    function ServeDocker {
        if (-not (Get-Command docker -ErrorAction SilentlyContinue)) { Write-Output '##SENZADOCKER'; exit }
        if (-not (DockerAcceso)) {
            Write-Output "Docker non e' acceso: lo avvio, attendi qualche istante..."
            $exe = Join-Path $env:ProgramFiles 'Docker\Docker\Docker Desktop.exe'
            if (Test-Path -LiteralPath $exe) { Start-Process -FilePath $exe }
            for ($i = 0; $i -lt 60; $i++) {
                if (DockerAcceso) { break }
                Start-Sleep -Seconds 5
            }
            if (-not (DockerAcceso)) {
                Write-Output "##ERR|Docker non si e' avviato. Aprilo a mano, attendi che sia pronto (l'icona della balena smette di muoversi) e riprova."
                exit
            }
        }
    }

    $porta = '8080'
    if (Test-Path -LiteralPath '.env') {
        $riga = Select-String -Path '.env' -Pattern '^DOCKER_APP_PORT=(.+)$' | Select-Object -Last 1
        if ($riga) { $porta = $riga.Matches[0].Groups[1].Value.Trim().Trim('"').Trim("'") }
    }
    $indirizzo = "http://localhost:$porta"

    if ($azione -eq 'avvia') {
        ServeDocker
        Write-Output "Avvio dell'applicazione. La PRIMA volta puo' richiedere alcuni minuti..."
        Lancia 'docker compose up -d --build'
        if ($global:esito -ne 0) { Write-Output "##ERR|Qualcosa non ha funzionato nell'avvio. Se serve assistenza, copia il testo della finestra e mandalo a chi gestisce l'installazione."; exit }
        Write-Output "Attendo che l'applicazione sia pronta..."
        $pronta = $false
        for ($i = 0; $i -lt 90; $i++) {
            try { Invoke-WebRequest -UseBasicParsing -Uri "$indirizzo/login" -TimeoutSec 5 | Out-Null; $pronta = $true; break } catch { Start-Sleep -Seconds 2 }
        }
        if (-not $pronta) { Write-Output "##ERR|L'applicazione ci sta mettendo piu' del previsto. Prova ad aprire $indirizzo tra un minuto; se non si apre, riprova."; exit }
        Write-Output "##OK|$indirizzo|PRONTO!`n`nL'applicazione e' aperta nel browser all'indirizzo:`n$indirizzo`n`nPrimo accesso:`nemail: amministratore@scuola.test`npassword: password`nDopo l'accesso cambia la password dalla voce Utenze.`n`nDagli altri computer della scuola: http://INDIRIZZO-DI-QUESTO-COMPUTER:$porta`n`nPuoi chiudere la finestra: l'applicazione resta attiva."
    }
    elseif ($azione -eq 'ferma') {
        if (-not (Get-Command docker -ErrorAction SilentlyContinue) -or -not (DockerAcceso)) {
            Write-Output "##OK|-|Docker non e' acceso: l'applicazione risulta gia' spenta."; exit
        }
        Lancia 'docker compose down'
        if ($global:esito -ne 0) { Write-Output "##ERR|Non sono riuscito a spegnere l'applicazione. Chiudi Docker Desktop per fermarla."; exit }
        Write-Output "##OK|-|Applicazione spenta. I dati sono al sicuro e ci saranno al prossimo avvio."
    }
    elseif ($azione -eq 'aggiorna') {
        if (-not (Test-Path -LiteralPath 'web')) { Write-Output "##ERR|Questa non sembra la cartella di Orario Scuola: non cambio nulla."; exit }
        ServeDocker
        $tmp = Join-Path $env:TEMP 'orario-agg'
        Remove-Item -LiteralPath $tmp -Recurse -Force -ErrorAction SilentlyContinue
        New-Item -ItemType Directory -Path $tmp | Out-Null
        Write-Output "Scarico l'ultima versione..."
        try {
            $ProgressPreference = 'SilentlyContinue'
            Invoke-WebRequest -UseBasicParsing -Uri $zip -OutFile (Join-Path $tmp 'p.zip')
            Expand-Archive -LiteralPath (Join-Path $tmp 'p.zip') -DestinationPath (Join-Path $tmp 'x') -Force
        } catch {
            Write-Output "##ERR|Download non riuscito: controlla la connessione."; exit
        }
        $sorgente = Get-ChildItem -LiteralPath (Join-Path $tmp 'x') -Directory | Select-Object -First 1
        Write-Output "Aggiorno i file..."
        # Prima si scarica e si scompatta tutto, solo poi si allinea la cartella (/MIR toglie i file non piu' nel progetto).
        Lancia ('robocopy "{0}" "{1}" /MIR /XD "{1}\.git" "{1}\web\vendor" "{1}\web\node_modules" "{1}\web\storage" "{1}\web\solver\.venv" /XF .env /NFL /NDL /NJH /NJS /NP' -f $sorgente.FullName, $p)
        if ($global:esito -ge 8) { Write-Output "##ERR|Allineamento dei file non riuscito."; exit }
        Remove-Item -LiteralPath $tmp -Recurse -Force -ErrorAction SilentlyContinue
        Write-Output "Ricostruisco l'applicazione (qualche minuto)..."
        Lancia 'docker compose up -d --build'
        if ($global:esito -ne 0) { Write-Output "##ERR|Ricostruzione non riuscita."; exit }
        $nuova = (Get-Content -LiteralPath 'VERSION' -TotalCount 1).Trim()
        Write-Output "##OK|-|Aggiornato alla versione $nuova.`n`nI dati non sono stati toccati."
    }
}

# ---------- finestra ----------
$f = New-Object System.Windows.Forms.Form
$f.Text = $Titolo
$f.ClientSize = New-Object System.Drawing.Size(640, 520)
$f.StartPosition = 'CenterScreen'
$f.FormBorderStyle = 'FixedSingle'
$f.MaximizeBox = $false
$f.BackColor = [System.Drawing.Color]::White
if (Test-Path -LiteralPath $icona) { $f.Icon = New-Object System.Drawing.Icon($icona) }

$titolo = New-Object System.Windows.Forms.Label
$titolo.Text = 'Orario Scuola'
$titolo.Font = New-Object System.Drawing.Font('Segoe UI', 20, [System.Drawing.FontStyle]::Bold)
$titolo.AutoSize = $true
$titolo.Location = New-Object System.Drawing.Point(20, 14)
$f.Controls.Add($titolo)
$ver = New-Object System.Windows.Forms.Label
$ver.Text = "versione $versione"
$ver.ForeColor = [System.Drawing.Color]::Gray
$ver.AutoSize = $true
$ver.Location = New-Object System.Drawing.Point(24, 56)
$f.Controls.Add($ver)

$pulsanti = @{}
function NuovoPulsante([string]$nome, [string]$descrizione, [int]$x) {
    $b = New-Object System.Windows.Forms.Button
    $b.Text = "$nome`r`n`r`n$descrizione"
    $b.Font = New-Object System.Drawing.Font('Segoe UI', 11, [System.Drawing.FontStyle]::Bold)
    $b.Size = New-Object System.Drawing.Size(190, 120)
    $b.Location = New-Object System.Drawing.Point($x, 90)
    $b.FlatStyle = 'Flat'
    $b.BackColor = [System.Drawing.Color]::FromArgb(240, 245, 255)
    $b.Cursor = [System.Windows.Forms.Cursors]::Hand
    $f.Controls.Add($b)
    return $b
}
$pulsanti.avvia = NuovoPulsante 'Avvia' "accende e apre`r`nl'applicazione" 20
$pulsanti.aggiorna = NuovoPulsante 'Aggiorna' "scarica l'ultima`r`nversione" 225
$pulsanti.ferma = NuovoPulsante 'Ferma' "spegne l'applicazione`r`n(i dati restano)" 430

$stato = New-Object System.Windows.Forms.Label
$stato.Text = 'Scegli cosa fare.'
$stato.AutoSize = $true
$stato.Location = New-Object System.Drawing.Point(20, 225)
$f.Controls.Add($stato)

$log = New-Object System.Windows.Forms.TextBox
$log.Multiline = $true
$log.ReadOnly = $true
$log.ScrollBars = 'Vertical'
$log.Font = New-Object System.Drawing.Font('Consolas', 9)
$log.Location = New-Object System.Drawing.Point(20, 250)
$log.Size = New-Object System.Drawing.Size(600, 220)
$f.Controls.Add($log)

$icone = New-Object System.Windows.Forms.Button
$icone.Text = 'Crea icona sul Desktop'
$icone.Size = New-Object System.Drawing.Size(190, 28)
$icone.Location = New-Object System.Drawing.Point(20, 482)
$f.Controls.Add($icone)

$script:lavoro = $null
$script:azioneInCorso = ''
$script:esitoFinale = ''

function Occupato([bool]$si) {
    foreach ($b in $pulsanti.Values) { $b.Enabled = -not $si }
    $icone.Enabled = -not $si
}

function Esegui([string]$azione, [string]$descrizione) {
    $log.Clear()
    $stato.Text = $descrizione
    $script:azioneInCorso = $azione
    $script:esitoFinale = ''
    Occupato $true
    $script:lavoro = Start-Job -ScriptBlock $logica -ArgumentList $progetto, $azione, $zipUrl
    $timer.Start()
}

# Ogni mezzo secondo si leggono le righe nuove del lavoro in corso; a lavoro finito si mostra l'esito.
$timer = New-Object System.Windows.Forms.Timer
$timer.Interval = 500
$timer.Add_Tick({
    if ($null -eq $script:lavoro) { return }
    foreach ($riga in @(Receive-Job -Job $script:lavoro)) {
        $testo = [string]$riga
        if ($testo.StartsWith('##')) { $script:esitoFinale = $testo } else { $log.AppendText($testo + "`r`n") }
    }
    if ($script:lavoro.State -eq 'Running') { return }
    foreach ($riga in @(Receive-Job -Job $script:lavoro)) {
        $testo = [string]$riga
        if ($testo.StartsWith('##')) { $script:esitoFinale = $testo } else { $log.AppendText($testo + "`r`n") }
    }
    $timer.Stop()
    Remove-Job -Job $script:lavoro -Force
    $script:lavoro = $null
    Occupato $false
    $e = $script:esitoFinale
    if ($e.StartsWith('##OK|')) {
        $parti = $e.Substring(5).Split('|', 2)
        $stato.Text = 'Fatto.'
        if ($script:azioneInCorso -eq 'avvia' -and $parti[0] -ne '-') { Start-Process $parti[0] }
        Messaggio $parti[1]
    }
    elseif ($e -eq '##SENZADOCKER') {
        $stato.Text = 'Docker Desktop non e'' installato.'
        $r = [System.Windows.Forms.MessageBox]::Show("Docker Desktop non e' installato su questo computer.`n`nVuoi aprire la pagina di download? Installalo, aprilo una volta e attendi che sia pronto, poi riprova.", $Titolo, 'YesNo', 'Warning')
        if ($r -eq 'Yes') { Start-Process 'https://www.docker.com/products/docker-desktop/' }
    }
    elseif ($e.StartsWith('##ERR|')) {
        $stato.Text = 'Qualcosa non ha funzionato.'
        Messaggio ($e.Substring(6) + "`n`nIl dettaglio e' nella casella della finestra.") 'Error'
    }
    else {
        $stato.Text = 'Qualcosa non ha funzionato.'
        Messaggio "Il lavoro si e' interrotto senza un esito. Il dettaglio e' nella casella della finestra." 'Error'
    }
})

$pulsanti.avvia.Add_Click({ Esegui 'avvia' "Avvio in corso... la prima volta puo' richiedere alcuni minuti." })
$pulsanti.aggiorna.Add_Click({ Esegui 'aggiorna' 'Aggiornamento in corso... ci vogliono alcuni minuti.' })
$pulsanti.ferma.Add_Click({ Esegui 'ferma' 'Spegnimento in corso...' })

$icone.Add_Click({
    try {
        $desktop = [Environment]::GetFolderPath('Desktop')
        $collegamento = (New-Object -ComObject WScript.Shell).CreateShortcut((Join-Path $desktop 'Orario Scuola.lnk'))
        $collegamento.TargetPath = Join-Path $env:WINDIR 'System32\WindowsPowerShell\v1.0\powershell.exe'
        # Argomenti brevi (alias e abbreviazioni): alcune versioni di Windows limitano a 260 caratteri gli argomenti di un collegamento.
        $collegamento.Arguments = "-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -Command `"`$bat='$bat';iex((gc -Raw -Enc UTF8 -Lit `$bat)-split('#'+'PS#'))[-1]`""
        $collegamento.WorkingDirectory = $progetto
        if (Test-Path -LiteralPath $icona) { $collegamento.IconLocation = $icona }
        $collegamento.Description = 'Orario Scuola: avvia, aggiorna o ferma l''applicazione'
        $collegamento.Save()
        Messaggio "Ho creato l'icona «Orario Scuola» sul Desktop: da ora in poi usa quella."
    } catch {
        Messaggio "Non sono riuscito a creare l'icona: $($_.Exception.Message)" 'Error'
    }
})

$f.Add_FormClosing({
    if ($null -ne $script:lavoro) {
        $_.Cancel = $true
        Messaggio "C'e' un lavoro in corso: attendi che finisca prima di chiudere."
    }
})

[System.Windows.Forms.Application]::Run($f)
