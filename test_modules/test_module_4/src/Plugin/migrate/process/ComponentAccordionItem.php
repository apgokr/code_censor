<?php

namespace Drupal\test_module_4\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\paragraphs\Entity\Paragraph;
use DomDocument;

/**
 * Provides a 'ComponentAccordionItem' migrate process plugin.
 *
 * @MigrateProcessPlugin(
 *   id = "component_accordion_item",
 * )
 */
class ComponentAccordionItem extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $component = [];
    if (!empty($value)) {
      $value = stripslashes($value);
      $markup = new DOMDocument();
      if (@$markup->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $value)) {
        $elements = $markup->getElementsByTagName('li');
        foreach ($elements as $element) {
          $nodes = $element->getElementsByTagName('div');
          foreach ($nodes as $node) {
            $class_name = $node->getAttribute('class');
            if ($class_name == 'opener') {
              $title = $node->nodeValue;
            }
            if ($class_name == 'content') {
              $body = $markup->saveHTML($node);
            }
          }
          $content_id = $this->getCreatedFullContent($body);
          $component[] = $this->getCreatedAccordianItem($title, $content_id);
        }
      }
      return $component;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedAccordianItem($title, $content) {
    $values = [
      'type' => 'accordion_item',
      'field_c_title' => $title,
      'field_column_first' => $content,
    ];
    $paragraph = Paragraph::create($values);
    $paragraph->save();
    return ['target_id' => $paragraph->id(), 'target_revision_id' => $paragraph->getRevisionId()];
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedFullContent($content) {
    $values = [
      'type' => 'c_text',
      'field_summary_text' => [
        'value' => $content,
        'format' => 'rich_text',
      ],
    ];
    $paragraph = Paragraph::create($values);
    $paragraph->save();
    return ['target_id' => $paragraph->id(), 'target_revision_id' => $paragraph->getRevisionId()];
  }

}
