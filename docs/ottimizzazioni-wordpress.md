# Ottimizzazioni WordPress

Registro delle ottimizzazioni del sito: cosa è già fatto (nel codice o nella configurazione) e cosa resta da fare, con chi lo deve fare e dove.
Va aggiornato ogni volta che si chiude o si aggiunge una voce.

Ultimo aggiornamento: 17/10/2026.

## Misure di riferimento

Pagina di test: il matrimonio *Tonnara di Scopello* (52 foto in galleria), importato dalla copia della cliente con `export/import-matrimonio.php`.
Le misure sono prese con Chrome headless, con cache vuota e scroll completo della pagina.

| Schermo | Prima (preview cliente) | Dopo (dev) | Note |
|---|---|---|---|
| Smartphone 390px, 3x | 9,7 MB, 5,6 MB al primo caricamento | **4,6 MB**, 0,8 MB al primo caricamento | `tall` orizzontali restano orizzontali, come prima |
| Laptop 1280px, 2x | 10,9 MB, foto **sottodimensionate** (0,52) | 8,7 MB, foto nitide (0,9) | una colonna da 1138px a 2x |
| Desktop 1536px, 1x | 9,8 MB | **3,2 MB** | |
| Desktop 1680px, 2x | 10,9 MB | **5,7 MB** | |
| Testata matrimonio (LCP) | 2,5 MB (JPEG originale) | 345 KB (WebP 2048) | precaricata |
| Home, 1536px 1x | 8,1 MB | 3,8 MB | il resto sono sfondi Elementor (vedi sotto) |

## Fatto

### Immagini — plugin `custom-tools`

Dettagli in `wp-content/plugins/custom-tools/README.md`, sezione 3.

- [x] Soglia "big image" a 2048px: le foto da fotocamera (5472px, 4–7MB) non arrivano più ai visitatori.
- [x] Formati intermedi e copia `-scaled` in WebP, qualità 82: un `large` passa da 56KB (JPEG) a 27KB.
- [x] Metadati EXIF/XMP tolti dai file generati: erano fino a 21KB a file, e una miniatura passa da 23KB a 2,6KB.
- [x] Formati ridotti a quelli usati. Via `2048x2048` (doppione della `-scaled`), `gallery_wide` (1480, doppione di 1536) e `gallery_tall` (0x1200). Aggiunto `gallery_1200`, la misura esatta per la galleria e per gli smartphone 3x.
- [x] Ritagli generati al caricamento per ogni fotografia: verticale 3:4 per le orizzontali, orizzontale 3:2 per le verticali. La cliente può cambiare l'ingombro in ogni momento.
- [x] `[modular_gallery]` con `srcset`/`sizes`, `width`/`height` (niente salti di layout) e `<picture>` dinamico: ritaglio sul desktop ≥1440px (due colonne), foto intera nel suo orientamento sotto i 1440px.
- [x] Lightbox sul formato 2048 invece dell'originale: 80MB → 8,8MB per l'intera galleria sfogliata.
- [x] `[portfolio_lightbox_item]` con `srcset` e `alt` reale.
- [x] Testata del matrimonio: formato 2048 al posto dell'originale, e precaricamento (`fetchpriority="high"`).
- [x] Galleria: 2 query per caricare post e meta di tutte le foto, invece di 2 per foto.
- [x] `alt` di riserva (il titolo del matrimonio) quando la cliente non lo compila.
- [x] Libreria media del sito di sviluppo rigenerata con le nuove regole (`wp media regenerate --skip-delete`).

### Tema `hello-theme-child`

- [x] La versione di `style.css` è la data di modifica del file: dopo ogni compilazione SCSS i browser scaricano il CSS nuovo.

### Configurazione (fuori da Git)

- [x] `WP_POST_REVISIONS` 5, `AUTOSAVE_INTERVAL` 120 in `wp-config.php` (vedi README).
- [x] `innodb_buffer_pool_size` 256M nel MySQL di Local.

## Da fare

### Nell'editor Elementor (non si fanno da codice: `_elementor_data` non si modifica a mano)

- [ ] **Sfondi della home con URL fisso al JPEG originale**: `section-realLife.jpg` (1,1MB), `section-realWeddings.jpg` (0,8MB), `main.jpg` (0,55MB), `bc-14-1-1.jpg` (0,35MB). Vanno riselezionati dalla Libreria media dopo la rigenerazione, oppure sostituiti con versioni WebP. Da soli valgono circa 2,8MB su 3,8MB della home.
- [ ] **Loop "Matrimonio Item's Template" (ID 132)**: il widget *Immagine in evidenza* non ha una dimensione impostata e carica l'immagine piena (2048px) in un box da 570px. Impostare `large` (1024).
- [ ] **Template "Matrimonio - Single Post" (ID 164)**: l'immagine hero usa `1536x1536`. Va bene, ma Elementor non aggiunge `sizes`: verificare dopo il passaggio a Elementor 4.
- [ ] Immagini PNG grandi in home (`Principale.jpg` 964KB, `Anteprima-Portfolio.png` 442KB, `fornasetti-1.png` 430KB, `Frame-*`): dopo la rigenerazione i widget prendono i WebP solo se la dimensione scelta nel widget non è "Completa".
- [ ] Loghi PNG da 8–10MB nella copia della cliente (`DeborahLoCastro_Emblema_*`, `LogoOvale_BIG`): sostituirli con SVG o PNG/WebP da poche decine di KB.

