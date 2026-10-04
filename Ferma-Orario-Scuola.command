#!/bin/bash
# Orario Scuola - spegnimento su Mac: fai doppio clic su questo file.
# I dati (orari, docenti, classi, ...) NON vengono cancellati.

cd "$(dirname "$0")" || exit 1
export PATH="$PATH:/usr/local/bin:/opt/homebrew/bin:/Applications/Docker.app/Contents/Resources/bin"

echo "=============================================="
echo "   ORARIO SCUOLA - spegnimento"
echo "=============================================="
echo

if ! command -v docker >/dev/null 2>&1 || ! docker info >/dev/null 2>&1; then
    echo "Docker non è acceso: l'applicazione risulta già spenta."
else
    docker compose down && echo && echo "Applicazione spenta. I dati sono al sicuro e ci saranno al prossimo avvio."
fi

echo
read -n 1 -s -r -p "Premi un tasto per chiudere questa finestra..."
echo
