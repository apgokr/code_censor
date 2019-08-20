<?php

namespace Drupal\test_module_4\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Defines content type name based on Path.
 *
 * @MigrateProcessPlugin(
 *   id = "extract_body_image",
 * )
 */
class ExtractBodyImage extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $output = NULL;
    if (!empty($value)) {
      preg_match("/<img[^>]+\>/i", $value, $matches);
      $output = $matches[0];
    }
    return $output;
  }

}
