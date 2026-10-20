<?php

/**
 * =======================================================
 * Import degli articoli dal sito attuale (deborahlocastro.com)
 * =======================================================
 *
 * Copia gli articoli del vecchio sito (WordPress 5.8 + WPBakery + WPML)
 * in questo sito, leggendo SOLO l'API REST pubblica: nessun accesso al
 * vecchio server, nessuna modifica là.
 *
 * Uso (dalla radice del repository, con il sito avviato in Local):
 *
 *   source ../.envrc
 *   wp eval-file export/import-articoli.php <slug|tutti> [opzioni]
 *
 *   wp eval-file export/import-articoli.php matrimonio-alla-tonnara-di-scopello dry-run
 *   wp eval-file export/import-articoli.php tutti stato=private limite=10
 *
 * Opzioni (dopo lo slug, in qualsiasi ordine):
 *   dry-run              mostra cosa farebbe, senza scrivere
 *   stato=private        stato dei post creati (private | draft | publish), default private
 *   limite=N             importa al massimo N articoli (con "tutti")
 *   sorgente=URL         sito di origine, default https://www.deborahlocastro.com
 *   lingua=it            lingua WPML da leggere (it | en), default it
 *
 * Cosa fa, per ogni articolo:
 *  1. Stesso slug e stessa data: l'URL /slug/ resta identico (permalink
 *     /%postname%/ in entrambi i siti) → nessun redirect necessario.
 *  2. Converte il contenuto WPBakery in blocchi Gutenberg:
 *       [vc_column_text]           → paragrafi / titoli
 *       [vc_custom_heading]        → titolo
 *       [vc_single_image], [vc_masonry_media_grid], [vc_gallery], [vc_media_grid]
 *                                  → [modular_gallery ids="…"] (la galleria dei matrimoni)
 *       [vc_video]                 → embed
 *       spaziatori, righe, colonne → eliminati
 *  3. Scarica le foto originali e le carica nella Libreria media: passano
 *     dalla pipeline di custom-tools (2048px, WebP, ritagli).
 *  4. Categorie e tag con lo stesso slug (creati se mancano).
 *  5. SEO: titolo e meta description presi dalla pagina pubblica (Rank
 *     Math), salvati nei meta di Rank Math; URL originale e versione
 *     inglese (hreflang) salvati per i redirect.
 *
 * Ripetibile: post e foto importati sono marcati con '_dlc_import_source';
 * un articolo già importato viene saltato, le foto già scaricate riusate.
 *
 * Prima di eseguirlo senza dry-run: export/export-db.sh (backup del DB).
 * =======================================================
 */

if (!defined('ABSPATH') || !defined('WP_CLI') || !WP_CLI) {
	exit;
}

require_once __DIR__ . '/lib/dlc-import.php';

$args = $args ?? [];

if (!$args) {
	WP_CLI::error('Uso: wp eval-file export/import-articoli.php <slug|tutti> [dry-run] [stato=private] [limite=N] [sorgente=URL] [lingua=it]');
}

$target  = array_shift($args);
$opts    = ['stato' => 'private', 'limite' => 0, 'sorgente' => 'https://www.deborahlocastro.com', 'lingua' => 'it'];
$dry_run = false;

foreach ($args as $a) {
	if ($a === 'dry-run') {
		$dry_run = true;
	} elseif (strpos($a, '=') !== false) {
		[$k, $v] = explode('=', $a, 2);
		$opts[$k] = $v;
	}
}

$opts['stato'] = in_array($opts['stato'], ['private', 'draft', 'publish'], true) ? $opts['stato'] : 'private';
$source        = untrailingslashit($opts['sorgente']);
$source_host   = (string) wp_parse_url($source, PHP_URL_HOST);
$meta_source   = '_dlc_import_source';


/**
 * =======================================================
 * 1. Accesso all'API REST del vecchio sito
 * =======================================================
 */
$api = function ($path, $query = []) use ($source, $opts) {
	$query = array_merge(['lang' => $opts['lingua']], $query);
	$url   = $source . '/wp-json/wp/v2/' . ltrim($path, '/') . '?' . http_build_query($query);
	$res   = wp_remote_get($url, ['timeout' => 30]);

	if (is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200) {
		return null;
	}

	return [
		'body'  => json_decode(wp_remote_retrieve_body($res), true),
		'total' => (int) wp_remote_retrieve_header($res, 'x-wp-total'),
		'pages' => (int) wp_remote_retrieve_header($res, 'x-wp-totalpages'),
	];
};

$fields = 'id,date,date_gmt,modified,slug,link,title,excerpt,content,categories,tags,featured_media,status';

