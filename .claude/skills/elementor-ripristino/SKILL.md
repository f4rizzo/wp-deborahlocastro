---
name: elementor-ripristino
description: Ripristina Elementor dopo un import, una migrazione o un cambio di dominio, quando la grafica o i font non si caricano.
disable-model-invocation: true
argument-hint: "[vecchio-url] [nuovo-url]"
---

Ripristino di Elementor dopo import, migrazione o cambio dominio. Argomenti: $ARGUMENTS

1. Carica l'ambiente: `source ../.envrc`, e verifica con `wp option get siteurl`.
2. Fai un backup con la skill `/db-backup`.
3. Se sono stati passati un vecchio e un nuovo URL:
   - `wp search-replace '<vecchio>' '<nuovo>' --all-tables --dry-run` e mostra il conteggio;
   - chiedi conferma, poi eseguilo senza `--dry-run`;
   - gli URL dentro `_elementor_data` hanno lo slash con escape (`http:\/\/…`): cercali anche in quella forma, oppure suggerisci *Elementor → Strumenti → Sostituisci URL*, che li gestisce entrambi.
4. Font: se `wp option get _elementor_local_google_fonts` punta a file che non esistono in `wp-content/uploads/elementor/google-fonts/css/`, elimina l'opzione (`wp option delete _elementor_local_google_fonts`).
5. Esegui `wp elementor flush-css`.
6. Chiedi a Fabrizio di aprire home, una pagina matrimonio e una pagina portfolio con Cmd+Shift+R. Poi verifica che `uploads/elementor/css/` e `uploads/elementor/google-fonts/` si siano ripopolate.
