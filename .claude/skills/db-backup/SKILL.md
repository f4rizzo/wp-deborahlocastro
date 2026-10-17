---
name: db-backup
description: Esegue un backup del database del sito Local in export/db-dumps/. Da usare prima di ogni operazione che scrive sul DB (search-replace, import, cancellazioni, modifiche alle opzioni, aggiornamento di plugin).
allowed-tools: Bash(export/export-db.sh *), Bash(./export/export-db.sh *)
---

1. Esegui `export/export-db.sh` dalla radice del repository (`app/public`).
2. Se fallisce con "Ambiente Local non trovato", il sito non è avviato in Local: fermati e chiedi a Fabrizio di avviarlo. Non tentare altre strade.
3. Riporta il nome del dump creato e la sua dimensione. Lo script tiene solo gli ultimi 3 dump.