### Configurazione WordPress / Elementor

- [ ] **Font**: Elementor carica i Google Fonts da remoto (`elementor_google_font` = 1, `elementor_local_google_fonts` = 0). Attivare *Elementor → Impostazioni → Prestazioni → Carica Google Fonts in locale* e il precaricamento. È la causa del CLS residuo (0,02–0,03) sui testi della testata.
- [ ] Font self-hosted nel tema (`assets/fonts/`, 27MB di TTF): nessun CSS li usa. Decidere se servirli come WOFF2 (sottoinsieme latino) al posto dei Google Fonts, oppure rimuoverli dalla repo.
- [ ] Elementor: attivare *Element Caching* (`elementor_element_cache_ttl` ora `disable`).
- [ ] Campo ACF `gallery_image_size`: la location è solo `image/jpeg`. Se la cliente carica WebP o HEIC il campo non compare. Da allargare dall'admin di ACF (non a mano nel JSON).
- [ ] Possibile miglioramento: un campo "punto di ritaglio" (sinistra / centro / destra) per scegliere quale porzione mostrare nei ritagli, oggi sempre centrati.

### Plugin

- [ ] **`elementor-pro-activator` (GPL Times)**: attivatore non ufficiale. È un rischio di sicurezza e blocca gli aggiornamenti di Elementor Pro. Serve una licenza Elementor Pro ufficiale.
- [ ] **Versioni Elementor**: dev 3.35.6 / Pro 3.35.1, copia cliente 4.3.5 / Pro 3.31.2. Allinearle prima della messa online (il core 4.x con il Pro 3.31 può rompere i widget).
- [ ] Disattivare a regime: All-in-One WP Migration + Unlimited Extension, Regenerate Thumbnails (c'è `wp media regenerate`), WordPress Importer.
- [ ] Image Optimizer (Elementor): installato e configurato ma inattivo. Con la pipeline WebP di `custom-tools` non serve; se lo si attiva, la conversione WebP va lasciata a uno solo dei due.
- [ ] ACF Extended Pro: verificare se serve davvero (i JSON hanno impostazioni `acfe_*`, nessuna funzione ACFE nel codice).
- [ ] Rank Math (attivo nella copia cliente, non in dev): decidere il plugin SEO prima del lancio.

### Hosting / produzione

- [ ] **Page cache**: nessuna cache di pagina. Sul vecchio sito il tempo di prima risposta è 1,5–3,6s. Serve una cache (del server o un plugin) prima del lancio.
- [ ] Compressione Brotli/Gzip e header `Cache-Control` lunghi per `/wp-content/uploads/` (i nomi dei file cambiano a ogni nuovo upload).
- [ ] Verificare sull'hosting che l'editor immagini supporti WebP (`wp eval 'var_dump(wp_image_editor_supports(["mime_type"=>"image/webp"]));'`); altrimenti i formati restano JPEG (funziona lo stesso, pesano di più).
- [ ] Dopo il trasferimento: `wp media regenerate --skip-delete --yes` per applicare formati e ritagli alle foto caricate nel frattempo dalla cliente.
- [ ] Il vecchio sito in produzione (deborahlocastro.com) è su WordPress 5.8.18, senza aggiornamenti di sicurezza: tenerlo il meno possibile.

### Contenuti importati dal vecchio sito

- [ ] **Risoluzione delle foto**: sul vecchio sito molte foto sono caricate a **1024px** (es. `csw-*`, `newyorktravel-*`, `sicilian-wedding-verdura-resort-*`). Nella galleria a colonna singola (fino a 1138px) e sugli schermi retina vengono ingrandite e perdono nitidezza. Nessun import può recuperare la risoluzione: servono i file originali dall'archivio della fotografa, da ricaricare sostituendo quelli importati.

### Possibili ottimizzazioni future

- [ ] AVIF al posto di WebP: Imagick in Local lo supporta e pesa circa il 20% in meno, ma la codifica è lenta e il supporto sull'hosting va verificato.
- [ ] Desktop 1x a due colonne: le foto standard usano il 768 per una cella da 564px (rapporto 1,36). Un formato da 600px ridurrebbe il peso di circa 270KB a pagina, al costo di un file in più per ogni foto.
