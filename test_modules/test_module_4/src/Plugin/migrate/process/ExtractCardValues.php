<?php

namespace Drupal\test_module_4\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\migrate\Row;
use DomDocument;
use DOMXPath;

/**
 * Provides a 'ExtractCardValues' migrate process plugin.
 *
 * @MigrateProcessPlugin(
 *  id = "extract_card_values"
 * )
 */
class ExtractCardValues extends ProcessPluginBase implements ContainerFactoryPluginInterface {

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
    $values = [
      'description' => '',
      'link_uri' => '',
      'image' => '',
    ];
    if (!empty($value)) {
      $value = stripcslashes($value);
      $markup = new DOMDocument();
      if (@$markup->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $value)) {
        $xpath = new DOMXPath($markup);
        $image = $xpath->query('//img');
        if ($image->length) {
          $values['image'] = $markup->saveHTML($image->item(0));
        }
        $elements = $markup->getElementsByTagName('div');
        if ($elements->length) {
          foreach ($elements as $element) {
            $content_class = $element->getAttribute('class');
            if ($content_class == 'contentwrapper') {
              $values['description'] = $this->domInnerHtml($element);
            }
          }
        }

        $links = $markup->getElementsByTagName('a');
        if ($links->length) {
          $values['link_uri'] = $links->item(0)->getAttribute('href');
          $values['link_uri'] = $this->migrationHelper->generateUriFromUrl($values['link_uri']);
        }
      }
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function domInnerHtml($element) {
    $innerHTML = "";
    $children = $element->childNodes;
    foreach ($children as $child) {
      $tmp_dom = new DOMDocument();
      $tmp_dom->appendChild($tmp_dom->importNode($child, TRUE));
      $innerHTML .= trim($tmp_dom->saveHTML());
    }
    return $innerHTML;
  }

}
