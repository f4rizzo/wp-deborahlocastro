<?php


/**
 * =======================================================
 * Shortcode: [nomi_coniugi]
 * =======================================================
 * Mostra il nome dei coniugi
 * Campi ACF:
 * - nome_coniuge_1
 * - nome_coniuge_2
 * =======================================================
 */

function mostra_nomi_coniugi_shortcode()
{
    // Recupera i valori dai campi ACF
    $nome1 = get_field('nome_coniuge_1');
    $nome2 = get_field('nome_coniuge_2');

    // Controlla che entrambi i campi abbiano un valore
    if ($nome1 && $nome2) {
        // Restituisce la stringa formattata
        return esc_html($nome1) . ' e ' . esc_html($nome2);
    }

    // Se uno dei due manca, non restituisce nulla
    return '';
}
add_shortcode('nomi_coniugi', 'mostra_nomi_coniugi_shortcode');
