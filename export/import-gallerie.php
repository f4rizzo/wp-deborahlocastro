<?php

/**
 * =======================================================
 * Import delle gallerie (/gallery/…) dal sito attuale
 * =======================================================
 *
 * Le gallerie del vecchio sito sono "portfolio item" del tema Azalea,
 * con le foto in griglie WPBakery caricate via AJAX: non sono nell'API
 * REST. Lo script legge la pagina pubblica e, per ogni griglia, chiede
 * le foto allo stesso endpoint AJAX pubblico usato dal browser (sola
 * lettura).
 *
 * Uso (sito avviato in Local):
 *
 *   source ../.envrc
 *   wp eval-file export/import-gallerie.php <slug> tipo=side-project [dry-run] [stato=private]
 *
 *   wp eval-file export/import-gallerie.php new-york tipo=side-project
 *   wp eval-file export/import-gallerie.php foto-di-famiglia-e-tanti-palloncini-colorati-per-ginevra tipo=real-life
 *
 * tipo: side-project | real-life (i matrimoni hanno campi ACF propri:
 * per quelli vedi export/import-matrimonio.php o il blog).
 *
 * Il post nuovo ha lo stesso slug; l'URL cambia (/gallery/slug/ →
 * /side-project/slug/): quello vecchio è salvato in '_dlc_url_originale'
 * per i redirect 301 (custom-tools/inc/redirect.php).
 * =======================================================
 */

if (!defined('ABSPATH') || !defined('WP_CLI') || !WP_CLI) {
	exit;
}

require_once __DIR__ . '/lib/dlc-import.php';

$args = $args ?? [];
if (!$args) {
	WP_CLI::error('Uso: wp eval-file export/import-gallerie.php <slug> tipo=side-project|real-life [dry-run] [stato=private]');
}

$slug    = sanitize_title(array_shift($args));
$opts    = ['tipo' => 'side-project', 'stato' => 'private', 'sorgente' => 'https://www.deborahlocastro.com', 'lingua' => 'it'];
$dry_run = false;
foreach ($args as $a) {
	if ($a === 'dry-run') {
		$dry_run = true;
	} elseif (strpos($a, '=') !== false) {
		[$k, $v] = explode('=', $a, 2);
		$opts[$k] = $v;
	}
}

if (!in_array($opts['tipo'], ['side-project', 'real-life'], true) || !post_type_exists($opts['tipo'])) {
	WP_CLI::error('tipo deve essere side-project o real-life');
}
$opts['stato'] = in_array($opts['stato'], ['private', 'draft', 'publish'], true) ? $opts['stato'] : 'private';

$source = untrailingslashit($opts['sorgente']);
$host   = (string) wp_parse_url($source, PHP_URL_HOST);
$prefix = $opts['lingua'] === 'it' ? '' : '/' . $opts['lingua'];
$url    = $source . $prefix . '/gallery/' . $slug . '/';
$key    = $host . '#gallery-' . $opts['lingua'] . '-' . $slug;

$existing = get_posts(['post_type' => $opts['tipo'], 'post_status' => 'any', 'meta_key' => DLC_IMPORT_META, 'meta_value' => $key, 'fields' => 'ids']);
if ($existing) {
	WP_CLI::error("Già importata come post {$existing[0]}.");
}

$res = wp_remote_get($url, ['timeout' => 30]);
if (is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200) {
	WP_CLI::error("Pagina non raggiungibile: $url");
}
$html = wp_remote_retrieve_body($res);


/**
 * =======================================================
 * 1. Lettura della pagina, nell'ordine
 * =======================================================
 */
$start = strpos($html, 'eltdf-ps-custom-layout');
$end   = strpos($html, 'eltdf-ps-navigation');
$body  = $start !== false ? substr($html, $start, ($end !== false ? $end : strlen($html)) - $start) : '';

if ($body === '') {
	WP_CLI::error('Struttura della galleria non riconosciuta.');
}

