## WordPress Debora Lo Castro – Progetto Modulare

Questo repository contiene **solo le personalizzazioni** del sito WordPress sviluppato in locale con Local, escluse le parti core e i plugin premium.

Progetto WordPress con architettura modulare che separa i componenti custom in repository indipendenti per un migliore controllo di versioning e sviluppo collaborativo.

## 📁 Struttura del progetto versionato

```
wp-content/                    ← repository principale
├── plugins/
│   └── custom-tools/          ← submodule
└── themes/
    └── hello-theme-child/     ← submodule
```

## 🏗️ Architettura

Il progetto è suddiviso in tre repository separate:

- **🌐 Core Project** (questa repo): Configurazioni WordPress, file base, utilities e struttura generale
- **🔧 Custom Plugin**: [custom-tools](https://github.com/f4rizzo/wp-plugin_custom-tools) - Plugin personalizzato per funzionalità specifiche
- **🎨 Custom Theme**: [hello-theme-child](https://github.com/f4rizzo/wp-theme_hello-theme-child) - Tema child personalizzato

## 📋 Documentazione

- [`AGENTS.md`](AGENTS.md) — guida al progetto per gli agenti (struttura, ambienti, regole)
- [`docs/ottimizzazioni-wordpress.md`](docs/ottimizzazioni-wordpress.md) — ottimizzazioni fatte e da fare, con le misure
- [`wp-content/plugins/custom-tools/README.md`](wp-content/plugins/custom-tools/README.md) — plugin: shortcode, pipeline immagini, campi ACF

## 🎨 Design

- **Figma – file di design (editor)**: [Deborah-LoCastro](https://www.figma.com/design/XyU3jnonUAPe2kHesnn3ZJ/Deborah-LoCastro?node-id=213-4)
- **Figma – prototipo**: [Deborah-LoCastro](https://www.figma.com/proto/XyU3jnonUAPe2kHesnn3ZJ/Deborah-LoCastro?node-id=213-4&starting-point-node-id=213%3A4)



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
├── docs/                      # Documentazione (ottimizzazioni WordPress)
├── export/                    # Backup/ripristino DB, import matrimoni
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

### Tuning prestazioni (fuori da Git — viaggia con l'Export di Local)

Modifiche applicate all'ambiente locale, da rifare se si parte da un `git clone`
puro invece che da un Import di Local:

- **`wp-config.php`** — le pagine Elementor salvano l'intero layout JSON a ogni
  revisione; teniamo le revisioni sotto controllo:
  ```php
  define( 'WP_POST_REVISIONS', 5 );
  define( 'AUTOSAVE_INTERVAL', 120 );
  ```
- **`../conf/mysql/my.cnf.hbs`** (template MySQL di Local) —
  `innodb_buffer_pool_size` alzato da `32M` a `256M`; riavviare il sito in Local
  per applicarlo.

## Deploy

Per il deploy in produzione:
```bash
# Clone sul server
git clone --recursive https://github.com/f4rizzo/wp-deborahlocastro.git

# Configura wp-config.php per l'ambiente di produzione
# Configura database
# Importa contenuti se necessario

# Applica formati, WebP e ritagli di custom-tools alle immagini già caricate
wp media regenerate --skip-delete --yes
```

Prima del lancio vedi le voci "Da fare" in
[`docs/ottimizzazioni-wordpress.md`](docs/ottimizzazioni-wordpress.md) (page cache,
font locali, licenza Elementor Pro, sfondi Elementor).

## Note Tecniche

- **WordPress Version**: Compatibile con WordPress 6.0+
- **PHP Version**: Richiede PHP 8.0+
- **Dipendenze**: Plugin e tema sono dependencies gestite tramite submodules

## Supporto

Per problemi o contributi ai singoli componenti, usa le repository specifiche:
- Issues plugin: [wp-plugin_custom-tools](https://github.com/f4rizzo/wp-plugin_custom-tools/issues)
- Issues tema: [wp-theme_hello-theme-child](https://github.com/f4rizzo/wp-theme_hello-theme-child/issues)





## 🗃️ Gestione Backup Database

Due meccanismi complementari, entrambi legati **all'uso del progetto** e non alla
macchina. Tutti i dump finiscono in `export/db-dumps/` (nome `dump-<YYYYMMDD_HHMMSS>.sql`,
cartella esclusa da Git) e vengono ruotati tenendo solo gli **ultimi 3**.

### 🔄 Backup automatico — `wp-content/mu-plugins/db-autobackup.php`

mu-plugin (si auto-attiva, nessuna installazione) che esporta il DB via **WP-Cron**
quando il sito è in uso in Local:

- a ogni visita (front o admin) controlla da quanto manca un backup; se è passato più
  di `DB_AUTOBACKUP_INTERVAL` (default **24h**) pianifica un evento singolo che parte
  ~20s dopo, senza rallentare la pagina;
- dump in **PHP puro** (via `$wpdb`): nessun binario esterno, nessun percorso assoluto;
- **progetto fermo = zero esecuzioni.** Nessun `crontab`, niente da configurare al
  cambio di Mac;
- attivo solo se `wp_get_environment_type() === 'local'`.

Parametri sovrascrivibili in `wp-config.php`:

```php
define('DB_AUTOBACKUP_INTERVAL', DAY_IN_SECONDS); // frequenza massima
define('DB_AUTOBACKUP_KEEP', 3);                  // dump da conservare
define('DB_AUTOBACKUP_DISABLE', true);            // disattiva del tutto
```

### 🖐️ Backup manuale on-demand — `export/export-db.sh`

Da lanciare prima di una modifica importante. Portabile: carica l'ambiente da
`app/.envrc` (rigenerato da Local a ogni avvio) e usa **WP-CLI**, che ricava nome DB,
credenziali e socket da `wp-config.php` + `MYSQL_HOME`.

```bash
./export/export-db.sh
```

### ♻️ Ripristino — `export/import-db.sh`

Senza argomenti importa il dump più recente in `export/db-dumps/`; altrimenti si passa
il percorso di un `.sql`. Prima di importare **azzera il database** (`wp db reset`) per
evitare tabelle orfane, poi chiede conferma.

```bash
./export/import-db.sh                      # ultimo dump disponibile
./export/import-db.sh percorso/al/dump.sql # file specifico
```

### 💍 Import di un matrimonio dalla copia della cliente — `export/import-matrimonio.php`

La cliente carica i nuovi matrimoni sulla sua copia (`deborahlocastro.preview.local`).
I due database **non si possono sovrapporre** (gli ID non coincidono), quindi il
singolo matrimonio si importa con questo script, che gira nel sito di sviluppo e
legge la copia della cliente **in sola lettura**:

```bash
source ../.envrc
./export/export-db.sh                                  # backup prima di tutto
wp eval-file export/import-matrimonio.php ../../../deborahlocastropreview/app/public 615 dry-run
wp eval-file export/import-matrimonio.php ../../../deborahlocastropreview/app/public 615
```

- primo argomento: la cartella `app/public` del sito sorgente (avviato in Local);
  secondo: ID o slug del matrimonio; `dry-run` mostra cosa farebbe senza scrivere;
- copia gli **originali** delle foto: formati, WebP e ritagli vengono rigenerati con
  le regole attuali di `custom-tools`, come per un nuovo caricamento;
- ricrea il post con nuovi ID e rimappa le immagini nei campi ACF (immagine,
  galleria, immagine in evidenza), testi alternativi e ingombro compresi;
- ripetibile: se il matrimonio è già stato importato si ferma; le foto già
  importate vengono riusate.

> ⚠️ Gli script `.sh` richiedono il **sito avviato in Local** e `bash` (compatibili
> con la 3.2 di macOS). Testati su macOS Apple Silicon.

> 🧹 In arrivo da una vecchia configurazione? Rimuovi l'eventuale cron di sistema
> obsoleto con `crontab -e` (o `crontab -r` per azzerarlo).




## Info Git

## 💻 Workflow con VS Code

### Setup iniziale
1. Apri il progetto principale in VS Code
2. Installa l'estensione **GitLens** per una migliore gestione Git
3. I submodules appariranno come cartelle separate con il loro stato Git

### Lavorare sui componenti

#### Modifiche al Plugin
1. Naviga in `wp-content/plugins/custom-tools/`
2. Modifica i file del plugin
3. Usa il pannello **Source Control** di VS Code:
   - Vedrai i cambiamenti nel plugin sotto la sezione del submodule
   - Stage e committa i file del plugin
   - Push direttamente dalla repo del plugin

#### Modifiche al Tema  
1. Naviga in `wp-content/themes/hello-theme-child/`
2. Modifica i file del tema
3. Usa il pannello **Source Control** di VS Code:
   - Stage e committa i file del tema
   - Push direttamente dalla repo del tema

#### Aggiornamento del progetto principale
Dopo ogni push di plugin/tema, VS Code mostrerà:
- Il file `.gitmodules` o le directory dei submodules come "modificate"
- Questo indica che il riferimento al commit è cambiato

Per aggiornare:
1. Stage le modifiche ai submodules nel progetto principale
2. Commit con messaggio tipo: `"Updated plugin to version X.X"`
3. Push del progetto principale

### Comandi da terminale integrato (opzionale)

```bash
# Aggiorna tutti i submodules alle ultime versioni
git submodule update --remote

# Verifica stato di tutti i submodules  
git submodule status

# Clone per nuovi collaboratori
git clone --recursive https://github.com/f4rizzo/wp-deborahlocastro.git
```

### Gestione branches nei submodules

Se lavori su feature branches:
```bash
# Nel submodule del plugin
cd wp-content/plugins/custom-tools
git checkout -b feature/nuova-funzionalità
# ... sviluppa e committa
git push origin feature/nuova-funzionalità

# Nel progetto principale, il submodule punterà al nuovo branch
git add wp-content/plugins/custom-tools
git commit -m "Plugin: working on new feature branch"
```

### Tips VS Code
- Usa **Ctrl/Cmd + Shift + G** per accedere rapidamente al Source Control
- GitLens mostra l'autore e la data dell'ultimo commit per ogni riga
- Il terminale integrato permette comandi Git specifici quando necessario
- Ogni submodule ha il suo stato Git indipendente nel pannello Source Control


### Risolvere l’errore fatal: You are not currently on a branch

Se provi a fare push direttamente dalla modalità detached HEAD riceverai un errore come questo:

```
fatal: You are not currently on a branch.
```

#### Per risolvere:
```bash
# 1.	Controlla i branch disponibili
git branch -a

# 2.	Passa a quello corretto
git checkout main

#	3.	Poi fai commit e push normalmente.
cd <repo-principale>
git add path/del/submodule
git commit -m "Aggiornato submodule al nuovo commit"
git push
```


## 📂 Gestione dei file ACF (Advanced Custom Fields)

Per mantenere sincronizzati i campi personalizzati tra ambiente locale e produzione, utilizziamo la cartella `assets/acf-json` all'interno del plugin ***custom-tools***.

### 🔄 Come importare i file ACF
1. Accedi al **Backoffice di WordPress**.
2. Vai su **Custom Fields > Field Groups**.
3. Se esistono differenze tra i file JSON e i campi presenti nel database:
   - Vedrai la voce **"Sync available"** accanto ai gruppi di campi.
4. Clicca su **Sync** per importare i file dal JSON al database di WordPress.
5. Ora i campi sono disponibili e modificabili normalmente dal pannello ACF.

### 📤 Esportazione automatica
- Ogni volta che modifichi un gruppo di campi dal backoffice, ACF salva automaticamente un file JSON aggiornato nella cartella `assets/acf-json`.

### ✅ Buone pratiche
- Versionare la cartella `assets/acf-json` con Git per mantenere i campi sincronizzati tra sviluppatori e ambienti.
- Non modificare direttamente i file JSON: gestire sempre i campi dal backoffice di WordPress.