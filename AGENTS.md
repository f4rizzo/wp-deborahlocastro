# Deborah Lo Castro – sito WordPress

Sito portfolio della fotografa di matrimoni **Deborah Lo Castro**. È costruito con WordPress, Elementor Pro e ACF Pro e sviluppato in locale con Local. Per il layout il riferimento è il Figma del progetto.

Questo file è la guida al progetto per qualunque agente (Claude Code, Codex, Cursor…). Va tenuto aggiornato quando cambiano stack, ambienti o regole.

## Struttura del repository

La radice del repository è `app/public` del sito in Local, cioè la document root di WordPress. Il repository versiona **solo il codice custom**; quello che viene escluso è in `.gitignore`.

```
app/public/                         ← repo f4rizzo/wp-deborahlocastro
├── AGENTS.md, CLAUDE.md, README.md
├── .claude/                        ← impostazioni, regole e skill di Claude Code
├── docs/
│   └── ottimizzazioni-wordpress.md ← ottimizzazioni fatte e da fare, con le misure: tenerlo aggiornato
├── export/
│   ├── export-db.sh                ← dump del DB in export/db-dumps/ (tiene gli ultimi 3)
│   ├── import-db.sh                ← import di un dump (DISTRUTTIVO: svuota il DB)
│   ├── import-matrimonio.php       ← importa UN matrimonio dalla copia della cliente (wp eval-file)
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
| Copia della cliente | http://deborahlocastro.preview.local | **Solo consultazione.** La cliente ci carica i nuovi contenuti (es. matrimoni). Viene da un export All-in-One WP Migration del 16/09/2026 del vecchio hosting di anteprima. |
| Produzione | https://deborahlocastro.com | **Vecchio sito** (WordPress 5.8, WPBakery): non fa parte di questo progetto, il rifacimento non è ancora online. |

- L'anteprima su IONOS (`deborahlocastro.site-preview.it`, PHP 8.1, MariaDB 10.11, prefisso `xvcb_`) non è più raggiungibile (il DNS non risolve, verificato il 17/10/2026). Il requisito PHP 8.1 resta valido finché non si sceglie l'hosting definitivo.

- La copia della cliente contiene un `.git` ereditato, con una versione **vecchia** di `custom-tools`. **Non modificarla e non fare commit né push da lì.**
- Sviluppo e copia della cliente sono andati avanti separati da febbraio 2026. Gli ID 646, 653 e 662 hanno contenuti diversi nei due database, quindi **non importare un DB sopra l'altro**. I singoli matrimoni si portano in sviluppo con `export/import-matrimonio.php`, che rimappa gli ID (vedi Comandi). Il piano previsto per unire tutto: database della cliente come base, codice da git, template "Header – Simple – Full Dark" (ID 609 nel dev) reimportato come JSON.

## Stack

- **WordPress 7.1**. In Local PHP 8.4, online PHP 8.1: **il codice deve funzionare con PHP 8.1**.
- **Elementor + Elementor Pro**: in dev 4.3.5 / 4.3.2 (verificato il 21/10/2026), online 4.2.4 / 3.31.2. Le versioni sono da allineare prima di andare in produzione.
- **ACF Pro + ACF Extended Pro.** Post type e gruppi di campi stanno in ACF JSON in `wp-content/plugins/custom-tools/assets/acf-json/` e si sincronizzano da *Campi personalizzati → Sync*. ACF Extended scrive il JSON solo per i gruppi con la sincronizzazione automatica JSON attiva (`acfe_autosync: ["json"]`): va attivata su ogni gruppo nuovo.
- **Post type**: `matrimonio`, `portfolio`, `evento`, più Side Project e Real Life (definiti in ACF JSON).
- **Eventi (prova della pagina a blocchi per i matrimoni, branch `feature/eventi`)**: un evento si compila nell'editor a blocchi.
  - Barra laterale: gruppo ACF "Dettagli evento" con i nomi, che hanno le stesse chiavi dei matrimoni (`nome_coniuge_1/2`), più l'immagine in evidenza, che è la foto dell'hero.
  - Pagina: blocchi ACF `dlc/hero`, `dlc/testo`, `dlc/foto-larga`, `dlc/info`, `dlc/galleria` (`custom-tools/blocks/`, campi nei gruppi "Blocco: …"), riordinabili, disponibili solo negli eventi.
  - Ogni evento nuovo parte con la struttura di un matrimonio (`template` del post type in `custom-tools/inc/eventi.php`); cambiarla vale solo per gli eventi nuovi.
  - Resa: `hello-theme-child/single-evento.php`. Il markup delle sezioni viene dalle funzioni `dlc_sezione_*()` (`custom-tools/shortcodes/dlc-blocchi.php`), le stesse di `single-matrimonio.php`: le due pagine escono identiche.
  - Evento di prova: ID 1148, `/eventi/prova-tonnara-di-scopello/`, con i dati del matrimonio 733.
- **Shortcode di custom-tools**: `matrimonio_titolo`, `nomi_coniugi`, `info_matrimonio`, `portfolio_lightbox_item`, `modular_gallery`.
  - `modular_gallery` accetta parametri facoltativi: `layout` (`modulare` di default, `colonna`, `coppia`, `trio`, `griglia`, `masonry`), `colonne` / `colonne_tablet` / `colonne_mobile`, `breakpoint`, `proporzione`, `gap`, `larghezza`, `didascalie`, `nitide`. Valori ammessi ed esempi sono nel README del plugin. Senza parametri la galleria resta quella storica: modulare, due colonne da 1440px.
  - Il layout `modulare` corregge gli ingombri perché su desktop non restino buchi, senza cambiare l'ordine delle foto. Una `tall` senza foto accanto diventa standard. L'ultima foto non è mai `tall`: se le due prima sono standard affiancate, la `tall` va a destra e le due si impilano a sinistra. L'ultima foto, se resta da sola nella riga, diventa `wide`. Nel `trio`, in fondo, due foto si affiancano e una sola diventa `wide`.
- **Query Elementor**: `home_portfolio_query`, che filtra il portfolio in home tramite il campo ACF `mostra_in_home`.
- **Pipeline immagini** (`custom-tools/inc/images.php`, dettagli nel README del plugin): soglia 2048px, formati in WebP, metadati EXIF/XMP tolti dai file generati, formati custom `portfolio_thumb` (454) e `gallery_1200`. Ogni fotografia riceve al caricamento anche il ritaglio con l'orientamento opposto (verticale 3:4 / orizzontale 3:2): il campo ACF `gallery_image_size` dell'immagine (vuoto, `wide`, `tall`) sceglie l'ingombro nella galleria e la cliente può cambiarlo in ogni momento. Il campo `gallery_crop_position` (centro, sinistra/destra per le orizzontali, alto/basso per le verticali) sceglie quale parte della foto tenere: al salvataggio i ritagli vengono rifatti. `gallery_wide` e `gallery_tall` non vengono più generati.
- **Shortcode**: nomi e parametri non si cambiano (sono usati nei template Elementor).
- **Stili della `modular_gallery`**: stanno nel plugin, non nel tema. `custom-tools/assets/scss/modular-gallery.scss` si compila in `assets/css/modular-gallery.css`, che il plugin carica prima del CSS del tema.
  - Larghezze, gap, breakpoint e colonne di default stanno **solo** in `assets/scss/_modular-gallery-settings.scss`. Da quel file li legge lo SCSS, con `var(--mg-*, default)`, e li legge anche il PHP (`custom_tools_gallery_settings()`), che li usa per `sizes` e per i valori ammessi. Un valore si cambia lì, poi si ricompila.
  - Il tema imposta solo le variabili di aspetto (`--mg-didascalia-*`) in `sass/shortcodes/_modular-gallery.scss`.
- **Font**: Anton (titoli), Literata (testo), Source Sans 3 (UI). I file sono in `hello-theme-child/assets/fonts/`.
- **Tema child**:
  - lo SCSS sta in `sass/` e viene compilato da VS Code *Live Sass Compiler* in `style.css` (versionato) e `dist/css/` (ignorato). La configurazione è in `.vscode/settings.json` e compila solo i file elencati in `includeItems`: `style.scss`, `dlc-blocks.scss` del tema e `modular-gallery.scss` del plugin;
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
wp media regenerate --skip-delete --yes    # rigenera formati, WebP e ritagli (backup prima)

# import di un matrimonio dalla copia della cliente (sito sorgente avviato in Local)
wp eval-file export/import-matrimonio.php ../../../deborahlocastropreview/app/public <id-o-slug> dry-run
wp eval-file export/import-matrimonio.php ../../../deborahlocastropreview/app/public <id-o-slug>
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
- **CSS**: si scrive in `sass/` del tema o in `assets/scss/` del plugin, mai direttamente nei `.css` compilati. Senza VS Code si compila con le stesse impostazioni di Live Sass Compiler: `npx sass@1.77.8 --no-source-map --style=expanded sass/style.scss style.css` (e `dist/css/style.css`) nel tema, `npx sass@1.77.8 --no-source-map --style=expanded assets/scss/modular-gallery.scss assets/css/modular-gallery.css` nel plugin.
- **Documentazione**: ogni modifica a plugin, tema o script va riportata nei README e in questo file; le ottimizzazioni in `docs/ottimizzazioni-wordpress.md`.
- **PHP**:
  - `if (!defined('ABSPATH')) exit;` in testa a ogni file;
  - escape di ogni output (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`);
  - le funzioni che usano ACF vanno caricate solo se ACF è attivo (vedi `inc/dependencies.php`).

