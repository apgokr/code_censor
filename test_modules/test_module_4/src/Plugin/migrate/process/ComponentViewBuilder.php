<?php

namespace Drupal\test_module_4\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Provides a 'ComponentViewBuilder' migrate process plugin.
 *
 * @MigrateProcessPlugin(
 *   id = "component_view_builder",
 * )
 */
class ComponentViewBuilder extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!empty($value)) {
      $mappings = [
        "Press_Release" => [
          'PressReleasesMiniCarousel' => [
            'press_release_carousel',
            'block_press_release_carousel',
          ],
          'PressReleasesList' => [
            'article_list',
            'block_press_releases',
          ],
        ],
        "BoardOfDirectors" => [
          'ManagementList' => [
            'profile_list',
            'block_directors',
          ],
        ],
        "BrandsFixed" => [
          'BrandsAZ' => [
            'brands_a_z',
            'block_brand_a_z',
          ],
        ],
        "EventsTabs" => [
          'PagesCarouselWithTabs' => [
            'events',
            'block_events',
          ],
        ],
        "ExecutiveBoard" => [
          'ManagementList' => [
            'profile_list',
            'block_executive',
          ],
        ],
        "GlobalPresence" => [
          'MapAndOfficesList' => [
            'map_locator',
            'office_locations',
          ],
        ],
        "MediaContactRef" => [
          'MediaContactsList' => [
            'media_contact',
            'block_contact_list',
          ],
        ],
        "PresentationRef" => [
          'PresentationsList' => [
            'presentations',
            'block_presentations',
          ],
        ],
        "CaseStudiesAutomatic" => [
          'MapAndCaseStudiesList' => [
            'media_contact',
            'case_studies',
          ],
        ],
        "NCP_DocumentsListMediaLibraryRef" => [
          'DocumentsList' => [
            'documents_reports',
            'block_documents_reports',
          ],
        ],
        "e25ab5ef94574b2d98e7ef61b60fb017" => [
          'EventsCalendarLinks' => [
            'automatic_dated_list',
            'automatic_dated_list_block',
          ],
          'List' => [
            'article_list',
            'block_news_list',
          ],
        ],
      ];
      if (isset($mappings[$value[0]][$value[1]])) {
        return $mappings[$value[0]][$value[1]];
      }
    }
  }

}
