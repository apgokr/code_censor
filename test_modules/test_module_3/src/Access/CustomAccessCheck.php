<?php

namespace Drupal\test_module_3\Access;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Checks access for displaying configuration translation page.
 */
class CustomAccessCheck implements AccessInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Constructor.
   */
  public function __construct(AccountInterface $account, ConfigFactoryInterface $config_factory) {
    $this->currentUser = $account;
    $this->config = $config_factory->get('lockdown_period.settings');
  }

  /**
   * A custom access check.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access() {

    $user_roles = $this->currentUser->getRoles(TRUE);
    $allowed_roles = !empty($this->config->get('lockdown_settings')['administer_lockdown']) ? array_filter($this->config->get('lockdown_settings')['administer_lockdown']) : ['core_group' => 'Core Group'];
    foreach ($user_roles as $role) {
      if (in_array($role, array_keys($allowed_roles))) {
        $access = 1;
      }
    }
    if (isset($access) && $access == 1) {
      return AccessResult::allowed();
    }
    else {
      return AccessResult::forbidden();
    }
  }

}
