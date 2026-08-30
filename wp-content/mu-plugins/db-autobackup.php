<?php

/**
 * Plugin Name: DB Autobackup (local)
 * Description: Esporta automaticamente il database quando il sito è in uso in Local, tramite WP-Cron. Nessun cron di sistema: se il sito resta fermo non viene eseguito nulla. Per un backup manuale on-demand: export/export-db.sh
 * Author: Fabrizio
 * Version: 1.0
 *
 * ---------------------------------------------------------------------------
 * Come funziona
 * ---------------------------------------------------------------------------
 * - A ogni visita (front o admin) controlla da quanto tempo manca un backup.
 * - Se è passato più di DB_AUTOBACKUP_INTERVAL, pianifica un evento WP-Cron
 *   singolo che parte pochi secondi dopo: la pagina corrente non rallenta.
 * - L'evento esegue un dump in PHP puro (via $wpdb, nessun binario esterno,
 *   nessun percorso assoluto) in export/db-dumps/ e tiene gli ultimi N file.
 * - WP-Cron in Local scatta solo quando qualcuno apre il sito: progetto fermo
 *   sei mesi => zero esecuzioni.
 *
 * Parametri sovrascrivibili da wp-config.php:
 *   define('DB_AUTOBACKUP_INTERVAL', DAY_IN_SECONDS); // frequenza massima
 *   define('DB_AUTOBACKUP_KEEP', 3);                  // dump da conservare
 *   define('DB_AUTOBACKUP_DIR', ABSPATH . 'export/db-dumps');
 *   define('DB_AUTOBACKUP_DISABLE', true);            // disattiva del tutto
 */

if (!defined('ABSPATH')) {
    exit;
}

// Solo in locale: mai in staging o produzione.
if (!function_exists('wp_get_environment_type') || wp_get_environment_type() !== 'local') {
    return;
}

if (defined('DB_AUTOBACKUP_DISABLE') && DB_AUTOBACKUP_DISABLE) {
    return;
}

if (!defined('DB_AUTOBACKUP_INTERVAL')) {
    define('DB_AUTOBACKUP_INTERVAL', DAY_IN_SECONDS);
}
if (!defined('DB_AUTOBACKUP_KEEP')) {
    define('DB_AUTOBACKUP_KEEP', 3);
}
if (!defined('DB_AUTOBACKUP_DIR')) {
    define('DB_AUTOBACKUP_DIR', ABSPATH . 'export/db-dumps');
}

define('DB_AUTOBACKUP_HOOK', 'db_autobackup_run_event');
define('DB_AUTOBACKUP_OPTION', 'db_autobackup_last_run');
define('DB_AUTOBACKUP_LOCK', 'db_autobackup_running');


/**
 * A ogni richiesta "normale" verifica se è ora di un backup e, in caso,
 * mette in coda un evento WP-Cron singolo. Operazione leggerissima.
 */
add_action('init', 'db_autobackup_maybe_schedule');
function db_autobackup_maybe_schedule()
{
    if (wp_doing_cron() || wp_installing() || (defined('WP_CLI') && WP_CLI)) {
        return;
    }

    $last = (int) get_option(DB_AUTOBACKUP_OPTION, 0);
    if ((time() - $last) < DB_AUTOBACKUP_INTERVAL) {
        return;
    }

    if (get_transient(DB_AUTOBACKUP_LOCK) || wp_next_scheduled(DB_AUTOBACKUP_HOOK)) {
        return;
    }

    wp_schedule_single_event(time() + 20, DB_AUTOBACKUP_HOOK);
}


/**
 * Esecuzione del backup (contesto WP-Cron).
 */
