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
