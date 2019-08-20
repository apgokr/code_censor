<?php

namespace Drupal\test_module_4\Commands;

use Drupal\lcp_general\AcsfSite;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Plugin\CachedDiscoveryClearerInterface;
use Drupal\Core\Render\Renderer;
use Drupal\media\Entity\Media;
use Drupal\views\Views;
use Drush\Commands\DrushCommands;
use Drupal\test_module_4\MigrationsHelper;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Menu\MenuLinkTree;
use Drupal\flag\FlagService;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;

/**
 * Corporate Migration drush commands.
 */
class LcpMigrationsCommands extends DrushCommands {
  protected $migrationsHelper;

  protected $entityTypeManager;

  protected $languageManager;

  protected $request;

  protected $renderer;

  protected $loggerFactory;

  protected $flag;

  protected $entityFieldManager;

  protected $entityTypeBundleInfo;

  /**
   * Path alias manager.
   *
   * @var \Drupal\Core\Path\AliasStorageInterface
   */
  protected $pathAliasManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    MigrationsHelper $migrationsHelper,
    EntityTypeManager $entityTypeManager,
    LanguageManager $languageManager,
    RequestStack $request,
    Renderer $renderer,
    LoggerChannelFactory $logger,
    $state,
    $pathAliasManager,
    CachedDiscoveryClearerInterface $cachedDiscoveryClearer,
    MenuLinkTree $menuTree,
    $aliasStorage,
    $database,
    FlagService $flag,
    EntityFieldManagerInterface $entity_field_manager,
    EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    $this->migrationsHelper = $migrationsHelper;
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
    $this->request = $request;
    $this->renderer = $renderer;
    $this->loggerFactory = $logger;
    $this->state = $state;
    $this->pathAliasManager = $pathAliasManager;
    $this->cachedDiscoveryClearer = $cachedDiscoveryClearer;
    $this->menuTree = $menuTree;
    $this->aliasStorage = $aliasStorage;
    $this->database = $database;
    $this->flag = $flag;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * Generates Unique Ids for each JSON file for Nestle Corporate.
   *
   * @validate-module-enabled test_module_4,migrate_tools
   *
   * @command nestle-migrate:generate:unique-id
   * @aliases nm-guid
   */
  public function uniqueId() {
    $helper = $this->migrationsHelper;
    $list = $helper->getAllFiles();
    foreach ($list as $path) {
      if (strpos($path, '_minorversion.json') !== FALSE) {
        unlink($path);
        $this->logger()->debug(dt('Deleted file:- @path', ['@path' => $path]));
      }
      else {
        $helper->updateUidInSourceJson($path);
      }
    }
    // Fix duplicate uid issues.
    $helper->fixDuplicateUid();

    $menu_path = $helper->getSourceFullPath() . '/' . 'navigation.xml';
    if (file_exists($menu_path)) {
      $helper->updateWeightInSourceXml($menu_path);
    }
    $this->logger()->debug(dt("Updated JSON files with correct UIDs."));
  }

  /**
   * Delete all menu items for main and secondary menu.
   *
   * @validate-module-enabled test_module_4,migrate_tools
   *
   * @command nestle-migrate:delete:menu-item
   * @aliases nm-dmi
   */
  public function deleteMenuItems() {
    $link_list = $this->excludeCareersLinks();
    $this->deleteExistingLinks($link_list);
    $this->logger()->debug(dt("Delete all menu items for main and secondary menu"));
  }

  /**
   * Drush script for changing the base language.
   *
   * @validate-module-enabled test_module_4,migrate_tools
   *
   * @command nestle-set:base:language
   * @aliases nbl
   */
  public function baseLanguage($secondary_languages = NULL) {

    $watchdog_log = [];
    $langcode = $this->languageManager->getDefaultLanguage()->getId();

    $this->output()->writeln('Default language code is ' . $langcode);
    $this->output()->writeln('Please take a backup of database before proceeding.');

    if ($this->io()->confirm('Are you sure you want to set "' . $langcode . '" as the base langcode for all content, menu-link, taxonomy terms, custom_blocks.')) {

      // Skipping update in case of Arabic & English languages.
      $skip_languages = ['en'];
      if (in_array($langcode, $skip_languages)) {
        return;
      }

      // Preserve secondary languages.
      if (!empty($secondary_languages)) {
        $secondary_languages = explode(',', $secondary_languages);
      }
      else {
        $secondary_languages = [];
        $multilingual = $this->state->get('test_module_4.source_multilingual', 0);
        if ($multilingual) {
          $all_languages = array_keys($this->languageManager->getLanguages());
          $secondary_languages = array_diff($all_languages, ['en', $langcode]);
        }
      }

      // Only changing base languages for following entity types.
      $content_entity_types = [
        'menu_link_content',
        'file',
        'media',
        'paragraph',
        'block_content',
        'taxonomy_term',
        'node',
      ];
      foreach ($content_entity_types as $entity_type) {
        if ($entity_type == 'node') {
          $entities = $this->entityTypeManager->getStorage($entity_type)->loadByProperties([
            'type' => [
              'article',
              'basic_page',
              'dsu_component_page',
              'teaser',
            ],
          ]);
        }
        else {
          $entities = $this->entityTypeManager->getStorage($entity_type)->loadMultiple();
        }

        foreach ($entities as $entity) {
          if ($entity_type == 'node') {
            if ($entity->get('langcode')->value != $langcode && !in_array($entity->get('langcode')->value, $secondary_languages)) {
              if ($entity->getType() == 'teaser') {
                $field_items = $entity->getFieldDefinitions();
                foreach ($field_items as $field_name => $field_definition) {
                  if ($field_definition->getType() == "entity_reference_revisions") {
                    $field_definition->set('langcode', $langcode);
                    $field_definition->save();
                  }
                  if ($field_definition->getFieldStorageDefinition()->isBaseField() == FALSE) {
                    if ($entity->hasField($field_name)) {
                      $entity->set($field_name, $entity->get($field_name)->getValue());
                    }
                  }
                }
              }
              $title = $entity->getTitle();
              $entity->set('langcode', $langcode);
              $entity->set('title', $title);
              $entity->save();
              $items = $entity_type . ' - ' . $title . '(' . $entity->id() . ')';
              $this->output()->writeln($items);
              $watchdog_log[] = $entity_type . ' - ' . $title . '(' . $entity->id() . ')';
            }
          }
          elseif ($entity_type == 'menu_link_content') {
            if ($entity->get('langcode')->value != $langcode && !in_array($entity->get('langcode')->value, $secondary_languages)) {
              $title = $entity->getTitle();
              $entity->set('langcode', $langcode);
              $entity->set('title', $title);
              $entity->save();
              $items = $entity_type . ' - ' . $title . '(' . $entity->id() . ')';
              $this->output()->writeln($items);
              $watchdog_log[] = $entity_type . ' - ' . $title . '(' . $entity->id() . ')';
            }
          }
          elseif ($entity_type == 'taxonomy_term') {
            if ($entity->get('langcode')->value != $langcode && !in_array($entity->get('langcode')->value, $secondary_languages)) {
              $title = $entity->getName();
              $entity->set('langcode', $langcode);
              $entity->setName($title);
              $entity->save();
              $items = $entity_type . ' - ' . $title . '(' . $entity->id() . ')';
              $this->output()->writeln($items);
              $watchdog_log[] = $entity_type . ' - ' . $title . '(' . $entity->id() . ')';
            }
          }
          elseif ($entity_type == 'paragraph' || $entity_type == 'media') {
            if ($entity->get('langcode')->value != $langcode && !in_array($entity->get('langcode')->value, $secondary_languages)) {
              $items = $entity->getFieldDefinitions();
              $translation = NULL;
              if ($entity->hasTranslation($langcode)) {
                $translation = $entity->getTranslation($langcode);
              }
              foreach ($items as $field_name => $field_definition) {
                if ($field_definition->getType() == "entity_reference_revisions") {
                  $field_definition->set('langcode', $langcode);
                  $field_definition->save();
                }
                if ($field_definition->getFieldStorageDefinition()->isBaseField() == FALSE) {
                  if ($entity->hasField($field_name)) {
                    if (!empty($translation) && $translation->hasField($field_name)) {
                      $entity->set($field_name, $translation->get($field_name)->getValue());
                    }
                    else {
                      $entity->set($field_name, $entity->get($field_name)->getValue());
                    }
                  }
                }
              }
              $entity->set('langcode', $langcode);
              $entity->save();
              $paragraph_items = $entity_type . ' - ' . $entity->label() . '(' . $entity->id() . ')';
              $this->output()->writeln($paragraph_items);
              $watchdog_log[] = $entity_type . ' - ' . $entity->label() . '(' . $entity->id() . ')';
            }
          }
          elseif ($entity_type == 'block_content') {
            if ($entity->get('langcode')->value != $langcode && !in_array($entity->get('langcode')->value, $secondary_languages)) {
              if ($entity->hasTranslation($langcode)) {
                $translation = $entity->getTranslation($langcode);
                if ($translation->hasField('body')) {
                  $entity->set('body', $translation->body->getValue());
                }
              }
              $entity->set('langcode', $langcode);
              $entity->save();
              $items = $entity_type . ' - ' . $title . '(' . $entity->id() . ')';
              $this->output()->writeln($items);
              $watchdog_log[] = $entity_type . ' - ' . $title . '(' . $entity->id() . ')';
            }
          }
          else {
            if ($entity->get('langcode')->value != $langcode && !in_array($entity->get('langcode')->value, $secondary_languages)) {
              $entity->set('langcode', $langcode);
              $entity->save();
              $items = $entity_type . ' - ' . $entity->label() . '(' . $entity->id() . ')';
              $this->output()->writeln($items);
              $watchdog_log[] = $entity_type . ' - ' . $entity->label() . '(' . $entity->id() . ')';
            }
          }
        }
      }

      // Rendered array of changed entities.
      $build = [
        '#title' => 'Base language changed for following entities',
        '#theme' => 'item_list',
        '#items' => $watchdog_log,
      ];

      $build = $this->renderer->renderPlain($build);
      $this->loggerFactory->get('test_module_4')->info($build);

    }
    else {
      $this->output()->writeln('Operation aborted by user.');
    }

  }

