<?php

namespace Drupal\test_module_2\Plugin\search_api\processor\Property;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Processor\ConfigurablePropertyBase;

/**
 * Defines a "country code synonym" property.
 *
 * @see \Drupal\test_module_2\Plugin\search_api\processor\NcmCountryCodeSynonym
 */
class NcmAddCountrySynonyms extends ConfigurablePropertyBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(FieldInterface $field, array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'search_api/drupal.search_api.admin_css';
    $form['#tree'] = TRUE;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(FieldInterface $field, array &$form, FormStateInterface $form_state) {
  }

}
