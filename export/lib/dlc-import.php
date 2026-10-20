<?php

/**
 * =======================================================
 * Funzioni comuni agli script di import dal vecchio sito
 * =======================================================
 * Usate da export/import-articoli.php e export/import-gallerie.php
 * (wp eval-file). Solo lettura sul vecchio sito: API REST pubblica e
 * pagine pubbliche.
 */

if (!defined('ABSPATH') || !defined('WP_CLI') || !WP_CLI) {
	exit;
}

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

const DLC_IMPORT_META = '_dlc_import_source';

/**
 * Allegato già importato con questa chiave? (0 se no)
 */
function dlc_imp_find_attachment($key)
{
	$found = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => DLC_IMPORT_META, 'meta_value' => $key, 'fields' => 'ids']);
	return $found ? (int) $found[0] : 0;
}

/**
 * Scarica un file e lo carica nella Libreria media (passa dalla pipeline
 * immagini di custom-tools). Restituisce l'ID o 0.
 */
function dlc_imp_sideload($url, $post_id, $key, $title = '', $alt = '', $caption = '')
{
	$existing = dlc_imp_find_attachment($key);
	if ($existing) {
		return $existing;
	}

	$tmp = download_url($url, 120);
	if (is_wp_error($tmp)) {
		WP_CLI::warning('  download non riuscito: ' . $url);
		return 0;
	}

	$id = media_handle_sideload(['name' => wp_basename(wp_parse_url($url, PHP_URL_PATH)), 'tmp_name' => $tmp], $post_id, $title);
	if (is_wp_error($id)) {
		@unlink($tmp);
		WP_CLI::warning('  caricamento non riuscito: ' . $id->get_error_message());
		return 0;
	}

	update_post_meta($id, DLC_IMPORT_META, $key);
	if ($alt !== '') {
		update_post_meta($id, '_wp_attachment_image_alt', sanitize_text_field($alt));
	}
	if ($caption !== '') {
		wp_update_post(['ID' => $id, 'post_excerpt' => $caption]);
	}

	return (int) $id;
}

/**
 * Immagine per URL (non per ID): scarica l'originale, non il formato ridotto.
 */
function dlc_imp_media_url($url, $post_id, $host, $dry_run = false)
{
	$url = preg_replace('/-\d+x\d+(?=\.[a-z]{3,4}$)/i', '', strtok($url, '?'));
	$key = $host . '#url-' . md5($url);

	if ($dry_run) {
		return dlc_imp_find_attachment($key) ?: -1;
	}

	return dlc_imp_sideload($url, $post_id, $key);
}

/**
 * Gruppo di foto → [modular_gallery ids="…"] in un blocco Shortcode.
 * Stessa galleria dei matrimoni: lightbox, srcset, ritagli, ingombro
 * wide/tall scelto foto per foto dalla Libreria media.
 *
 * @param int[]  $ids
 * @param string $slideshow Gruppo della lightbox (es. slug dell'articolo)
 * @return string
 */
function dlc_imp_gallery_block($ids, $slideshow = 'gallery')
{
	$ids = array_values(array_filter(array_map('intval', $ids)));

	if (!$ids) {
		return '';
	}

	return sprintf(
		"<!-- wp:shortcode -->\n[modular_gallery ids=\"%s\" slideshow=\"%s\"]\n<!-- /wp:shortcode -->",
		implode(',', $ids),
		sanitize_title($slideshow)
	);
}

/**
 * Paragrafo Gutenberg.
 */
function dlc_imp_paragraph_block($html)
{
	return "<!-- wp:paragraph -->\n<p>" . $html . "</p>\n<!-- /wp:paragraph -->";
}

/**
 * SEO della pagina pubblica: <title>, meta description, hreflang.
 */
function dlc_imp_scrape_seo($url, $html = null)
{
	if ($html === null) {
		$res = wp_remote_get($url, ['timeout' => 30]);
		if (is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200) {
			return [];
		}
		$html = wp_remote_retrieve_body($res);
	}

	$seo = [];
	if (preg_match('#<title>(.*?)</title>#si', $html, $m)) {
		$seo['title'] = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
	}
	if (preg_match('#<meta name="description" content="([^"]*)"#i', $html, $m)) {
		$seo['description'] = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
	}
	if (preg_match_all('#<link rel="alternate" hreflang="([a-z-]+)" href="([^"]+)"#i', $html, $mm, PREG_SET_ORDER)) {
		foreach ($mm as $x) {
			$seo['hreflang'][$x[1]] = $x[2];
		}
	}
	return $seo;
}

/**
 * Salva SEO e URL di provenienza nei meta del post (Rank Math + redirect).
 */
function dlc_imp_save_seo($post_id, $old_url, $seo)
{
	update_post_meta($post_id, '_dlc_url_originale', $old_url);
	if (!empty($seo['title'])) {
		update_post_meta($post_id, 'rank_math_title', $seo['title']);
	}
	if (!empty($seo['description'])) {
		update_post_meta($post_id, 'rank_math_description', $seo['description']);
	}
	foreach (($seo['hreflang'] ?? []) as $lang => $url) {
		if ($url !== $old_url && $lang !== 'x-default') {
			update_post_meta($post_id, '_dlc_url_' . sanitize_key($lang), $url);
		}
	}
}
