<?php

namespace Drupal\test_module_4\Plugin\migrate\source;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_plus\Plugin\migrate\source\Url;
use Drupal\migrate\Row;
use Drupal\Core\Url as UrlHelper;

/**
 * Source plugin for retrieving data via URLs.
 *
 * @MigrateSource(
 *   id = "json_parser_multiple"
 * )
 */
class JsonParserMultiple extends Url {
  protected $dataNewParserPlugin;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {
    $this->logger = \Drupal::logger('test_module_4');
    $this->state = \Drupal::state();
    $this->migration = $migration;
    $filter_path = $configuration['filter_path'] ?? NULL;
    $filters = $configuration['filters'] ?? [];
    $paragraphType = $configuration['paragraph_type'] ?? NULL;
    if (!empty($configuration['filename'])) {
      $configuration['urls'] = $this->getDynamicUrl($configuration['filename']);
    }
    else {
      $configuration['urls'] = $this->getUrls($migration->id(), $configuration['content_type'], $filter_path, $filters, $paragraphType);
    }
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
  }

  /**
   * {@inheritdoc}
   */
  protected function getUrls($migration_id, $content_type, $filter_path = NULL, $filters = [], $paragraphType = NULL) {
    $migrations_helper = \Drupal::service("test_module_4.migrations_helper");
    return $migrations_helper->getSourceUrls($migration_id, $content_type, $filter_path, $filters, $paragraphType);
  }

  /**
   * Helper function for dynamic url.
   */
  protected function getDynamicUrl($settings) {
    $migrations_helper = \Drupal::service("test_module_4.migrations_helper");
    return $migrations_helper->getSourceFullPath() . '/' . $settings . '.json';
  }

