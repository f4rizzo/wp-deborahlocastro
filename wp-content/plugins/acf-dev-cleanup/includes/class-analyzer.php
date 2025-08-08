<?php

/**
 * Classe Analyzer per ACF Dev Cleanup
 *
 * @package ACFDevCleanup
 */

if (!defined('ABSPATH')) {
  exit;
}

class ACF_Cleanup_Analyzer
{
  private $registered_fields = [];
  private $db_fields = [];

  public function __construct()
  {
    // Questo costruttore è vuoto, l'azione viene avviata su richiesta.
  }

  /**
   * Esegue l'intera analisi.
   */
  public function analyze()
  {
    $this->get_all_registered_fields();
    $this->get_all_db_acf_fields();

    $analysis_results = [
      'unused_fields'    => $this->find_unused_fields(),
      'orphaned_fields'  => $this->find_orphaned_fields(),
      'empty_groups'     => $this->find_empty_field_groups(),
      'stats'            => [
        'total_registered' => count($this->registered_fields),
        'total_in_db'      => count($this->db_fields)
      ],
      'timestamp'        => current_time('mysql')
    ];

    // Salva i risultati in un transient per 24 ore
    set_transient('acf_dev_cleanup_analysis', $analysis_results, DAY_IN_SECONDS);

    $this->render_analysis_results($analysis_results);
  }

  /**
   * Recupera tutti i campi definiti nei gruppi di campi ACF.
   */
  private function get_all_registered_fields()
  {
    $this->registered_fields = [];
    $field_groups = acf_get_field_groups();

    if (empty($field_groups)) {
      return;
    }

    foreach ($field_groups as $group) {
      $fields = acf_get_fields($group['key']);
      if (!empty($fields)) {
        foreach ($fields as $field) {
          $this->registered_fields[$field['key']] = $field['name'];
        }
      }
    }
  }

  /**
   * Recupera tutti i meta_key dal database che sembrano campi ACF.
   */
  private function get_all_db_acf_fields()
  {
    global $wpdb;
    $this->db_fields = [];

    // Query per trovare tutti i meta_key che hanno un meta_key corrispondente con un underscore
    // Questo è il modo in cui ACF salva la referenza (field_key) al valore
    $query = "
            SELECT DISTINCT a.meta_key FROM {$wpdb->postmeta} a
            INNER JOIN {$wpdb->postmeta} b ON a.meta_key = CONCAT('_', b.meta_key)
            WHERE a.meta_key LIKE 'field_%'
        ";
    $results = $wpdb->get_col($query);

    if (!empty($results)) {
      foreach ($results as $field_key) {
        // Rimuoviamo l'underscore iniziale per ottenere il vero field_key
        $key = ltrim($field_key, '_');
        $this->db_fields[$key] = 'found_in_db'; // Il valore non è importante qui
      }
    }
  }

  /**
   * Trova campi registrati ma mai utilizzati (non presenti nel DB).
   */
  private function find_unused_fields()
  {
    return array_diff_key($this->registered_fields, $this->db_fields);
  }

  /**
   * Trova campi nel DB che non sono più registrati (orfani).
   */
  private function find_orphaned_fields()
  {
    global $wpdb;
    $orphaned = [];
    $db_field_keys = array_keys($this->db_fields);

    foreach ($db_field_keys as $key) {
      if (!isset($this->registered_fields[$key])) {
        // Per trovare il nome del campo orfano, dobbiamo fare una query
        $field_name = $wpdb->get_var($wpdb->prepare(
          "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT 1",
          $key
        ));
        // Attenzione: il meta_key è il nome del campo, il meta_value è il campo stesso
        $field_name_key = $wpdb->get_var($wpdb->prepare(
          "SELECT meta_key FROM {$wpdb->postmeta} WHERE meta_value = %s LIMIT 1",
          $key
        ));
        if ($field_name_key) {
          $orphaned[$key] = $field_name_key;
        }
      }
    }
    return $orphaned;
  }

  /**
   * Trova gruppi di campi che non contengono alcun campo.
   */
  private function find_empty_field_groups()
  {
    $empty_groups = [];
    $field_groups = acf_get_field_groups();

    if (empty($field_groups)) {
      return [];
    }

    foreach ($field_groups as $group) {
      $fields = acf_get_fields($group['key']);
      if (empty($fields)) {
        $empty_groups[$group['key']] = $group['title'];
      }
    }
    return $empty_groups;
  }

