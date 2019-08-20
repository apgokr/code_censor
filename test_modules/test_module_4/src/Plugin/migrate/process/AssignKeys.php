<?php

namespace Drupal\test_module_4\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Transforms an array of values into an array of associative arrays.
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 *
 * @MigrateProcessPlugin(
 *   id = "assign_keys",
 *   handle_multiples = TRUE
 *  * )
 */
class AssignKeys extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $keyname = (is_string($this->configuration['keyname']) && $this->configuration['keyname'] != '') ? $this->configuration['keyname'] : 'value';

    if (is_array($value) || $value instanceof \Traversable) {
      $result = [];
      foreach ($value as $sub_value) {
        $result[] = [$keyname => $sub_value];
      }
      return $result;
    }
    else {
      throw new MigrateException(sprintf('%s is not traversable', var_export($value, TRUE)));
    }
  }

}
