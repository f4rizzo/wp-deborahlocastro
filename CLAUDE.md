@AGENTS.md

## Note per Claude Code

- Rispondi e scrivi commenti e commit **in italiano**.
- Avvia Claude Code da questa cartella (`app/public`, radice del repository). Per i comandi WP-CLI carica prima l'ambiente di Local con `source ../.envrc`.
- Prima di qualunque comando che scrive sul database usa la skill `/db-backup`, oppure esegui `export/export-db.sh`.
- `git commit` e `git push` solo se te li chiedo esplicitamente. Per i submodule usa la skill `/commit-submodule`.
- Dopo un import, una migrazione o un cambio di dominio usa la skill `/elementor-ripristino`.
- Le regole specifiche per PHP, SCSS e template Elementor sono in `.claude/rules/` e si caricano quando lavori su quei file.
