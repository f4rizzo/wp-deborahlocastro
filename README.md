## WordPress Debora Lo Castro – Progetto Modulare

Questo repository contiene **solo le personalizzazioni** del sito WordPress sviluppato in locale con Local, escluse le parti core e i plugin premium.

Progetto WordPress con architettura modulare che separa i componenti custom in repository indipendenti per un migliore controllo di versioning e sviluppo collaborativo.

## 📁 Struttura del progetto versionato

wp-content/                    ← repository principale
├── plugins/
│   └── custom-tools/          ← submodule
└── themes/
    └── hello-theme-child/     ← submodule


## 🏗️ Architettura

Il progetto è suddiviso in tre repository separate:

- **🌐 Core Project** (questa repo): Configurazioni WordPress, file base, utilities e struttura generale
- **🔧 Custom Plugin**: [custom-tools](https://github.com/f4rizzo/wp-plugin_custom-tools) - Plugin personalizzato per funzionalità specifiche
- **🎨 Custom Theme**: [hello-theme-child](https://github.com/f4rizzo/wp-theme_hello-theme-child) - Tema child personalizzato



## ⚙️ Requisiti

- WordPress (>= 6.x)
- PHP >= 7.4
- MySQL >= 5.7
- ACF PRO (non incluso)
- Elementor PRO (non incluso)

## 🧰 Plugin richiesti (da installare manualmente)

| Plugin                     | Versione minima | Note                            |
| -------------------------- | --------------- | ------------------------------- |
| Advanced Custom Fields PRO | 6.x             | Obbligatorio per i campi custom |
| Elementor PRO              | 3.x             | Obbligatorio per il layout      |




## 📦 Setup Completo

### Clone iniziale con tutti i componenti
```bash
# Clone con submodules
git clone --recursive https://github.com/f4rizzo/wp-deborahlocastro.git

# Oppure clone normale + inizializzazione submodules
git clone https://github.com/f4rizzo/wp-deborahlocastro.git
cd wp-deborahlocastro
git submodule update --init --recursive
```

### Setup ambiente di sviluppo locale
1. Configura il tuo ambiente WordPress (XAMPP, Local, Docker, etc.)
2. Copia il progetto nella directory web root
3. Configura database e `wp-config.php`
4. Attiva plugin e tema dall'admin WordPress

## 🔄 Workflow di Sviluppo

### Aggiornare tutto
```bash
# Aggiorna il progetto principale
git pull origin main

# Aggiorna tutti i submodules
git submodule update --remote
```

### Lavorare sul plugin
```bash
cd wp-content/plugins/custom-tools
# Sviluppa, testa, committa...
git add .
git commit -m "Nuova funzionalità plugin"
git push

# Torna alla root e aggiorna il riferimento
cd ../../..
git add wp-content/plugins/custom-tools
git commit -m "Updated plugin to latest version"
git push
```

### Lavorare sul tema
```bash
cd wp-content/themes/hello-theme-child
# Sviluppa, testa, committa...
git add .
git commit -m "Aggiornamenti styling"
git push

# Torna alla root e aggiorna il riferimento
cd ../../..
git add wp-content/themes/hello-theme-child
git commit -m "Updated theme to latest version"
git push
```

## 📁 Struttura Directory descrittiva

```
/
├── wp-admin/                  # WordPress core (non versionato)
├── wp-includes/               # WordPress core (non versionato)
├── wp-content/
│   ├── plugins/
│   │   └── custom-tools/      # 📌 Submodule
│   ├── themes/
│   │   └── hello-theme-child/ # 📌 Submodule
│   └── uploads/               # Media files (non versionato)
├── index.php                  # WordPress core
├── wp-config.php              # Configurazione (versionato se personalizzato)
├── .htaccess                  # Configurazione server
├── .gitignore                 # Esclusioni Git
└── README.md                  # Questa guida
```

## ⚙️ Configurazione

### File importanti da configurare:
- `wp-config.php` - Database e configurazioni WordPress
- `.htaccess` - Regole server web
- Plugin e tema tramite admin WordPress

### File ignorati da Git:
- Core WordPress (`wp-admin/`, `wp-includes/`)
- Upload e cache (`wp-content/uploads/`, cache varie)
- File di sistema (`.DS_Store`)

## Deploy

Per il deploy in produzione:
```bash
# Clone sul server
git clone --recursive https://github.com/f4rizzo/wp-deborahlocastro.git

# Configura wp-config.php per l'ambiente di produzione
# Configura database
# Importa contenuti se necessario
```

## Note Tecniche

- **WordPress Version**: Compatibile con WordPress 6.0+
- **PHP Version**: Richiede PHP 8.0+
- **Dipendenze**: Plugin e tema sono dependencies gestite tramite submodules

## Supporto

Per problemi o contributi ai singoli componenti, usa le repository specifiche:
- Issues plugin: [wp-plugin_custom-tools](https://github.com/f4rizzo/wp-plugin_custom-tools/issues)
- Issues tema: [wp-theme_hello-theme-child](https://github.com/f4rizzo/wp-theme_hello-theme-child/issues)





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

   _(Sostituisci il percorso con quello corretto se necessario. Evita spazi nei nomi delle cartelle o gestiscili con `\` oppure racchiudi il percorso tra virgolette.)_

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
