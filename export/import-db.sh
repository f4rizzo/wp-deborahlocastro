#!/bin/bash

read -p "📦 Nome database di destinazione [local]: " DB_NAME
DB_NAME=${DB_NAME:-local}

read -p "📄 File da importare [db-dumps/db-export.sql]: " IMPORT_FILE
IMPORT_FILE=${IMPORT_FILE:-db-dumps/db-export.sql}

mysql -u root -proot $DB_NAME < $IMPORT_FILE

if [ $? -eq 0 ]; then
    echo "✅ Import completato!"
else
    echo "❌ Errore nell'import."
fi