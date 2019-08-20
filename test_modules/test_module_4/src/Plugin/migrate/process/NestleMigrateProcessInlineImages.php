<?php

namespace Drupal\test_module_4\Plugin\migrate\process;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\State\StateInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\media\Entity\Media;
use Drupal\migrate_process_inline_images\Plugin\migrate\process\MigrateProcessInlineImages;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Symfony\Component\DomCrawler\Crawler;
use Drupal\Core\Url;
use DOMDocument;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'NestleMigrateProcessInlineImages' migrate process plugin.
 *
 * @MigrateProcessPlugin(
 *  id = "nestle_inline_images"
 * )
 */
class NestleMigrateProcessInlineImages extends MigrateProcessInlineImages {

  /**
   * The Drupal state storage service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The migration helper services.
   *
   * @var \Drupal\test_module_4\MigrationsHelper
   */
  protected $migrationHelper;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, $entityTypeManager, $httpClient, $fileSystem, $streamWrapperManager, $migrationHelper, StateInterface $state) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entityTypeManager, $httpClient, $fileSystem, $streamWrapperManager);
    $this->migrationHelper = $migrationHelper;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('http_client'),
      $container->get('file_system'),
      $container->get('stream_wrapper_manager'),
      $container->get('test_module_4.migrations_helper'),
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function downloadFile($imagePath) {

    $file = parent::downloadFile($imagePath);

    // Get media category.
    $image_path = strstr($imagePath, '/asset-library');
    $tids = $this->getMediaTermsFromImagePath($image_path);

    // Create media entity referencing image file.
    $media = Media::create([
      'bundle' => 'image',
      'image' => ['entity' => $file],
      'status' => TRUE,
      'field_media_in_library' => TRUE,
      'uid' => 1,
      'field_media_category' => $tids,
      'moderation_state' => 'published',
    ]);
    $media->save();
    // Save focal-point for this image media.
    $this->migrationHelper->updateMediaFocalPoint($media);

    return $file;
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $dom = new DomDocument();
    $value = stripslashes($value);
    if (@$dom->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $value)) {
      if ($encoded_string = $dom->getElementById('RadEditorEncodedTag')->nodeValue) {
        $decoded_string = base64_decode($encoded_string);
        $value = str_replace($encoded_string, $decoded_string, $value);
      }
      $links = $dom->getElementsByTagName('a');
      if ($links->length) {
        foreach ($links as $link) {
          $urls[] = $link->getAttribute('href');
        }
        foreach ($urls as $url) {
          $old_url = $url;
          $url = strtolower($url);

          // Check if the document URL is not external.
          // If it is an external URL, don't modify $value.
          if (!UrlHelper::isExternal($url)) {
            // Check for file extensions.
            if (!empty($file_extensions = $this->state->get('test_module_4.documents'))) {
              // Check if the above mentioned file extension is present in URL.
              // str_replace will return url without the matched extensions.
              if (str_replace(explode(',', $file_extensions), '', $url) != $url) {
                // Get the internal files path of Drupal.
                $document_path = '/' . PublicStream::basePath();
                // Append it to incoming relative URL value.
                $updated_url = $document_path . $url;
                $value = str_replace($old_url, $updated_url, $value);
              }
            }
          }
          if (strpos($url, '.aspx')) {
            $url = str_replace('.aspx', '', $url);
            $target_url = explode('/pages/', $url);
            $url = implode('/', $target_url);
            $value = str_replace($old_url, $url, $value);
          }
        }
      }
    }
    $domCrawler = new Crawler($value, NULL, Url::fromRoute('<front>')->setAbsolute()->toString());
    // Search for all <img> tag in the value (usually the body).
    if ($images = $domCrawler->filter('img')->images()) {
      foreach ($images as $image) {
        // Cleaning image path.
        $image_path = urldecode($image->getUri());
        $image_path = strtolower($image_path);
        $new_url = $this->getUpdatedUrl($image_path);
        $image->getNode()->setAttribute('src', $new_url);
        $this->cleanUpImageAttributes($image, $migrate_executable);
        $new_url = parse_url($new_url);
        $image->getNode()->setAttribute('src', $new_url['path']);

      }
      return $domCrawler->html();
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getUpdatedUrl($old_value) {
    $url = parse_url($old_value);
    $public_path = '/' . PublicStream::basePath();
    $new_value = str_replace($url['path'], $public_path . $url['path'], $old_value);
    return $new_value;
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

}