  /**
   * Mostra i risultati dell'analisi a schermo.
   */
  public function render_analysis_results($results)
  {
?>
    <h2><?php _e('Risultati Analisi', 'acf-dev-cleanup'); ?></h2>
    <p><?php printf(__('Analisi completata il %s.', 'acf-dev-cleanup'), $results['timestamp']); ?></p>

    <div id="analysis-summary">
      <h3><?php _e('Riepilogo', 'acf-dev-cleanup'); ?></h3>
      <ul>
        <li><strong><?php _e('Campi Registrati:', 'acf-dev-cleanup'); ?></strong> <?php echo esc_html($results['stats']['total_registered']); ?></li>
        <li><strong><?php _e('Campi Inutilizzati:', 'acf-dev-cleanup'); ?></strong> <span class="count-badge unused"><?php echo count($results['unused_fields']); ?></span></li>
        <li><strong><?php _e('Campi Orfani:', 'acf-dev-cleanup'); ?></strong> <span class="count-badge orphaned"><?php echo count($results['orphaned_fields']); ?></span></li>
        <li><strong><?php _e('Gruppi Vuoti:', 'acf-dev-cleanup'); ?></strong> <span class="count-badge empty"><?php echo count($results['empty_groups']); ?></span></li>
      </ul>
      <div class="actions">
        <a href="<?php echo admin_url('admin.php?page=acf-dev-cleanup-clean'); ?>" class="button button-primary"><?php _e('Vai alla Pulizia', 'acf-dev-cleanup'); ?></a>
        <form method="post" style="display: inline-block;">
          <?php wp_nonce_field('acf_cleanup_nonce'); ?>
          <input type="hidden" name="action" value="export_report">
          <button type="submit" class="button button-secondary"><?php _e('Esporta Report (JSON)', 'acf-dev-cleanup'); ?></button>
        </form>
      </div>
    </div>

    <?php if (!empty($results['orphaned_fields'])): ?>
      <div class="analysis-section">
        <h3><?php _e('Campi Orfani (Dati nel DB senza un campo registrato)', 'acf-dev-cleanup'); ?></h3>
        <p><?php _e('Questi campi esistono nel database ma non sono più definiti nei tuoi gruppi di campi. Sono candidati sicuri per la pulizia.', 'acf-dev-cleanup'); ?></p>
        <table class="wp-list-table widefat fixed striped">
          <thead>
            <tr>
              <th><?php _e('Nome Campo (meta_key)', 'acf-dev-cleanup'); ?></th>
              <th><?php _e('Field Key', 'acf-dev-cleanup'); ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($results['orphaned_fields'] as $key => $name): ?>
              <tr>
                <td><code><?php echo esc_html($name); ?></code></td>
                <td><code><?php echo esc_html($key); ?></code></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if (!empty($results['unused_fields'])): ?>
      <div class="analysis-section">
        <h3><?php _e('Campi Inutilizzati (Registrati ma senza dati nel DB)', 'acf-dev-cleanup'); ?></h3>
        <p><?php _e('Questi campi sono definiti ma non sono mai stati usati per salvare dati. Puoi considerarli per la rimozione dal gruppo di campi.', 'acf-dev-cleanup'); ?></p>
        <table class="wp-list-table widefat fixed striped">
          <thead>
            <tr>
              <th><?php _e('Nome Campo', 'acf-dev-cleanup'); ?></th>
              <th><?php _e('Field Key', 'acf-dev-cleanup'); ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($results['unused_fields'] as $key => $name): ?>
              <tr>
                <td><code><?php echo esc_html($name); ?></code></td>
                <td><code><?php echo esc_html($key); ?></code></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if (!empty($results['empty_groups'])): ?>
      <div class="analysis-section">
        <h3><?php _e('Gruppi di Campi Vuoti', 'acf-dev-cleanup'); ?></h3>
        <p><?php _e('Questi gruppi di campi non contengono nessun campo. Probabilmente puoi eliminarli.', 'acf-dev-cleanup'); ?></p>
        <table class="wp-list-table widefat fixed striped">
          <thead>
            <tr>
              <th><?php _e('Nome Gruppo', 'acf-dev-cleanup'); ?></th>
              <th><?php _e('Group Key', 'acf-dev-cleanup'); ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($results['empty_groups'] as $key => $name): ?>
              <tr>
                <td><?php echo esc_html($name); ?></td>
                <td><code><?php echo esc_html($key); ?></code></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
<?php
  }

  /**
   * Esporta i risultati dell'analisi in JSON.
   */
  public function export_report()
  {
    $results = get_transient('acf_dev_cleanup_analysis');
    if (false === $results) {
      echo '<div class="notice notice-error"><p>' . __('Nessun report di analisi trovato. Esegui prima una scansione.', 'acf-dev-cleanup') . '</p></div>';
      return;
    }

    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename=acf-cleanup-report-' . date('Y-m-d') . '.json');
    echo json_encode($results, JSON_PRETTY_PRINT);
    exit;
  }
}
