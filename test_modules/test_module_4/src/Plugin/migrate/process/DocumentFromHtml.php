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
 *   id = "document_from_html"
 * )
 */
class DocumentFromHtml extends ProcessPluginBase implements ContainerFactoryPluginInterface {

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
    $titles = array_slice($value, 1);
    $titles = array_filter($titles);
    $title = current($titles);
    if (!empty($title) && $title != 'Default Page Title') {
      $value[1] = $title;
    }
    else {
      $value[1] = '';
    }

    $value[0] = stripcslashes($value[0]);
    $value[1] = strip_tags($value[1]);
    if ($file = $this->migrationHelper->createReusableMediaFormDocuments($value)) {
      return $file->id();
    }
    return NULL;
  }

}
