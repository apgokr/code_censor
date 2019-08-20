<?php

namespace Drupal\test_module_4\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\paragraphs\Entity\Paragraph;
use DomDocument;
use DOMXPath;

/**
 * Defines content type name based on Path.
 *
 * @MigrateProcessPlugin(
 *   id = "component_teaser_item"
 * )
 */
class ComponentTeaserItem extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, $migrationHelper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->migrationHelper = $migrationHelper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('test_module_4.migrations_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $component = [];
    if (!empty($value)) {
      $value = stripcslashes($value);
      $markup = new DOMDocument();
      if (@$markup->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $value)) {
        $xpath = new DOMXPath($markup);
        $items = $xpath->query('//div[@title="_link"]');
        foreach ($items as $item) {
          $elements = $item->getElementsByTagName('span');
          foreach ($elements as $element) {
            $title_name = $element->getAttribute('title');
            switch ($title_name) {
              case '_title':
                $teaser_title = $element->nodeValue;
                break;

              case '_description':
                $teaser_description = $element->nodeValue;
                break;

              case '_linkurl':
                $teaser_link = trim(preg_replace('/\s\s+/', ' ', $element->nodeValue));
                break;

              case '_imageurl':
                $teaser_image = trim(preg_replace('/\s\s+/', ' ', $element->nodeValue));
                break;
            }
          }
          $component[] = $this->getCreatedTeaserItem($teaser_title, $teaser_description, $teaser_link, $teaser_image);
        }
      }
    }
    return $component;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTeaserItem($teaser_title, $teaser_description, $teaser_link, $teaser_image) {
    $image_id = NULL;
    $teaser_image = "<img alt=\"" . $teaser_title . "\"  title=\"" . $teaser_title . "\" src=\"" . $teaser_image . "\">";
    if ($file = $this->migrationHelper->createReusableMediaFromImage($teaser_image)) {
      $image_id = $file->id();
    }
    $teaser_link = $this->migrationHelper->generateUriFromUrl($teaser_link);
    $values = [
      'type' => 'c_teasercycle_item',
      'field_c_title' => $teaser_title,
      'field_c_text' => $teaser_description,
      'field_c_link' => [
        'uri' => $teaser_link,
        'title' => $teaser_title,
      ],
      'field_c_image' => [
        'target_id' => $image_id,
      ],
    ];
    $paragraph = Paragraph::create($values);
    $paragraph->save();
    return ['target_id' => $paragraph->id(), 'target_revision_id' => $paragraph->getRevisionId()];
  }

}
