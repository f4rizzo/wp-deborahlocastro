<?php

/**
 * =======================================================
 * Import di un matrimonio da un altro sito Local
 * =======================================================
 *
 * Copia un post 'matrimonio' (testi, campi ACF, foto) da un altro sito Local
 * — tipicamente la copia della cliente, deborahlocastro.preview.local — in
 * questo sito. Il sito sorgente viene solo LETTO.
 *
 * Uso (dalla radice del repository, con il sito avviato in Local):
 *
 *   source ../.envrc
 *   wp eval-file export/import-matrimonio.php <public-sorgente> <id-o-slug> [dry-run]
 *
 *   es. wp eval-file export/import-matrimonio.php ../../../deborahlocastropreview/app/public 615 dry-run
 *
 * Cosa fa:
 *  1. Si collega al DB del sito sorgente: credenziali dal suo wp-config.php,
 *     socket MySQL dal suo ../.envrc (generato da Local, il sito deve essere avviato).
 *  2. Legge il post, i suoi meta e tutte le immagini collegate (immagine in
 *     evidenza, campi ACF immagine e galleria, anche dentro i repeater).
 *  3. Copia gli ORIGINALI delle foto in uploads/ e li registra come nuovi
 *     allegati: WordPress rigenera formati, WebP e ritagli con le regole
 *     attuali di custom-tools, esattamente come per un nuovo caricamento.
 *  4. Crea il post con nuovi ID e rimappa gli ID delle immagini nei campi ACF.
 *
 * Gli ID dei due database NON coincidono (vedi AGENTS.md): per questo non si
 * importa un DB sopra l'altro e gli ID vengono sempre rimappati.
 *
 * Ripetibile: post e allegati importati sono marcati con il meta
 * '_custom_tools_import_source'. Se il post esiste già lo script si ferma;
 * le immagini già importate vengono riusate.
 *
 * Prima di eseguirlo senza dry-run: export/export-db.sh (backup del DB).
 * =======================================================
 */

if (!defined('ABSPATH') || !defined('WP_CLI') || !WP_CLI) {
    exit;
}

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';

$args = $args ?? [];

if (count($args) < 2) {
    WP_CLI::error('Uso: wp eval-file export/import-matrimonio.php <public-sorgente> <id-o-slug> [dry-run]');
}

$source_root = rtrim(realpath($args[0]) ?: $args[0], '/');
$source_ref  = $args[1];
$dry_run     = in_array('dry-run', array_slice($args, 2), true);
$meta_source = '_custom_tools_import_source';

if (!is_file($source_root . '/wp-config.php')) {
    WP_CLI::error("wp-config.php non trovato in $source_root");
}


/**
 * =======================================================
 * 1. Connessione al database sorgente (sola lettura)
 * =======================================================
 */
$config = file_get_contents($source_root . '/wp-config.php');

