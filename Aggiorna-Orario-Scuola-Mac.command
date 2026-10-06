#!/bin/bash
# Orario Scuola - aggiornamento su Mac: scarica da GitHub lo ZIP dell'ultima versione, lo sovrascrive sui file
# del programma e ricostruisce l'applicazione. Non serve Git. I dati (nel database Docker) non si perdono.

cd "$(dirname "$0")" || exit 1
export PATH="$PATH:/usr/local/bin:/opt/homebrew/bin:/Applications/Docker.app/Contents/Resources/bin"
ZIP="https://github.com/johnnybaida/orario-scuola/archive/refs/heads/main.zip"

fine() {
    echo
    read -n 1 -s -r -p "Premi un tasto per chiudere questa finestra..."
    echo
    exit "${1:-0}"
}

echo "ORARIO SCUOLA - aggiornamento"
echo

docker info >/dev/null 2>&1 || { echo "Docker non è acceso: avvia Docker Desktop e riprova."; fine 1; }

TMP=$(mktemp -d)
echo "Scarico l'ultima versione..."
curl -fsSL "$ZIP" -o "$TMP/p.zip" && unzip -q "$TMP/p.zip" -d "$TMP" || { echo "Download non riuscito: controlla la connessione."; fine 1; }
# l'attuale script resta com'è (sovrascriverlo mentre gira lo romperebbe); i file tolti dal progetto restano dove sono
rsync -a --exclude Aggiorna-Orario-Scuola-Mac.command "$TMP"/orario-scuola-*/ ./ || { echo "Copia dei file non riuscita."; fine 1; }
rm -rf "$TMP"

docker compose up -d --build || { echo; echo "Ricostruzione non riuscita."; fine 1; }

echo
echo "Aggiornato alla versione $(cat VERSION)."
fine 0