$nonce = preg_match('/data-vc-public-nonce="([^"]+)"/', $html, $m) ? $m[1] : '';
$title = preg_match('#<h2[^>]*vc_custom_heading[^>]*>(.*?)</h2>#s', $body, $m) ? trim(wp_strip_all_tags(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'))) : $slug;

preg_match_all(
	'#<div class="wpb_text_column[^"]*"[^>]*>\s*<div class="wpb_wrapper">(.*?)</div>\s*</div>|data-vc-grid-settings="([^"]+)"#s',
	$body,
	$matches,
	PREG_SET_ORDER
);

$pieces = [];
foreach ($matches as $m) {
	if (!empty($m[1])) {
		$text = trim(wp_kses($m[1], ['p' => [], 'br' => [], 'strong' => [], 'em' => [], 'a' => ['href' => true]]));
		$plain = trim(wp_strip_all_tags($text));
		// Il titolo ripetuto come <h4> non serve
		if ($plain === '' || strcasecmp($plain, $title) === 0) {
			continue;
		}
		foreach (preg_split('#</p>\s*<p>|<br\s*/?>\s*<br\s*/?>#', preg_replace('#^<p>|</p>$#', '', $text)) as $p) {
			if (trim(wp_strip_all_tags($p)) !== '') {
				$pieces[] = ['type' => 'p', 'html' => trim($p)];
			}
		}
		continue;
	}

	// Griglia: le foto arrivano dall'endpoint AJAX di WPBakery
	$settings = json_decode(html_entity_decode($m[2], ENT_QUOTES, 'UTF-8'), true);
	if (!$settings) {
		continue;
	}

	$post = [
		'action'     => 'vc_get_vc_grid_data',
		'vc_action'  => 'vc_get_vc_grid_data',
		'tag'        => $settings['tag'] ?? 'vc_masonry_media_grid',
		'vc_post_id' => (string) ($settings['page_id'] ?? ''),
		'_vcnonce'   => $nonce,
	];
	foreach ($settings as $k => $v) {
		$post["data[$k]"] = is_scalar($v) ? (string) $v : wp_json_encode($v);
	}

	$r = wp_remote_post($source . '/wp-admin/admin-ajax.php?lang=' . $opts['lingua'], ['timeout' => 30, 'body' => $post]);
	if (is_wp_error($r)) {
		WP_CLI::warning('  griglia non letta: ' . $r->get_error_message());
		continue;
	}

	preg_match_all('#href="(' . preg_quote($source, '#') . '/wp-content/uploads/[^"]+\.(?:jpe?g|png|webp))"#i', wp_remote_retrieve_body($r), $imgs);
	$urls = array_values(array_unique($imgs[1]));
	if ($urls) {
		$pieces[] = ['type' => 'gallery', 'urls' => $urls];
	}
}

$n_img = array_sum(array_map(function ($p) {
	return $p['type'] === 'gallery' ? count($p['urls']) : 0;
}, $pieces));

WP_CLI::log(sprintf('Galleria "%s" (%s): %d testi, %d gruppi di foto, %d foto → %s', $title, $url, count(array_filter($pieces, function ($p) {
	return $p['type'] === 'p';
})), count(array_filter($pieces, function ($p) {
	return $p['type'] === 'gallery';
})), $n_img, $opts['tipo']));

if ($dry_run) {
	WP_CLI::success('Dry run: nessuna modifica.');
	return;
}


/**
 * =======================================================
 * 2. Creazione del post e delle foto
 * =======================================================
 */
$post_id = wp_insert_post([
	'post_type'   => $opts['tipo'],
	'post_status' => 'draft',
	'post_title'  => $title,
	'post_name'   => $slug,
	'post_author' => get_current_user_id() ?: 1,
], true);

if (is_wp_error($post_id)) {
	WP_CLI::error($post_id->get_error_message());
}

$progress = \WP_CLI\Utils\make_progress_bar('Foto', $n_img);
$blocks   = [];
$featured = 0;

foreach ($pieces as $p) {
	if ($p['type'] === 'p') {
		$blocks[] = dlc_imp_paragraph_block($p['html']);
		continue;
	}

	$ids = [];
	foreach ($p['urls'] as $u) {
		$id = dlc_imp_media_url($u, $post_id, $host);
		if ($id) {
			$ids[] = $id;
			$featured = $featured ?: $id;
		}
		$progress->tick();
	}
	$blocks[] = dlc_imp_gallery_block($ids, $slug);
}
$progress->finish();

wp_update_post([
	'ID'           => $post_id,
	'post_content' => wp_slash(implode("\n\n", array_filter($blocks))),
	'post_status'  => $opts['stato'],
]);

if ($featured) {
	set_post_thumbnail($post_id, $featured);
}

update_post_meta($post_id, DLC_IMPORT_META, $key);
dlc_imp_save_seo($post_id, $url, dlc_imp_scrape_seo($url, $html));

WP_CLI::success(sprintf('Importata come %s %d (%s): %d foto.', $opts['tipo'], $post_id, $opts['stato'], $n_img));
