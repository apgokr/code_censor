<?php

namespace Drupal\test_module_4\Plugin\migrate\process;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines content type name based on Path.
 *
 * @MigrateProcessPlugin(
 *   id = "internal_tag_creation"
 * )
 */
class InternalTagCreation extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager, $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
    $configuration,
    $plugin_id,
    $plugin_definition,
    $container->get('entity.manager'),
    $container->get('logger.factory')->get('test_module_4')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $uid = $value;
    $url = explode('/', $value);
    array_pop($url);
    $value = implode('/', $url);
    $vocabulary = 'internal_tag';
    if (!empty($value)) {
      if ($tid = $this->getTidByName($value, $vocabulary)) {
        $term = Term::load($tid);
      }
      else {
        $term = Term::create([
          'name' => $value,
          'vid' => $vocabulary,
        ])->save();
        if ($tid = $this->getTidByName($value, $vocabulary)) {
          $term = Term::load($tid);
        }
      }
    }
    else {
      $this->logger->debug('Skipped internal_tag creation for: @uid.', ['@uid' => $uid]);
    }
    return [
      'target_id' => (isset($term) && is_object($term)) ? $term->id() : 0,
    ];
  }

  /**
   * Load term by name.
   */
  protected function getTidByName($name = NULL, $vocabulary = NULL) {
    $properties = [
      'name' => '',
      'vid' => '',
    ];
    if (!empty($name)) {
      $properties['name'] = $name;
    }
    if (!empty($vocabulary)) {
      $properties['vid'] = $vocabulary;
    }
    $terms = \Drupal::entityManager()->getStorage('taxonomy_term')->loadByProperties($properties);
    $term = reset($terms);
    return !empty($term) ? $term->id() : 0;
  }

}
