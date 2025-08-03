<?php


/**
 * =======================================================
 * Shortcode: modular_gallery
 * =======================================================
 * Mostra una galleria immagini ACF con supporto lightbox.
 * Parametri:
 * - field: nome del campo ACF (obbligatorio)
 * - slideshow: nome del gruppo lightbox (opzionale, default: "gallery")
 * =======================================================
 */
add_shortcode('modular_gallery', function ($atts) {
    global $post;

    $atts = shortcode_atts([
        'field' => '',             // es. 'galleria_matrimonio_parte_1'
        'slideshow' => 'gallery',  // nome usato nella lightbox
    ], $atts);

    if (empty($atts['field'])) {
        return '<p>Campo ACF non specificato.</p>';
    }

    $gallery = get_field($atts['field'], $post->ID);

    if (empty($gallery)) {
        return '<p>Nessuna immagine disponibile.</p>';
    }

    ob_start();
    echo '<div class="modular-gallery">';

    foreach ($gallery as $image) {
        // URL ottimizzato della thumbnail
        $thumb_url = get_portfolio_thumb_url($image['ID']);

        // URL originale per la lightbox
        $full_url = $image['url'];

        // ALT text dell'immagine
        $alt = esc_attr($image['alt']);

        // Classe CSS per gestire layout delle immagini
        $is_large = get_field('large_gallery_image', $image['ID']);
        $class = $is_large ? 'modular-gallery__item large' : 'modular-gallery__item';
        // Output HTML
        echo "<a href='{$full_url}' class='{$class}' data-elementor-open-lightbox='yes' data-elementor-lightbox-slideshow='{$atts['slideshow']}' rel='lightbox'>
            <img src='{$thumb_url}' alt='{$alt}' loading='lazy' />
        </a>";
    }

    echo '</div>';
    return ob_get_clean();
});