  /**
   * Reads a translation-mapping file having the mapping of translated contents.
   *
   * @validate-module-enabled test_module_4,migrate_tools
   *
   * @command nestle-migrate:merge:translations
   * @aliases nm-mt
   */
  public function mergeTranslations() {
    $mapping_file = $this->state->get('test_module_4.source_translations_mapping_file');
    $mapping_file = $this->migrationsHelper->getSourceFullPath() . '/' . $mapping_file;
    $this->logger()->debug(dt('Found mapping File: @file', ['@file' => $mapping_file]));
    $handle = fopen($mapping_file, "r");
    if (!empty($handle)) {
      $header = fgetcsv($handle);
      $mapped_langcodes = $this->migrationsHelper->getMigrationLanguageCodesMapping();
      foreach ($header as &$item) {
        if (isset($mapped_langcodes[$item])) {
          $item = $mapped_langcodes[$item];
        }
      }
      while (($translated_paths = fgetcsv($handle)) !== FALSE) {

        $source_node = NULL;
        $translated_paths = array_combine($header, $translated_paths);
        $this->logger()->debug(dt('Fetching Row:'));
        // First column is the base lanuage.
        $source_uid = $this->getUidFromPath($translated_paths[$header[0]]);
        $source_node = $this->loadNodeFromPath($source_uid);

        if (!empty($source_node)) {
          // Components translations will be handled manually.
          if ($source_node->bundle() == 'dsu_component_page') {
            $this->logger()->debug(dt('Skipping Component page: @nid', ['@nid' => $source_node->id()]));
            continue;
          }
          $this->logger()->debug(dt('Source Node Found (@langcode): @nid', ['@langcode' => $header[0], '@nid' => $source_node->id()]));
          array_shift($translated_paths);
          foreach ($translated_paths as $shell_langcode => $current_translation_path) {
            $current_translation_path = $this->getUidFromPath($current_translation_path);
            $translation_node = $this->loadNodeFromPath($current_translation_path);
            if (!empty($translation_node) && $translation_node->id() != $source_node->id()) {
              $drupal_langcode = current(array_keys($mapped_langcodes, $shell_langcode));
              if ($source_node->hasTranslation($drupal_langcode)) {
                $source_node->removeTranslation($drupal_langcode);
              }
              $this->logger()->debug(dt('Got translation (@langcode) node: @nid', ['@langcode' => $drupal_langcode, '@nid' => $translation_node->id()]));
              $values = $translation_node->toArray();
              $source_node->addTranslation($drupal_langcode, $values);
              $source_node->save();
              // Adding redirection if the langcode has changed.
              if ($drupal_langcode !== FALSE && $drupal_langcode != $shell_langcode) {
                $t = $source_node->getTranslation($drupal_langcode);
                $new_translation_path = 'internal:/' . $drupal_langcode . $t->path->get(0)->getValue()['alias'];
                $this->migrationsHelper->addUrlRedirection(ltrim($current_translation_path, '/'), $new_translation_path, 'und');
              }
              $translation_node->delete();
            }
            else {
              $this->logger()->debug(dt('Translation Node (@langcode) not found for @path. SKIPPED.', ['@langcode' => $shell_langcode, '@path' => $current_translation_path]));
            }
          }
        }
        else {
          $this->logger()->debug(dt('Source Node not found for @path. SKIPPED.', ['@path' => $source_uid]));
        }
      }
      fclose($handle);
    }
  }

