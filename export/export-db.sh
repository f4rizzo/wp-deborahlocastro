#!/bin/bash

# === CONFIGURAZIONE ===

EXPORT_PATH="/Users/fabrizio/Project/Local Site/deborahlocastro/app/public/export/db-dumps"
EXPORT_NAME="dump-$(date +%Y%m%d_%H%M%S).sql"
#Estratto da Local
MYSQLDUMP="/Users/fabrizio/Library/Application Support/Local/lightning-services/mysql-8.0.35+4/bin/darwin-arm64/bin/mysqldump"
SOCKET_PATH="/Users/fabrizio/Library/Application Support/Local/run/L-YkgLqnA/mysql/mysqld.sock"

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

# === VERIFICA SUCCESSO ===
if [ $? -eq 0 ]; then
  echo "✅ Dump completato: $EXPORT_PATH/$EXPORT_NAME"
else
  echo "❌ Errore durante il dump del database."
fi