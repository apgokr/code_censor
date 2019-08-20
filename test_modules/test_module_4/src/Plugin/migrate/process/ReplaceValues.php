<?php

namespace Drupal\test_module_4\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Provides a 'ReplaceValues' migrate process plugin.
 *
 * @MigrateProcessPlugin(
 *   id = "replace_values",
 *   handle_multiples = TRUE
 * )
 */
class ReplaceValues extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $mapping = $this->configuration['map'];
    $values = [];
    if (!is_array($value)) {
      $value = [$value];
    }
    foreach ($value as $item) {
      $values[] = $mapping[$item] ?? $item;
    }
    return $values;
  }

}