## Problemi noti

- **Dopo un import o un cambio di dominio la grafica sparisce**: i file CSS di Elementor in `uploads/elementor/css/` mancano, ma il DB li dà per presenti. Soluzione: *Elementor → Strumenti → Rigenera i file e i dati* (oppure `wp elementor flush-css`).
- **I Google Fonts non si caricano**: l'opzione `_elementor_local_google_fonts` punta a file in `uploads/elementor/google-fonts/` che non esistono. Soluzione: eliminare l'opzione; Elementor riscarica i font alla prima visita.
- **`wp media regenerate` senza `--skip-delete`** cancella i vecchi formati JPEG: le copie HTML di riserva che Elementor salva in `post_content` (pagine 39, 45 e template 383, 428, 554) puntano ancora a quei file. Usare sempre `--skip-delete`.
- **In zsh** una lista di ID in una variabile non si divide da sola: `wp media regenerate ${=IDS}`, non `$IDS`.
- **ACF Pro 6.8 + ACF Extended Pro 0.9.2.3**: i campi checkbox in admin davano l'errore fatale `Cannot access private property acf_field_checkbox::$_values` (es. *ACF → Strumenti*). Correttivo temporaneo in `custom-tools/inc/compat-acfe.php`, che rimette il render del checkbox di ACF. Si toglie quando si aggiorna ACFE.
- **URL `http://dblc.local` rimasti** nei dati Elementor del dev (24 occorrenze, nomi di dominio di un vecchio ambiente). Vanno sostituiti con `http://deborahlocastro.local` usando *Elementor → Strumenti → Sostituisci URL*, che gestisce anche gli URL con escape JSON.
