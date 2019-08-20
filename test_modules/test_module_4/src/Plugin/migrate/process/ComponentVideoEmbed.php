<?php

namespace Drupal\test_module_4\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use DOMDocument;

/**
 * Provides a 'ComponentVideoEmbed' migrate process plugin.
 *
 * @MigrateProcessPlugin(
 *   id = "component_video_embed",
 * )
 */
class ComponentVideoEmbed extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $values = [
      'video_url' => '',
      'video_placeholder' => '',
    ];
    if (!empty($value)) {
      $value = stripslashes($value);
      $dom = new DomDocument();
      $dom->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $value);
      $element = $dom->getElementById('RadEditorEncodedTag')->nodeValue;
      $element = base64_decode($element);
      if (@$dom->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $element)) {
        preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $element, $match);
        $values['video_url'] = "https://www.youtube.com/watch?v=" . $match[1];

        $image = $dom->getElementsByTagName('img');
        if ($image->length) {
          $values['video_placeholder'] = $dom->saveHTML($image->item(0));
        }
      }
    }
    return $values;
  }

}
