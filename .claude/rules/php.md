---
paths:
  - "wp-content/plugins/custom-tools/**/*.php"
  - "wp-content/themes/hello-theme-child/**/*.php"
  - "wp-content/mu-plugins/*.php"
---

# Regole PHP (WordPress)

- Il codice deve essere compatibile con **PHP 8.1** (requisito minimo finché non si sceglie l'hosting definitivo), anche se in Local gira 8.4. Per il controllo della sintassi c'è anche il PHP 8.2 di Local: `"$HOME/Library/Application Support/Local/lightning-services/php-8.2.29+0/bin/darwin-arm64/bin/php" -l <file>`.
- In testa a ogni file: `if (!defined('ABSPATH')) exit;`
- Escape di ogni output: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`. Sanitizzazione di ogni input.
- Le funzioni ACF (`get_field()` ecc.) si usano solo nei moduli caricati quando ACF è attivo: vedi `custom-tools/inc/dependencies.php` e il bootstrap su `plugins_loaded` in `custom-tools.php`.
- Un nuovo modulo di `custom-tools` va in `inc/` (logica) o `shortcodes/` (shortcode) e si registra nel bootstrap di `custom-tools.php`.
- Nomi di funzioni con prefisso o dentro closure, per evitare collisioni.
- Commenti in italiano, nello stile a blocchi `=====` già usato nei file.
- Dopo ogni modifica: `php -l <file>`.
- Shortcode: nomi e parametri esistenti non si cambiano (sono usati nei template Elementor). Le funzioni pubbliche già documentate (es. `get_gallery_thumb_url()`) restano con la stessa firma.
- Immagini: formati e ritagli si gestiscono in `custom-tools/inc/images.php`; ogni modifica va riportata nel README del plugin e in `docs/ottimizzazioni-wordpress.md`.
