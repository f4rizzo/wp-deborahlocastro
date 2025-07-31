<?php

function mostra_info_matrimonio($atts)
{
    ob_start();

    // Recupera i 3 campi fissi
    $location = get_field('matrimonio_location');
    $provincia = get_field('matrimonio_provincia');
    $catering = get_field('matrimonio_catering');

    // Inizializza un array con gli elementi della griglia
    $info_items = [];

    // Aggiungi i campi fissi se presenti
    if ($location) {
        $info_items[] = [
            'label' => 'Venue',
            'value' => $location
        ];
    }
    if ($provincia) {
        $info_items[] = [
            'label' => 'Provincia',
            'value' => $provincia
        ];
    }
    if ($catering) {
        $info_items[] = [
            'label' => 'Catering',
            'value' => $catering
        ];
    }

    // Aggiungi i campi dinamici dal repeater
    if (have_rows('info_matrimonio')) {
        while (have_rows('info_matrimonio')) {
            the_row();
            $label = get_sub_field('etichetta_info_matrimonio');
            $value = get_sub_field('valore_info_matrimonio');

            if ($label && $value) {
                $info_items[] = [
                    'label' => $label,
                    'value' => $value
                ];
            }
        }
    }

    // Output HTML solo se ci sono elementi
    if (!empty($info_items)) : ?>
        <div class="matrimonio-info">
            <?php foreach ($info_items as $item) : ?>
                <div class="matrimonio-info__item">
                    <strong class="matrimonio-info__label"><?php echo esc_html($item['label']); ?></strong>
                    <p class="matrimonio-info__value"><?php echo esc_html($item['value']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
<?php endif;

    return ob_get_clean();
}

if (function_exists('get_field')) {
    add_shortcode('info_matrimonio', 'mostra_info_matrimonio');
}
