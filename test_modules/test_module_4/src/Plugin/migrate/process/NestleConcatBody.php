<?php

namespace Drupal\test_module_4\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\migrate\Row;

/**
 * Defines content type name based on Path.
 *
 * @MigrateProcessPlugin(
 *   id = "nestle_concat_body",
 * )
 */
class NestleConcatBody extends ProcessPluginBase implements ContainerFactoryPluginInterface {

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
    $body = NULL;
    if (!empty($value)) {
      if (!empty($value[1])) {
        if (empty($value[5])) {
          $value[1] = preg_replace("/<img[^>]+\>/i", "", $value[1]);
        }
        $value[1] = '<h3>Programme description</h3>' . $value[1];
      }
      if (!empty($value[2])) {
        $value[2] = '<h3>Value to Society</h3>' . $value[2];
      }
      if (!empty($value[3])) {
        $value[3] = '<h3>Value to Nestlé</h3>' . $value[3];
      }
      if (!empty($value[4])) {
        $value[4] = '<h3>Next Steps</h3>' . $value[4];
      }
      $body = $value[0] . $value[1] . $value[2] . $value[3];
    }
    return $body;
  }

}
