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
require_once plugin_dir_path(__FILE__) . 'shortcodes/matrimonio.php';
require_once plugin_dir_path(__FILE__) . 'shortcodes/modular-gallery.php';

// require_once plugin_dir_path(__FILE__) . 'inc/acf-fields.php'; // se ti serve in futuro


/**
 * =======================================================
 * ACF Export in Json
 * =======================================================
 * Esporta le impostazioni ACF in un file JSON così da
 * archiviare tutto nella repo del progetto
 */
add_filter('acf/settings/save_json', function () {
    $path = plugin_dir_path(__FILE__) . 'assets/acf-json';

    if (!file_exists($path)) {
        mkdir($path, 0755, true);
    }

    return $path;
});

add_filter('acf/settings/load_json', function ($paths) {
    $paths[] = plugin_dir_path(__FILE__) . 'assets/acf-json';
    return $paths;
});


/**
 * ======================================================================================
 * 1. Registra nuovi formati immagine
 * ======================================================================================
 *
 * Questi formati vengono usati per ottimizzare il caricamento
 * delle immagini nelle gallerie:
 * - portfolio_thumb: thumbnail da 454px per anteprime piccole.
 * - portfolio_large: immagini ottimizzate fino a 1500px di larghezza.
 */

add_action('after_setup_theme', function () {

    // Thumbnail per piccole anteprime (mantiene proporzioni, larghezza max 454px)
    add_image_size('portfolio_thumb', 454, 0, false); // true = crop / false = proporzioni originali

    // Formato ottimizzato per immagini large (max 1500px di larghezza)
    add_image_size('portfolio_large', 1500, 0, false); // Mantiene proporzioni
});


// Mostrare i formati nel Media Picker
add_filter('image_size_names_choose', 'aggiungi_dimensioni_personalizzate');
function aggiungi_dimensioni_personalizzate($sizes)
{
    return array_merge($sizes, [
        'portfolio_thumb' => 'Portfolio Thumb - 454 x 0 (454px)',
        'portfolio_large'  => 'Portfolio Large - 1500px',
    ]);
}



/**
 * ======================================================================================
 * 2. Shortcode per mostrare una thumb cliccabile che apre l'immagine originale in Fancybox
 * ======================================================================================
 *
 * Questo shortcode viene utilizzato all'interno del Loop Item Template di Elementor Pro
 * (via widget "Shortcode") e genera il markup HTML necessario per:
 *
 *  - Mostrare l'immagine 'immagine_anteprima_portfolio' (in formato custom, es. 'portfolio_thumb')
 *  - Aprire in lightbox nativa (Fancybox 3) l'immagine 'immagine_originale_portfolio'
 *  - Consentire la navigazione tra le immagini con lo stesso gruppo Fancybox
 *
 * Requisiti:
 *  - Elementor Pro (per Loop Grid e supporto lightbox nativo)
 *  - ACF Pro (con i campi immagine impostati per restituire un ARRAY)
 *  - Le immagini devono essere filtrate tramite la query Elementor con meta_query su 'mostra_in_home'
 *
 * Come usare:
 *  - Inserisci lo shortcode [portfolio_lightbox_item] nel widget "Shortcode" all'interno del Loop Item Template
 *  - Elementor eseguirà la funzione PHP e inietterà il markup dinamico nel DOM
 */

function portfolio_lightbox_shortcode()
{
    // Recupera i campi ACF immagine come ARRAY
    $img_anteprima = get_field('immagine_anteprima_portfolio');
    $img_originale = get_field('immagine_originale_portfolio');

    // Verifica che entrambi i campi siano presenti
    if (!$img_anteprima || !$img_originale) {
        return ''; // Se uno dei due è vuoto, non genera nulla
    }

    // === 1. Specifica la size registrata via add_image_size (thumb personalizzata) ===
    $thumb_size = 'portfolio_thumb'; // Cambia con la tua size, es: 'portfolio_thumb_due'

    // === 2. Estrai l’URL corretto dal campo immagine (array) ===
    $url_anteprima = is_array($img_anteprima) && isset($img_anteprima['sizes'][$thumb_size])
        ? $img_anteprima['sizes'][$thumb_size]
        : (is_array($img_anteprima) ? $img_anteprima['url'] : $img_anteprima);

    $url_originale = is_array($img_originale) ? $img_originale['url'] : $img_originale;

    // === 3. Crea l'output HTML con link in lightbox e immagine preview ===
    ob_start();
?>
    <a href="<?php echo esc_url($url_originale); ?>"
        data-elementor-open-lightbox="yes"
        data-elementor-lightbox-slideshow="portfolio"
        rel="lightbox">
        <img src="<?php echo esc_url($url_anteprima); ?>" alt="Anteprima Portfolio" />
    </a>
<?php
    return ob_get_clean();
}

// === 4. Registra lo shortcode ===
// Questo rende utilizzabile [portfolio_lightbox_item] ovunque nel sito
add_shortcode('portfolio_lightbox_item', 'portfolio_lightbox_shortcode');



/**
 * =========================================================
 * Aggiunge la classe `.large` alle immagini marcate con meta large = true
 * =========================================================
 *
 * Questo codice intercetta l'output del widget Gallery di Elementor
 * e aggiunge la classe `large` alle immagini che hanno
 * il campo ACF (meta) `large_gallery_image` impostato su true.
 */
add_filter('elementor/widget/render_content', 'aggiungi_classi_large_gallery', 10, 2);

function aggiungi_classi_large_gallery($content, $widget)
{
    if ('gallery' !== $widget->get_name()) {
        return $content;
    }

    // Trova tutti gli ID delle immagini nel markup della gallery
    preg_match_all('/wp-image-(\d+)/', $content, $matches);
    if (!empty($matches[1])) {
        foreach ($matches[1] as $image_id) {
            $is_large = get_field('large_gallery_image', $image_id);
            if ($is_large) {
                $content = preg_replace(
                    '/(wp-image-' . $image_id . ')/',
                    '$1 large',
                    $content
                );
            }
        }
    }

    return $content;
}

/**
 * =======================================================
 * Funzione Helper: get_portfolio_thumb_url
 * =======================================================
 * Ritorna l'URL ottimizzato di un'immagine del portfolio
 * in base al campo ACF 'large_gallery_image'.
 *
 * @param int $image_id L'ID dell'immagine in WP.
 * @return string L'URL della thumb ottimizzata.
 */
function get_portfolio_thumb_url($image_id)
{
    // Verifica se il campo ACF 'large_gallery_image' è attivo
    $is_large = get_field('large_gallery_image', $image_id);

    // Determina il formato corretto
    $size = $is_large ? 'portfolio_large' : 'large';

    // Recupera l'array con i dati del formato immagine
    $image_data = wp_get_attachment_image_src($image_id, $size);

    // Se il formato esiste, ritorna l'URL, altrimenti l'URL originale
    return $image_data ? $image_data[0] : wp_get_attachment_url($image_id);
}