if ($target === 'tutti') {
	$posts = [];
	$page  = 1;
	do {
		$r = $api('posts', ['per_page' => 50, 'page' => $page, '_fields' => $fields]);
		if (!$r) {
			WP_CLI::error('API del sito sorgente non raggiungibile.');
		}
		$posts = array_merge($posts, $r['body']);
		$page++;
	} while ($page <= $r['pages']);
} else {
	$r = $api('posts', ['slug' => $target, '_fields' => $fields]);
	$posts = $r['body'] ?? [];
}

if (!$posts) {
	WP_CLI::error("Nessun articolo trovato per '$target'.");
}

if ((int) $opts['limite'] > 0) {
	$posts = array_slice($posts, 0, (int) $opts['limite']);
}

WP_CLI::log(sprintf('Sorgente: %s (lingua %s) — %d articoli da elaborare.', $source, $opts['lingua'], count($posts)));


/**
 * =======================================================
 * 2. Conversione WPBakery → blocchi Gutenberg
 * =======================================================
 */

/**
 * Normalizza l'HTML restituito dall'API: entità e virgolette tipografiche
 * dentro gli shortcode (wptexturize trasforma " in ” e ″).
 */
$normalize = function ($html) {
	$html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	// Le virgolette tipografiche solo dentro gli shortcode [ … ]
	return preg_replace_callback('/\[[^\[\]]+\]/u', function ($m) {
		return str_replace(['”', '“', '″', '′', '«', '»'], '"', $m[0]);
	}, $html);
};

/**
 * Attributi di uno shortcode (stringa dentro le parentesi quadre).
 */
$attrs = function ($s) {
	return shortcode_parse_atts($s) ?: [];
};

/**
 * Pulisce un frammento HTML di testo (vc_column_text) e lo trasforma in
 * blocchi paragrafo/titolo/lista/citazione.
 */
$text_to_blocks = function ($html) {
	// Via i tag di impaginazione e gli attributi superflui
	$html = preg_replace('#</?(div|span|font|section|center)[^>]*>#i', '', $html);
	$html = preg_replace('#\s(class|style|id|dir|data-[a-z-]+)="[^"]*"#i', '', $html);
	$html = preg_replace('#<p>\s*(&nbsp;|\s)*</p>#i', '', $html);
	$html = wp_kses($html, [
		'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [],
		'a' => ['href' => true, 'target' => true, 'rel' => true],
		'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [],
		'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [],
		'img' => ['src' => true, 'alt' => true, 'width' => true, 'height' => true],
	]);
	$html = trim(wpautop(trim($html)));

	$blocks = [];
	if ($html === '') {
		return $blocks;
	}

	$doc = new DOMDocument();
	libxml_use_internal_errors(true);
	$doc->loadHTML('<?xml encoding="UTF-8"><body>' . $html . '</body>');
	libxml_clear_errors();

	foreach ($doc->getElementsByTagName('body')->item(0)->childNodes as $node) {
		$inner = '';
		foreach ($node->childNodes ?? [] as $child) {
			$inner .= $doc->saveHTML($child);
		}
		$inner = trim($inner);
		$tag   = strtolower($node->nodeName);

		if ($node->nodeType === XML_TEXT_NODE) {
			if (trim($node->textContent) !== '') {
				$blocks[] = ['type' => 'p', 'html' => esc_html(trim($node->textContent))];
			}
			continue;
		}

		// Immagini dentro il testo: blocco immagine separato
		if ($node->getElementsByTagName('img')->length) {
			foreach ($node->getElementsByTagName('img') as $img) {
				// Emoji inserite come immagini (16px, CDN di Facebook/WordPress): scartate
				$w   = (int) $img->getAttribute('width');
				$src = $img->getAttribute('src');
				if (($w > 0 && $w <= 64) || preg_match('#emoji|fbcdn|s\.w\.org#i', $src)) {
					continue;
				}
				$blocks[] = ['type' => 'img_url', 'src' => $src, 'alt' => $img->getAttribute('alt')];
			}
			$inner = trim(preg_replace('#<img[^>]*>#i', '', $inner));
			if ($inner === '' || $tag === 'img') {
				continue;
			}
		}

		if (in_array($tag, ['h1', 'h2', 'h3', 'h4', 'h5'], true)) {
			$blocks[] = ['type' => 'h', 'level' => max(2, min(4, (int) $tag[1])), 'html' => $inner];
		} elseif (in_array($tag, ['ul', 'ol'], true)) {
			$blocks[] = ['type' => 'list', 'ordered' => $tag === 'ol', 'html' => $doc->saveHTML($node)];
		} elseif ($tag === 'blockquote') {
			$blocks[] = ['type' => 'quote', 'html' => $inner];
		} elseif ($inner !== '' && wp_strip_all_tags($inner) !== '') {
			$blocks[] = ['type' => 'p', 'html' => $inner];
		}
	}

	return $blocks;
};

