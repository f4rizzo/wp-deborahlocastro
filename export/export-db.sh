#!/bin/bash

# === CONFIGURAZIONE ===

EXPORT_PATH="/Users/fabrizio/Project/Local Site/dblc/app/public/export/db-dumps"
EXPORT_NAME="dump-$(date +%Y%m%d_%H%M%S).sql"
#Estratto da Local
MYSQLDUMP="/Users/fabrizio/Library/Application Support/Local/lightning-services/mysql-8.0.35+4/bin/darwin-arm64/bin/mysqldump"
SOCKET_PATH="/Users/fabrizio/Library/Application Support/Local/run/CsXBKk1Yi/mysql/mysqld.sock"

DB_NAME="local"              # Nome del DB, cambia se diverso
DB_USER="root"
DB_PASSWORD="root"       # Prendi la password da Local, se diversa

# === CREA CARTELLA SE NON ESISTE ===
mkdir -p "$EXPORT_PATH"

# === ESEGUI DUMP ===
"$MYSQLDUMP" \
  --user="$DB_USER" \
  --password="$DB_PASSWORD" \
  --socket="$SOCKET_PATH" \
  "$DB_NAME" > "$EXPORT_PATH/$EXPORT_NAME"

# === VERIFICA SUCCESSO E PULIZIA ===
if [ $? -eq 0 ]; then
  echo "✅ Dump completato: $EXPORT_PATH/$EXPORT_NAME"

  # === PULIZIA VECCHI DUMP (MANTIENI GLI ULTIMI 3) ===
  echo "🧹 Inizio la pulizia dei vecchi dump..."
  (
    cd "$EXPORT_PATH" || exit
    # Conta il numero di dump presenti
    NUM_FILES=$(ls -1 dump-*.sql 2>/dev/null | wc -l)

    if [ "$NUM_FILES" -gt 3 ]; then
      # Elenca i file per data (dal più nuovo al più vecchio),
      # salta i primi 3 e cancella gli altri.
      ls -1t dump-*.sql | tail -n +4 | xargs rm
      echo "🗑️  Dump più vecchi eliminati correttamente."
    else
      echo "👍 Non ci sono vecchi dump da eliminare (limite di 3 non superato)."
    fi
  )

else
  echo "❌ Errore durante il dump del database."
fi