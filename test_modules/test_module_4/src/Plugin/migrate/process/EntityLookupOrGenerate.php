<?php

namespace Drupal\test_module_4\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate_plus\Plugin\migrate\process\EntityGenerate;

/**
 * Provides a 'EntityLookupOrGenerate' migrate process plugin.
 *
 * @MigrateProcessPlugin(
 *   id = "entity_lookup_or_generate",
 *   handle_multiples = TRUE
 * )
 */
class EntityLookupOrGenerate extends EntityGenerate {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $empty = FALSE;
    if (is_array($value)) {
      if (empty(array_filter($value))) {
        $empty = TRUE;
      }
    }
    else {
      $empty = (empty($value)) ? TRUE : FALSE;
    }
    if (!$empty) {
      return parent::transform($value, $migrate_executable, $row, $destination_property);
    }
  }

}
