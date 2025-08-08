<?php

/**
 * Plugin Name: ACF Dev Cleanup - Pre-Production Optimizer
 * Plugin URI: https://yoursite.com
 * Description: Analizza e pulisce campi ACF inutilizzati prima di andare in produzione. Ottimizza il database rimuovendo campi obsoleti, orfani e mai utilizzati.
 * Version: 1.0.0
 * Author: Il Tuo Nome
 * Author URI: https://yoursite.com
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: acf-dev-cleanup
 * Domain Path: /languages
 * 
 * @package ACFDevCleanup
 */

// Impedisci accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Definisci costanti del plugin
define('ACF_DEV_CLEANUP_VERSION', '1.0.0');
define('ACF_DEV_CLEANUP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ACF_DEV_CLEANUP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ACF_DEV_CLEANUP_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Classe principale del plugin
 */
class ACF_Dev_Cleanup
{
    private static $instance = null;
    private $analyzer;

    /**
     * Singleton instance
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Costruttore
     */
    private function __construct()
    {
        add_action('init', array($this, 'init'));
        add_action('admin_init', array($this, 'check_requirements'));

        // Hook di attivazione/disattivazione
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * Inizializzazione
     */
    public function init()
    {
        // Carica traduzioni
        load_plugin_textdomain('acf-dev-cleanup', false, dirname(plugin_basename(__FILE__)) . '/languages');

        // Menu admin solo se ACF è presente
        if ($this->is_acf_active()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        }

        // Include analyzer
        require_once ACF_DEV_CLEANUP_PLUGIN_DIR . 'includes/class-analyzer.php';
        $this->analyzer = new ACF_Cleanup_Analyzer();
    }

    /**
     * Controlla i requisiti
     */
    public function check_requirements()
    {
        if (!$this->is_acf_active()) {
            add_action('admin_notices', array($this, 'acf_missing_notice'));
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        // Avviso solo in ambiente di sviluppo
        if (!$this->is_dev_environment()) {
            add_action('admin_notices', array($this, 'production_warning'));
        }
    }

    /**
     * Verifica se ACF è attivo
     */
    private function is_acf_active()
    {
        return function_exists('acf_get_field_groups');
    }

    /**
     * Verifica se siamo in ambiente di sviluppo
     */
    private function is_dev_environment()
    {
        $indicators = [
            defined('WP_DEBUG') && WP_DEBUG,
            strpos(get_site_url(), 'localhost') !== false,
            strpos(get_site_url(), '.local') !== false,
            strpos(get_site_url(), '.dev') !== false,
            strpos(get_site_url(), 'staging') !== false,
        ];

        return in_array(true, $indicators, true);
    }

    /**
     * Notice ACF mancante
     */
    public function acf_missing_notice()
    {
?>
        <div class="notice notice-error">
            <p>
                <strong><?php _e('ACF Dev Cleanup', 'acf-dev-cleanup'); ?>:</strong>
                <?php _e('Questo plugin richiede Advanced Custom Fields per funzionare.', 'acf-dev-cleanup'); ?>
                <a href="<?php echo admin_url('plugin-install.php?s=advanced+custom+fields&tab=search&type=term'); ?>">
                    <?php _e('Installa ACF ora', 'acf-dev-cleanup'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Avviso ambiente produzione
     */
    public function production_warning()
    {
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'acf-dev-cleanup') !== false) {
        ?>
            <div class="notice notice-warning">
                <p>
                    <strong>⚠️ <?php _e('Attenzione', 'acf-dev-cleanup'); ?>:</strong>
                    <?php _e('Questo plugin è progettato per ambienti di sviluppo. Usare con cautela in produzione!', 'acf-dev-cleanup'); ?>
                </p>
            </div>
        <?php
        }
    }

    /**
     * Aggiungi menu admin
     */
    public function add_admin_menu()
    {
        // Menu principale
        add_menu_page(
            __('ACF Dev Cleanup', 'acf-dev-cleanup'),
            __('ACF Cleanup', 'acf-dev-cleanup'),
            'manage_options',
            'acf-dev-cleanup',
            array($this, 'admin_page'),
            'dashicons-admin-tools',
            80
        );

        // Sottomenu
        add_submenu_page(
            'acf-dev-cleanup',
            __('Analisi Campi', 'acf-dev-cleanup'),
            __('Analisi', 'acf-dev-cleanup'),
            'manage_options',
            'acf-dev-cleanup',
            array($this, 'admin_page')
        );

        add_submenu_page(
            'acf-dev-cleanup',
            __('Pulizia Database', 'acf-dev-cleanup'),
            __('Pulizia', 'acf-dev-cleanup'),
            'manage_options',
            'acf-dev-cleanup-clean',
            array($this, 'cleanup_page')
        );

        add_submenu_page(
            'acf-dev-cleanup',
            __('Impostazioni', 'acf-dev-cleanup'),
            __('Impostazioni', 'acf-dev-cleanup'),
            'manage_options',
            'acf-dev-cleanup-settings',
            array($this, 'settings_page')
        );
    }

    /**
     * Carica script admin
     */
    public function enqueue_admin_scripts($hook)
    {
        if (strpos($hook, 'acf-dev-cleanup') === false) {
            return;
        }

        wp_enqueue_style(
            'acf-dev-cleanup-admin',
            ACF_DEV_CLEANUP_PLUGIN_URL . 'assets/admin.css',
            array(),
            ACF_DEV_CLEANUP_VERSION
        );

        wp_enqueue_script(
            'acf-dev-cleanup-admin',
            ACF_DEV_CLEANUP_PLUGIN_URL . 'assets/admin.js',
            array('jquery'),
            ACF_DEV_CLEANUP_VERSION,
            true
        );

        wp_localize_script('acf-dev-cleanup-admin', 'acfCleanup', array(
            'nonce' => wp_create_nonce('acf_cleanup_nonce'),
            'confirm_cleanup' => __('Sei sicuro di voler procedere con la pulizia? Questa operazione non può essere annullata!', 'acf-dev-cleanup'),
            'backup_required' => __('È obbligatorio fare un backup prima di procedere!', 'acf-dev-cleanup')
        ));
    }

    /**
     * Pagina principale di analisi
     */
    public function admin_page()
    {
        if (!$this->is_acf_active()) {
            echo '<div class="wrap"><h1>' . __('ACF Dev Cleanup', 'acf-dev-cleanup') . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('Advanced Custom Fields non è attivo!', 'acf-dev-cleanup') . '</p></div></div>';
            return;
        }

        echo '<div class="wrap acf-dev-cleanup-wrap">';
        $this->render_header();

        // Gestisci azioni
        if (isset($_POST['action']) && wp_verify_nonce($_POST['_wpnonce'], 'acf_cleanup_nonce')) {
            switch ($_POST['action']) {
                case 'analyze':
                    $this->analyzer->analyze();
                    break;
                case 'export_report':
                    $this->analyzer->export_report();
                    break;
            }
        } else {
            $this->render_dashboard();
        }

        echo '</div>';
    }

    /**
     * Pagina di pulizia
     */
    public function cleanup_page()
    {
        echo '<div class="wrap acf-dev-cleanup-wrap">';
        $this->render_header();

        if (isset($_POST['action']) && wp_verify_nonce($_POST['_wpnonce'], 'acf_cleanup_nonce')) {
            if ($_POST['action'] === 'cleanup_selected') {
                $options = array(
                    'cleanup_unused' => isset($_POST['cleanup_unused']),
                    'cleanup_orphaned' => isset($_POST['cleanup_orphaned']),
                    'cleanup_empty_groups' => isset($_POST['cleanup_empty_groups'])
                );

                $this->analyzer->execute_cleanup($options);
            }
        }

        $this->render_cleanup_interface();
        echo '</div>';
    }

    /**
     * Pagina impostazioni
     */
    public function settings_page()
    {
        echo '<div class="wrap acf-dev-cleanup-wrap">';
        $this->render_header();
        $this->render_settings();
        echo '</div>';
    }

    /**
     * Render header
     */
    private function render_header()
    {
        ?>
        <div class="acf-cleanup-header">
            <h1>
                <span class="dashicons dashicons-admin-tools"></span>
                <?php _e('ACF Dev Cleanup - Pre-Production Optimizer', 'acf-dev-cleanup'); ?>
            </h1>
            <p class="description">
                <?php _e('Ottimizza il tuo database WordPress rimuovendo campi ACF obsoleti prima di andare in produzione.', 'acf-dev-cleanup'); ?>
            </p>
        </div>

        <nav class="nav-tab-wrapper">
            <a href="<?php echo admin_url('admin.php?page=acf-dev-cleanup'); ?>"
                class="nav-tab <?php echo (isset($_GET['page']) && $_GET['page'] === 'acf-dev-cleanup') ? 'nav-tab-active' : ''; ?>">
                <?php _e('Analisi', 'acf-dev-cleanup'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=acf-dev-cleanup-clean'); ?>"
                class="nav-tab <?php echo (isset($_GET['page']) && $_GET['page'] === 'acf-dev-cleanup-clean') ? 'nav-tab-active' : ''; ?>">
                <?php _e('Pulizia', 'acf-dev-cleanup'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=acf-dev-cleanup-settings'); ?>"
                class="nav-tab <?php echo (isset($_GET['page']) && $_GET['page'] === 'acf-dev-cleanup-settings') ? 'nav-tab-active' : ''; ?>">
                <?php _e('Impostazioni', 'acf-dev-cleanup'); ?>
            </a>
        </nav>
    <?php
    }

    /**
     * Render dashboard
     */
    private function render_dashboard()
    {
    ?>
        <div class="acf-cleanup-dashboard">
            <div class="welcome-panel">
                <div class="welcome-panel-content">
                    <h2><?php _e('Benvenuto in ACF Dev Cleanup!', 'acf-dev-cleanup'); ?></h2>
                    <p class="about-description">
                        <?php _e('Questo strumento ti aiuta a identificare e rimuovere campi ACF inutilizzati per ottimizzare il database prima di andare in produzione.', 'acf-dev-cleanup'); ?>
                    </p>

                    <div class="welcome-panel-column-container">
                        <div class="welcome-panel-column">
                            <h3><?php _e('🔍 Analisi Automatica', 'acf-dev-cleanup'); ?></h3>
                            <ul>
                                <li><?php _e('Scansiona tutti i campi ACF', 'acf-dev-cleanup'); ?></li>
                                <li><?php _e('Identifica campi inutilizzati', 'acf-dev-cleanup'); ?></li>
                                <li><?php _e('Trova campi orfani nel database', 'acf-dev-cleanup'); ?></li>
                            </ul>
                        </div>

                        <div class="welcome-panel-column">
                            <h3><?php _e('🧹 Pulizia Sicura', 'acf-dev-cleanup'); ?></h3>
                            <ul>
                                <li><?php _e('Backup obbligatorio', 'acf-dev-cleanup'); ?></li>
                                <li><?php _e('Pulizia selettiva', 'acf-dev-cleanup'); ?></li>
                                <li><?php _e('Conferme di sicurezza', 'acf-dev-cleanup'); ?></li>
                            </ul>
                        </div>

                        <div class="welcome-panel-column">
                            <h3><?php _e('📊 Report Dettagliati', 'acf-dev-cleanup'); ?></h3>
                            <ul>
                                <li><?php _e('Statistiche complete', 'acf-dev-cleanup'); ?></li>
                                <li><?php _e('Esportazione JSON', 'acf-dev-cleanup'); ?></li>
                                <li><?php _e('Raccomandazioni specifiche', 'acf-dev-cleanup'); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="quick-actions">
                <h2><?php _e('Azioni Rapide', 'acf-dev-cleanup'); ?></h2>

                <div class="action-cards">
                    <div class="action-card">
                        <h3><?php _e('Inizia Analisi', 'acf-dev-cleanup'); ?></h3>
                        <p><?php _e('Scansiona tutti i campi ACF e ottieni un report dettagliato.', 'acf-dev-cleanup'); ?></p>
                        <form method="post">
                            <?php wp_nonce_field('acf_cleanup_nonce'); ?>
                            <input type="hidden" name="action" value="analyze">
                            <button type="submit" class="button button-primary button-large">
                                <?php _e('Avvia Scansione', 'acf-dev-cleanup'); ?>
                            </button>
                        </form>
                    </div>

                    <div class="action-card">
                        <h3><?php _e('Vai alla Pulizia', 'acf-dev-cleanup'); ?></h3>
                        <p><?php _e('Seleziona e rimuovi campi obsoleti dal database.', 'acf-dev-cleanup'); ?></p>
                        <a href="<?php echo admin_url('admin.php?page=acf-dev-cleanup-clean'); ?>" class="button button-secondary button-large">
                            <?php _e('Pulizia Database', 'acf-dev-cleanup'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php
    }

    /**
     * Render cleanup interface
     */
    private function render_cleanup_interface()
    {
    ?>
        <div class="cleanup-interface">
            <div class="notice notice-warning">
                <h3><?php _e('⚠️ Attenzione: Operazione Irreversibile', 'acf-dev-cleanup'); ?></h3>
                <p><?php _e('La pulizia del database rimuove permanentemente i dati. Assicurati di aver fatto un backup completo prima di procedere.', 'acf-dev-cleanup'); ?></p>
            </div>

            <div class="backup-checklist">
                <h3><?php _e('📋 Checklist Pre-Pulizia', 'acf-dev-cleanup'); ?></h3>
                <ul>
                    <li>✅ <?php _e('Backup del database completo', 'acf-dev-cleanup'); ?></li>
                    <li>✅ <?php _e('Backup dei file del tema', 'acf-dev-cleanup'); ?></li>
                    <li>✅ <?php _e('Test in ambiente di sviluppo', 'acf-dev-cleanup'); ?></li>
                    <li>✅ <?php _e('Analisi completata e verificata', 'acf-dev-cleanup'); ?></li>
                </ul>
            </div>

            <p>
                <strong><?php _e('Prima di procedere, esegui un\'analisi per vedere cosa verrà rimosso:', 'acf-dev-cleanup'); ?></strong>
            </p>

            <p>
                <a href="<?php echo admin_url('admin.php?page=acf-dev-cleanup'); ?>" class="button button-secondary">
                    <?php _e('← Torna all\'Analisi', 'acf-dev-cleanup'); ?>
                </a>
            </p>
        </div>
    <?php
    }

    /**
     * Render settings
     */
    private function render_settings()
    {
    ?>
        <div class="settings-page">
            <h2><?php _e('Impostazioni Plugin', 'acf-dev-cleanup'); ?></h2>

            <div class="settings-section">
                <h3><?php _e('Informazioni Sistema', 'acf-dev-cleanup'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th><?php _e('Versione Plugin', 'acf-dev-cleanup'); ?></th>
                        <td><?php echo ACF_DEV_CLEANUP_VERSION; ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Versione WordPress', 'acf-dev-cleanup'); ?></th>
                        <td><?php echo get_bloginfo('version'); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Versione ACF', 'acf-dev-cleanup'); ?></th>
                        <td><?php echo $this->is_acf_active() ? acf_get_setting('version') : __('Non installato', 'acf-dev-cleanup'); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Ambiente', 'acf-dev-cleanup'); ?></th>
                        <td>
                            <span class="environment-badge <?php echo $this->is_dev_environment() ? 'dev' : 'prod'; ?>">
                                <?php echo $this->is_dev_environment() ? __('Sviluppo', 'acf-dev-cleanup') : __('Produzione', 'acf-dev-cleanup'); ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="settings-section">
                <h3><?php _e('Supporto e Documentazione', 'acf-dev-cleanup'); ?></h3>
                <p><?php _e('Per supporto tecnico o segnalazione bug, contatta lo sviluppatore.', 'acf-dev-cleanup'); ?></p>

                <div class="support-links">
                    <a href="#" class="button" target="_blank"><?php _e('Documentazione', 'acf-dev-cleanup'); ?></a>
                    <a href="#" class="button" target="_blank"><?php _e('Segnala Bug', 'acf-dev-cleanup'); ?></a>
                    <a href="#" class="button" target="_blank"><?php _e('Richiedi Feature', 'acf-dev-cleanup'); ?></a>
                </div>
            </div>
        </div>
<?php
    }

    /**
     * Attivazione plugin
     */
    public function activate()
    {
        // Verifica versione WordPress minima
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            deactivate_plugins(ACF_DEV_CLEANUP_PLUGIN_BASENAME);
            wp_die(__('Questo plugin richiede WordPress 5.0 o superiore.', 'acf-dev-cleanup'));
        }

        // Crea opzioni default
        add_option('acf_dev_cleanup_version', ACF_DEV_CLEANUP_VERSION);
        add_option('acf_dev_cleanup_activated', current_time('mysql'));
    }

    /**
     * Disattivazione plugin
     */
    public function deactivate()
    {
        // Pulisci eventuali transient
        delete_transient('acf_dev_cleanup_analysis');
    }
}

// Inizializza il plugin
ACF_Dev_Cleanup::get_instance();

/**
 * Funzione helper per ottenere l'istanza del plugin
 */
function acf_dev_cleanup()
{
    return ACF_Dev_Cleanup::get_instance();
}
?>