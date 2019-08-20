<?php

namespace Drupal\test_module_4;

use Drupal\redirect\Entity\Redirect;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\File\FileSystem;
use Drupal\media\Entity\Media;
use GuzzleHttp\Exception\RequestException;
use Drupal\Component\Utility\UrlHelper;
use DomDocument;
use Drupal\Core\Url;

/**
 * Helper service for migrations.
 */
class MigrationsHelper {

  /**
   * {@inheritdoc}
   */
  protected $typeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct($typeManager, $defaultCache, $state, $fileSystem, $httpClient, $loggerFactory, $configFactory, $imageFactory, $focalPointManager, $languageManager) {
    $this->typeManager = $typeManager;
    $this->cache = $defaultCache;
    $this->state = $state;
    $this->file_system = $fileSystem;
    $this->httpClient = $httpClient;
    $this->logger = $loggerFactory->get('test_module_4');
    $this->geolocationConfig = $configFactory->get('geolocation.settings');
    $this->imageFactory = $imageFactory;
    $this->focalPointManager = $focalPointManager;
    $this->languageManager = $languageManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceUrls($migration_id, $contentType = NULL, $filter_path = NULL, $filters = [], $paragraphType = NULL) {
    $urls = [];
    if (!empty($migration_id)) {
      $cid = 'test_module_4:migrations_helper:source_urls:' . $migration_id;
      if ($cache = $this->cache->get($cid)) {
        $urls = $cache->data;
      }
      else {
        $urls = $this->filterFiles($contentType, $filters);
        if (!empty($filter_path)) {
          $urls = array_filter($urls, function ($url) use ($filter_path) {
            return strpos($url, $filter_path) !== FALSE;
          });
          $urls = array_values($urls);
        }
        if (!empty($paragraphType)) {
          $list = [];
          $exclude_widget = [
            'WidgetTopBox',
            'ImageLinkTextWithBlackBar',
            'ColorBoxHtml',
            'BoxWithImageAndBottomText',
            'PeopleCarousel',
            'Privacy',
            'carousel1',
            'carousel2',
          ];
          foreach ($urls as $path) {
            $json = $this->readJsonFromPath($path);
            if (!in_array($json['Fields']['NSE_WidgetInstanceTypeName'], $exclude_widget)) {
              $content = NULL;
              if (isset($json['Fields']['NSE_HTMLContent'])) {
                $content = $json['Fields']['NSE_HTMLContent'];
              }
              elseif (isset($json['Fields']['NSE_HTMLTitle'])) {
                $content = $json['Fields']['NSE_HTMLTitle'];
              }
              if (!empty($content)) {
                $component_type = $this->getParagraphComponentName($content);

                if ($component_type == 'ln_c_card') {
                  $dom = new DOMDocument();
                  if (@$dom->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $content)) {
                    $nodes = $dom->getElementsByTagName('div');
                    foreach ($nodes as $node) {
                      $card_class[] = $node->getAttribute('class');
                    }
                    if (in_array('imagewrapper', $card_class) && in_array('contentwrapper', $card_class)) {
                      $component_type = 'ln_c_card';
                    }
                    else {
                      $component_type = 'c_text';
                    }
                  }
                }

                if ($component_type == $paragraphType) {
                  $list[] = $path;
                }
              }
            }
          }
          $urls = $list;
        }
        if ($migration_id == 'node_news') {
          $urls = $this->filterNewsContent($urls);
        }
        if ($migration_id == 'node_teaser_news') {
          $urls = $this->filterTeaserContent($urls);
        }
        if ($migration_id == 'brand_term') {
          $urls = $this->filterTaxonomyPages($urls);
        }
        if ($migration_id == 'node_components') {
          $results = $this->filterTaxonomyPages($urls);
          $urls = array_values(array_diff($urls, $results));
        }
        $this->cache->set($cid, $urls, CacheBackendInterface::CACHE_PERMANENT);
      }
    }
    return $urls;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceCsv($filename) {
    return $this->getSourceFullPath() . '/' . $filename;
  }

  /**
   * {@inheritdoc}
   */
  public function filterFiles($contentType, $filters = []) {
    $list = [];
    $items = $this->getAllFiles();
    if ($contentType == '*') {
      return $items;
    }

    if (!is_array($contentType)) {
      $contentType = [$contentType];
    }

    // Iterate to each filter and fetch the respective url.
    foreach ($items as $path) {
      $json = $this->readJsonFromPath($path);
      if (isset($json['Fields']['ContentType']) && in_array($json['Fields']['ContentType'], $contentType)) {
        if (!empty($filters)) {
          $filters_match = FALSE;
          foreach ($filters as $filter) {
            $filters_match = FALSE;
            foreach ($filter as $key => $value) {
              if (isset($json['Fields'][$key]) && ($json['Fields'][$key] == $value || $value == '*')) {
                $filters_match = TRUE;
              }
              else {
                $filters_match = FALSE;
                break;
              }
            }
            if ($filters_match) {
              $list[] = $path;
            }
          }
        }
        else {
          $list[] = $path;
        }
      }
    }
    return $list;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllFiles($type = 'json') {
    $files = [];
    $cid = 'test_module_4:migrations_helper:source_files';
    if ($cache = $this->cache->get($cid)) {
      $files = $cache->data;
    }
    else {
      $path = $this->getSourceFullPath();
      $objects = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path), \RecursiveIteratorIterator::SELF_FIRST);
      $objects = new \RegexIterator($objects, '/^.+\.' . $type . '$/i');
      foreach ($objects as $name => $object) {
        $files[] = $name;
      }
      $this->cache->set($cid, $files, CacheBackendInterface::CACHE_PERMANENT);
    }
    return $files;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceFullPath() {
    $dir = $this->state->get('test_module_4.source_directory', '');
    return $this->file_system->realpath('private://' . $dir);
  }

  /**
   * {@inheritdoc}
   */
  public function readJsonFromPath($path) {
    $data = file_get_contents($path);
    $data = json_decode($data, TRUE);
    if (is_array($data)) {
      return $data;
    }
    elseif (is_string($data)) {
      $data = json_decode($data, TRUE);
    }
    if (JSON_ERROR_NONE !== json_last_error()) {
      $this->logger->debug('Error parsing JSON File @file. Error: @error', ['@file' => $path, '@error' => json_last_error_msg()]);
    }
    return $data === NULL ? [] : $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getUidbyFilePath($path) {
    $url = NULL;
    if (!empty($path)) {
      $data = $this->readJsonFromPath($path);
      $url_path = '';
      if (isset($data['Fields']['Url'])) {
        $url_path = $data['Fields']['Url'];
      }
      elseif (isset($data['Url'])) {
        $url_path = $data['Url'];
      }
      // Remove Url Component if isHomePage = 'true'.
      if (isset($data['Fields']['isHomePage']) && $data['Fields']['isHomePage'] == 'true') {
        $url_path = '';
      }
      $full_path = $this->getSourceFullPath();
      $url = str_replace($full_path, '', $path);
      $url = explode('/', $url);
      // Remove last two components of Url.
      array_pop($url);
      array_pop($url);
      $url = implode('/', $url);
      $url = strtolower($url);
      $trims = ['Pages', '.aspx'];
      $url_path = str_replace($trims, '', $url_path);
      $url_path = strtolower($url_path);
      $url = $url . $url_path;
      if ($url != '/home') {
        $url = str_replace('/home', '', $url);
      }
    }
    return $url;
  }

  /**
   * {@inheritdoc}
   */
  public function updateUidInSourceJson($path) {
    $object = $this->readJsonFromPath($path);
    if (!empty($object)) {
      if (empty($object['Fields']['Url'])) {
        $object['Fields']['Url'] = $object['Url'];
        unset($object['Url']);
      }
      if ($object['Fields']['ContentType'] == 'NCP_Document') {
        $uid = strtolower($object['Fields']['Url']);
        if (empty($object['Fields']['Title'])) {
          $object['Fields']['Title'] = $object['Fields']['FileLeafRef'];
        }
      }
      else {
        $uid = $this->getUidbyFilePath($path);
      }

      $multilingual = $this->state->get('test_module_4.source_multilingual', 0);
      if ($multilingual) {
        // Language alterations to uid.
        $available_languages = array_keys($this->languageManager->getLanguages());
        $mapped_langcodes = $this->getMigrationLanguageCodesMapping();
        foreach ($available_languages as $langcode) {
          // Replace the langcode as per the shell.
          if (isset($mapped_langcodes[$langcode])) {
            $langcode = $mapped_langcodes[$langcode];
          }
          if (substr($uid, 0, strlen($langcode) + 1) === '/' . $langcode) {
            $object['Fields']['langcode'] = $langcode;
          }
        }
      }

      // Adding exception for Home page.
      if (empty($uid) && isset($object['Fields']['isHomePage']) && $object['Fields']['isHomePage'] == 'true' && in_array($object['Fields']['Url'], ['Pages/default.aspx', 'Pages/home.aspx'])) {
        $uid = '/home';
      }

      $object['Fields']['uid'] = $uid;
      $data = json_encode($object, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
      file_unmanaged_save_data($data, $path, FILE_EXISTS_REPLACE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function updateWeightInSourceXml($path) {
    $xml = simplexml_load_file($path);
    $weight = 1;
    foreach ($xml->children() as $item) {
      $item->addAttribute('weight', $weight);
      $weight++;
    }
    $xml->asXML($path);
  }

  /**
   * {@inheritdoc}
   */
  public function removeLanguageFromUid($uid) {
    $multilingual = $this->state->get('test_module_4.source_multilingual', 0);
    if ($multilingual) {
      // Language alterations to uid.
      $available_languages = array_keys($this->languageManager->getLanguages());
      $mapped_langcodes = $this->getMigrationLanguageCodesMapping();
      foreach ($available_languages as $langcode) {
        // Replace the langcode as per the shell.
        if (isset($mapped_langcodes[$langcode])) {
          $langcode = $mapped_langcodes[$langcode];
        }
        if (substr($uid, 0, strlen($langcode) + 1) === '/' . $langcode) {
          $path_alias = str_replace('/' . $langcode . '/', '/', $uid);
          return $path_alias;
        }
      }
    }
    return $uid;
  }

  /**
   * {@inheritdoc}
   */
  public function cleanImageAndGenerateTids($image_path) {
    // Use relative path for urls begining with the configured string.
    if (strpos($image_path, $this->state->get('test_module_4.whitelist_image_url')) !== FALSE) {
      $image_parse = parse_url($image_path);
      $image_path = $image_parse['path'];
      $this->logger->debug("Found Image starting with https://auth-prod: @image.", ['@image' => $image_path]);
    }

    if (UrlHelper::isExternal($image_path)) {
      // Image is external.
      $image_parse = parse_url($image_path);
      $path = $image_path;
      $tids = $this->getMediaTermsFromImagePath($image_parse['path']);
      $this->logger->debug("External Image Found: @path (@tids).", [
        '@path' => $path,
        '@tids' => implode(',', $tids),
      ]);
    }
    else {
      // Image is internal.
      $image_path = urldecode($image_path);
      $image_path = strtolower($image_path);
      // Remove unwanted querystring chars.
      if (strrpos($image_path, '?') !== FALSE) {
        $image_path = substr($image_path, 0, strrpos($image_path, '?'));
      }
      $tids = $this->getMediaTermsFromImagePath($image_path);
      $path = $this->getSourceFullPath() . $image_path;
    }

    return [
      $path,
      $tids,
      $image_path,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function createReusableMediaFromImageTag($value) {
    $file = NULL;
    if (preg_match('/src="([^"]*)"/', $value, $matches)) {
      $image_path = $matches[1];
      preg_match('/alt="([^"]*)"/', $value, $alt_tag_matches);
      $alt_tag = $alt_tag_matches[1];
      preg_match('/title="([^"]*)"/', $value, $title_tag_matches);
      $title_tag = $title_tag_matches[1];

      list($path, $tids, $image_path) = $this->cleanImageAndGenerateTids($image_path);

      // Find the image media if it already exists.
      $media = $this->lookupExistingMedia($image_path, 'media', 'image.entity.uri');
      // Else continue with creating the new media.
      if ($media == FALSE) {
        $image_data = file_get_contents($path);
        if ($file = file_save_data($image_data, 'public:/' . $image_path, FILE_EXISTS_REPLACE)) {
          $media = Media::create([
            'bundle' => 'image',
            'image' => [
              'entity' => $file,
              'alt' => $alt_tag,
              'title' => $title_tag,
            ],
            'status' => TRUE,
            'field_media_in_library' => TRUE,
            'field_media_category' => $tids,
            'uid' => 1,
            'moderation_state' => 'published',
          ]);
          $media->save();
          // Save focal-point for this image media.
          $this->updateMediaFocalPoint($media);
        }
      }
    }
    return $file;
  }

  /**
   * {@inheritdoc}
   */
  public function createReusableMediaFormDocuments($value) {
    $media = NULL;
    if (preg_match('/href="([^"]*)"/', $value[0], $matches)) {
      $file_path = $matches[1];
      // Cleaning file path.
      $file_path = urldecode($file_path);
      $file_path = strtolower($file_path);
      // Remove unwanted querystring chars.
      if (strrpos($file_path, '?') !== FALSE) {
        $file_path = substr($file_path, 0, strrpos($file_path, '?'));
      }
      // Find the document media if it already exists.
      $media = $this->lookupExistingMedia($file_path);
      // Else continue with creating the new media.
      if ($media == FALSE) {
        $tids = $this->getMediaTermsFromImagePath($file_path);
        $path = $this->getSourceFullPath() . $file_path;
        $file_data = file_get_contents($path);
        $filename = FileSystem::basename($path);
        if ($file = file_save_data($file_data, 'public:/' . $file_path, FILE_EXISTS_REPLACE)) {
          if (!empty($value[1])) {
            $name = $value[1];
          }
          else {
            $name = $filename;
          }
          $media = Media::create([
            'bundle' => 'document',
            'field_document' => ['entity' => $file],
            'status' => TRUE,
            'field_media_in_library' => TRUE,
            'field_media_category' => $tids,
            'uid' => 1,
            'moderation_state' => 'published',
            'name' => $name,
          ]);
          $media->save();
        }
      }
    }
    return $media;
  }

  /**
   * {@inheritdoc}
   */
  public function lookupExistingMedia($path, $storage = 'media', $field = 'field_document.entity.uri') {
    $result = \Drupal::entityQuery($storage)->condition('status', 1)->condition($field, 'public:/' . $path)->execute();
    if (!empty($result)) {
      $media = reset($result);
      return \Drupal::entityTypeManager()->getStorage('media')->load($media);
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function createReusableMediaFormVideos($value) {
    $media = NULL;
    if (preg_match('/href="([^"]*)"/', $value, $matches)) {
      $video_url = $matches[1];
      if (strpos($video_url, 'youtube') || strpos($video_url, 'youtu.be')) {
        $media = Media::create([
          'bundle' => 'video',
          'field_media_video_embed_field' => $video_url,
          'status' => TRUE,
          'field_media_in_library' => TRUE,
          'uid' => 1,
          'moderation_state' => 'published',
        ]);
        $media->save();
      }
    }
    return $media;
  }

  /**
   * {@inheritdoc}
   */
  public function reverseGeolocationLookup($lat, $lng) {
    $address = [];
    $key = $this->geolocationConfig->get('google_map_api_key');
    if (!empty($key)) {
      $request_url = 'https://maps.googleapis.com/maps/api/geocode/json?latlng=' . $lat . ',' . $lng . '&key=' . $key;
      try {
        $data = $this->httpClient->request('GET', $request_url)->getBody();
      }
      catch (RequestException $e) {
        $this->logger->error('Error requesting Geolocation API: @url, @message', ['@url' => $request_url, '@message' => $e->getMessage()]);
      }
      if (!empty($data)) {
        $data = json_decode($data, TRUE);
        if (empty($data['error_message']) && !empty($data['results'])) {
          $results = $data['results'];
          foreach ($results[0]['address_components'] as $item) {
            if ($item['types'][0] == 'country') {
              $address[$item['types'][0]] = $item['short_name'];
              continue;
            }
            $address[$item['types'][0]] = $item['long_name'];
          }
        }
      }
    }

    return $address;
  }

  /**
   * Matches the given html's class for returning the component name.
   *
   * @param string $html
   *   HTML snippet from the json file.
   *
   * @return string
   *   The component name.
   */
  public function getParagraphComponentName($html) {
    // Prepare assoc array of predictive class names.
    $html_class_names = [
      'card' => 'ln_c_card',
      'csv3cols' => 'layout_columns_3',
      'ShellAccordeonContainer' => 'accordion',
      'button' => 'dsu_c_cta_button',
      'ColoredBox' => 'ln_c_box_expandable',
      'flickrslideshowsnippet' => 'ln_c_flickr',
      'socialBar' => 'dsu_c_socialbuttons',
      'youtube-wrapper' => 'c_externalvideo',
    ];
    $value = stripcslashes($html);
    $value = str_replace(["<br />", "\r\n"], '', $value);
    $value = trim($value);
    $markup = new DOMDocument();
    libxml_use_internal_errors(TRUE);
    if (@$markup->loadHTML($value, LIBXML_HTML_NOIMPLIED)) {
      libxml_clear_errors();
      if ($markup->childNodes->length == 2) {
        $node = $markup->childNodes->item(1);
        $node_classes = explode(' ', $node->getAttribute('class'));
        foreach (array_keys($html_class_names) as $component) {
          if (in_array($component, $node_classes)) {
            return $html_class_names[$component];
          }
        }
        if ($node->getAttribute('style') != '' && $node->childNodes->length == 1) {
          if ($node->childNodes->item(0)->tagName == 'div') {
            $youtube_class = $node->childNodes->item(0)->getAttribute('class');
            if ($youtube_class == 'youtube-wrapper') {
              return 'c_externalvideo';
            }
          }
        }
      }
    }
    return 'c_text';
  }

  /**
   * Gets Media category from image paths.
   */
  public function getMediaTermsFromImagePath($image_path) {

    if (!isset($image_path) && empty($image_path)) {
      return [];
    }

    $mediaterms = explode("/", $image_path);
    $mediaterms = array_filter($mediaterms);
    array_pop($mediaterms);
    $tids = [];
    $i = 0;

    if (!empty($mediaterms)) {

      foreach ($mediaterms as $mediaterm) {
        // Check if term exists and if its first term in the path.
        if ($i == 0) {
          $tids[] = $this->getTidByName($mediaterm, 'media_category', []);
        }
        else {
          $parenttermid = [end($tids)];
          $tids[] = $this->getTidByName($mediaterm, 'media_category', $parenttermid);
        }
        $i++;
      }
    }

    return $tids;
  }

  /**
   * Get term Id by name.
   */
  public function getTidByName($term_name, $vocab, $parent = []) {

    $properties = [];
    if (!empty($term_name)) {
      $properties['name'] = $term_name;
    }
    if (!empty($vocab)) {
      $properties['vid'] = $vocab;
    }
    if (!empty($parent)) {
      $properties['parent'] = $parent;
    }
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties($properties);
    $term = reset($terms);

    if (!empty($term)) {
      return $term->id();
    }
    else {
      // Create the taxonomy term.
      $new_term = Term::create([
        'name' => $term_name,
        'vid' => $vocab,
        'parent' => $parent,
      ]);

      // Save the taxonomy term.
      $new_term->save();
    }

    return $new_term->id();
  }

  /**
   * {@inheritdoc}
   */
  public function filterNewsContent($urls = []) {
    $items = $urls;
    $list = [];
    foreach ($items as $item) {
      $json = $this->readJsonFromPath($item);
      if (empty($json['Fields']['NCP_NAndF_RelatedLink'])) {
        $list[] = $item;
      }
      if (isset($json['Fields']['NCP_NAndF_RelatedLink'])) {
        $value = $json['Fields']['NCP_NAndF_RelatedLink'];
        if (preg_match('/href="([^"]*)"/', $value, $matches)) {
          $url_path = $matches[1];
          if (strpos($url_path, '.aspx')) {
            $url_path = str_replace('.aspx', '', $url_path);
            $url_path = strtolower($url_path);
            $url = explode('/pages/', $url_path);
            $url_path = implode('/', $url);
          }
          $url_path = strtok($url_path, '?');
          if ($url_path == $json['Fields']['uid']) {
            $list[] = $item;
          }
        }
      }
    }
    return $list;
  }

  /**
   * {@inheritdoc}
   */
  public function filterTeaserContent($urls = []) {
    $items = $urls;
    $list = [];
    foreach ($items as $item) {
      $json = $this->readJsonFromPath($item);
      if (isset($json['Fields']['NCP_NAndF_RelatedLink'])) {
        $list[] = $item;
      }
    }
    return $list;
  }

  /**
   * {@inheritdoc}
   */
  public function createReusableMediaFromImage($value) {
    $file = NULL;
    if (preg_match('/src="([^"]*)"/', $value, $matches)) {
      $image_path = $matches[1];
      preg_match('/alt="([^"]*)"/', $value, $alt_tag_matches);
      $alt_tag = $alt_tag_matches[1];
      preg_match('/title="([^"]*)"/', $value, $title_tag_matches);
      $title_tag = $title_tag_matches[1];

      list($path, $tids, $image_path) = $this->cleanImageAndGenerateTids($image_path);

      // Find the image media if it already exists.
      $media = $this->lookupExistingMedia($image_path, 'media', 'image.entity.uri');
      // Else continue with creating the new media.
      if ($media == FALSE) {
        $image_data = file_get_contents($path);
        if ($file = file_save_data($image_data, 'public:/' . $image_path, FILE_EXISTS_REPLACE)) {
          $media = Media::create([
            'bundle' => 'image',
            'image' => [
              'entity' => $file,
              'alt' => $alt_tag,
              'title' => $title_tag,
            ],
            'status' => TRUE,
            'field_media_in_library' => TRUE,
            'field_media_category' => $tids,
            'uid' => 1,
            'moderation_state' => 'published',
          ]);
          $media->save();
          // Save focal-point for this image media.
          $this->updateMediaFocalPoint($media);
        }
      }
    }
    return $media;
  }

  /**
   * {@inheritdoc}
   */
  public function generateUriFromUrl($url) {
    if (UrlHelper::isExternal($url)) {
      return $url;
    }
    else {
      if (strpos($url, '.aspx')) {
        $url = str_replace('.aspx', '', $url);
      }
      $url = strtolower($url);
      $link = explode('/pages/', $url);
      $url = implode('/', $link);
      $url = 'internal:' . $url;
      return $url;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function filterTaxonomyPages($urls = []) {
    $items = $urls;
    $list = [];
    foreach ($items as $item) {
      $get_full_path = $this->getSourceFullPath();
      $brand_folders = $this->state->get('test_module_4.brand_folders', '/brands');
      $brand_folders = explode("\r\n", $brand_folders);
      $brand_folder_found = FALSE;
      $brand_pages_folder_found = FALSE;
      foreach ($brand_folders as $value) {
        if ($brand_folder_found == FALSE && stripos($item, $get_full_path . $value) !== FALSE) {
          $brand_folder_found = TRUE;
        }
        if ($brand_pages_folder_found == FALSE && stripos($item, $get_full_path . $value . '/pages') !== FALSE) {
          $brand_pages_folder_found = TRUE;
        }
      }
      $brandall_folders = $this->state->get('test_module_4.brandall_folders', '/brands/allbrands');
      $brandall_folders = explode("\r\n", $brandall_folders);
      $brandall_folder_found = FALSE;
      foreach ($brandall_folders as $value) {
        if (stripos($item, $get_full_path . $value) !== FALSE) {
          $brandall_folder_found = TRUE;
          break;
        }
      }
      // Allow creating /brands/* items as Brand category.
      // Disallow creating /brands/allbrands etc as brand category.
      if ($brand_folder_found && !($brandall_folder_found || $brand_pages_folder_found)) {
        $this->logger->debug('Brand Category Item found: @uid', ['@uid' => $item]);
        $json = $this->readJsonFromPath($item);
        if (isset($json['Fields']['isHomePage'])) {
          $list[] = $item;
        }
      }
    }
    return $list;
  }

  /**
   * Get CSS class for a component.
   */
  public function getColorAndClassForComponent($componetId, $splitColor = TRUE) {
    $classes = [
      'color' => '',
      'class' => '',
    ];

    // Get all Class list.
    $class_list = NULL;
    $title = str_replace('/forinternaluse/widgets/', '', $componetId);
    $path = $this->getSourceFullPath();
    $path = $path . '/forinternaluse/' . '__list__widgetsettings.json';
    $cid = 'test_module_4:extract_css_class:' . $path;
    if ($cache = $this->cache->get($cid)) {
      $items = $cache->data;
    }
    else {
      $items = $this->readJsonFromPath($path);
      $this->cache->set($cid, $items, CacheBackendInterface::CACHE_PERMANENT);
    }
    foreach ($items as $item) {
      if ($item['Fields']['Title'] == $title) {
        $settings = json_decode($item['Fields']['NSE_WidgetSettingsValue'], TRUE);
        foreach ($settings[0]['value'] as $settings_value) {
          $settings_items = json_decode($settings_value['SettingValue'], TRUE);
          foreach ($settings_items as $value) {
            if ($value['key'] == 'CssClass') {
              $class_list = $value['value'];
              break 3;
            }
          }
        }
      }
    }

    // Seprate Color class & find correct color class in drupal.
    if (!empty($class_list)) {
      $color_class = '';
      $list = explode(' ', $class_list);
      // Replace old mappings with new ones.
      $mapping_values = [
        'red' => 'bg-cherry',
        'yellow' => 'bg-apricot',
        'turquoise' => 'bg-aqua-dark',
        'grey' => 'bg-oak',
        'purple' => 'bg-pink-dark',
        'brown' => 'bg-coffee-dark',
        'orange' => 'bg-orange',
        'green' => 'bg-green',
        'darkgreen' => 'bg-green-dark',
        'lightblue' => 'bg-blue',
        'blue' => 'bg-blueberry',
        'darkblue' => 'bg-blueberry-dark',
        'lightgreen' => 'bg-olive',
      ];
      foreach ($list as $class) {
        if (in_array($class, array_keys($mapping_values))) {
          $class = $mapping_values[$class];
        }
        if (substr($class, 0, 3) === "bg-") {
          $color_class = $class;
          break;
        }
      }
      if (!empty($color_class) && $splitColor == TRUE) {
        $list = array_diff($list, [$color_class]);
        $classes['color'] = str_replace('bg-', 'color-library-', $color_class);
        $classes['color'] = str_replace('-', '_', $classes['color']);
      }
      $classes['class'] = implode(' ', $list);
    }
    return $classes;
  }

  /**
   * {@inheritdoc}
   */
  public function updateMediaFocalPoint($media) {
    // Setting focal point to be set to Top-Right.
    $x = '100';
    $y = '0';
    $file = $this->typeManager->getStorage('file')->load($media->image->target_id);
    $image = $this->imageFactory->get($file->getFileUri());
    $crop = $this->focalPointManager->getCropEntity($file, 'focal_point');
    $this->focalPointManager->saveCropEntity($x, $y, $image->getWidth(), $image->getHeight(), $crop);
  }

  /**
   * {@inheritdoc}
   */
  public function getInternalUriFromAlias($alias) {
    $uri = NULL;
    $url = parse_url($alias);
    if (!empty($url['scheme'])) {
      // Check if External Url.
      $uri = $alias;
    }
    else {
      $url = Url::fromUserInput($alias);
      // Check if alias exists in Drupal.
      if ($url->isRouted()) {
        $params = $url->getRouteParameters();
        $uri = 'internal:/' . key($params) . '/' . current($params);
      }
    }
    return $uri;
  }

  /**
   * {@inheritdoc}
   */
  public function getMigrationLanguageCodesMapping() {
    $mappings = $this->state->get('test_module_4.langcode_mappings');
    $langcode_mappings = [];
    if (!empty($mappings)) {
      foreach (explode("\n", $mappings) as $item) {
        if (!empty($item)) {
          $item = trim($item);
          list($shell, $drupal) = explode('|', $item);
          $langcode_mappings[$shell] = $drupal;
        }
      }
    }
    return $langcode_mappings;
  }

  /**
   * {@inheritdoc}
   */
  public function addUrlRedirection($from, $to, $langcode) {
    $redirectRepository = \Drupal::service('redirect.repository');

    if ($redirect = $redirectRepository->findBySourcePath($from)) {
      $redirect = reset($redirect);
      $redirect->delete();
    }

    $value = [
      'redirect_source' => [
        'path' => $from,
        'query' => 'a:0:{}',
      ],
      'redirect_redirect' => [
        'uri' => $to,
        'title' => NULL,
        'options' => 'a:0:{}',
      ],
      'status_code' => 301,
      'language' => $langcode,
      'uid' => 1,
    ];

    $redirect = Redirect::create($value);
    $redirect->save();
  }

  /**
   * {@inheritdoc}
   */
  public function fixDuplicateUid() {
    drupal_flush_all_caches();
    $list = $this->getAllFiles();
    $uids = [];
    foreach ($list as $path) {
      $object = $this->readJsonFromPath($path);
      $uids[$path] = $object['Fields']['uid'];
    }
    $dups = $new_arr = [];
    foreach ($uids as $key => $val) {
      if (!isset($new_arr[$val])) {
        $new_arr[$val] = $key;
      }
      else {
        if (isset($dups[$val])) {
          $dups[$val][] = $key;
        }
        else {
          $dups[$val] = [$new_arr[$val], $key];
        }
      }
    }
    foreach ($dups as $uid => $filepath) {
      if ($uid == '') {
        continue;
      }
      else {
        $maxlen = max(array_map('strlen', $filepath));
        foreach ($filepath as $file_path) {
          if (strlen($file_path) < $maxlen) {
            unlink($file_path);
          }
        }
      }
    }
  }

  /**
   * Process each operations of batch process.
   *
   * @param int $entity_ids
   *   HTML snippet from the json file.
   * @param string $entity_type
   *   Entity type of given entities id.
   * @param int $total
   *   Total entiities id of each entity type.
   * @param object $context
   *   Batch process data.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function checkMediaUsedInEntity($entity_ids, $entity_type, $total, &$context) {
    $context['results'][$entity_type]['count'] += count($entity_ids);
    $formatted_text_fields = [
      'text_with_summary',
      'text_long',
      'text',
    ];
    $other_media_entities = \Drupal::state()->get('test_module_4.other_media_entities');
    $found = 0;
    if ($other_media_entities) {
      $entities = \Drupal::entityTypeManager()
        ->getStorage($entity_type)
        ->loadMultiple($entity_ids);
      foreach ($entities as $entity) {
        $field_definitions = $entity->getFieldDefinitions();
        // Load all fields of entity.
        foreach ($field_definitions as $field_name => $field_definition) {
          $field_type = $field_definition->getType();
          // Check field is Textarea.
          if (in_array($field_type, $formatted_text_fields)) {
            $field_value = $entity->{$field_name}->getValue();
            foreach ($field_value as $value) {
              // Check media all remaining uuids is present in body or not.
              foreach ($other_media_entities as $key => $media_value) {
                if (isset($value['value']) && strpos($value['value'], $media_value->uuid) !== FALSE) {
                  unset($other_media_entities[$key]);
                  $found++;
                }
              }
            }
          }
        }
      }

    }
    if ($found) {
      \Drupal::state()->set('test_module_4.other_media_entities', $other_media_entities);
    }
    $context['message'] = t('Checked @entitytype entities @processed out of @total (@found Found)',
        [
          '@processed' => $context['results'][$entity_type]['count'],
          '@entitytype' => strtoupper($entity_type),
          '@total' => $total,
          '@found' => $found,
        ]);
  }

  /**
   * Delete the unused media entity.
   *
   * @param bool $success
   *   Success of the operation.
   * @param array $results
   *   Array of results for post processing.
   * @param array $operations
   *   Array of operations.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function cleanUpMediaEntities($success, array $results, array $operations) {
    $messenger = \Drupal::messenger();
    if ($success) {
      // Deletes the remaining media entities.
      $other_media_entities = \Drupal::state()->get('test_module_4.other_media_entities');
      if (!empty($other_media_entities)) {
        foreach ($other_media_entities as $media_load) {
          $media_obj = Media::load($media_load->mid);
          \Drupal::logger('test_module_4')->notice(dt("Deleted Media - ID:@media_id, Media Name:@media_name", [
            '@media_id' => $media_obj->id(),
            '@media_name' => $media_obj->getName(),
          ]));
          $media_obj->delete();
        }
        \Drupal::state()->delete('test_module_4.other_media_entities');
        $messenger->addMessage(t('@count Media Entities are deleted.', ['@count' => count($other_media_entities)]));
        \Drupal::state()->set('nm_file_entity_cleanup', FALSE);
        $messenger->addMessage(t('Clean up process completed.'));
      }
      else {
        $messenger->addMessage(t('No Media entities to be deleted.'));
      }

    }
    else {
      // An error occurred.
      // $operations contains the operations that remained unprocessed.
      $error_operation = reset($operations);
      $messenger->addMessage(
          t('An error occurred while processing @operation with arguments : @args',
              [
                '@operation' => $error_operation[0],
                '@args' => print_r($error_operation[0], TRUE),
              ]
          )
      );
    }
  }

  /**
   * Returns all files URLs from private folder for given file extensions.
   *
   * @param string $file_extensions
   *   Extensions of file we need to search for.
   *
   * @return array
   *   Returns array of files URLs.
   */
  public function getPrivateFolderFiles($file_extensions) {
    $files = [];
    $private_path = $this->getSourceFullPath();
    $objects = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($private_path), \RecursiveIteratorIterator::SELF_FIRST);
    $objects = new \RegexIterator($objects, '/^.+\.' . $file_extensions . '$/i');
    foreach ($objects as $url => $object) {
      $url = str_replace($private_path . '/', '', $url);
      // Skip the files present in _t & _w folders in Shell.
      if (strpos($url, '/_t/') !== FALSE || strpos($url, '/_w/') !== FALSE) {
        continue;
      }
      if (strpos($url, '/') !== FALSE) {
        $files[] = $url;
      }
    }
    return $files;
  }

}
