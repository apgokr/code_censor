<?php

namespace Drupal\test_module_4\Plugin\migrate\source;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_plus\Plugin\migrate\source\Url;
use Drupal\migrate\Row;

/**
 * Source plugin for retrieving data via URLs.
 *
 * @MigrateSource(
 *   id = "xml_parser"
 * )
 */
class XmlParser extends Url {
  protected $dataNewParserPlugin;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {
    $this->state = \Drupal::state();
    $this->migrationHelper = \Drupal::service("test_module_4.migrations_helper");
    $this->languageManager = \Drupal::languageManager();
    $this->logger = \Drupal::logger('test_module_4');
    $configuration['urls'] = $this->getDynamicUrl($configuration['filename']);
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
  }

  /**
   * Helper function for dynamic url.
   */
  protected function getDynamicUrl($filename) {
    return $this->migrationHelper->getSourceFullPath() . '/' . $filename;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    // For multilingual dumps, only migrate site's default language menu items.
    $is_multilingual = $this->state->get('test_module_4.source_multilingual', 0);
    if ($is_multilingual) {
      $default_langcode = $this->languageManager->getDefaultLanguage()->getId();
      // Modify languagecode if shell has different languagecode.
      $langcode_mappings = $this->migrationHelper->getMigrationLanguageCodesMapping();

      if ($source_uid = $row->getSourceProperty('path')) {
        $source_uid = urldecode($source_uid);
        $parts = explode('/', $source_uid);
        // Process the default language item, ignore non-default langcode rows.
        if ($parts[1] == $default_langcode || (isset($langcode_mappings[$default_langcode]) && $parts[1] == $langcode_mappings[$default_langcode])) {
          $fixed_uid = $this->migrationHelper->removeLanguageFromUid($source_uid);
          $row->setSourceProperty('path', urlencode($fixed_uid));
          $this->logger->debug("Importing & removed langcode from the item: @uid.", ['@uid' => $fixed_uid]);
        }
        else {
          $this->logger->debug("Ignoring item as it is not in default language: @uid.", ['@uid' => $source_uid]);
          return FALSE;
        }
      }
    }
  }

}