add_action(DB_AUTOBACKUP_HOOK, 'db_autobackup_run');
function db_autobackup_run()
{
    if (get_transient(DB_AUTOBACKUP_LOCK)) {
        return;
    }
    set_transient(DB_AUTOBACKUP_LOCK, 1, 10 * MINUTE_IN_SECONDS);

    // Segna subito il tentativo: se fallisce non ritenta a ogni pagina.
    update_option(DB_AUTOBACKUP_OPTION, time(), false);

    try {
        if (!wp_mkdir_p(DB_AUTOBACKUP_DIR)) {
            throw new RuntimeException('cartella non scrivibile: ' . DB_AUTOBACKUP_DIR);
        }

        $file = rtrim(DB_AUTOBACKUP_DIR, '/\\') . '/dump-' . current_time('Ymd_His') . '.sql';
        db_autobackup_dump($file);
        db_autobackup_rotate(DB_AUTOBACKUP_DIR, (int) DB_AUTOBACKUP_KEEP);
    } catch (Throwable $e) {
        error_log('[db-autobackup] ' . $e->getMessage());
    }

    delete_transient(DB_AUTOBACKUP_LOCK);
}


/**
 * Dump del database in PHP puro. Struttura + dati di tutte le tabelle,
 * lettura a blocchi per tenere bassa la memoria.
 *
 * @param string $file Percorso assoluto del file .sql da creare.
 */
function db_autobackup_dump($file)
{
    global $wpdb;

    $fh = fopen($file, 'wb');
    if (!$fh) {
        throw new RuntimeException('impossibile creare ' . $file);
    }

    fwrite($fh, "-- DB autobackup — " . current_time('mysql') . "\n");
    fwrite($fh, "-- db: " . DB_NAME . "\n\n");
    fwrite($fh, "/*!40101 SET NAMES " . DB_CHARSET . " */;\n");
    fwrite($fh, "SET SESSION sql_mode = 'NO_AUTO_VALUE_ON_ZERO';\n");
    fwrite($fh, "SET foreign_key_checks = 0;\n");
    fwrite($fh, "SET autocommit = 0;\n");

    $tables = $wpdb->get_col('SHOW TABLES');

    foreach ($tables as $table) {
        $create = $wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
        $ddl    = isset($create[1]) ? $create[1] : '';
        $is_view = (stripos($ddl, 'CREATE ALGORITHM') !== false) || (stripos($ddl, ' VIEW ') !== false && stripos($ddl, 'CREATE VIEW') !== false);

        fwrite($fh, "\n--\n-- " . ($is_view ? 'View' : 'Table') . " `{$table}`\n--\n\n");
        fwrite($fh, $is_view ? "DROP VIEW IF EXISTS `{$table}`;\n" : "DROP TABLE IF EXISTS `{$table}`;\n");
        fwrite($fh, $ddl . ";\n\n");

        if ($is_view) {
            continue;
        }

        $offset = 0;
        $chunk  = 500;
        $cols   = null;

        do {
            $rows = $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM `{$table}` LIMIT %d OFFSET %d", $chunk, $offset),
                ARRAY_A
            );
            if (!$rows) {
                break;
            }
            if ($cols === null) {
                $cols = '`' . implode('`, `', array_keys($rows[0])) . '`';
            }

            $tuples = array();
            foreach ($rows as $row) {
                $vals = array();
                foreach ($row as $value) {
                    $vals[] = ($value === null) ? 'NULL' : "'" . $wpdb->_real_escape($value) . "'";
                }
                $tuples[] = '(' . implode(',', $vals) . ')';
            }
            fwrite($fh, "INSERT INTO `{$table}` ({$cols}) VALUES\n" . implode(",\n", $tuples) . ";\n");

            $count   = count($rows);
            $offset += $chunk;
        } while ($count === $chunk);
    }

    fwrite($fh, "\nSET foreign_key_checks = 1;\n");
    fwrite($fh, "COMMIT;\n");
    fwrite($fh, "SET autocommit = 1;\n");
    fclose($fh);
}


/**
 * Tiene solo gli ultimi $keep file dump-*.sql nella cartella.
 *
 * @param string $dir
 * @param int    $keep
 */
function db_autobackup_rotate($dir, $keep)
{
    $files = glob(rtrim($dir, '/\\') . '/dump-*.sql');
    if (!is_array($files) || count($files) <= $keep) {
        return;
    }

    usort($files, static function ($a, $b) {
        return filemtime($b) <=> filemtime($a);
    });

    foreach (array_slice($files, $keep) as $old) {
        @unlink($old);
    }
}
