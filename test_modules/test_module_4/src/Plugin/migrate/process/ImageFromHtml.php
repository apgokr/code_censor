<?php

namespace Drupal\test_module_4\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines content type name based on Path.
 *
 * @MigrateProcessPlugin(
 *   id = "image_from_html"
 * )
 */
class ImageFromHtml extends ProcessPluginBase implements ContainerFactoryPluginInterface {

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
      'target_id' => '',
      'alt' => '',
      'title' => '',
    ];
    $value[0] = stripcslashes($value[0]);
    if ($file = $this->migrationHelper->createReusableMediaFromImageTag($value[0])) {
      $values['target_id'] = $file->id();
      preg_match('/alt="([^"]*)"/', $value[0], $alt_tag_matches);
      $values['alt'] = $alt_tag_matches[1];
      preg_match('/title="([^"]*)"/', $value[0], $title_tag_matches);
      $values['title'] = $title_tag_matches[1];
      if (empty($values['alt'])) {
        $values['alt'] = $value[1];
      }
      if (empty($values['title'])) {
        $values['title'] = $value[1];
      }
      return $values;
    }
    return NULL;
  }

}
