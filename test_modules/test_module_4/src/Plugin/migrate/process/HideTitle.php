<?php

namespace Drupal\test_module_4\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Defines content type name based on Path.
 *
 * @MigrateProcessPlugin(
 *   id = "hide_title"
 * )
 */
class HideTitle extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!empty($value)) {
      if ($value == 'BoxWithImageAndBottomText') {
        return NULL;
      }
      else {
        return 'hide-title';
      }
    }

  }

}
