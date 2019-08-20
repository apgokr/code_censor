<?php

namespace Drupal\test_module_4\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\migrate\Row;
use DOMDocument;

/**
 * Defines content type name based on Path.
 *
 * @MigrateProcessPlugin(
 *   id = "extract_url",
 * )
 */
class ExtractUrl extends ProcessPluginBase implements ContainerFactoryPluginInterface {

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
    $values = [];
    if (!empty($value)) {
      $dom = new DomDocument();
      $dom->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $value);
      $links = $dom->getElementsByTagName('a');
      if ($links->length) {
        $values['uri'] = $links->item(0)->getAttribute('href');
        $values['title'] = $links->item(0)->nodeValue;
        $values['uri'] = $this->migrationHelper->generateUriFromUrl($values['uri']);
        if (strpos($values['title'], '.aspx')) {
          $values['title'] = str_replace('.aspx', '', $values['title']);
        }
        if (!empty($links->item(0)->getAttribute('target'))) {
          $values['options']['attributes']['target'] = $links->item(0)->getAttribute('target');
        }
      }
    }
    return $values;
  }

}
