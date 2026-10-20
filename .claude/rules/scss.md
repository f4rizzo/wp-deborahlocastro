---
paths:
  - "wp-content/themes/hello-theme-child/sass/**"
  - "wp-content/themes/hello-theme-child/style.css"
  - "wp-content/plugins/custom-tools/assets/scss/**"
---

# Regole SCSS / CSS del tema child

- Si scrive **solo** in `sass/` (tema) e `assets/scss/` (plugin custom-tools). `style.css` (tema) e `dist/css/style.css` vengono generati da VS Code *Live Sass Compiler* (impostazioni in `.vscode/settings.json`): non si modificano a mano.
- Il commento `/*! Theme Name … Template: hello-elementor */` in `sass/style.scss` deve restare, perché WordPress legge da lì l'intestazione del tema.
- Variabili (font, breakpoint) in `sass/util/_variables.scss`, mixin in `sass/util/_mixins.scss`. I media query si scrivono con i mixin, non con valori fissi.
- Stili degli shortcode in `sass/shortcodes/_<nome>.scss`, importati da `sass/shortcodes/_index.scss`.
- Colori e tipografia globali stanno nel Kit di Elementor: nel CSS non si duplicano.
- Senza VS Code si compila con le stesse impostazioni di Live Sass Compiler: `npx sass@1.77.8 --no-source-map --style=expanded sass/style.scss style.css` (stessa cosa per `dist/css/style.css`).
- Modular Gallery: la struttura e i valori (`max-width`, `gap`, `padding`, breakpoint, colonne) stanno nel plugin, in `custom-tools/assets/scss/`. L'unica fonte dei valori è `_modular-gallery-settings.scss`, letto anche dal PHP per gli attributi `sizes`. Lì le media query usano le variabili delle impostazioni, non i mixin del tema: il plugin non dipende dal tema. Nel tema (`sass/shortcodes/_modular-gallery.scss`) si impostano solo variabili di aspetto `--mg-*`.
- Il CSS compilato del plugin (`assets/css/modular-gallery.css`) si genera con Live Sass Compiler oppure con `npx sass@1.77.8 --no-source-map --style=expanded assets/scss/modular-gallery.scss assets/css/modular-gallery.css`.
