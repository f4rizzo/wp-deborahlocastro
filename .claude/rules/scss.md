---
paths:
  - "wp-content/themes/hello-theme-child/sass/**"
  - "wp-content/themes/hello-theme-child/style.css"
---

# Regole SCSS / CSS del tema child

- Si scrive **solo** in `sass/`. `style.css` (tema) e `dist/css/style.css` vengono generati da VS Code *Live Sass Compiler* (impostazioni in `.vscode/settings.json`): non si modificano a mano.
- Il commento `/*! Theme Name … Template: hello-elementor */` in `sass/style.scss` deve restare, perché WordPress legge da lì l'intestazione del tema.
- Variabili (font, breakpoint) in `sass/util/_variables.scss`, mixin in `sass/util/_mixins.scss`. I media query si scrivono con i mixin, non con valori fissi.
- Stili degli shortcode in `sass/shortcodes/_<nome>.scss`, importati da `sass/shortcodes/_index.scss`.
- Colori e tipografia globali stanno nel Kit di Elementor: nel CSS non si duplicano.
