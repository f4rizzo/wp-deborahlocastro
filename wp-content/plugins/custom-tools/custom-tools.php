<?php

/**
 * Plugin Name: Custom Tools
 * Description: Plugin personalizzato con CPT, colonne admin, e funzionalità extra per il sito.
 * Author: Fabrizio
 * Version: 1.0
 */

// Sicurezza: impedisce l'accesso diretto al file
if (!defined('ABSPATH')) exit;

// Includi i file delle funzionalità
require_once plugin_dir_path(__FILE__) . 'inc/register-cpt.php';
require_once plugin_dir_path(__FILE__) . 'inc/admin-columns.php';

// require_once plugin_dir_path(__FILE__) . 'inc/acf-fields.php'; // se ti serve in futuro


/**
 * =======================================================
 * ACF Export in Json
 * =======================================================
 * Esporta le impostazioni ACF in un file JSON così da
 * archiviare tutto nella repo del progetto
 */
add_filter('acf/settings/save_json', function () {
    return plugin_dir_path(__FILE__) . 'assets/acf-json';
});

add_filter('acf/settings/load_json', function ($paths) {
    $paths[] = plugin_dir_path(__FILE__) . 'assets/acf-json';
    return $paths;
});



