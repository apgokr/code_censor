<?php

namespace Drupal\test_module_2\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\test_module_2\Plugin\search_api\processor\Property\NcmAddCountrySynonyms;

/**
 * Adds location specific country codes to nodes.
 *
 * @SearchApiProcessor(
 *   id = "ncm_country_code_synonym",
 *   label = @Translation("Add Country Codes"),
 *   description = @Translation("Adds the country's synonym to the indexed data."),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class NcmCountryCodeSynonym extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('Country Code Synonyms'),
        'description' => $this->t('Add country code synonyms to the index'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['ncm_country_code_synonym'] = new NcmAddCountrySynonyms($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $object = $item->getOriginalObject()->getValue();
    if ($object->getType() == 'job') {
      $address = $object->get('field_job_address')->getValue();
      $country_code = $address[0]['country_code'];
      $countries = \Drupal::service("address.country_repository")->getList();
      $country_name = $countries[$country_code];
      $fields = $item->getFields(FALSE);
      $fields = $this->getFieldsHelper()->filterForPropertyPath($fields, NULL, 'ncm_country_code_synonym');
      foreach ($fields as $field) {
        $synonyms = [];
        $query = \Drupal::entityQuery('search_api_synonym')
          ->condition('status', 1)
          ->condition('word', $country_name);
        $result = $query->execute();
        $results = \Drupal::entityTypeManager()->getStorage('search_api_synonym')->loadMultiple($result);
        foreach ($results as $result) {
          $synonyms[] = $result->getSynonyms();
        }
        $synonyms = implode(',', $synonyms);
        $field->addValue($synonyms);
      }
    }
  }

}
