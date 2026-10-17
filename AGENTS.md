# Deborah Lo Castro – sito WordPress

Sito portfolio della fotografa di matrimoni **Deborah Lo Castro**. È costruito con WordPress, Elementor Pro e ACF Pro e sviluppato in locale con Local. Per il layout il riferimento è il Figma del progetto.

Questo file è la guida al progetto per qualunque agente (Claude Code, Codex, Cursor…). Va tenuto aggiornato quando cambiano stack, ambienti o regole.

## Struttura del repository

La radice del repository è `app/public` del sito in Local, cioè la document root di WordPress. Il repository versiona **solo il codice custom**; quello che viene escluso è in `.gitignore`.

```
app/public/                         ← repo f4rizzo/wp-deborahlocastro
├── AGENTS.md, CLAUDE.md, README.md
├── .claude/                        ← impostazioni, regole e skill di Claude Code
├── export/
│   ├── export-db.sh                ← dump del DB in export/db-dumps/ (tiene gli ultimi 3)
│   ├── import-db.sh                ← import di un dump (DISTRUTTIVO: svuota il DB)
│   └── db-dumps/                   ← ignorato da git
└── wp-content/
    ├── mu-plugins/db-autobackup.php   ← dump automatico del DB, solo con WP_ENVIRONMENT_TYPE=local
    ├── plugins/custom-tools/          ← SUBMODULE f4rizzo/wp-plugin_custom-tools
    └── themes/hello-theme-child/      ← SUBMODULE f4rizzo/wp-theme_hello-theme-child
```

Non sono versionati: il core di WordPress, i plugin di terzi, il tema padre `hello-elementor`, `uploads/` e `wp-config.php`.

## Ambienti

| Ambiente | URL | Ruolo |
|---|---|---|
| **Sviluppo** | http://deborahlocastro.local | Si lavora **solo qui**. |
| Copia della cliente | http://deborahlocastro.preview.local | **Solo consultazione.** Import del sito online (export All-in-One WP Migration del 16/09/2026). |
| Online | https://deborahlocastro.site-preview.it | Hosting IONOS (PHP 8.1, MariaDB 10.11, prefisso tabelle `xvcb_`), gestito dalla cliente. |

- La copia della cliente contiene un `.git` ereditato, con una versione **vecchia** di `custom-tools`. **Non modificarla e non fare commit né push da lì.**
- Sviluppo e sito online sono andati avanti separati da febbraio 2026. Gli ID 646, 653 e 662 hanno contenuti diversi nei due database, quindi **non importare un DB sopra l'altro**. Il piano previsto per unirli: database online come base, codice da git, template "Header – Simple – Full Dark" (ID 609 nel dev) reimportato come JSON.

## Stack

- **WordPress 7.1**. In Local PHP 8.4, online PHP 8.1: **il codice deve funzionare con PHP 8.1**.
- **Elementor + Elementor Pro**: in dev 3.35.x / 3.35.x, online 4.2.4 / 3.31.2. Le versioni sono da allineare prima di andare in produzione.
- **ACF Pro + ACF Extended Pro.** Post type e gruppi di campi stanno in ACF JSON in `wp-content/plugins/custom-tools/assets/acf-json/` e si sincronizzano da *Campi personalizzati → Sync*.
- **Post type**: `matrimonio`, `portfolio`, più Side Project e Real Life (definiti in ACF JSON).
- **Shortcode di custom-tools**: `matrimonio_titolo`, `nomi_coniugi`, `info_matrimonio`, `portfolio_lightbox_item`, `modular_gallery`.
- **Query Elementor**: `home_portfolio_query`, che filtra il portfolio in home tramite il campo ACF `mostra_in_home`.
- **Formati immagine custom**: `portfolio_thumb`, `gallery_wide`, `gallery_tall`. Il campo ACF `gallery_image_size` dell'immagine sceglie il formato.
- **Font**: Anton (titoli), Literata (testo), Source Sans 3 (UI). I file sono in `hello-theme-child/assets/fonts/`.
- **Tema child**:
  - lo SCSS sta in `sass/` e viene compilato da VS Code *Live Sass Compiler* in `style.css` (versionato) e `dist/css/` (ignorato);
  - `elementor-templates/*.json` sono export dei template Elementor, tenuti come backup e riferimento.

## Comandi

WP-CLI funziona solo con il **sito avviato in Local** e con l'ambiente di Local caricato. Ci sono due modi:
- Local → *Site shell*;
- dalla cartella del repository: `source ../.envrc`.

```bash
export/export-db.sh                        # backup del DB, da fare PRIMA di ogni operazione rischiosa
export/import-db.sh [file.sql]             # ripristino (chiede conferma, svuota il DB)
wp elementor flush-css                     # rigenera il CSS di Elementor
wp search-replace 'VECCHIO' 'NUOVO' --all-tables --dry-run   # sempre prima con --dry-run
php -l percorso/file.php                   # controllo della sintassi
git submodule status                       # stato dei submodule
```

## Workflow git

1. Le modifiche al tema o al plugin si fanno **dentro il submodule**: commit e push lì, sul branch `main`.
2. Poi, nel repository principale, si aggiorna il puntatore: `git add wp-content/plugins/custom-tools` (o `wp-content/themes/hello-theme-child`), poi commit `chore: bump submodule …`.
3. Messaggi di commit in italiano, in stile conventional: `feat:`, `fix:`, `refactor:`, `perf:`, `docs:`, `chore:`.
4. Se un submodule è in *detached HEAD*: `git checkout main` prima di fare commit.

## Regole

- **Mai modificare** il core di WordPress, i plugin di terzi o il tema padre `hello-elementor`. Il codice custom va solo in `custom-tools` (logica, post type, shortcode) o in `hello-theme-child` (stile, template).
- **Mai versionare** `wp-config.php`, dump del DB, `uploads/`, credenziali o chiavi di licenza.
- **Prima di ogni operazione che scrive sul DB** (search-replace, import, cancellazioni, modifiche alle opzioni) si esegue `export/export-db.sh`.
- **Layout Elementor**: si modificano dall'editor. Non si modifica a mano `_elementor_data` nel DB.
- **Campi ACF**: si modificano dall'admin, che rigenera da solo il JSON. Non si modificano a mano i file in `acf-json/`.
- **CSS**: si scrive in `sass/`, mai direttamente in `style.css`, che è un file compilato.
- **PHP**:
  - `if (!defined('ABSPATH')) exit;` in testa a ogni file;
  - escape di ogni output (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`);
  - le funzioni che usano ACF vanno caricate solo se ACF è attivo (vedi `inc/dependencies.php`).

## Problemi noti

- **Dopo un import o un cambio di dominio la grafica sparisce**: i file CSS di Elementor in `uploads/elementor/css/` mancano, ma il DB li dà per presenti. Soluzione: *Elementor → Strumenti → Rigenera i file e i dati* (oppure `wp elementor flush-css`).
- **I Google Fonts non si caricano**: l'opzione `_elementor_local_google_fonts` punta a file in `uploads/elementor/google-fonts/` che non esistono. Soluzione: eliminare l'opzione; Elementor riscarica i font alla prima visita.
- **URL `http://dblc.local` rimasti** nei dati Elementor del dev (24 occorrenze, nomi di dominio di un vecchio ambiente). Vanno sostituiti con `http://deborahlocastro.local` usando *Elementor → Strumenti → Sostituisci URL*, che gestisce anche gli URL con escape JSON.