  /**
   * {@inheritdoc}
   */
  protected function fetchNextRow() {
    $this->getIterator()->next();
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    if (\Drupal::state()->get('mig_update') == TRUE) {
      $current_id = $row->getSourceProperty('uid');
      $db = \Drupal::database();
      $item = $db->select('diff_report', 'a')->condition('uid', $current_id)->fields('a')->execute()->fetchAll();
      // If current id not in diff_report then skip the row.
      if (count($item) == 0) {
        $this->logger->debug('Skipping prepareRow() for: @uid.', ['@uid' => $current_id]);
        return FALSE;
      }
    }

    $this->logger->debug('Running prepareRow() for: @uid.', ['@uid' => $row->getSourceProperty('uid')]);

    $migrations_helper = \Drupal::service("test_module_4.migrations_helper");

    // Fix unwanted titles.
    if ($row->getSourceProperty('html_title') == '<br />') {
      $row->setSourceProperty('html_title', NULL);
    }

    // Fix html-title with more than 255 character.
    if (strlen($title = $row->getSourceProperty('html_title')) > 255) {
      $title = $this->reduceString($title);
      $row->setSourceProperty('html_title', $title);
    }

    // Fix title with more than 255 character.
    if (strlen($title = $row->getSourceProperty('title')) > 255) {
      $title = $this->reduceString($title);
      $row->setSourceProperty('title', $title);
    }
    // Add a default Title if empty.
    if (empty($row->getSourceProperty('title'))) {
      $row->setSourceProperty('title', 'Default Page Title');
    }
    // SEO metatags fields to be applied across all content types.
    $metatags = [];
    if ($keywords = $row->getSourceProperty('seo_keywords')) {
      $metatags['keywords'] = $keywords;
    }
    if ($description = $row->getSourceProperty('seo_description')) {
      $metatags['description'] = $description;
    }
    if ($canonical_url = $row->getSourceProperty('seo_canonical_url')) {
      $metatags['canonical_url'] = $canonical_url;
    }
    else {
      $page_url = $row->getSourceProperty('uid');
      try {
        $current_url = UrlHelper::fromUserInput($page_url, ["absolute" => TRUE])->toString();
        $metatags['canonical_url'] = $current_url;
      }
      catch (\InvalidArgumentException $e) {
        $this->logger->debug('@input is not a valid Url.', ['@input' => $page_url]);
      }
    }
    if ($row->getSourceProperty('seo_noindex') == 'True') {
      $metatags['robots'] = 'noindex';
    }
    // Set Twitter cards metatags.
    if ($image = $row->getSourceProperty('twitter_image')) {
      if ($file = $migrations_helper->createReusableMediaFromImageTag($image)) {
        $metatags['twitter_cards_image'] = file_create_url($file->getFileUri());
      }
    }
    if ($description = $row->getSourceProperty('twitter_description')) {
      $metatags['twitter_cards_description'] = $description;
    }
    if (isset($metatags['twitter_cards_description']) || isset($metatags['twitter_cards_image'])) {
      $metatags['twitter_cards_type'] = 'summary';
    }

    if (!empty($metatags)) {
      $metatags = serialize($metatags);
      $row->setSourceProperty('metatags', $metatags);
    }

    // Set Scheduled Transition Date && State.
    $scheduled_transitions = [];
    if ($publishing_date = $row->getSourceProperty('publishing_start_date')) {
      $scheduled_transitions['published'] = date('Y-m-d\TH:i:s', strtotime($publishing_date));
    }
    if ($expiration_date = $row->getSourceProperty('publishing_expiration_date')) {
      $scheduled_transitions['draft'] = date('Y-m-d\TH:i:s', strtotime($expiration_date));
    }
    if (!empty($scheduled_transitions)) {
      $row->setSourceProperty('transition_dates', array_values($scheduled_transitions));
      $row->setSourceProperty('transition_states', array_keys($scheduled_transitions));
    }
    $moderation_state = 'published';
    if (!empty($expiration_date)) {
      if (strtotime($expiration_date) < time()) {
        $moderation_state = 'draft';
      }
    }
    $row->setSourceProperty('moderation_state', $moderation_state);

    $langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();
    if ($code = $row->getSourceProperty('language_code')) {
      $mapped_langcodes = $migrations_helper->getMigrationLanguageCodesMapping();
      if (array_search($code, $mapped_langcodes) != FALSE) {
        $langcode = array_search($code, $mapped_langcodes);
      }
    }
    $row->setSourceProperty('language_code', $langcode);

    // If multilingual, Remove the language component from uid.
    $uid = $row->getSourceProperty('uid');
    $path_alias = $migrations_helper->removeLanguageFromUid($uid);
    $row->setSourceProperty('path_alias', $path_alias);

    // Run post processing required for each rows.
    $this->postPrepareRow($row);

    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return parent::fields() + [
      'metatags' => $this->t('Metatags'),
      'moderation_state' => $this->t('Moderation State'),
      'transition_dates' => $this->t('Transition Dates'),
      'transition_states' => $this->t('Transition States'),
      'language_code' => $this->t('Language code'),
      'path_alias' => $this->t('Path Alias'),
    ];
  }

  /**
   * Reduces the string to limit a string to certain character count only.
   *
   * @param string $string
   *   String to process.
   * @param int $count
   *   Desired character count.
   *
   * @return bool|string
   *   Returns trimmed string.
   */
  private function reduceString($string, $count = 255) {
    $string = strip_tags($string, '<br><sub><sup><b><i>');
    if (strlen($string) > $count) {
      $string = strip_tags($string);
      $string = substr($string, 0, $count);
    }

    return $string;
  }

  /**
   * {@inheritdoc}
   */
  private function postPrepareRow(&$row) {
    // Reduce configured strings to a certain count.
    $trim_fields = $this->state->get('test_module_4.trim_fields', '');
    $trim_fields = explode("\r\n", $trim_fields);
    foreach ($trim_fields as $item) {
      list($migration_id, $source_property, $char_count) = explode("|", $item);
      if ($this->migration->id() == $migration_id && $row->hasSourceProperty($source_property)) {
        $value = $row->getSourceProperty($source_property);
        if (strlen($value) > (int) $char_count) {
          $value = $this->reduceString($value, (int) $char_count);
          $row->setSourceProperty($source_property, $value);
        }
      }
    }

    $source = $row->getSource();
    // Clean all UTF-8 strings of unwated chars.
    foreach ($source['fields'] as $field) {
      $source_value = $row->getSourceProperty($field['name']);
      if (!empty($source_value) && is_string($source_value)) {
        $source_value = mb_convert_encoding($source_value, 'UTF-8');
        $row->setSourceProperty($field['name'], $source_value);
      }
    }
  }

}
