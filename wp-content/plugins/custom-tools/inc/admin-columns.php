<?php

/**
 * ===============================================================
 * 1. Funzioni per la Gestione delle Colonne Admin del CPT Portfolio
 * ===============================================================
 *
 * Queste funzioni aggiungono una colonna 'Thumbs' (anteprima) alla lista
 * di tutti i progetti del Custom Post Type 'Portfolio' nell'area admin di WordPress.
 *
 * NOTA: Assicurati che il campo ACF 'immagine_anteprima_portfolio'
 * abbia il "Valore di Ritorno" impostato su 'Array'.
 */

add_filter('manage_portfolio_posts_columns', 'aggiungi_colonna_anteprima');
function aggiungi_colonna_anteprima($columns)
{
	$new_columns = [];

	// Itera attraverso le colonne esistenti per inserire la nostra nuova colonna.
	foreach ($columns as $key => $value) {
		// Inserisce la colonna 'anteprima' subito dopo la checkbox di selezione.
		if ($key === 'cb') {
			$new_columns[$key] = $value; // Aggiunge la checkbox originale.
			$new_columns['anteprima'] = 'Thumbs'; // Aggiunge la nostra nuova colonna.
		} else {
			$new_columns[$key] = $value; // Aggiunge le altre colonne originali.
		}
	}

	return $new_columns;
}

// Popola la nuova colonna 'Thumbs' con l'immagine di anteprima del portfolio.
add_action('manage_portfolio_posts_custom_column', 'mostra_anteprima_portfolio', 10, 2);
function mostra_anteprima_portfolio($column, $post_id)
{

	// Controlla se la colonna corrente è quella che abbiamo appena aggiunto.
	if ($column === 'anteprima') {
		// Recupera il valore del campo ACF 'immagine_anteprima_portfolio' per il post corrente.
		// Poiché il "Valore di Ritorno" è 'Array', $img conterrà tutti i dati dell'immagine.
		$img = get_field('immagine_anteprima_portfolio', $post_id);

		// Controlla se un'immagine è stata effettivamente caricata.
		if ($img) {
			// Stampa il tag <img> usando l'URL della dimensione 'thumbnail' per ottimizzare la visualizzazione.
			// Aggiungi stili inline per una migliore resa visiva nell'admin.
			echo '<img src="' . esc_url($img['sizes']['thumbnail']) . '" ';
			echo 'width="80" height="80" ';
			echo 'style="object-fit:cover; border-radius:4px; max-width:80px; height:auto; display:block;" ';
			echo 'alt="' . esc_attr($img['alt']) . '">'; // Aggiungi alt text per accessibilità.
		} else {
			// Se non c'è un'immagine, mostra un trattino.
			echo '—';
		}
	}
}

// Aggiunge CSS personalizzato solo alla pagina admin del CPT "portfolio"
add_action('admin_head', 'riduci_larghezza_colonna_anteprima_portfolio');
function riduci_larghezza_colonna_anteprima_portfolio()
{
	$screen = get_current_screen();

	// Applica solo alla lista dei Portfolio
	if ($screen && $screen->post_type === 'portfolio') {
		echo '<style>
            th.column-anteprima,
            td.column-anteprima {
                width: 100px !important;
                max-width: 100px !important;
                overflow: hidden;
                padding: 4px 6px;
            }
        </style>';
	}
}


/**
 * =====================================================================================
 * 2. Estensione Colonne Admin del CPT Portfolio: Aggiunta colonna "Visibile in Home"
 * =====================================================================================
 *
 * Questo blocco aggiunge una nuova colonna alla tabella di amministrazione del CPT
 * 'Portfolio' per indicare se un progetto è stato marcato come visibile in homepage,
 * tramite il campo ACF booleano 'mostra_in_home'.
 */

// Aggiungiamo la nuova colonna "Visibile in Home" alle colonne esistenti
add_filter('manage_portfolio_posts_columns', 'aggiungi_colonna_visibile_in_home');
function aggiungi_colonna_visibile_in_home($columns)
{
	// Inseriamo la colonna alla fine, dopo tutte le altre
	$columns['visibile_home'] = 'Visibile in Home';
	return $columns;
}

// Popoliamo la colonna con il valore booleano del campo ACF
add_action('manage_portfolio_posts_custom_column', 'mostra_valore_visibile_in_home', 10, 2);
function mostra_valore_visibile_in_home($column, $post_id)
{
	if ($column === 'visibile_home') {
		// Recupera il valore del campo ACF 'mostra_in_home'
		$mostra = get_field('mostra_in_home', $post_id);

		// Mostra una icona check/cross o testo, in base al valore booleano
		if ($mostra) {
			// ✔️ Positivo
			echo '<span style="color:green; font-weight:bold;">✔ Sì</span>';
		} else {
			// ❌ Negativo
			echo '<span style="color:#999;">—</span>';
		}
	}
}

// Aggiungiamo uno stile CSS personalizzato alla nuova colonna (opzionale)
add_action('admin_head', 'stile_colonna_visibile_home');
function stile_colonna_visibile_home()
{
	$screen = get_current_screen();

	if ($screen && $screen->post_type === 'portfolio') {
		echo '<style>
            th.column-visibile_home,
            td.column-visibile_home {
                width: 140px;
                text-align: center;
            }
        </style>';
	}
}
