<?php

namespace Drupal\test_module_4\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\paragraphs\Entity\Paragraph;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\migrate\Plugin\migrate\process\MigrationLookup;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigratePluginManagerInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;

/**
 * Provides a 'ComponentCardItem' migrate process plugin.
 *
 * @MigrateProcessPlugin(
 *  id = "component_card_item"
 * )
 */
class ComponentCardItem extends MigrationLookup {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, MigrationPluginManagerInterface $migration_plugin_manager, MigratePluginManagerInterface $process_plugin_manager, $migrationHelper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $migration_plugin_manager, $process_plugin_manager);
    $this->migrationHelper = $migrationHelper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('plugin.manager.migration'),
      $container->get('plugin.manager.migrate.process'),
      $container->get('test_module_4.migrations_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $component = [];
    $lookup_item = parent::transform($value, $migrate_executable, $row, '');
    if (!empty($lookup_item)) {
      $values = [
        'type' => 'ln_c_card_item',
        'field_card_entity_selector' => ['target_id' => $lookup_item],
      ];
      $paragraph = Paragraph::create($values);
      $paragraph->save();
      $component['target_id'] = $paragraph->id();
      $component['target_revision_id'] = $paragraph->getRevisionId();
      return $component;
    }
  }

}
