<?php

namespace Drupal\test_module_3\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Drupal\Core\Routing\UrlGeneratorTrait;

/**
 * Class ResponseSubscriber.
 *
 * @package Drupal\test_module_3
 */
class ResponseSubscriber implements EventSubscriberInterface {
  use StringTranslationTrait;
  use UrlGeneratorTrait;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Database\Connection definition.
   *
   * @var Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructor.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, Connection $connection, ConfigFactoryInterface $config_factory, AccountProxy $current_user, MessengerInterface $messenger) {
    $this->entityTypeManager = $entityTypeManager;
    $this->connection = $connection;
    $this->config = $config_factory->get('lockdown_period.settings');
    $this->currentUser = $current_user;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['handle', 200];

    return $events;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(GetResponseEvent $event) {
    $list = [];
    $lockdown_settings = $this->config->get('lockdown_settings');
    if ($lockdown_settings['lockdown_status'] == 1 && $this->currentUser->id() > 0) {
      if ($disallowed_user = $this->getAllActiveUser($lockdown_settings)) {
        foreach ($disallowed_user as $item) {
          if ($item->uid != 0) {
            $user = $this->entityTypeManager->getStorage('user')->load($item->uid);
            if (!$user->hasRole('core_group')) {
              $list[] = $item->uid;
            }
          }
        }
        if (!empty($list)) {
          $user_id = $this->currentUser->id();
          if (in_array($user_id, $list)) {
            user_logout();
            // Redirect to homepage.
            $this->messenger->addMessage($this->t('Access denied for maintenance. Please try again later.'), MessengerInterface::TYPE_ERROR, TRUE);
            $event->setResponse($this->redirect(('<front>')));
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getAllActiveUser($lockdown_settings) {
    if (isset($lockdown_settings['allowed_users'])) {
      $allowed_users = array_column($lockdown_settings['allowed_users'], 'target_id');
    }

    $query_list = $this->connection->select('sessions', 's');
    $query_list->leftJoin('user__roles', 'u', 'u.entity_id = s.uid');
    $query_list->addExpression('COUNT(s.uid)', 'count_uid');
    $query_list->fields('s', ['uid']);
    $query_list->groupBy('s.uid');
    if (!empty($allowed_users)) {
      $query_list->condition('s.uid', $allowed_users, 'NOT IN');
    }
    $result_rc = $query_list->execute()->fetchAll();
    return $result_rc;
  }

}
