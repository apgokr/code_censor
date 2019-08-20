<?php

namespace Drupal\test_module_4\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Fetches address from given latitude/longitude from Google APIs.
 *
 * @MigrateProcessPlugin(
 *   id = "reverse_geocode"
 * )
 */
class ReverseGeocode extends ProcessPluginBase implements ContainerFactoryPluginInterface {

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
    $lat = $value[0] ?? NULL;
    $lng = $value[1] ?? NULL;

    if (!empty($lat) && !empty($lng)) {
      $mappings = $this->configuration['map'];
      if ($geolocation_data = $this->migrationHelper->reverseGeolocationLookup($lat, $lng)) {
        foreach ($mappings as $source => $api_key) {
          $mappings[$source] = $geolocation_data[$api_key];
        }
        return $mappings;
      }
    }

    return NULL;
  }

}
