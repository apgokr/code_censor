<?php

namespace Drupal\test_module_4\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Provides a 'ExplodeMultivalued' migrate process plugin.
 *
 * @MigrateProcessPlugin(
 *   id = "explode_multivalued",
 *   handle_multiples = TRUE
 * )
 */
class ExplodeMultivalued extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $delimiter1 = $this->configuration['delimiter1'];
    $delimiter2 = $this->configuration['delimiter2'];
    $select_index = $this->configuration['select_index'];
    $items = [];
    foreach (explode($delimiter1, $value) as $item) {
      $item = explode($delimiter2, $item);
      $items[] = $item[$select_index];
    }
    return $items;
  }

}
