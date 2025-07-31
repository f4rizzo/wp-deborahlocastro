<?php

// Impedisci l'accesso diretto al file per motivi di sicurezza.
if (! defined('ABSPATH')) {
    exit; // Termina lo script se ABSPATH non è definito.
}

/**
 * =======================================================
 * 1. Funzione per la Custom Query di Elementor (Home Page)
 * =======================================================
 *
 * Questa funzione modifica la query predefinita di Elementor Loop Grid
 * per includere solo i progetti del Portfolio marcati come 'mostra_in_home'.
 *
 * Assicurati che il campo ACF 'mostra_in_home' sia di tipo 'True/False'
 * e che il suo "Valore di Ritorno" sia impostato su 'Boolean'.
 *
 * L'ID della query personalizzata in Elementor (sezione 'Query ID' del widget Loop Grid)
 * DEVE corrispondere esattamente a 'home_portfolio_query'.
 */
add_action('elementor/query/home_portfolio_query', 'filter_portfolio_for_home');

function filter_portfolio_for_home($query)
{
    // Aggiungi una meta query per filtrare in base al campo ACF 'mostra_in_home'.
    // La meta query è un array di array, che permette di combinare più condizioni.
    $query->set('meta_query', array(
        array(
            'key'     => 'mostra_in_home', // Lo slug (nome programmatico) esatto del tuo campo ACF 'Mostra in Home'.
            // Controlla in ACF > Gruppi di Campi > [Tuo Gruppo] > [Tuo Campo].
            'value'   => '1',             // Quando un campo True/False è spuntato (True), WordPress lo salva come '1'.
            // Quando non è spuntato (False), lo salva come '0' (o non lo salva affatto,
            // ma con type 'BOOLEAN' è meglio confrontare con '1').
            'compare' => '=',             // L'operatore di confronto: il valore del campo deve essere UGUALE a '1'.
            'type'    => 'BOOLEAN',       // Specifica il tipo di dati per un confronto preciso e affidabile.
            // Questo aiuta il database a interpretare '1' e '0' come booleani.
        ),
    ));

    // --- Impostazioni Aggiuntive per la Query (Opzionali) ---
    // Puoi impostare qui il numero di post per pagina e l'ordinamento se non li hai già
    // configurati direttamente nel widget Loop Grid di Elementor.
    // Le impostazioni della Loop Grid di solito prevalgono se sono state configurate lì.

    // Esempio: Limitare il numero di post a 12 (se non impostato in Elementor)
    // $query->set( 'posts_per_page', 12 );

    // Esempio: Ordinare i post per data in ordine decrescente (dal più recente al più vecchio)
    // $query->set( 'orderby', 'date' );
    // $query->set( 'order', 'DESC' );

    // Esempio: Ordinare i post per un campo numerico personalizzato in ordine crescente
    // $query->set( 'orderby', 'meta_value_num' ); // Per campi ACF numerici
    // $query->set( 'meta_key', 'ordine_personalizzato' ); // Lo slug del campo numerico ACF
    // $query->set( 'order', 'ASC' );
}
