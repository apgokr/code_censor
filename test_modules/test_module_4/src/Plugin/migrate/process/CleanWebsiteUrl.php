<?php

namespace Drupal\test_module_4\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Defines content type name based on Path.
 *
 * @MigrateProcessPlugin(
 *   id = "clean_website_url"
 * )
 */
class CleanWebsiteUrl extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    preg_match('/href=(["\'])([^\1]*)\1/i', $value, $url);
    return $url[2];
  }

}
