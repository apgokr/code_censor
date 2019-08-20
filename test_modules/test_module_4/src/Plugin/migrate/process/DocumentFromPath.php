<?php

namespace Drupal\test_module_4\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Defines content type name based on Path.
 *
 * @MigrateProcessPlugin(
 *   id = "document_from_path"
 * )
 */
class DocumentFromPath extends DocumentFromHtml {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $value[0] = '<a href="' . $value[0] . '"></a>';
    return parent::transform($value, $migrate_executable, $row, $destination_property);
  }

}
