<?php

namespace Drupal\test_module_4\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Defines content type name based on Path.
 *
 * @MigrateProcessPlugin(
 *   id = "add_component_class"
 * )
 */
class AddComponentClass extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if ($row->getSourceProperty('instance_type') == 'StandardBoxHtml') {
      return $value . ' fw-img';
    }
    else {
      return $value;
    }
  }

}
