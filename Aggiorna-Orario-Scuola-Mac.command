#!/bin/bash
# Orario Scuola - aggiornamento su Mac: scarica da GitHub lo ZIP dell'ultima versione, allinea la cartella del
# programma (i file che non esistono più vengono cancellati) e ricostruisce l'applicazione. Non serve Git.
# I dati (nel database Docker) non si perdono. Restano intatti: i file .env, i due script di aggiornamento,
# .git e, per chi sviluppa, web/vendor, web/node_modules, web/storage e web/solver/.venv.

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

# La cartella viene allineata con cancellazione: ci si ferma se non è quella del programma.
[ -f VERSION ] && [ -f compose.yaml ] && [ -d web ] || { echo "Questa non sembra la cartella di Orario Scuola: non cambio nulla."; fine 1; }
docker info >/dev/null 2>&1 || { echo "Docker non è acceso: avvia Docker Desktop e riprova."; fine 1; }

TMP=$(mktemp -d)
echo "Scarico l'ultima versione..."
curl -fsSL "$ZIP" -o "$TMP/p.zip" && unzip -q "$TMP/p.zip" -d "$TMP" || { echo "Download non riuscito: controlla la connessione."; fine 1; }
# Prima si scarica e si scompatta tutto, solo poi si allinea la cartella (--delete toglie i file non più nel progetto).
rsync -a --delete --exclude .env --exclude .git --exclude Aggiorna-Orario-Scuola-Mac.command --exclude Aggiorna-Orario-Scuola-Windows.bat \
    --exclude /web/vendor --exclude /web/node_modules --exclude /web/storage --exclude /web/solver/.venv \
    "$TMP"/orario-scuola-*/ ./ || { echo "Allineamento dei file non riuscito."; fine 1; }
rm -rf "$TMP"

docker compose up -d --build || { echo; echo "Ricostruzione non riuscita."; fine 1; }

echo
echo "Aggiornato alla versione $(cat VERSION)."
fine 0
