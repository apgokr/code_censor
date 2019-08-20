<?php

namespace Drupal\test_module_4\Plugin\migrate\process;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\Component\Utility\UrlHelper;

/**
 * Defines content type name based on Path.
 *
 * @MigrateProcessPlugin(
 *   id = "menu_builder"
 * )
 */
class MenuBuilder extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager, $migrationHelper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
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
    $container->get('entity.manager'),
    $container->get('test_module_4.migrations_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $values = [
      'title' => '',
      'url' => '',
      'bundle' => '',
      'external' => 0,
      'parent' => '',
    ];
    $bundle = NULL;
    $value[1] = urldecode($value[1]);
    $values['title'] = str_replace('+', ' ', $value[1]);

    $value[0] = urldecode($value[0]);
    $values['url'] = $this->migrationHelper->generateUriFromUrl($value[0]);

    if (UrlHelper::isExternal($value[0])) {
      $values['external'] = 1;
    }
    if ($value[2] == 'Home') {
      $values['parent'] = NULL;
    }
    else {
      $value[2] = urldecode($value[2]);
      $value[2] = str_replace('+', ' ', $value[2]);
      $menu_item_names[] = $this->entityTypeManager->getStorage('menu_link_content')->loadByProperties([
        'title' => $value[2],
      ]);
      if (!empty($menu_item_names[0]) && count($menu_item_names) == 1) {
        $menu_item_name = array_values($menu_item_names[0]);
        $values['parent'] = $menu_item_name[0]->getPluginId();
        $bundle = $menu_item_name[0]->bundle();
      }
      else {
        $url = explode('/', $values['url']);
        array_pop($url);
        $url_element = implode('/', $url);
        $menu_item_link = \Drupal::entityManager()->getStorage('menu_link_content')->loadByProperties([
          'link.uri' => $url_element,
        ]);
        $menu_item_link = array_values($menu_item_link);
        if (!empty($menu_item_link)) {
          $values['parent'] = $menu_item_link[0]->getPluginId();
          $bundle = $menu_item_link[0]->bundle();
        }
      }
    }

    if (empty($values['parent']) && empty($value[3])) {
      $values['bundle'] = 'main';
    }
    elseif (empty($values['parent']) && !empty($value[3])) {
      $values['bundle'] = 'secondary-menu';
    }
    else {
      $values['bundle'] = $bundle;
    }
    return $values;
  }

}
