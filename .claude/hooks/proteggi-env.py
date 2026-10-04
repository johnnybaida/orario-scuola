#!/usr/bin/env python3
"""Hook PreToolUse (Bash): impedisce a Claude Code di modificare, sovrascrivere o cancellare il file .env.

Il .env contiene credenziali e la chiave dell'applicazione e non è recuperabile da git. Questo hook blocca i
comandi di shell che scrivono su .env (redirect, cp, mv, rm, tee, sed -i, ...) e `artisan key:generate` senza
--show (che riscrive il .env). Leggere il file resta consentito. I file .env.example sono esclusi.
Exit code 2 = comando bloccato, il messaggio su stderr torna a Claude.
"""
import json
import re
import sys

try:
    comando = json.load(sys.stdin).get("tool_input", {}).get("command", "")
except Exception:
    sys.exit(0)

# .env, .env.local, .env.production, ... ma non .env.example
ENV = r"(?<![\w.\-])\.env(?:\.(?!example(?![\w.\-]))[A-Za-z0-9_.\-]+)?(?![\w.\-])"
SCRITTORI = r"(?:rm|mv|cp|tee|truncate|dd|ln|install|shred|unlink|sed\s+-[A-Za-z]*i|perl\s+-[A-Za-z]*i)"

regole = [
    # redirect verso .env:  > .env   >> $DIR/.env   &> .env
    (r">>?\s*[\"']?[^\s\"'<>|;&]*" + ENV, "redirect verso .env"),
    # comando che scrive/sposta/cancella con .env tra gli argomenti, nello stesso comando di shell
    (r"(?:^|[;&|(]\s*|\n\s*)" + SCRITTORI + r"\b[^;&|\n]*" + ENV, "comando di scrittura su .env"),
    # artisan che riscrive il .env
    (r"artisan\s+key:generate(?![^\n;&|]*--show)", "artisan key:generate senza --show (riscrive .env)"),
    (r"artisan\s+env:decrypt", "artisan env:decrypt (riscrive .env)"),
]

for pattern, motivo in regole:
    if re.search(pattern, comando):
        sys.stderr.write(
            f"Bloccato: {motivo}. Il file .env non va mai modificato da Claude Code (contiene credenziali e la "
            "chiave dell'applicazione e non è recuperabile da git). Chiedi all'utente di cambiarlo a mano e, "
            "per le prove, usa un percorso temporaneo o variabili d'ambiente.\n"
        )
        sys.exit(2)
sys.exit(0)