  /**
   * Generates Search indexes for each cloned site.
   *
   * @param null|string $careers_search_index_id
   *   Existing search index id of careers index.
   * @param null|string $global_search_index_id
   *   Existing search index id of global index.
   *
   * @validate-module-enabled test_module_4,migrate_tools
   *
   * @command nestle-migrate:generate:indexes
   * @aliases nm-sapl
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setSearchIndex($careers_search_index_id = 'careers_jobs_master', $global_search_index_id = 'global_search_jobs_master') {
    if ($careers_search_index_id && $global_search_index_id) {
      $mappings = [
        $careers_search_index_id => [
          'search',
        ],
        $global_search_index_id => [
          "global_search",
          "brands_a_z",
          "error_page_search_results",
          "faq_search",
        ],
      ];

      $index_labels = [
        $careers_search_index_id => 'Careers',
        $global_search_index_id => 'Global',
      ];
      $updated_index_id = [
        $careers_search_index_id => 'careers',
        $global_search_index_id => 'global',
      ];
      $index_bin = [];

      $site = AcsfSite::load();
      // Assuming 'local' in case of a non ACSF site.
      $site_id = 'local';
      if (!empty($site->__get('site_id')) && !empty($site->__get('site_name'))) {
        $site_id = $site->__get('site_name');
      }

      foreach ($mappings as $index_name => $views_list) {
        // 1. Duplicate Index.
        $index = $this->entityTypeManager->getStorage('search_api_index')->load($index_name);
        if ($index === NULL) {
          continue;
        }
        $new_index = $index->createDuplicate();
        $new_index_id = $updated_index_id[$index_name] . '_' . $site_id;
        $results = $this->entityTypeManager->getStorage('search_api_index')->getQuery()->condition('id', $new_index_id, '=')->execute();
        if (empty($results)) {
          $new_index->set('id', $new_index_id);
          $new_index->enforceIsNew();
        }
        $new_index_name = $index_labels[$index_name] . ' - ' . $site_id;
        $new_index->set('name', $new_index_name);
        $new_index->set('read_only', FALSE);
        $new_index->save();

        // 2. Modify Index's view.
        foreach ($views_list as $view_name) {
          $view = $this->entityTypeManager->getStorage('view')->load($view_name);
          $new_base_table = 'search_api_index_' . $new_index_id;
          $new_node_base_table = 'search_api_datasource_' . $new_index_id . '_entity_node';
          $displays = $view->get('display');
          $handler_types = Views::getHandlerTypes();
          foreach ($displays as &$display) {
            foreach ($handler_types as $handler_type) {
              if (!empty($display['display_options'][$handler_type['plural']])) {
                foreach ($display['display_options'][$handler_type['plural']] as &$field) {
                  if (substr($field['table'], 0, 17) === "search_api_index_") {
                    $field['table'] = $new_base_table;
                  }
                  elseif (substr($field['table'], 0, 22) === "search_api_datasource_") {
                    $field['table'] = $new_node_base_table;
                  }
                }
              }
            }
          }
          $view->set('base_table', $new_base_table);
          $view->set('display', $displays);
          $view->save();
        }

        $index_bin[] = $index;
      }

      $this->cachedDiscoveryClearer->clearCachedDefinitions();

      // 3. Fix Search autocomplete.
      $autocomplete = $this->entityTypeManager->getStorage('search_api_autocomplete_search')->load('search');
      if ($autocomplete) {
        $autocomplete->set('index_id', 'careers_' . $site_id);
        $autocomplete->save();
      }

      // 4. Fix Facets.
      $facets = $this->entityTypeManager->getStorage('facets_facet')->loadMultiple();
      array_walk($facets, function ($facet) {
        $facet->calculateDependencies();
        $facet->save();
      });

      // 5. Delete Indexes.
      array_walk($index_bin, function ($index) {
        $index->delete();
      });
      $this->logger()->debug(dt("Created Careers and Global search indexs."));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getUidFromPath($path) {
    $path = strtolower($path);
    $path = str_replace('\\', '/', $path);
    $path = $this->migrationsHelper->getSourceFullPath() . $path;
    return $this->migrationsHelper->getUidbyFilePath($path);
  }

  /**
   * {@inheritdoc}
   */
  protected function loadNodeFromPath($alias) {
    // Remove the langcode element from alias.
    list($alias, $langcode) = $this->removeLangcodeFromAlias($alias);
    if (empty($langcode)) {
      $langcode = $this->languageManager->getDefaultLanguage()->getId();
    }
    // First column on the file is the base language.
    $path = $this->pathAliasManager->getPathByAlias($alias, $langcode);
    if (preg_match('/node\/(\d+)/', $path, $matches)) {
      return $this->entityTypeManager->getStorage('node')->load($matches[1]);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function removeLangcodeFromAlias($alias) {
    $available_languages = array_keys($this->languageManager->getLanguages());
    $mapped_langcodes = $this->migrationsHelper->getMigrationLanguageCodesMapping();
    foreach ($available_languages as $langcode) {
      // Replace the langcode as per the shell.
      if (isset($mapped_langcodes[$langcode])) {
        $langcode = $mapped_langcodes[$langcode];
      }
      if (substr($alias, 0, strlen($langcode) + 1) === '/' . $langcode) {
        $drupal_langcode = array_search($langcode, $mapped_langcodes);
        $alias = str_replace('/' . $langcode, '', $alias);
        return [$alias, $drupal_langcode];
      }
    }
    $drupal_langcode = array_search($langcode, $mapped_langcodes);
    return [$alias, $drupal_langcode];
  }

  /**
   * {@inheritdoc}
   */
  public function excludeCareersLinks() {
    $parameters = new MenuTreeParameters();
    $menu_item = $this->entityTypeManager->getStorage('menu_link_content')->loadByProperties([
      'menu_name' => 'secondary-menu',
      'link.uri' => 'internal:/careers-wave2',
    ]);
    $menu_item = array_values($menu_item);
    $menu_plugin_id = 'menu_link_content:' . $menu_item[0]->uuid->value;
    $parameters
      ->setRoot($menu_plugin_id)
      ->onlyEnabledLinks();
    $tree = $this->menuTree
      ->load('secondary-menu', $parameters);
    $manipulators = [
      [
        'callable' => 'menu.default_tree_manipulators:checkAccess',
      ],
      [
        'callable' => 'menu.default_tree_manipulators:flatten',
      ],
    ];
    $tree = $this->menuTree
      ->transform($tree, $manipulators);

    // Transform the tree to a list of menu links.
    $menu_links = [];
    foreach ($tree as $element) {
      $menu_links[] = $element->link->getPluginId();
    }
    return $menu_links;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteExistingLinks($link_list) {
    $menu_items = $this->entityTypeManager->getStorage('menu_link_content')->loadByProperties([
      'bundle' => ['secondary-menu', 'main'],
    ]);
    foreach ($menu_items as $menu_item) {
      $menu_plugin_id = $menu_item->getPluginId();
      if (!in_array($menu_plugin_id, $link_list)) {
        $menu_item->delete();
      }
    }
  }

  /**
   * Adds Redirection to files marked as isHomePage=true in migration dump.
   *
   * @validate-module-enabled test_module_4,migrate_tools
   *
   * @command nestle-migrate:add-redirect-ishome-uid
   * @aliases nm-ariu
   */
  public function addRedirectForIsHomePageUids() {
    $langcode = $this->languageManager->getDefaultLanguage()->getId();
    $site_multilingual = $this->state->get('test_module_4.source_multilingual', 0);
    $helper = $this->migrationsHelper;
    $list = $helper->getAllFiles();
    $itemCount = 0;
    $redirectCount = 0;
    foreach ($list as $path) {
      $data = $this->migrationsHelper->readJsonFromPath($path);
      if (isset($data['Fields']['isHomePage']) && $data['Fields']['isHomePage'] == 'true') {
        $itemCount++;
        $uid = $this->migrationsHelper->getUidbyFilePath($path);
        if ($site_multilingual) {
          $uid = $this->migrationsHelper->removeLanguageFromUid($uid);
        }
        if ($alias = $this->aliasStorage->load(['alias' => $uid])) {
          $parts = explode('/', $uid);
          $trims = ['Pages', '.aspx'];
          $alt_uid = str_replace($trims, '', $data['Fields']['Url']);
          $parts = array_merge($parts, explode('/', $alt_uid));
          $parts = array_filter($parts);
          $alt_uid = implode('/', $parts);

          if ($site_multilingual) {
            $langcode = $alias['langcode'];
          }
          $redirect_uri = 'internal:' . $alias['source'];
          // Add a Url Redirection to the correct page.
          $this->migrationsHelper->addUrlRedirection($alt_uid, $redirect_uri, $langcode);
          $redirectCount++;
          $this->logger()->debug(dt("Adding Redirection: (@langcode) @source ==> @redirect.", [
            '@langcode' => $langcode,
            '@source' => $alt_uid,
            '@redirect' => $uid,
          ]));
        }
        else {
          $this->logger()->debug(dt("Alias not found - @url.", ['@url' => $uid]));
        }
      }
    }
    $this->logger()->success(dt("Total $itemCount items found having isHomePage=true."));
    $this->logger()->success(dt("Total $redirectCount redirects added."));
  }

  /**
   * POC: update UID in diff_report Table.
   *
   * @validate-module-enabled test_module_4,migrate_tools
   *
   * @command nestle-migrate:poc:update:uids
   * @aliases nm-poc-uuid
   */
  public function pocUpdateUid() {
    $this->logger()->debug(dt("Action: Saving UUID in diff_report table."));
    $all_items = $this->database->select('diff_report', 'a')->fields('a')->execute()->fetchAll();
    // Update all items.
    foreach ($all_items as $item) {
      $path = '\\' . $item->identifier;
      $uid = $this->getUidFromPath($path);
      $this->database->update('diff_report')->condition('id', $item->id)->fields(['uid' => $uid])->execute();
      $this->logger()->debug(dt("Updated UID for $item->identifier($uid)"));
    }
  }

  /**
   * Disable Title, Breadcrumbs, Share block for component pages.
   *
   * @validate-module-enabled test_module_4,migrate_tools
   *
   * @command nestle-migrate:hide:flag:blocks
   * @aliases nm-hfb
   */
  public function hideFlagBlocks() {
    // Load all the nodes in current website.
    $entities = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => [
        'dsu_component_page',
      ],
    ]);
    $flags = [
      'hide_title',
      'hide_breadcrumbs',
      'hide_social_share',
    ];
    foreach ($entities as $entity) {
      $paragraphs = $entity->field_components->referencedEntities();
      if (!empty($paragraphs[0]) && $paragraphs[0]->bundle() == 'ln_c_entityslider') {
        foreach ($flags as $flag) {
          $content_flag = $this->flag->getFlagById($flag);
          if ($content_flag) {
            $flagged = $this->flag->getFlagging($content_flag, $entity);
            if (empty($flagged)) {
              // Flag an entity with a specific flag.
              $this->flag->flag($content_flag, $entity);
            }
          }
        }
        $item = 'Updated Flags for--' . $entity->label() . '(' . $entity->id() . ')';
        $this->output()->writeln($item);
      }
    }
  }

  /**
   * Moves the node pages from the CSV list to draft state.
   *
   * @validate-module-enabled test_module_4,migrate_tools
   *
   * @command nestle-migrate:delete:node:pages
   * @aliases nm-dnp
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function changeModerataionNodePages($state = 'archived') {
    $file = $this->migrationsHelper->getSourceFullPath() . '/' . 'node-delete.csv';
    if (file_exists($file)) {
      $handle = fopen($file, 'r');
      while (($node_pages = fgetcsv($handle)) !== FALSE) {
        if ($node_pages[1] == '404' || $node_pages[1] == '403') {
          $url = parse_url($node_pages[0])['path'];
          $path = $this->pathAliasManager->getPathByAlias($url);
          if (preg_match('/node\/(\d+)/', $path, $matches)) {
            if ($node = $this->entityTypeManager->getStorage('node')->load($matches[1])) {
              $old_state = $node->moderation_state->value;
              $node->set('moderation_state', $state);
              $node->save();
              $item = 'Change moderation state of Node page -' . $node->label() . '(' . $node->id() . ') From : ' . $old_state . ' to ' . $state;
              $this->output()->writeln($item);
            }
          }
        }
      }
      fclose($handle);
    }
  }

  /**
   * Replace acsf site id string in all image files references in text fields.
   *
   * @validate-module-enabled test_module_4,migrate_tools
   *
   * @command nestle-migrate:fix-file-paths
   * @aliases nm-ffp
   */
  public function fixAcsfFilePaths($replace_text, $replace_value = NULL, $items = 30) {
    // Set the AcsfSite 'site_db' value as $replace_text.
    if (empty($replace_value)) {
      $site = AcsfSite::load();
      $replace_value = $site->__get('site_db');
    }
    $this->logger()->notice(dt("This will Replace '$replace_text' with '$replace_value'."));

    $content_entity_types = [
      'block_content',
      'node',
      'paragraph',
    ];
    $operations = [];
    foreach ($content_entity_types as $entity_type) {
      $query = $this->entityTypeManager->getStorage($entity_type);
      $entity_ids = $query->getQuery()->execute();
      $total = count($entity_ids);

      $ids_arr = array_chunk($entity_ids, $items);
      // Loop every entity (node, customblock, paragraph).
      foreach ($ids_arr as $ids) {
        // Initialise operations.
        $operations[] = [
          'update_acsf_file_paths',
          [
            $ids,
            $entity_type,
            $replace_text,
            $replace_value,
            $total,
          ],
        ];
      }
    }
    // Define batch process.
    $batch = [
      'title' => 'Updating Acsf Paths',
      'operations' => $operations,
      'finished' => 'source_destination_replace_finished',
    ];
    // Batch starts.
    batch_set($batch);
    drush_backend_batch_process();
  }

  /**
   * POC: update Link target from Teaser.
   *
   * @validate-module-enabled test_module_4,migrate_tools
   *
   * @command nestle-migrate:update:linktarget
   * @aliases nm-up-lt
   */
  public function updateLinktarget() {
    $entities = $this->entityTypeManager->getStorage('node')->loadByProperties(
      [
        'type' => [
          'teaser',
        ],
      ]);
    foreach ($entities as $item) {
      $teaser_link = $item->get('field_teaser_link')->getValue();
      if (!empty($teaser_link[0]['uri']) && isExternal($teaser_link[0]['uri'])) {
        if (empty($teaser_link[0]['options']['attributes']['target'])) {
          $teaser_link[0]['options']['attributes']['target'] = '_blank';
          $item->set('field_teaser_link', $teaser_link);
          $item->save();
        }
      }
    }
  }

  /**
   * Deletes unused media document entities from the site.
   *
   * @param null|int $items
   *   Items to be processed on each batch process.
   *
   * @validate-module-enabled test_module_4,migrate_tools
   *
   * @command nestle-migrate:image-media-entity-cleanup
   * @aliases nm-imec
   */
  public function imageMediaEntityCleanup($items = '100') {
    $this->state->set('nm_file_entity_cleanup', TRUE);
    $media_bundle = 'image';
    // All Media entities except attached in content types Media fields.
    $all_media_entity = $this->database->select('media', 'm');
    $all_media_entity->condition('m.bundle', $media_bundle, '=');
    $all_media_entity->fields('m', ['uuid', 'mid']);
    $all_media_entity = $all_media_entity->execute()->fetchAll();
    $this->output()->writeln('All Media entities: ' . count($all_media_entity));

    // Fetching all Media reference field and image reference field tables name.
    $content_entity_types = [
      'block_content',
      'node',
      'paragraph',
      'taxonomy_term',
    ];
    foreach ($content_entity_types as $entity_type_id) {
      $bundles = $this->entityTypeBundleInfo
        ->getBundleInfo($entity_type_id);
      foreach ($bundles as $bundle_key => $bundle_label) {
        foreach ($this->entityFieldManager
          ->getFieldDefinitions($entity_type_id, $bundle_key) as $field_definition) {
          if ($field_definition->getType() == 'image') {
            $image_reference_fields[$entity_type_id . '__' . $field_definition->getName()] = $field_definition->getName() . '_target_id';
          }
          if ($field_definition->getType() == 'entity_reference') {
            $settings = $field_definition->getSettings();
            if ($settings['target_type'] == "media") {
              if (in_array($media_bundle, $settings['handler_settings']['target_bundles'])) {
                $media_fields[$entity_type_id . '__' . $field_definition->getName()] = $field_definition->getName() . '_target_id';
              }
            }
          }
        }
      }
    }
    // Fetching all media reference tables data.
    if ($media_fields) {
      foreach ($media_fields as $media_field_table => $media_field_column) {
        $query = $this->database->select($media_field_table, 'media');
        $query->fields('media', [$media_field_column]);
        $all_field_media = $query->execute()->fetchAll();
        if (!empty($all_field_media)) {
          foreach ($all_field_media as $media_obj) {
            $all_field_media_ids[$media_obj->$media_field_column] = $media_obj->$media_field_column;
          }
        }
      }
    }
    $this->output()->writeln('Total Media field reference ids: ' . count($all_field_media_ids));

    // Fetching all image reference tables data.
    $preserve_file_ids = [];
    if ($image_reference_fields) {
      foreach ($image_reference_fields as $image_field_table => $image_field_column) {
        $query = $this->database->select($image_field_table, 'ift');
        $query->fields('ift', [$image_field_column]);
        $all_field_media = $query->execute()->fetchAll();
        if (!empty($all_field_media)) {
          foreach ($all_field_media as $media_obj) {
            $preserve_file_ids[$media_obj->$image_field_column] = $media_obj->$image_field_column;
          }
        }
      }
    }
    $this->output()->writeln('Total Image reference file ids: ' . count($preserve_file_ids));

    $image_media = [];
    // Sorted image media ids.
    foreach ($all_media_entity as $media_entity) {
      $media_obj = Media::load($media_entity->mid);
      $value = $media_obj->image->getValue();
      if (!empty($value) && in_array($value[0]['target_id'], $preserve_file_ids)) {
        $image_mids[$value[0]['target_id']][] = $media_entity->mid;
      }
    }
    // Preserves only unique media for image fid.
    foreach ($image_mids as $media_group) {
      $image_media[current($media_group)] = current($media_group);
    }
    $this->output()->writeln('Image field related media Image: ' . count($image_media));

    $preserve_media = array_merge($image_media, $all_field_media_ids);
    // All Media entities except attached in content fields.
    $orphan_media_entity = $this->database->select('media', 'm');
    $orphan_media_entity->condition('m.bundle', $media_bundle, '=');
    $orphan_media_entity->fields('m', ['uuid', 'mid']);
    $orphan_media_entity->condition('mid', $preserve_media, 'NOT IN');
    $orphan_media_entity = $orphan_media_entity->execute()->fetchAll();

    $this->state->set('test_module_4.other_media_entities', $orphan_media_entity);
    $this->output()->writeln('Need to check Orphans Media ' . $media_bundle . ' Entity used in site: ' . count($orphan_media_entity));

    // Checking media entities which attached in ckeditor (body) field.
    if ($orphan_media_entity) {
      $this->output()->writeln('Preparing Batch process ..');

      $content_entity_types = [
        'block_content',
        'node',
        'paragraph',
      ];
      $operations = [];
      foreach ($content_entity_types as $entity_type) {
        $query = $this->entityTypeManager->getStorage($entity_type);
        $entity_ids = $query->getQuery()->execute();
        $total = count($entity_ids);

        $ids_arr = array_chunk($entity_ids, $items);
        // Loop every entity (node, customblock, paragraph).
        foreach ($ids_arr as $ids) {
          // Initialise operations.
          $operations[] = [
            '\Drupal\test_module_4\MigrationsHelper::checkMediaUsedInEntity',
              [
                $ids,
                $entity_type,
                $total,
              ],
          ];
        }
      }
      // Define batch process.
      $batch = [
        'title' => 'Checking Unused Media Entities',
        'operations' => $operations,
        'finished' => '\Drupal\test_module_4\MigrationsHelper::cleanUpMediaEntities',
      ];
      // Batch starts.
      batch_set($batch);
      drush_backend_batch_process();
      $this->state->set('nm_file_entity_cleanup', FALSE);
      $this->output()->writeln('Clean up process completed.');
    }
  }

  /**
   * Removes given text.
   *
   * @validate-module-enabled test_module_4,migrate_tools
   *
   * @command nestle-migrate:remove:empty-tags
   * @aliases nm-rmtags
   */
  public function convertEmptytags($replace_text, $text_fields = NULL, $types = NULL) {
    $this->logger()->notice(dt("Removing '$replace_text'"));
    // Set default text fields to search on.
    if (empty($text_fields)) {
      $formatted_text_fields = [
        'text_with_summary',
        'text_long',
        'text',
      ];
    }
    else {
      $formatted_text_fields = explode(',', $text_fields);
    }
    // Set the default content entity types.
    if (empty($types)) {
      $content_entity_types = [
        'block_content',
        'node',
        'paragraph',
      ];
    }
    else {
      $content_entity_types = explode(',', $types);
    }

    foreach ($content_entity_types as $entity_type) {
      $entities = $this->entityTypeManager->getStorage($entity_type)->loadMultiple();
      foreach ($entities as $id => $entity) {
        $field_definitions = $entity->getFieldDefinitions();
        foreach ($field_definitions as $field_name => $field_definition) {
          $field_type = $field_definition->getType();
          if (in_array($field_type, $formatted_text_fields)) {
            $field_changed = FALSE;
            $field_value = $entity->{$field_name}->getValue();
            foreach ($field_value as $i => $value) {
              // Replace the string if found.
              if (strpos($value['value'], $replace_text) !== FALSE) {
                $count = substr_count($value['value'], $replace_text);
                $field_value[$i]['value'] = str_replace($replace_text, '', $value['value']);
                $field_changed = TRUE;
              }
              // Update the summary field of text_with_summary field type.
              if ($field_type == 'text_with_summary') {
                if (strpos($value['summary'], $replace_text) !== FALSE) {
                  $count = $count + substr_count($value['summary'], $replace_text);
                  $field_value[$i]['summary'] = str_replace($replace_text, '', $value['summary']);
                  $field_changed = TRUE;
                }
              }
            }
            if ($field_changed) {
              $entity->set($field_name, $field_value)->save();
              $this->logger()->success(dt("Replaced @count instances in @field (@field_type) field of @type - @id", [
                '@count' => $count,
                '@field' => $field_name,
                '@field_type' => $field_type,
                '@type' => $entity_type,
                '@id' => $id,
              ]));
              Cache::invalidateTags($entity->getCacheTagsToInvalidate());
            }
          }
        }
      }
    }
  }

  /**
   * Enable header persistent menu on all enabled parent menu links.
   *
   * @validate-module-enabled test_module_4,migrate_tools
   *
   * @command nestle-migrate:set-header-persistent-menu
   * @aliases nm-shpm
   */
  public function setHeaderPersistentMenu($items = '100') {

    $query = $this->database->select('menu_tree', 'm');
    $query->condition('m.has_children', 1, '=');
    $query->condition('m.menu_name', ['main', 'secondary-menu'], 'IN');
    $query->fields('m', ['id']);
    $results = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

    $field_inside = [
      [
        "plugin_id" => "system_menu_block:header-persistent-submenu",
        "settings" => [
          "id" => "system_menu_block:header-persistent-submenu",
          "label" => "Header persistent submenu",
          "provider" => "system",
          "label_display" => FALSE,
          "level" => "1",
          "depth" => "0",
          "expand_all_items" => 0,
        ],
      ],
    ];

    foreach ($results as $item) {
      list($type, $uuid) = explode(':', $item['id']);
      if ($menu_item = $this->entityTypeManager->getStorage($type)->loadByProperties(['uuid' => $uuid])) {
        $menu_item = reset($menu_item);
        if ($menu_item->hasField('field_inside')) {
          if ($menu_item->field_inside->getValue() != $field_inside) {
            echo "HEADER PERSISTENT MENU SET FOR ::: {$menu_item->id()}\n";
            $menu_item->set('field_inside', $field_inside)->save();
          }
          else {
            echo "ALREADY SET FOR ::: {$menu_item->id()}\n";
          }
        }
      }
    }
  }

  /**
   * Updates file entity with original SHELL path and delete unused file entity.
   *
   * @validate-module-enabled test_module_4,migrate_tools
   *
   * @command nestle-migrate:image-file-entity-cleanup
   * @aliases nm-ifec
   */
  public function imagefileEntityCleanup($update = '10', $scan = '100', $delete = '50') {
    $this->state->set('nm_file_entity_cleanup', TRUE);
    $content_entity_types = [
      'block_content',
      'node',
      'paragraph',
      'taxonomy_term',
      'media',
    ];
    $file_operations = $this->state->get('test_module_4.file_operations');
    if ($file_operations) {
      $this->output()->writeln('Resuming previous process..');
      $source_destination = $this->state->get('test_module_4.source_destination');
    }
    else {
      $this->output()->writeln('Scanning Images...');
      foreach ($content_entity_types as $entity_type_id) {
        $bundles = $this->entityTypeBundleInfo
          ->getBundleInfo($entity_type_id);
        foreach ($bundles as $bundle_key => $bundle_label) {
          foreach ($this->entityFieldManager
            ->getFieldDefinitions($entity_type_id, $bundle_key) as $field_definition) {
            if ($entity_type_id == 'media' && $field_definition->getName() == 'thumbnail' || $entity_type_id == 'media' && $bundle_key != 'image') {
              continue;
            }
            if ($field_definition->getType() == 'image') {
              $image_reference_fields[$entity_type_id . '__' . $field_definition->getName()] = $field_definition->getName() . '_target_id';
            }
          }
        }
      }
      if ($image_reference_fields) {
        foreach ($image_reference_fields as $image_field_table => $image_field_column) {
          $query = $this->database->select($image_field_table, 'ift');
          $query->fields('ift', [$image_field_column]);
          $all_field_media = $query->execute()->fetchAll();
          if (!empty($all_field_media)) {
            foreach ($all_field_media as $media_obj) {
              $file_ids[$media_obj->$image_field_column] = $media_obj->$image_field_column;
            }
          }
        }
      }
      $this->output()->writeln('Total image files found in content: ' . count($file_ids));
      $file_loads = $this->entityTypeManager->getStorage('file')->loadMultiple($file_ids);
      foreach ($file_loads as $file_load) {
        if ($file_load) {
          $existing_file_uris[$file_load->id()] = $file_load->getFileUri();
        }
      }
      asort($existing_file_uris);

      foreach ($existing_file_uris as $fid => $file_uri) {
        $uri_without_ext = substr($file_uri, 0, strrpos($file_uri, "."));
        $last_str = substr($uri_without_ext, strrpos($uri_without_ext, "_") + 1);
        if (is_numeric($last_str) && $last_str < 99) {
          $clean_file_uris[$fid] = substr($file_uri, 0, strrpos($file_uri, "_"));
        }
        else {
          $clean_file_uris[$fid] = $uri_without_ext;
        }
      }
      arsort($clean_file_uris);
      foreach ($clean_file_uris as $clean_uri) {
        foreach ($existing_file_uris as $fid => $actual_uri) {
          if (strpos($actual_uri, $clean_uri . '_') === 0) {
            $sorted_uris[$clean_uri][$fid] = $actual_uri;
            unset($existing_file_uris[$fid]);
          }
          elseif (strpos($actual_uri, $clean_uri . '.') === 0) {
            $sorted_uris[$clean_uri][$fid] = $actual_uri;
            unset($existing_file_uris[$fid]);
          }
        }
      }
      $this->output()->writeln('Scanning image paths in DUMP..');
      $all_dump_images = $this->migrationsHelper->getPrivateFolderFiles('(png|jpg|jpeg|gif)');
      if (empty($all_dump_images)) {
        $this->output()->writeln('File private Directory is missing.');
        $this->state->set('nm_file_entity_cleanup', FALSE);
        exit;
      }
      // Get list of all URIs that are matched in DUMP.
      $dump_uri_list = [];
      foreach ($sorted_uris as $uri => $file_group) {
        $clean_file_name = str_replace("public://", "", $uri);
        $find_images = filter_vals($clean_file_name . '.', $all_dump_images);
        // If one path found on DUMP.
        if (count($find_images) == 1) {
          $dump_uri_list[$uri] = 'public://' . current($find_images);
        }
        // Skips if file multiple occurances in DUMP.
        if (count($find_images) > 1) {
          $this->logger()
            ->debug(dt('Multiple occurences found in DUMP for image:- @path', ['@path' => $find_images]));
          unset($sorted_uris[$uri]);
        }
      }

      // Get a list of All the fid with matched their URIs.
      $source_destination = [];
      $file_operations = [];
      foreach ($sorted_uris as $clean_uri => $file_group) {
        $n = 1;
        foreach ($file_group as $fid => $file_uri) {
          if ($n == 1) {
            $destination_fid = $fid;
            $uri_without_ext = substr($file_uri, 0, strrpos($file_uri, "."));
            if ($uri_without_ext != $clean_uri) {
              if (isset($dump_uri_list[$clean_uri])) {
                $file_operations[$fid] = $dump_uri_list[$clean_uri];
              }
              else {
                $file_operations[$fid] = $clean_uri . '.' . pathinfo($file_uri, PATHINFO_EXTENSION);
              }
            }
          }
          else {
            $source_destination[$fid] = $destination_fid;
          }
          $n++;
        }
      }
      $this->state->set('test_module_4.source_destination', $source_destination);
      $this->state->set('test_module_4.file_operations', $file_operations);
    }

    $this->output()->writeln('Total image path needs to be correct:' . count($file_operations));
    $this->output()->writeln('Total repeated file entities :' . count($source_destination));
    $batch_operations = [];

    // Defines operations for correcting file entities.
    if ($file_operations) {
      $fids_chunk = array_chunk($file_operations, $update, TRUE);
      // Loop every entity (node, customblock, paragraph).
      foreach ($fids_chunk as $chunk) {
        // Initialise operations.
        $batch_operations[] = [
          'lcp_general_file_entity_update',
          [
            $chunk,
            count($file_operations),
          ],
        ];
      }
    }
    if (!empty($source_destination)) {
      // Defines operations for source destination mapping.
      foreach ($content_entity_types as $entity_type) {
        $query = $this->entityTypeManager->getStorage($entity_type);
        if ($entity_type == 'media') {
          $entity_ids = $query->getQuery()->condition('bundle', 'image')->execute();
        }
        else {
          $entity_ids = $query->getQuery()->execute();
        }
        $total = count($entity_ids);
        $ids_arr = array_chunk($entity_ids, $scan);
        // Loop every entity (node, customblock, paragraph).
        foreach ($ids_arr as $ids) {
          // Initialise operations.
          $batch_operations[] = [
            'source_destination_replace_id',
            [
              $ids,
              $entity_type,
              $total,
              'fid',
            ],
          ];
        }
      }

      // Define batch process for deleting file entities.
      $source_destination = array_keys($source_destination);
      $file_ids = array_chunk($source_destination, $delete);
      // Define batch process for del.
      foreach ($file_ids as $chunk) {
        // Initialise operations.
        $batch_operations[] = [
          'file_entity_deletion',
          [
            $chunk,
          ],
        ];
      }
    }

    $this->output()->writeln('Starting batch process..');
    // Defines the batch process.
    $batch = [
      'title' => 'Updating File URIs and flush existing image styles and Replace File Entities',
      'operations' => $batch_operations,
      'finished' => 'source_destination_replace_finished',
    ];
    // Batch starts.
    batch_set($batch);
    drush_backend_batch_process();
    $this->state->set('nm_file_entity_cleanup', FALSE);
    $this->output()->writeln('Clean up process completed.');
  }

  /**
   * Removes unused file entity used in entity revisions.
   *
   * @validate-module-enabled test_module_4,migrate_tools
   *
   * @command nestle-migrate:file-entity-revisions-cleanup
   * @aliases nm-ferc
   */
  public function fileEntityRevisionsCleanup() {
    $content_entity_types = [
      'block_content',
      'node',
      'paragraph',
      'taxonomy_term',
      'media',
    ];
    foreach ($content_entity_types as $entity_type_id) {
      $bundles = $this->entityTypeBundleInfo
        ->getBundleInfo($entity_type_id);
      foreach ($bundles as $bundle_key => $bundle_label) {
        foreach ($this->entityFieldManager
          ->getFieldDefinitions($entity_type_id, $bundle_key) as $field_definition) {
          if ($entity_type_id == 'media' && $field_definition->getName() == 'thumbnail' || $entity_type_id == 'media' && $bundle_key != 'image') {
            continue;
          }
          if ($field_definition->getType() == 'image') {
            $image_reference_fields[$entity_type_id . '__' . $field_definition->getName()] = $field_definition->getName() . '_target_id';
            $image_revision_fields[$entity_type_id . '_revision__' . $field_definition->getName()] = $field_definition->getName() . '_target_id';

          }
        }
      }
    }
    if ($image_reference_fields) {
      foreach ($image_reference_fields as $image_field_table => $image_field_column) {
        $query = $this->database->select($image_field_table, 'ift');
        $query->fields('ift', [$image_field_column]);
        $all_field_media = $query->execute()->fetchAll();
        if (!empty($all_field_media)) {
          foreach ($all_field_media as $media_obj) {
            $file_ids[$media_obj->$image_field_column] = $media_obj->$image_field_column;
          }
        }
      }
    }
    $this->output()->writeln('Total content images:' . count($file_ids));

    if ($image_revision_fields) {
      foreach ($image_revision_fields as $image_field_table => $image_field_column) {
        if ($image_field_table == "paragraph_revision__field_banner_background_image") {
          continue;
        }
        $query = $this->database->select($image_field_table, 'ift');
        $query->fields('ift', [$image_field_column]);
        $all_field_media = $query->execute()->fetchAll();
        if (!empty($all_field_media)) {
          foreach ($all_field_media as $media_obj) {
            $file_revision_ids[$media_obj->$image_field_column] = $media_obj->$image_field_column;
          }
        }
      }
    }
    $all_revision_file_ids = array_diff($file_revision_ids, $file_ids);

    if (!empty($all_revision_file_ids)) {
      $all_revision_file_entity = $this->database->select('file_managed', 'fm');
      $all_revision_file_entity->condition('fm.status', 1);
      $all_revision_file_entity->condition('fm.fid', $all_revision_file_ids, 'IN');
      $all_revision_file_entity->fields('fm', ['fid', 'uri']);
      $all_revision_file_entity = $all_revision_file_entity->execute()->fetchAll();

      $this->output()->writeln('Total orphans revision file entities :' . count($all_revision_file_entity));

      if (!empty($all_revision_file_entity)) {
        foreach ($all_revision_file_entity as $file_item) {
          $orphan_fids[$file_item->fid] = $file_item->fid;
        }
        if ($orphan_fids) {
          $batch_operations[] = [
            'file_entity_deletion',
            [
              $orphan_fids,
            ],
          ];
        }

        $this->output()->writeln('Starting batch process..');
        // Defines the batch process.
        $batch = [
          'title' => 'Deleting Revision orphan file entities.',
          'operations' => $batch_operations,
          'finished' => 'source_destination_replace_finished',
        ];
        // Batch starts.
        batch_set($batch);
        drush_backend_batch_process();
      }
      else {
        $this->output()->writeln('No file entities to be deleted.');
      }

    }
  }

  /**
   * Fix meta tags absolute issues and acsf id issue on twitter tag.
   *
   * @param string $replace_text
   *   Text that needs to be replaced.
   * @param null|string $replace_value
   *   Text that needs to be replaced with.
   *
   * @validate-module-enabled test_module_4,migrate_tools
   *
   * @command nestle-migrate:fix-meta-tags
   * @aliases nm-fmt
   * @usage nestle-migrate:fix-meta-tags replace_text replace_value
   */
  public function fixMetaTags($replace_text, $replace_value = NULL) {
    // Set the AcsfSite 'site_db' value as $replace_text.
    $host = 'https://' . $this->request->getCurrentRequest()->getHost();
    if (empty($replace_value)) {
      $site = AcsfSite::load();
      $replace_value = $site->__get('site_db');
    }
    $text = 'This will Replace ' . $replace_text . ' with ' . $replace_value . ' for twitter image meta tag';
    $this->output()->writeln($text);
    $field_name = 'field_meta_tags';
    // Get the individual fields (field instances) associated with bundles.
    $fields = $this->entityTypeManager->getStorage('field_config')->loadByProperties(['field_name' => $field_name]);

    foreach ($fields as $field) {
      // Get the bundle this field is attached to.
      $bundle = $field->getTargetBundle();
      // Determine the table and "value" field names.
      $field_table = "node__" . $field_name;
      $field_value_field = $field_name . "_value";
      // Get all records where the field data does not match the default.
      $query = $this->database->select($field_table);
      $query->addField($field_table, 'entity_id');
      $query->addField($field_table, 'revision_id');
      $query->addField($field_table, 'langcode');
      $query->addField($field_table, $field_value_field);
      $query->condition('bundle', $bundle, '=');
      $result = $query->execute();
      $records = $result->fetchAll();
      $counter = 1;
      foreach ($records as $record) {
        $current_tags = unserialize($record->$field_value_field);
        if (!empty($current_tags['canonical_url'])) {
          unset($current_tags['canonical_url']);
        }
        if (!empty($current_tags['twitter_cards_image'])) {
          $path = parse_url($current_tags['twitter_cards_image']);
          $current_tags['twitter_cards_image'] = $host . str_replace($replace_text, $replace_value, $path['path']);
        }
        $tags_string = serialize($current_tags);
        $this->database->update($field_table)
          ->fields([
            $field_value_field => $tags_string,
          ])
          ->condition('entity_id', $record->entity_id)
          ->condition('revision_id', $record->revision_id)
          ->condition('langcode', $record->langcode)
          ->execute();
        $items = 'Processed ' . $counter . ' of ' . count($records) . ' ' . $bundle . ' records';
        $this->output()->writeln($items);
        $counter++;
      }
    }
  }

  /**
   * Deletes unused media document entities from the site.
   *
   * @param null|int $items
   *   Items to be processed on each batch process.
   *
   * @validate-module-enabled test_module_4,migrate_tools
   *
   * @command nestle-migrate:document-media-entity-cleanup
   * @aliases nm-dmc
   */
  public function documentmediaCleanup($items = '100') {
    $this->state->set('nm_file_entity_cleanup', TRUE);
    // All Media entities except attached in content types Media fields.
    $all_media_entity = $this->database->select('media', 'm');
    $all_media_entity->condition('m.bundle', 'document', '=');
    $all_media_entity->fields('m', ['uuid', 'mid']);
    $all_media_entity = $all_media_entity->execute()->fetchAll();
    $this->output()->writeln('All Document Media entities: ' . count($all_media_entity));

    // Fetching all Media reference field and image reference field tables name.
    $content_entity_types = [
      'block_content',
      'node',
      'paragraph',
      'taxonomy_term',
    ];
    foreach ($content_entity_types as $entity_type_id) {
      $bundles = $this->entityTypeBundleInfo
        ->getBundleInfo($entity_type_id);
      foreach ($bundles as $bundle_key => $bundle_label) {
        foreach ($this->entityFieldManager
          ->getFieldDefinitions($entity_type_id, $bundle_key) as $field_definition) {
          if ($field_definition->getType() == 'entity_reference') {
            $settings = $field_definition->getSettings();
            if ($settings['target_type'] == "media") {
              if (in_array('document', $settings['handler_settings']['target_bundles'])) {
                $media_fields[$entity_type_id . '__' . $field_definition->getName()] = $field_definition->getName() . '_target_id';
              }
            }
          }
        }
      }
    }
    // Fetching all media reference tables data.
    if ($media_fields) {
      foreach ($media_fields as $media_field_table => $media_field_column) {
        $query = $this->database->select($media_field_table, 'media');
        $query->fields('media', [$media_field_column]);
        $all_field_media = $query->execute()->fetchAll();
        if (!empty($all_field_media)) {
          foreach ($all_field_media as $media_obj) {
            $field_media_ids[$media_obj->$media_field_column] = $media_obj->$media_field_column;
          }
        }
      }
    }
    // Create media id => file_uri.
    foreach ($all_media_entity as $media_entity) {
      $media_obj = Media::load($media_entity->mid);
      $value = $media_obj->field_document;
      if (!empty($value)) {
        $value = $value->getValue();
        $media_fids[$media_entity->mid] = $value[0]['target_id'];
      }
    }
    $file_loads = $this->entityTypeManager->getStorage('file')
      ->loadMultiple($media_fids);
    foreach ($file_loads as $file_load) {
      if ($file_load) {
        $file_uris[$file_load->id()] = $file_load->getFileUri();
      }
    }
    foreach ($media_fids as $mid => $fid) {
      $all_media_uris[$mid] = $file_uris[$fid];
    }
    // Finding orphans media ids.
    $total_orphans_media = array_diff_key($all_media_uris, $field_media_ids);
    foreach ($total_orphans_media as $all_mid => $all_uri) {
      if ($all_uri) {
        $parse_url = parse_url($all_uri);
        if (isset($parse_url['path'])) {
          $proper_uris[$all_mid] = 'public://' . basename($parse_url['path']);;
        }
        else {
          $uri_without_ext = substr($all_uri, 0, strrpos($all_uri, "."));
          $last_str = substr($uri_without_ext, strrpos($uri_without_ext, "_") + 1);
          if (is_numeric($last_str) && $last_str < 99) {
            $orphan_uris[$all_mid] = substr($parse_url['host'], 0, strrpos($parse_url['host'], "_"));
          }
        }
      }
    }
    $source_destination = [];
    if (!empty($orphan_uris)) {
      foreach ($field_media_ids as $mid) {
        $ext = pathinfo($all_media_uris[$mid], PATHINFO_EXTENSION);
        $uri_without_ext = substr($all_media_uris[$mid], 0, strrpos($all_media_uris[$mid], "."));
        $last_str = substr($uri_without_ext, strrpos($uri_without_ext, "_") + 1);
        if (is_numeric($last_str) && $last_str < 99) {
          $field_clean_uri = substr($all_media_uris[$mid], 0, strrpos($all_media_uris[$mid], "_")) . '.' . $ext;
          foreach ($proper_uris as $proper_mid => $proper_uri) {
            if ($proper_uri == $field_clean_uri) {
              $source_destination[$mid] = $proper_mid;
              $delete_mid[$mid] = $mid;
            }
          }
        }
        foreach ($orphan_uris as $orphan_mid => $orphan_uri) {
          if (strpos($all_media_uris[$mid], $orphan_uri)) {
            $delete_mid[$orphan_mid] = $orphan_mid;
          }
        }
      }
    }
    $this->state->set('test_module_4.source_destination', $source_destination);
    $this->output()->writeln('Total Media field reference ids: ' . count($field_media_ids));
    $this->output()->writeln('Total Media Need to corrected: ' . count($source_destination));
    if (!empty($source_destination)) {
      // Defines operations for source destination mapping.
      foreach ($content_entity_types as $entity_type) {
        $query = $this->entityTypeManager->getStorage($entity_type);
        if ($entity_type == 'media') {
          $entity_ids = $query->getQuery()
            ->condition('bundle', 'image')
            ->execute();
        }
        else {
          $entity_ids = $query->getQuery()->execute();
        }
        $total = count($entity_ids);
        $ids_arr = array_chunk($entity_ids, $items);
        // Loop every entity (node, customblock, paragraph).
        foreach ($ids_arr as $ids) {
          // Initialise operations.
          $batch_operations[] = [
            'source_destination_replace_id',
            [
              $ids,
              $entity_type,
              $total,
              'mid',
            ],
          ];
        }
      }
      $batch = [
        'title' => 'Replacing media entities with correct one',
        'operations' => $batch_operations,
        'finished' => 'source_destination_replace_finished',
      ];
      // Batch starts.
      batch_set($batch);
      drush_backend_batch_process();
    }
    if (!empty($delete_mid)) {
      $this->output()->writeln('Deleting orphans Media entities');
      $storage = $this->entityTypeManager->getStorage('media');
      $orphan_entities = $storage->loadMultiple($delete_mid);
      $storage->delete($orphan_entities);
      $this->output()->writeln('Total Media document deleted: ' . count($delete_mid));
    }
    $this->state->set('nm_file_entity_cleanup', FALSE);
    $this->output()->writeln('Clean up process completed.');
  }

  /**
   * Deletes unused file entities from the site.
   *
   * @param null|string $type
   *   File Type to be processed.
   * @param null|int $items
   *   Items to be processed on each batch process.
   *
   * @validate-module-enabled test_module_4,migrate_tools
   *
   * @command nestle-migrate:unused-file-entity-cleanup
   * @aliases nm-ufec
   */
  public function unusedFileEntity($type, $items = '100') {
    if ($type == 'document') {
      $ext = ['pdf', 'doc', 'ppt', 'xlsx'];
    }
    elseif ($type == 'image') {
      $ext = ['png', 'gif', 'jpg', 'jpeg'];
    }
    else {
      $this->output()->writeln('File type invalid');
    }
    $all_file_entity = $this->database->select('file_managed', 'fm');
    $all_file_entity->fields('fm', ['fid', 'filename']);
    $all_file_entity = $all_file_entity->execute()->fetchAll();

    foreach ($all_file_entity as $file_entity) {
      $file_ext = pathinfo($file_entity->filename, PATHINFO_EXTENSION);
      if (in_array($file_ext, $ext)) {
        $document_fid[$file_entity->fid] = $file_entity->filename;
      }
    }
    $this->output()->writeln('Total Document file entity: ' . count($document_fid));

    if (!empty($document_fid)) {
      $fids_chunk = array_chunk($document_fid, $items, TRUE);
      // Loop every entity (node, customblock, paragraph).
      foreach ($fids_chunk as $chunk) {
        // Initialise operations.
        $batch_operations[] = [
          'delete_unusedfile_entity',
          [
            $chunk,
          ],
        ];
      }
      $batch = [
        'title' => 'Deleting Unused Document file entities',
        'operations' => $batch_operations,
        'finished' => 'source_destination_replace_finished',
      ];
      // Batch starts.
      batch_set($batch);
      drush_backend_batch_process();
    }
  }

}