$read_define = function ($name) use ($config) {
    return preg_match("/define\(\s*['\"]" . $name . "['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $config, $m) ? $m[1] : null;
};

$db_name = $read_define('DB_NAME');
$db_user = $read_define('DB_USER');
$db_pass = $read_define('DB_PASSWORD');
$prefix  = preg_match('/\$table_prefix\s*=\s*[\'"](\w+)[\'"]/', $config, $m) ? $m[1] : 'wp_';

// Socket MySQL del sito sorgente: Local lo scrive nel my.cnf indicato da MYSQL_HOME in ../.envrc
$socket = getenv('IMPORT_SOURCE_SOCKET') ?: null;
$envrc  = dirname($source_root) . '/.envrc';

if (!$socket && is_file($envrc) && preg_match('/MYSQL_HOME="([^"]+)"/', file_get_contents($envrc), $m)) {
    $mycnf = $m[1] . '/my.cnf';
    if (is_file($mycnf) && preg_match('/^\s*socket\s*=\s*(.+)$/m', file_get_contents($mycnf), $m)) {
        $socket = trim($m[1]);
    }
}

if (!$socket || !file_exists($socket)) {
    WP_CLI::error('Socket MySQL del sito sorgente non trovato: il sito è avviato in Local? (oppure imposta IMPORT_SOURCE_SOCKET)');
}

mysqli_report(MYSQLI_REPORT_OFF);
$src = new mysqli('localhost', $db_user, $db_pass, $db_name, 0, $socket);

if ($src->connect_error) {
    WP_CLI::error('Connessione al DB sorgente fallita: ' . $src->connect_error);
}

$src->set_charset('utf8mb4');

$query = function ($sql, ...$params) use ($src) {
    $stmt = $src->prepare($sql);
    if (!$stmt) {
        WP_CLI::error('Query sorgente non valida: ' . $src->error);
    }
    if ($params) {
        $stmt->bind_param(str_repeat('s', count($params)), ...array_map('strval', $params));
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
};

$source_site = $query("SELECT option_value FROM {$prefix}options WHERE option_name = 'siteurl'")[0]['option_value'] ?? 'sorgente';
$source_host = parse_url($source_site, PHP_URL_HOST) ?: $source_site;
$source_uploads = $source_root . '/wp-content/uploads';

WP_CLI::log("Sorgente: $source_site (DB $db_name, prefisso $prefix)");


/**
 * =======================================================
 * 2. Lettura del matrimonio e delle immagini collegate
 * =======================================================
 */
$post_rows = is_numeric($source_ref)
    ? $query("SELECT * FROM {$prefix}posts WHERE ID = ? AND post_type = 'matrimonio'", $source_ref)
    : $query("SELECT * FROM {$prefix}posts WHERE post_name = ? AND post_type = 'matrimonio'", $source_ref);

if (!$post_rows) {
    WP_CLI::error("Matrimonio '$source_ref' non trovato nel sito sorgente.");
}

$source_post = $post_rows[0];
$source_id   = (int) $source_post['ID'];
$source_key  = $source_host . '#' . $source_id;

$existing = get_posts([
    'post_type'   => 'matrimonio',
    'post_status' => 'any',
    'meta_key'    => $meta_source,
    'meta_value'  => $source_key,
    'fields'      => 'ids',
]);

if ($existing) {
    WP_CLI::error(sprintf('Già importato come post %d. Per reimportarlo elimina prima quel post.', $existing[0]));
}

$source_meta = $query("SELECT meta_key, meta_value FROM {$prefix}postmeta WHERE post_id = ? ORDER BY meta_id", $source_id);

// Meta di servizio che non hanno senso nel sito di destinazione
$skip_meta = function ($key) {
    return in_array($key, ['_edit_lock', '_edit_last', '_elementor_page_assets', '_wp_old_slug'], true)
        || strpos($key, 'rank_math_') === 0;
};

// Tipo ACF di ogni meta, letto dalla definizione dei campi di QUESTO sito (acf-json)
$field_refs = [];
foreach ($source_meta as $row) {
    if ($row['meta_key'][0] === '_' && strpos((string) $row['meta_value'], 'field_') === 0) {
        $field_refs[substr($row['meta_key'], 1)] = $row['meta_value'];
    }
}

$field_type = function ($meta_key) use ($field_refs) {
    if ($meta_key === '_thumbnail_id') {
        return 'image';
    }
    if (!isset($field_refs[$meta_key]) || !function_exists('acf_get_field')) {
        return null;
    }
    $field = acf_get_field($field_refs[$meta_key]);
    return $field ? $field['type'] : 'sconosciuto';
};

// Raccolta degli ID immagine in ordine di apparizione
$image_ids = [];
foreach ($source_meta as $row) {
    $type = $field_type($row['meta_key']);
    if ($type === 'image' && is_numeric($row['meta_value'])) {
        $image_ids[] = (int) $row['meta_value'];
    } elseif ($type === 'gallery') {
        foreach ((array) maybe_unserialize($row['meta_value']) as $id) {
            $image_ids[] = (int) $id;
        }
    }
}
$image_ids = array_values(array_unique(array_filter($image_ids)));

$unknown = array_keys(array_filter($field_refs, function ($ref, $key) use ($field_type) {
    return $field_type($key) === 'sconosciuto';
}, ARRAY_FILTER_USE_BOTH));

if ($unknown) {
    WP_CLI::warning('Campi non presenti nella configurazione ACF di questo sito (copiati così come sono): ' . implode(', ', $unknown));
}

// Allegati: riga del post + meta utili
$attachments = [];
foreach ($image_ids as $id) {
    $rows = $query("SELECT * FROM {$prefix}posts WHERE ID = ? AND post_type = 'attachment'", $id);
    if (!$rows) {
        WP_CLI::warning("Immagine $id referenziata ma assente nel sito sorgente: saltata.");
        continue;
    }

    $meta = [];
    foreach ($query("SELECT meta_key, meta_value FROM {$prefix}postmeta WHERE post_id = ?", $id) as $m) {
        $meta[$m['meta_key']] = $m['meta_value'];
    }

    $relative = $meta['_wp_attached_file'] ?? '';
    $file_meta = maybe_unserialize($meta['_wp_attachment_metadata'] ?? '');

    // L'originale caricato: se WordPress ha creato la copia "-scaled", l'originale è in 'original_image'
    $original = !empty($file_meta['original_image'])
        ? dirname($relative) . '/' . $file_meta['original_image']
        : $relative;

    $attachments[$id] = [
        'post'     => $rows[0],
        'meta'     => $meta,
        'subdir'   => dirname($relative),
        'path'     => $source_uploads . '/' . $original,
    ];
}

$missing = array_filter($attachments, function ($a) {
    return !is_file($a['path']);
});

foreach ($missing as $id => $a) {
    WP_CLI::warning("File originale mancante per l'immagine $id: {$a['path']}");
}

$total_bytes = array_sum(array_map(function ($a) {
    return is_file($a['path']) ? filesize($a['path']) : 0;
}, $attachments));

WP_CLI::log(sprintf(
    "Matrimonio: \"%s\" (ID %d, %s) — %d immagini, %.1f MB di originali",
    $source_post['post_title'],
    $source_id,
    $source_post['post_status'],
    count($attachments),
    $total_bytes / 1048576
));

$layouts = array_count_values(array_map(function ($a) {
    return ($a['meta']['gallery_image_size'] ?? '') ?: 'standard';
}, $attachments));
WP_CLI::log('Ingombri nella gallery: ' . http_build_query($layouts, '', ', '));

if ($dry_run) {
    WP_CLI::success('Dry run: nessuna modifica. Rilancia senza "dry-run" per importare.');
    return;
}


/**
 * =======================================================
 * 3. Import delle immagini (rigenerate con le regole di custom-tools)
 * =======================================================
 */
$map     = [];
$reused  = 0;
$started = microtime(true);
$progress = \WP_CLI\Utils\make_progress_bar('Immagini', count($attachments));

foreach ($attachments as $old_id => $a) {
    $key = $source_host . '#' . $old_id;

    $found = get_posts([
        'post_type'   => 'attachment',
        'post_status' => 'inherit',
        'meta_key'    => $meta_source,
        'meta_value'  => $key,
        'fields'      => 'ids',
    ]);

    if ($found) {
        $map[$old_id] = (int) $found[0];
        $reused++;
        $progress->tick();
        continue;
    }

    if (!is_file($a['path'])) {
        $progress->tick();
        continue;
    }

    // Stessa cartella anno/mese del sito sorgente
    $uploads = wp_upload_dir(preg_match('#^\d{4}/\d{2}$#', $a['subdir']) ? $a['subdir'] : null);
    $name    = wp_unique_filename($uploads['path'], basename($a['path']));
    $dest    = trailingslashit($uploads['path']) . $name;

    if (!copy($a['path'], $dest)) {
        WP_CLI::warning("Copia non riuscita: {$a['path']}");
        $progress->tick();
        continue;
    }

    $p = $a['post'];
    $new_id = wp_insert_attachment([
        'post_title'     => $p['post_title'],
        'post_content'   => $p['post_content'],
        'post_excerpt'   => $p['post_excerpt'],
        'post_mime_type' => $p['post_mime_type'],
        'post_date'      => $p['post_date'],
        'post_date_gmt'  => $p['post_date_gmt'],
        'post_status'    => 'inherit',
        'guid'           => trailingslashit($uploads['url']) . $name,
    ], $dest, 0, true);

    if (is_wp_error($new_id)) {
        WP_CLI::warning("Allegato non creato per {$name}: " . $new_id->get_error_message());
        $progress->tick();
        continue;
    }

    update_post_meta($new_id, $meta_source, $key);

    // Metadati scelti dalla cliente: PRIMA di generare i formati, così i ritagli
    // della gallery vengono creati subito (vedi custom-tools/inc/images.php)
    foreach (['_wp_attachment_image_alt', 'gallery_image_size', '_gallery_image_size'] as $k) {
        if (isset($a['meta'][$k]) && $a['meta'][$k] !== '') {
            update_post_meta($new_id, $k, $a['meta'][$k]);
        }
    }

    wp_update_attachment_metadata($new_id, wp_generate_attachment_metadata($new_id, $dest));

    $map[$old_id] = (int) $new_id;
    $progress->tick();
}

$progress->finish();


/**
 * =======================================================
 * 4. Creazione del post e dei campi, con gli ID rimappati
 * =======================================================
 */
$new_post_id = wp_insert_post([
    'post_type'     => 'matrimonio',
    'post_title'    => $source_post['post_title'],
    'post_name'     => $source_post['post_name'],
    'post_content'  => $source_post['post_content'],
    'post_excerpt'  => $source_post['post_excerpt'],
    'post_status'   => $source_post['post_status'],
    'post_date'     => $source_post['post_date'],
    'post_date_gmt' => $source_post['post_date_gmt'],
    'menu_order'    => (int) $source_post['menu_order'],
    'post_author'   => get_current_user_id() ?: 1,
], true);

if (is_wp_error($new_post_id)) {
    WP_CLI::error('Post non creato: ' . $new_post_id->get_error_message());
}

update_post_meta($new_post_id, $meta_source, $source_key);

$remap = function ($id) use ($map) {
    return $map[(int) $id] ?? null;
};

foreach ($source_meta as $row) {
    $key = $row['meta_key'];

    if ($skip_meta($key)) {
        continue;
    }

    $value = maybe_unserialize($row['meta_value']);
    $type  = $field_type($key);

    if ($type === 'image') {
        $value = $remap($value);
        if (!$value) {
            continue;
        }
    } elseif ($type === 'gallery') {
        $value = array_values(array_filter(array_map(function ($id) use ($remap) {
            $new = $remap($id);
            return $new ? (string) $new : null;
        }, (array) $value)));
    }

    // add_post_meta e non update: alcuni meta possono essere ripetuti
    add_post_meta($new_post_id, $key, wp_slash($value));
}

// Le foto appartengono al nuovo post (allegate a lui nella Libreria media)
foreach ($map as $new_id) {
    if (!wp_get_post_parent_id($new_id)) {
        wp_update_post(['ID' => $new_id, 'post_parent' => $new_post_id]);
    }
}

clean_post_cache($new_post_id);


/**
 * =======================================================
 * 5. Riepilogo
 * =======================================================
 */
$crops = 0;
foreach ($map as $new_id) {
    $meta = wp_get_attachment_metadata($new_id);
    foreach (array_keys($meta['sizes'] ?? []) as $size) {
        $crops += strpos($size, '_crop_') !== false ? 1 : 0;
    }
}

WP_CLI::success(sprintf(
    "Importato \"%s\" come post %d: %d immagini (%d nuove, %d riusate), %d ritagli gallery, %.0f s.\n%s",
    get_the_title($new_post_id),
    $new_post_id,
    count($map),
    count($map) - $reused,
    $reused,
    $crops,
    microtime(true) - $started,
    get_permalink($new_post_id)
));
