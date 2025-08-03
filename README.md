## WordPress Custom Project – Fabrizio

Questo repository contiene **solo le personalizzazioni** del sito WordPress sviluppato in locale con Local, escluse le parti core e i plugin premium.

## 📁 Struttura del repository

wp-content/  
├── plugins/  
│   └── custom-tool         &nbsp;&nbsp;&nbsp; # Plugin custom  
├── themes/  
│   └── fabrizio-theme/            &nbsp;&nbsp;&nbsp; # Tema custom sviluppato da zero  
│       ├── acf-json/ # Configurazione ACF salvata in JSON  
│       └── elementor-templates/   &nbsp;&nbsp;&nbsp; # Template Elementor esportati


## ⚙️ Requisiti

- WordPress (>= 6.x)
- PHP >= 7.4
- MySQL >= 5.7
- ACF PRO (non incluso)
- Elementor PRO (non incluso)

## 🧰 Plugin richiesti (da installare manualmente)

| Plugin         | Versione minima | Note                      |
|----------------|-----------------|---------------------------|
| Advanced Custom Fields PRO | 6.x | Obbligatorio per i campi custom |
| Elementor PRO  | 3.x             | Obbligatorio per il layout |

## 💾 Setup progetto

1. Clona il repository:
   ```bash
   git clone https://github.com/tuo-utente/wordpress-custom-fabrizio.git

2. Installa WordPress (via Local o altro ambiente)
3. Copia le cartelle plugins/fabrizio-plugin e themes/fabrizio-theme nel tuo wp-content/
4. Installa i plugin richiesti (ACF PRO, Elementor PRO)
5. Importa il database di sviluppo (non incluso nel repository)
6. Elementor:
   •	Vai su Template salvati
   •	Reimporta quelli da elementor-templates/ se necessario


💡 Note
•	I campi ACF vengono salvati automaticamente in acf-json/ e caricati dal tema
•	Non vengono salvati: upload, wp-config.php, WordPress core




## 🗃️ Gestione Backup Database

### 📄 File disponibili

#### `export-db.sh`
Script Bash che esegue l’**esportazione del database** in formato `.sql`, con **data e ora incluse nel nome del file** per una gestione cronologica dei backup. Il file viene salvato nella directory `public/export` del progetto.

Esempio di output:
```
deborahlocastro_backup_2025-07-30_10-00-00.sql
```

#### `import-db.sh`
Script Bash che permette di **importare manualmente** un file `.sql` precedentemente esportato nel database locale. L’utente può specificare il nome del file da importare oppure usare l’ultimo backup disponibile.

---

### ⚙️ Configurazione del Cronjob (macOS)

#### Obiettivo
Eseguire automaticamente `export-db.sh` ogni giorno alle ore 10:00 per salvare un backup aggiornato del database.

#### Passaggi

1. Apri il terminale.
2. Modifica il file dei cronjob con `nano`:
   ```bash
   EDITOR=nano crontab -e
   ```
3. Aggiungi questa riga:
   ```bash
   0 10 * * * /Users/fabrizio/Project/LocalSite/deborahlocastro/app/public/export/export-db.sh
   ```
   *(Sostituisci il percorso con quello corretto se necessario. Evita spazi nei nomi delle cartelle o gestiscili con `\` oppure racchiudi il percorso tra virgolette.)*

4. Salva con `CTRL + O`, poi premi `Invio`. Esci con `CTRL + X`.

#### Comandi utili

- Verificare i cronjob attivi:
  ```bash
  crontab -l
  ```

- Rimuovere tutti i cronjob:
  ```bash
  crontab -r
  ```

---

### 📁 Note e consigli

- Assicurati che lo script `export-db.sh` sia eseguibile:
  ```bash
  chmod +x export-db.sh
  ```

- Evita percorsi contenenti spazi. In caso contrario:
  - Usa virgolette: `"/Users/tuonome/Cartella Con Spazi/script.sh"`
  - Oppure l’escape: `/Users/tuonome/Cartella\ Con\ Spazi/script.sh`

---

### 📦 Requisiti

- MySQL/MariaDB CLI tools (es. `mysqldump`, `mysql`)
- Permessi sufficienti per accedere al database
- Script testati su macOS (Apple Silicon)