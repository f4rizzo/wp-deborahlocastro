---
name: commit-submodule
description: Commit e push di una modifica nel tema child o nel plugin custom-tools (submodule), poi aggiornamento del puntatore nel repository principale.
disable-model-invocation: true
argument-hint: "[tema|plugin] [messaggio di commit]"
---

Argomenti: $ARGUMENTS. Il primo indica il submodule (`tema` = `wp-content/themes/hello-theme-child`, `plugin` = `wp-content/plugins/custom-tools`), il resto è il messaggio.

1. Entra nel submodule e controlla `git status -sb`. Se è in detached HEAD, esegui `git checkout main`, verificando prima che non si perdano commit.
2. Mostra il `git diff` e proponi un messaggio in italiano, stile conventional (`feat:`, `fix:`, `refactor:`, `perf:`, `docs:`, `chore:`), se non è già stato dato.
3. Per i file PHP modificati esegui `php -l`.
4. Dopo la conferma: `git add` dei file pertinenti (mai `dist/`, mai file di sistema), `git commit`, `git push origin main`.
5. Torna in `app/public`: `git add <percorso-submodule>` e `git commit -m "chore: bump submodule <nome>"`, poi `git push` solo con conferma.
6. Mostra lo stato finale con `git submodule status`.
