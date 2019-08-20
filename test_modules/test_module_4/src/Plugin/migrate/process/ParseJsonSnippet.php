<?php

namespace Drupal\test_module_4\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Provides a 'ParseJsonSnippet' migrate process plugin.
 *
 * @MigrateProcessPlugin(
 *   id = "parse_json_snippet",
 *   handle_multiples = TRUE
 * )
 */
class ParseJsonSnippet extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $value = stripslashes($value);
    $ids = [];
    $prefix = '/forinternaluse/widgets/';
    if (preg_match_all('/["]\d\w{31}["]/', $value, $matches)) {
      foreach ($matches[0] as $item) {
        $ids[] = $prefix . trim($item, '"');
      }
    }
    return $ids;
  }

}
