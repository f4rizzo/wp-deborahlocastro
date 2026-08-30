#!/bin/bash
#
# export-db.sh — Esporta il database del sito Local in un file .sql con timestamp.
#
# Portabile: NON contiene percorsi assoluti. Carica l'ambiente del sito da
# app/.envrc (rigenerato da Local a ogni avvio) e usa WP-CLI, che ricava
# nome DB, credenziali e socket da wp-config.php + MYSQL_HOME.
#
# Requisiti: il sito dev'essere avviato in Local.
#
# Uso:
#   ./export-db.sh
#   (eseguibile anche da cron, purché il sito sia in esecuzione)
#
set -uo pipefail

# --- Percorsi relativi allo script ------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PUBLIC_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"          # .../app/public
ENVRC="$(cd "$PUBLIC_DIR/.." && pwd)/.envrc"        # .../app/.envrc

EXPORT_PATH="$SCRIPT_DIR/db-dumps"
EXPORT_FILE="$EXPORT_PATH/dump-$(date +%Y%m%d_%H%M%S).sql"
KEEP=3                                              # quanti dump conservare

# --- Ambiente di Local (PATH -> wp/mysql, MYSQL_HOME -> socket/credenziali) --
have_local_env() {
  command -v wp >/dev/null 2>&1 && [ -n "${MYSQL_HOME:-}" ]
}

if ! have_local_env; then
  [ -f "$ENVRC" ] && . "$ENVRC"
fi

if ! have_local_env; then
  echo "❌ Ambiente Local non trovato."
  echo "   Avvia il sito in Local e riprova, oppure lancia lo script dalla"
  echo "   shell del sito (Local ▸ tasto destro sul sito ▸ Open site shell)."
  exit 1
fi

# --- Dump -------------------------------------------------------------------
mkdir -p "$EXPORT_PATH"
echo "📦 Export database → $EXPORT_FILE"

if wp --path="$PUBLIC_DIR" db export "$EXPORT_FILE" --defaults; then
  echo "✅ Dump completato ($(du -h "$EXPORT_FILE" | cut -f1))"
else
  echo "❌ Export fallito. Il sito è avviato in Local?"
  rm -f "$EXPORT_FILE"
  exit 1
fi

# --- Pulizia: mantieni solo gli ultimi $KEEP dump --------------------------
{ ls -1t "$EXPORT_PATH"/dump-*.sql 2>/dev/null || true; } \
  | tail -n +"$((KEEP + 1))" \
  | while IFS= read -r old; do
      rm -f "$old" && echo "🗑️  Rimosso vecchio dump: $(basename "$old")"
    done
