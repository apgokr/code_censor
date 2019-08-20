<?php

namespace Drupal\test_module_4\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\migrate\Row;
use DomDocument;
use DOMXPath;

/**
 * Provides a 'ExtractHtmlValues' migrate process plugin.
 *
 * @MigrateProcessPlugin(
 *  id = "extract_html_values"
 * )
 */
class ExtractHtmlValues extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, $migrationHelper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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
      $container->get('test_module_4.migrations_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $values = [
      'title' => '',
      'description' => '',
      'link_uri' => '',
      'link_text' => '',
      'link_color' => '',
      'link_position' => '',
      'heading' => '',
      'image' => '',
    ];
    if (!empty($value)) {
      $value = stripcslashes($value);
      $markup = new DOMDocument();
      if (@$markup->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $value)) {
        $title = $markup->getElementsByTagName('h2');
        if ($title->length) {
          $values['title'] = $title->item(0)->nodeValue;
        }
        $xpath = new DOMXPath($markup);
        $heads = $xpath->query('//h1|//h2|//h3|//h4|//h5|//h6');
        if ($heads->length) {
          $values['heading'] = $heads->item(0)->nodeValue;
        }
        $image = $xpath->query('//img');
        if ($image->length) {
          $values['image'] = $markup->saveHTML($image->item(0));
        }
        $description = $markup->getElementsByTagName('p');
        if ($description->length) {
          $values['description'] = $markup->saveHTML($description->item(0));
        }

        $links = $markup->getElementsByTagName('a');
        if ($links->length) {
          $values['link_uri'] = $links->item(0)->getAttribute('href');
          $values['link_text'] = $links->item(0)->nodeValue;
          if ($row->getSourceProperty('paragraph_type') == 'dsu_c_cta_button') {
            foreach ($links as $link) {
              if (strpos($link->getAttribute('class'), 'button') !== FALSE) {
                $list = $link;
              }
            }
            $link_details = explode(" ", $link->getAttribute('class'));

            $link_position = [
              'left' => 'position_left',
              'right' => 'position_right',
              'center' => 'position_center',
            ];

            if (count($link_details) == 3) {
              if (in_array($link_details[1], array_keys($link_position))) {
                $values['link_position'] = $link_position[$link_details[1]];
                $values['link_color'] = 'color_library_' . str_replace('-', '_', $link_details[2]);
              }
              else {
                $values['link_position'] = $link_position[$link_details[2]];
                $values['link_color'] = 'color_library_' . str_replace('-', '_', $link_details[1]);
              }
            }
            else {
              if (in_array($link_details[1], array_keys($link_position))) {
                $values['link_position'] = $link_position[$link_details[1]];
              }
              else {
                $values['link_color'] = 'color_library_' . str_replace('-', '_', $link_details[1]);
              }
            }
            $values['link_uri'] = $list->getAttribute('href');
            $values['link_text'] = $list->nodeValue;
          }
          $values['link_uri'] = $this->migrationHelper->generateUriFromUrl($values['link_uri']);
          if (strpos($values['link_text'], '.aspx')) {
            $values['link_text'] = str_replace('.aspx', '', $values['link_text']);
          }
          if ($row->getSourceProperty('content_type') == 'NSE_WidgetTopBox') {
            if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $value, $match)) {
              $values['link_uri'] = NULL;
              $values['link_text'] = NULL;
            }
          }
        }
      }
    }
    return $values;
  }

}
