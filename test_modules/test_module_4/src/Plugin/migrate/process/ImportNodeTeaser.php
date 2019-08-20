<?php

namespace Drupal\test_module_4\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\Entity\Node;
use DomDocument;
use DOMXPath;

/**
 * Defines content type name based on Path.
 *
 * @MigrateProcessPlugin(
 *   id = "import_node_teaser"
 * )
 */
class ImportNodeTeaser extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The Drupal state storage service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, $migrationHelper, StateInterface $state) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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
      $container->get('test_module_4.migrations_helper'),
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $nodes = [];
    if (!empty($value)) {
      $value = stripcslashes($value);
      $markup = new DOMDocument();
      if (@$markup->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $value)) {
        $xpath = new DOMXPath($markup);
        $items = $xpath->query('//div[@title="_link"]');
        foreach ($items as $item) {
          $elements = $item->getElementsByTagName('span');
          foreach ($elements as $element) {
            $title_name = $element->getAttribute('title');
            switch ($title_name) {
              case '_title':
                $teaser_title = $element->nodeValue;
                break;

              case '_description':
                $teaser_description = $element->nodeValue;
                break;

              case '_linkurl':
                $teaser_link = trim(preg_replace('/\s\s+/', ' ', $element->nodeValue));
                break;

              case '_imageurl':
                $teaser_image = trim(preg_replace('/\s\s+/', ' ', $element->nodeValue));
                break;
            }
          }
          $nodes[] = $this->getCreatedTeaserNodes($teaser_title, $teaser_description, $teaser_link, $teaser_image);
          $uid = $row->getSourceProperty('uid');
          $this->updateTeaserNodeInState($nodes, $uid);
        }
      }
    }
    return $nodes;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTeaserNodes($teaser_title, $teaser_description, $teaser_link, $teaser_image) {
    $image_id = NULL;
    $teaser_image = "<img alt=\"" . $teaser_title . "\"  title=\"" . $teaser_title . "\" src=\"" . $teaser_image . "\">";
    if ($file = $this->migrationHelper->createReusableMediaFromImageTag($teaser_image)) {
      $image_id = $file->id();
    }
    if (empty($teaser_description)) {
      $teaser_description = $teaser_title;
    }
    $teaser_link = $this->migrationHelper->generateUriFromUrl($teaser_link);
    $values = [
      'type' => 'teaser',
      'title' => $teaser_title,
      'body' => [
        'format' => 'rich_text',
        'value' => $teaser_description,
      ],
      'field_teaser_link' => [
        'title' => $teaser_title,
        'uri' => $teaser_link,
      ],
      'field_image' => [
        'target_id' => $image_id,
        'alt' => $teaser_title,
        'title' => $teaser_title,
      ],
      'status' => 1,
      'uid' => 1,
      'moderation_state' => 'published',
    ];
    $node = Node::create($values);
    $node->save();
    return ['target_id' => $node->id()];
  }

  /**
   * {@inheritdoc}
   */
  public function getNodeIdInState() {
    return $this->state->get('teaser_nids');
  }

  /**
   * {@inheritdoc}
   */
  public function updateNodeIdInState($data) {
    return $this->state->set('teaser_nids', $data);
  }

  /**
   * {@inheritdoc}
   */
  public function updateTeaserNodeInState($data, $uid) {
    $teaser_data = $this->getNodeIdInState();
    $teaser_data[$uid] = $data;
    $this->updateNodeIdInState($teaser_data);
  }

}
