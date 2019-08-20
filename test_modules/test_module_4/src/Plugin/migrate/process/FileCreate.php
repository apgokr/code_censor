<?php

namespace Drupal\test_module_4\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'FileCreate' migrate process plugin.
 *
 * @MigrateProcessPlugin(
 *  id = "file_create"
 * )
 */
class FileCreate extends ProcessPluginBase implements ContainerFactoryPluginInterface {

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
    $full_path = $this->migrationHelper->getSourceFullPath() . $value;
    $full_path = iconv('UTF-8', 'big-5', $full_path);
    $file_data = file_get_contents($full_path);
    if ($file = file_save_data($file_data, 'public://' . basename($value), FILE_EXISTS_RENAME)) {
      return $file->id();
    }
  }

}