/**
 * Lista ordinata di "pezzi" (testo, immagini, gallerie, video) dal
 * contenuto WPBakery.
 */
$parse_content = function ($html) use ($normalize, $attrs, $text_to_blocks) {
	$html   = $normalize($html);
	$pieces = [];
	$unknown = [];

	// Testo: [vc_column_text]…[/vc_column_text]; tutto il resto sono shortcode singoli
	$pattern = '/\[vc_column_text([^\]]*)\](.*?)\[\/vc_column_text\]|\[(vc_[a-z_]+|gallery)([^\]]*)\]/s';

	preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);

	foreach ($matches as $m) {
		if (strpos($m[0], '[vc_column_text') === 0) {
			foreach ($text_to_blocks($m[2] ?? '') as $b) {
				$pieces[] = $b;
			}
			continue;
		}

		$name = $m[3] ?? '';
		$a    = $attrs($m[4] ?? '');

		switch ($name) {
			case 'vc_masonry_media_grid':
			case 'vc_media_grid':
			case 'vc_basic_grid':
				$ids = array_filter(array_map('intval', explode(',', (string) ($a['include'] ?? ''))));
				if ($ids) {
					$pieces[] = ['type' => 'gallery', 'ids' => $ids];
				}
				break;
			case 'vc_gallery':
			case 'vc_images_carousel':
			case 'gallery':
				$ids = array_filter(array_map('intval', explode(',', (string) ($a['images'] ?? $a['ids'] ?? ''))));
				if ($ids) {
					$pieces[] = ['type' => 'gallery', 'ids' => $ids];
				}
				break;
			case 'vc_single_image':
				if (!empty($a['image'])) {
					$pieces[] = ['type' => 'gallery', 'ids' => [(int) $a['image']]];
				}
				break;
			case 'vc_custom_heading':
				if (!empty($a['text'])) {
					$pieces[] = ['type' => 'h', 'level' => 2, 'html' => esc_html(wp_strip_all_tags($a['text']))];
				}
				break;
			case 'vc_video':
				if (!empty($a['link'])) {
					$pieces[] = ['type' => 'embed', 'url' => $a['link']];
				}
				break;
			case 'vc_row': case 'vc_row_inner': case 'vc_column': case 'vc_column_inner':
			case 'vc_empty_space': case 'vc_separator': case 'vc_text_separator':
				break;
			default:
				$unknown[$name] = ($unknown[$name] ?? 0) + 1;
		}
	}

	// Contenuto senza WPBakery (articoli scritti con l'editor classico)
	if (!$matches) {
		$pieces = $text_to_blocks($html);
	}

	return [$pieces, $unknown];
};


/**
 * =======================================================
 * 3. Media: download e caricamento nella Libreria
 * =======================================================
 */
$media_cache = [];

$import_media = function ($old_id, $post_id, $dry_run) use ($api, $source_host, $meta_source, &$media_cache) {
	if (isset($media_cache[$old_id])) {
		return $media_cache[$old_id];
	}

	$key   = $source_host . '#media-' . $old_id;
	$found = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => $meta_source, 'meta_value' => $key, 'fields' => 'ids']);
	if ($found) {
		return $media_cache[$old_id] = (int) $found[0];
	}

	$r = $api('media/' . (int) $old_id, ['_fields' => 'id,source_url,alt_text,caption,title,mime_type']);
	if (!$r || empty($r['body']['source_url'])) {
		WP_CLI::warning("  media $old_id non trovato sul sito sorgente");
		return $media_cache[$old_id] = 0;
	}

	if ($dry_run) {
		return $media_cache[$old_id] = -1;
	}

	$m   = $r['body'];
	$tmp = download_url($m['source_url'], 120);
	if (is_wp_error($tmp)) {
		WP_CLI::warning('  download non riuscito: ' . $m['source_url']);
		return $media_cache[$old_id] = 0;
	}

	$id = media_handle_sideload(['name' => wp_basename(wp_parse_url($m['source_url'], PHP_URL_PATH)), 'tmp_name' => $tmp], $post_id, wp_strip_all_tags($m['title']['rendered'] ?? ''));
	if (is_wp_error($id)) {
		@unlink($tmp);
		WP_CLI::warning('  caricamento non riuscito: ' . $id->get_error_message());
		return $media_cache[$old_id] = 0;
	}

	update_post_meta($id, $meta_source, $key);
	if (!empty($m['alt_text'])) {
		update_post_meta($id, '_wp_attachment_image_alt', sanitize_text_field($m['alt_text']));
	}
	$caption = trim(wp_strip_all_tags($m['caption']['rendered'] ?? ''));
	if ($caption !== '') {
		wp_update_post(['ID' => $id, 'post_excerpt' => $caption]);
	}

	return $media_cache[$old_id] = (int) $id;
};

