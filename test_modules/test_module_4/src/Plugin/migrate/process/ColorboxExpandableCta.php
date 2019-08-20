<?php

namespace Drupal\test_module_4\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Provides a 'CTA Button' for Colorbox expandable component.
 *
 * @MigrateProcessPlugin(
 *  id = "colorbox_expandable_cta"
 * )
 */
class ColorboxExpandableCta extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $paragraph = Paragraph::create([
      'type' => 'dsu_c_cta_button',
      'field_cta_button_url' => [
        'title' => 'LEARN MORE',
        'uri' => 'internal:#',
      ],
    ]);
    $paragraph->save();
    return [
      'target_id' => $paragraph->id(),
      'target_revision_id' => $paragraph->getRevisionId(),
    ];
  }

}
