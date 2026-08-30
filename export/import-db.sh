#!/bin/bash
#
# import-db.sh — Importa un dump .sql nel database del sito Local.
#
# Portabile: NON contiene percorsi assoluti. Carica l'ambiente del sito da
# app/.envrc e usa WP-CLI (nome DB, credenziali e socket vengono da
# wp-config.php + MYSQL_HOME).
#
# Requisiti: il sito dev'essere avviato in Local.
#
# Uso:
#   ./import-db.sh                       # importa il dump più recente in ./db-dumps/
#   ./import-db.sh percorso/al/dump.sql  # importa il file indicato
#
set -uo pipefail

# --- Percorsi relativi allo script ------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PUBLIC_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"          # .../app/public
ENVRC="$(cd "$PUBLIC_DIR/.." && pwd)/.envrc"        # .../app/.envrc
EXPORT_PATH="$SCRIPT_DIR/db-dumps"

# --- File da importare -----------------------------------------------------
IMPORT_FILE="${1:-}"
if [ -z "$IMPORT_FILE" ]; then
  IMPORT_FILE="$({ ls -1t "$EXPORT_PATH"/dump-*.sql 2>/dev/null || true; } | head -n 1)"
fi

if [ -z "$IMPORT_FILE" ] || [ ! -f "$IMPORT_FILE" ]; then
  echo "❌ Nessun file da importare."
  echo "   Uso: $(basename "$0") [percorso/al/dump.sql]"
  echo "   Senza argomenti importa il dump più recente in: $EXPORT_PATH"
  exit 1
fi

# --- Ambiente di Local ----------------------------------------------------
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

DB_NAME="$(wp --path="$PUBLIC_DIR" config get DB_NAME 2>/dev/null || echo '(sconosciuto)')"

# --- Conferma ------------------------------------------------------------
echo "⚠️  Il database '$DB_NAME' verrà SVUOTATO e sostituito con:"
echo "    $IMPORT_FILE"
printf "Continuare? [y/N] "
read -r answer
case "$answer" in
  y|Y|yes|si|s|S) ;;
  *) echo "Annullato."; exit 0 ;;
esac

# --- Import (reset per evitare tabelle orfane, poi import) --------------
wp --path="$PUBLIC_DIR" db reset --yes --defaults
wp --path="$PUBLIC_DIR" db import "$IMPORT_FILE" --defaults

echo "✅ Import completato nel database '$DB_NAME'."
