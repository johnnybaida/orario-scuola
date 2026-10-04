#!/bin/bash
# Orario Scuola - avvio su Mac: fai doppio clic su questo file.
# Controlla che Docker Desktop sia installato e acceso, avvia l'applicazione e apre il browser.

cd "$(dirname "$0")" || exit 1
export PATH="$PATH:/usr/local/bin:/opt/homebrew/bin:/Applications/Docker.app/Contents/Resources/bin"

PORTA=8080
if [ -f .env ]; then
    DAL_FILE=$(grep -E '^DOCKER_APP_PORT=' .env | tail -1 | cut -d= -f2 | tr -d '\r"'"'")
    [ -n "$DAL_FILE" ] && PORTA="$DAL_FILE"
fi
INDIRIZZO="http://localhost:$PORTA"

fine() {
    echo
    read -n 1 -s -r -p "Premi un tasto per chiudere questa finestra..."
    echo
    exit "${1:-0}"
}

echo "=============================================="
echo "   ORARIO SCUOLA - avvio"
echo "=============================================="
echo

# 1. Docker installato?
if ! command -v docker >/dev/null 2>&1; then
    echo "Docker Desktop non è installato su questo computer."
    echo
    echo "Che cosa fare:"
    echo "  1. Si apre ora la pagina di download: scarica e installa Docker Desktop."
    echo "  2. Apri Docker Desktop una volta e attendi che sia pronto."
    echo "  3. Poi fai di nuovo doppio clic su questo file."
    open "https://www.docker.com/products/docker-desktop/"
    fine 1
fi

# 2. Docker acceso? Se no, lo avvio e aspetto (fino a 5 minuti).
if ! docker info >/dev/null 2>&1; then
    echo "Docker non è acceso: lo avvio, attendi qualche istante..."
    open -a Docker
    for _ in $(seq 1 60); do
        docker info >/dev/null 2>&1 && break
        sleep 5
    done
    if ! docker info >/dev/null 2>&1; then
        echo
        echo "Docker non si è avviato. Apri Docker Desktop a mano, attendi che sia pronto"
        echo "(l'icona della balena smette di muoversi) e rilancia questo file."
        fine 1
    fi
fi

# 3. Avvio dell'applicazione
echo "Avvio dell'applicazione. La PRIMA volta può richiedere alcuni minuti: non chiudere la finestra."
echo
if ! docker compose up -d --build; then
    echo
    echo "Qualcosa non ha funzionato nell'avvio. Se serve assistenza, fai una foto o copia"
    echo "il testo qui sopra e mandalo a chi gestisce l'installazione."
    fine 1
fi

# 4. Attesa che risponda (fino a 3 minuti: migrazioni e dati di esempio al primo avvio)
echo
echo "Attendo che l'applicazione sia pronta..."
PRONTA=0
for _ in $(seq 1 90); do
    if curl -s -f -o /dev/null "$INDIRIZZO/login"; then PRONTA=1; break; fi
    sleep 2
done
if [ "$PRONTA" != 1 ]; then
    echo
    echo "L'applicazione ci sta mettendo più del previsto. Prova ad aprire $INDIRIZZO tra un minuto."
    echo "Se non si apre, rilancia questo file."
    fine 1
fi

open "$INDIRIZZO"

echo
echo "=============================================="
echo "   PRONTO!"
echo "=============================================="
echo
echo "L'applicazione è aperta nel browser all'indirizzo:"
echo "    $INDIRIZZO"
echo
echo "Primo accesso:"
echo "    email:     amministratore@scuola.test"
echo "    password:  password"
echo "  Dopo l'accesso cambia la password dalla voce Utenze."
echo
echo "Dagli altri computer della scuola: http://INDIRIZZO-DI-QUESTO-COMPUTER:$PORTA"
echo
echo "Puoi chiudere questa finestra: l'applicazione resta attiva."
echo "Per spegnerla usa il file \"Ferma-Orario-Scuola.command\"."
fine 0
