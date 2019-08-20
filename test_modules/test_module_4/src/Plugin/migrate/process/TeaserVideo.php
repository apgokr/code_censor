<?php

namespace Drupal\test_module_4\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Provides a 'TeaserVideo' migrate process plugin.
 *
 * @MigrateProcessPlugin(
 *   id = "teaser_video",
 * )
 */
class TeaserVideo extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $video_url = NULL;
    if (!empty($value)) {
      $value = stripslashes($value);
      preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $value, $match);
      if (isset($match[1])) {
        $video_url = "http://youtu.be/" . $match[1];
      }
    }
    return $video_url;
  }

}
