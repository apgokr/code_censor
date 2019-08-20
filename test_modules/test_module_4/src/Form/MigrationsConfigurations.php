<?php

namespace Drupal\test_module_4\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Provides configurations for Migrations.
 */
class MigrationsConfigurations extends FormBase {

  /**
   * The Drupal state storage service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs an NestleEventConfigForm object.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language_manager service.
   */
  public function __construct(StateInterface $state, LanguageManagerInterface $languageManager) {
    $this->state = $state;
    $this->languageManager = $languageManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('state'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'test_module_4_configurations_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $source_directory = $this->state->get('test_module_4.source_directory', '');

    $form['source_directory'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Source Directory'),
      '#default_value' => $source_directory,
      '#description' => $this->t('Source directory relative to the drupal private folder. Eg: "nestle_it", "source/nestle_it".'),
      '#required' => TRUE,
    ];

    $form['source_multilingual'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Is Source multilingual?'),
      '#default_value' => $this->state->get('test_module_4.source_multilingual', FALSE),
    ];

    $form['source_translations_mapping_file'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Source translation mapping file.'),
      '#default_value' => $this->state->get('test_module_4.source_translations_mapping_file', ''),
      '#description' => $this->t('File relative to the Source Directory.'),
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="source_multilingual"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['langcode_mappings'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Language codes mapping.'),
      '#default_value' => $this->state->get('test_module_4.langcode_mappings', ''),
      '#description' => $this->t('Language code mapping of Drupal from Shell. One mapping per line. Eg Catlan language is `cat` in shell and is `ca` in Drupal, it should be entered as `ca|cat`.'),
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="source_multilingual"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['whitelist_image_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Whitelist Image URL type.'),
      '#default_value' => $this->state->get('test_module_4.whitelist_image_url', 'https://auth-prod'),
      '#description' => $this->t('Use relative path instead of Absolute URLs for Images having Absolute URLs & starting with this string during migration.'),
      '#required' => FALSE,
    ];

    $form['brand_folders'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Brand Folders'),
      '#default_value' => $this->state->get('test_module_4.brand_folders', "/brands"),
      '#description' => $this->t('The Brand folders according to the dump. All items having /brands/* will be migrated as a brand category. For multilingual enter one entry per line. Eg; /es/marcas, /cat/marques'),
      '#required' => FALSE,
    ];
    $form['brandall_folders'] = [
      '#type' => 'textarea',
      '#title' => $this->t('All Brand Folder'),
      '#default_value' => $this->state->get('test_module_4.brandall_folders', "/brands/allbrands"),
      '#description' => $this->t('The All Brand folder according to the dump. This path will be created as a component page. For multilingual enter one entry per line. Eg; /es/marcas/todas-las-marcas, /cat/marques/totes-les-marques'),
      '#required' => FALSE,
    ];

    $form['documents'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Document extensions'),
      '#default_value' => $this->state->get('test_module_4.documents', '.doc,.docx,.mp3,.pdf,.txt'),
      '#description' => $this->t('All the documents/files extensions which needs to be migrated. Put comma separated extensions. Eg: .docx,.pdf'),
      '#required' => TRUE,
    ];
    $form['file_extensions'] = [
      '#type' => 'textfield',
      '#title' => $this->t('File extensions'),
      '#default_value' => $this->state->get('test_module_4.file_extensions', 'doc|docx|txt|xls|xlsx|pdf|pptx|csv|mp3|mp4|3gp'),
      '#description' => $this->t('All the files with extensions which needs to be redirected. Put pipe separated extensions. Eg: docx|pdf'),
      '#required' => TRUE,
    ];
    $form['trim_fields'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Trim Fields'),
      '#default_value' => $this->state->get('test_module_4.trim_fields', ""),
      '#description' => $this->t("Some fields causes error during automation migration. Thus those fields can be explictly whitelisted here along with their character count. Example: one entry per line in this format <em>migration-id|source-field|count: node_teaser_slider|sub_title|255"),
      '#required' => FALSE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Explode to create an array from string to compare extensions.
    if ($file_extensions = explode(',', $form_state->getValue('documents'))) {
      // Iterate over all comma separated extensions stored.
      foreach ($file_extensions as $extension) {
        // If the dot(.) is not found, set an error.
        if (str_replace('.', '', $extension) == $extension) {
          $form_state->setErrorByName('documents', $this->t('Invalid extension file detected'));
        }
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->cleanValues();
    $this->state->set('test_module_4.source_directory', $form_state->getValue('source_directory'));
    $this->state->set('test_module_4.source_multilingual', $form_state->getValue('source_multilingual'));
    $this->state->set('test_module_4.source_translations_mapping_file', $form_state->getValue('source_translations_mapping_file'));
    $this->state->set('test_module_4.langcode_mappings', $form_state->getValue('langcode_mappings'));
    $this->state->set('test_module_4.whitelist_image_url', $form_state->getValue('whitelist_image_url'));
    $this->state->set('test_module_4.brand_folders', $form_state->getValue('brand_folders'));
    $this->state->set('test_module_4.brandall_folders', $form_state->getValue('brandall_folders'));
    $this->state->set('test_module_4.documents', $form_state->getValue('documents'));
    $this->state->set('test_module_4.file_extensions', $form_state->getValue('file_extensions'));
    $this->state->set('test_module_4.trim_fields', $form_state->getValue('trim_fields'));
    drupal_set_message($this->t('Your configuration have been saved.'));
  }

}
