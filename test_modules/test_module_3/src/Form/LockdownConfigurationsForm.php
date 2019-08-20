<?php

namespace Drupal\test_module_3\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\UserStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for setting configurations to Lockdown Period.
 */
class LockdownConfigurationsForm extends ConfigFormBase {

  /* @var string Config settings */
  const SETTINGS = 'lockdown_period.settings';

  /**
   * The user storage services.
   *
   * @var \Drupal\user\Entity\User
   */
  private $userStorage;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, UserStorage $user_storage) {
    parent::__construct($config_factory);
    $this->userStorage = $user_storage;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')->getStorage('user')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lockdown_period_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $lockdown_settings = $config->get('lockdown_settings');

    $form['lockdown_settings']['lockdown_status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Lockdown'),
      '#default_value' => $lockdown_settings['lockdown_status'] ?? NULL,
      '#description' => $this->t('When enabled, only a core group of content publishers access to the site during periods of publishing a highly confidential news and lock out all other users.'),
    ];

    // Load all allowed users saved in config.
    if (!empty($lockdown_settings['allowed_users'])) {
      foreach ($lockdown_settings['allowed_users'] as $user_id) {
        $entities[] = $this->userStorage->load($user_id['target_id']);
      }
    }
    $roles = ['content_manager', 'administrator'];
    $form['lockdown_settings']['allowed_users'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#title' => $this->t('Allowed Users'),
      '#description' => $this->t('Content publishers & Site Administrators who will have login access during the lockdown period.'),
      '#default_value' => $entities ?? NULL,
      '#selection_handler' => 'default:user',
      '#selection_settings' => [
        'include_anonymous' => FALSE,
        'filter' => [
          'type' => 'role',
          'role' => $roles,
        ],
      ],
      '#tags' => TRUE,
      '#weight' => '0',
    ];

    // Load all the user roles.
    $form['lockdown_settings']['administer_lockdown'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Administer Lockdown Configurations'),
      '#options' => array_diff(user_role_names(), ['anonymous' => 'Anonymous user', 'authenticated' => 'Authenticated user']),
      '#default_value' => $lockdown_settings['administer_lockdown'] ?? ['core_group'],
      '#multiple' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration.
    $lockdown_settings = [
      'lockdown_status' => $form_state->getValue('lockdown_status'),
      'allowed_users' => $form_state->getValue('allowed_users'),
      'administer_lockdown' => $form_state->getValue('administer_lockdown'),
    ];
    $this->configFactory->getEditable(static::SETTINGS)
      ->set('lockdown_settings', $lockdown_settings)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
