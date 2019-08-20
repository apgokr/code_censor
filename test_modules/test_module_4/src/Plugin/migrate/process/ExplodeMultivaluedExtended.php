<?php

namespace Drupal\test_module_4\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Provides a 'ExplodeMultivaluedExtended' migrate process plugin.
 *
 * @MigrateProcessPlugin(
 *  id = "explode_multivalued_extended"
 * )
 */
class ExplodeMultivaluedExtended extends ExplodeMultivalued {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $value = implode($this->configuration['glue'], $value);
    $values = parent::transform($value, $migrate_executable, $row, $destination_property);
    $return = [];
    foreach ($values as $val) {
      $return[] = [$this->configuration['value_key'] => $val];
    }
    return $return;
  }

}
