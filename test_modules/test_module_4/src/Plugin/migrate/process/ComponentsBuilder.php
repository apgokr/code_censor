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
 * Provides a 'ComponentsBuilder' migrate process plugin.
 *
 * @MigrateProcessPlugin(
 *  id = "components_builder"
 * )
 */
class ComponentsBuilder extends MigrationLookup {

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
  private $widgetKeyMapping = [
    'NESFullWidth' => NULL,
    'NESCol' => NULL,
    'NESoneCol' => NULL,
    'NEStwoCol' => 'layout_columns_2',
    'NEStwoColBigLeft' => 'layout_66_33',
    'NEStwoColBigRight' => 'layout_33_66',
    'NESthreeCol' => 'layout_columns_3',
    'NESfourCol' => 'layout_columns_4',
    'NESfiveCol' => 'layout_columns_5',
  ];

  /**
   * {@inheritdoc}
   */
  private $colsFields = [
    'field_column_first',
    'field_column_second',
    'field_column_third',
    'field_column_fourth',
    'field_fifth_column',
  ];

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $components = [];
    // Add NSE_HTMLContent content to text component at top.
    $body = $value[0];
    if (!empty($body)) {
      $values = [
        'type' => 'c_text',
        'field_summary_text' => [
          'format' => 'rich_text',
          'value' => $body,
        ],
        'field_css_class' => 'tw',
      ];
      $paragraph = Paragraph::create($values);
      if ($paragraph->save()) {
        $components[] = [
          'target_id' => $paragraph->id(),
          'target_revision_id' => $paragraph->getRevisionId(),
        ];
      }
    }

    // Parse Snippets.
    $snippets = $value[1];
    $jsonSnippet = stripcslashes($snippets);
    $jsonSnippet = json_decode($jsonSnippet, TRUE);
    $widgets_tree = [];
    if (!empty($jsonSnippet)) {
      foreach ($jsonSnippet as $key => $value) {
        if (isset($jsonSnippet[$key]['widget-content'])) {
          $widgets_tree = $jsonSnippet[$key]['widget-content'];
        }
      }
      foreach ($widgets_tree as $cols) {
        $widget_key = $cols['panelId'];
        $component = [];
        // Set the layout paragraph type.
        if (in_array($widget_key, ['NESFullWidth', 'NESCol', 'NESoneCol']) && isset($cols['cols'][0][0][0])) {
          foreach ($cols['cols'][0][0] as $item) {
            $id = $this->prepareSourceId($item);
            if ($lookup_item = $this->migrateLookup($id, $migrate_executable, $row, '')) {
              $components[] = $lookup_item;
            }
          }
          continue;
        }
        else {
          $paragraph_id = $this->widgetKeyMapping[$widget_key] ?? NULL;
        }
        if (!empty($paragraph_id)) {
          $values = [];
          $values['type'] = $paragraph_id;
          // Iterate thru each columns and prepare a field list of values.
          foreach ($cols['cols'] as $index => $col) {
            foreach ($col[$index] as $item) {
              if (!empty($item)) {
                // Migrate lookup the referenced paragraph.
                $id = $this->prepareSourceId($item);
                if ($lookup_item = $this->migrateLookup($id, $migrate_executable, $row, '')) {
                  if (isset($this->colsFields[$index])) {
                    $values[$this->colsFields[$index]][] = $lookup_item;
                  }
                }
              }
            }
          }

          if (count($values) > 1) {
            // Create the Layout paragraph.
            $paragraph = Paragraph::create($values);
            if ($paragraph->save()) {
              // Reference the Layout Paragraph to components.
              $component['target_id'] = $paragraph->id();
              $component['target_revision_id'] = $paragraph->getRevisionId();
            }
          }
        }
        if (!empty($component)) {
          $components[] = $component;
        }
      }
    }
    return $components;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareSourceId($id) {
    $prefix = $this->configuration['prefix'] ?? '';
    $suffix = $this->configuration['suffix'] ?? '';
    return $prefix . $id . $suffix;
  }

  /**
   * {@inheritdoc}
   */
  public function migrateLookup($id, $migrate_executable, $row, $destination_property) {
    if ($values = parent::transform($id, $migrate_executable, $row, $destination_property)) {
      $keys = ['target_id', 'target_revision_id'];
      return array_combine($keys, $values);
    }
  }

}