/**
 * Immagine inserita nel testo con l'URL (non con l'ID): scaricata una volta.
 */
$import_media_url = function ($url, $post_id, $dry_run) use ($source_host, $meta_source) {
	$url = preg_replace('/-\d+x\d+(?=\.[a-z]{3,4}$)/i', '', strtok($url, '?')); // l'originale, non il formato ridotto
	$key = $source_host . '#url-' . md5($url);

	$found = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => $meta_source, 'meta_value' => $key, 'fields' => 'ids']);
	if ($found) {
		return (int) $found[0];
	}
	if ($dry_run) {
		return -1;
	}

	$tmp = download_url($url, 120);
	if (is_wp_error($tmp)) {
		return 0;
	}
	$id = media_handle_sideload(['name' => wp_basename(wp_parse_url($url, PHP_URL_PATH)), 'tmp_name' => $tmp], $post_id);
	if (is_wp_error($id)) {
		@unlink($tmp);
		return 0;
	}
	update_post_meta($id, $meta_source, $key);

	return (int) $id;
};


/**
 * =======================================================
 * 4. Serializzazione dei blocchi
 * =======================================================
 */
$serialize = function ($blocks, $slideshow) {
	$out = [];
	foreach ($blocks as $b) {
		switch ($b['type']) {
			case 'p':
				$out[] = "<!-- wp:paragraph -->\n<p>" . $b['html'] . "</p>\n<!-- /wp:paragraph -->";
				break;
			case 'h':
				$out[] = sprintf("<!-- wp:heading {\"level\":%d} -->\n<h%d class=\"wp-block-heading\">%s</h%d>\n<!-- /wp:heading -->", $b['level'], $b['level'], $b['html'], $b['level']);
				break;
			case 'list':
				$out[] = '<!-- wp:html -->' . "\n" . $b['html'] . "\n" . '<!-- /wp:html -->';
				break;
			case 'quote':
				$out[] = "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><p>" . $b['html'] . "</p></blockquote>\n<!-- /wp:quote -->";
				break;
			case 'gallery':
				if ($b['ids']) {
					$out[] = dlc_imp_gallery_block($b['ids'], $slideshow);
				}
				break;
			case 'embed':
				$out[] = sprintf("<!-- wp:embed {\"url\":\"%s\"} -->\n<figure class=\"wp-block-embed\"><div class=\"wp-block-embed__wrapper\">\n%s\n</div></figure>\n<!-- /wp:embed -->", esc_url($b['url']), esc_url($b['url']));
				break;
		}
	}
	return implode("\n\n", $out);
};


/**
 * =======================================================
 * 5. SEO dalla pagina pubblica
 * =======================================================
 */
$scrape_seo = function ($url) {
	$res = wp_remote_get($url, ['timeout' => 30]);
	if (is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200) {
		return [];
	}
	$html = wp_remote_retrieve_body($res);
	$seo  = [];

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
};


/**
 * =======================================================
 * 6. Import
 * =======================================================
 */
$term_ids = function ($taxonomy, $old_ids) use ($api, $dry_run) {
	$ids = [];
	foreach ($old_ids as $old) {
		$r = $api($taxonomy === 'category' ? 'categories/' . $old : 'tags/' . $old, ['_fields' => 'id,name,slug,description']);
		if (!$r) {
			continue;
		}
		$t     = $r['body'];
		$found = get_term_by('slug', $t['slug'], $taxonomy);
		if ($found) {
			$ids[] = (int) $found->term_id;
		} elseif (!$dry_run) {
			$new = wp_insert_term(html_entity_decode($t['name'], ENT_QUOTES, 'UTF-8'), $taxonomy, ['slug' => $t['slug'], 'description' => $t['description'] ?? '']);
			if (!is_wp_error($new)) {
				$ids[] = (int) $new['term_id'];
			}
		}
	}
	return $ids;
};

$report = ['importati' => 0, 'saltati' => 0, 'foto' => 0, 'sconosciuti' => []];

