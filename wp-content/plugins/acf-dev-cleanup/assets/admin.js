jQuery(document).ready(function ($) {
  "use strict";

  // Gestione della conferma di pulizia
  $("#cleanup-form").on("submit", function (e) {
    // Controlla se la checkbox di backup è stata spuntata
    if (!$("#confirm-backup").is(":checked")) {
      alert(acfCleanup.backup_required);
      e.preventDefault(); // Impedisce l'invio del form
      return;
    }

    // Mostra il popup di conferma
    if (!confirm(acfCleanup.confirm_cleanup)) {
      e.preventDefault(); // Impedisce l'invio del form se l'utente annulla
    }
  });

  // Aggiungi un piccolo fix per il form di analisi che non è un vero form,
  // questo codice non è strettamente necessario se il tuo form punta già alla pagina giusta.
  // L'abbiamo gestito lato PHP, quindi questo JS si concentra sulla pulizia.
});