foreach ($posts as $p) {
	$key  = $source_host . '#post-' . $p['id'];
	$slug = $p['slug'];

	$existing = get_posts(['post_type' => 'post', 'post_status' => 'any', 'meta_key' => $meta_source, 'meta_value' => $key, 'fields' => 'ids']);
	if ($existing) {
		WP_CLI::log("= $slug: già importato (post {$existing[0]}), saltato");
		$report['saltati']++;
		continue;
	}

	[$pieces, $unknown] = $parse_content($p['content']['rendered']);
	foreach ($unknown as $name => $n) {
		$report['sconosciuti'][$name] = ($report['sconosciuti'][$name] ?? 0) + $n;
	}

	$n_img = 0;
	foreach ($pieces as $piece) {
		$n_img += $piece['type'] === 'gallery' ? count($piece['ids']) : ($piece['type'] === 'img_url' ? 1 : 0);
	}

	WP_CLI::log(sprintf('+ %s — %d blocchi, %d foto%s', $slug, count($pieces), $n_img, $unknown ? ' — shortcode ignorati: ' . implode(', ', array_keys($unknown)) : ''));

	if ($dry_run) {
		continue;
	}

	$title   = html_entity_decode(wp_strip_all_tags($p['title']['rendered']), ENT_QUOTES, 'UTF-8');
	$excerpt = trim(html_entity_decode(wp_strip_all_tags($p['excerpt']['rendered']), ENT_QUOTES, 'UTF-8'));
	$excerpt = preg_replace('/\s*\[[^\]]*\]\s*$/', '', $excerpt); // via il "[…]" finale

	// Post vuoto prima, per allegarci le foto
	$post_id = wp_insert_post([
		'post_type'     => 'post',
		'post_status'   => 'draft',
		'post_title'    => $title,
		'post_name'     => $slug,
		'post_date'     => $p['date'],
		'post_date_gmt' => $p['date_gmt'],
		'post_excerpt'  => $excerpt,
		'post_author'   => get_current_user_id() ?: 1,
	], true);

	if (is_wp_error($post_id)) {
		WP_CLI::warning("  $slug: " . $post_id->get_error_message());
		continue;
	}

	// Foto: ID del vecchio sito → ID nuovi
	foreach ($pieces as &$piece) {
		if ($piece['type'] === 'gallery') {
			$piece['ids'] = array_values(array_filter(array_map(function ($old) use ($import_media, $post_id) {
				return $import_media($old, $post_id, false);
			}, $piece['ids'])));
			$report['foto'] += count($piece['ids']);
		} elseif ($piece['type'] === 'img_url') {
			$new = $import_media_url($piece['src'], $post_id, false);
			$piece = $new ? ['type' => 'gallery', 'ids' => [$new]] : ['type' => 'skip'];
			$report['foto'] += $new ? 1 : 0;
		}
	}
	unset($piece);

	$featured = $p['featured_media'] ? $import_media($p['featured_media'], $post_id, false) : 0;
	if (!$featured) {
		// Nessuna immagine in evidenza: la prima foto dell'articolo
		foreach ($pieces as $piece) {
			if ($piece['type'] === 'gallery' && $piece['ids']) {
				$featured = $piece['ids'][0];
				break;
			}
		}
	}

	wp_update_post([
		'ID'           => $post_id,
		'post_content' => wp_slash($serialize($pieces, $slug)),
		'post_status'  => $opts['stato'],
	]);

	if ($featured) {
		set_post_thumbnail($post_id, $featured);
	}

	wp_set_post_categories($post_id, $term_ids('category', $p['categories']));
	wp_set_post_tags($post_id, $term_ids('post_tag', $p['tags']));

	$seo = $scrape_seo($p['link']);
	update_post_meta($post_id, $meta_source, $key);
	update_post_meta($post_id, '_dlc_url_originale', $p['link']);
	if (!empty($seo['title'])) {
		update_post_meta($post_id, 'rank_math_title', $seo['title']);
	}
	if (!empty($seo['description'])) {
		update_post_meta($post_id, 'rank_math_description', $seo['description']);
	}
	foreach (($seo['hreflang'] ?? []) as $lang => $url) {
		if ($url !== $p['link']) {
			update_post_meta($post_id, '_dlc_url_' . sanitize_key($lang), $url);
		}
	}

	WP_CLI::log(sprintf('  → post %d (%s) %s', $post_id, $opts['stato'], get_permalink($post_id)));
	$report['importati']++;
}

WP_CLI::success(sprintf(
	'%s: %d importati, %d saltati, %d foto.%s',
	$dry_run ? 'Dry run' : 'Fatto',
	$report['importati'],
	$report['saltati'],
	$report['foto'],
	$report['sconosciuti'] ? ' Shortcode non convertiti: ' . http_build_query($report['sconosciuti'], '', ', ') : ''
));
