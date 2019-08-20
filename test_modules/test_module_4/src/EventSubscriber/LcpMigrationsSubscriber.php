<?php

namespace Drupal\test_module_4\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateRollbackEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\State\StateInterface;
use Drupal\migrate\Event\MigrateImportEvent;

/**
 * Migrations Subscriber class.
 */
class LcpMigrationsSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, StateInterface $state) {
    $this->entityTypeManager = $entityTypeManager;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::POST_ROLLBACK][] = ['onPostRollback'];
    $events[MigrateEvents::PRE_IMPORT][] = ['preImport'];
    return $events;
  }

  /**
   * {@inheritdoc}
   */
  public function preImport(MigrateImportEvent $event) {
    $table_exists = \Drupal::database()->schema()->tableExists('diff_report');
    // Saving mig_update state var if the diff_report table exists.
    $this->state->set('mig_update', $table_exists);
  }

  /**
   * Function to delete node after migrations.
   *
   * @param \Drupal\migrate\Event\MigrateRollbackEvent $event
   *   MigrateRollbackEvent $event.
   */
  public function onPostRollback(MigrateRollbackEvent $event) {
    // Migration object just finished rollback operations.
    $migration_id = $event->getMigration()->id();
    if ($migration_id == 'paragraph_entity_slider_caption_right') {
      $items = $this->state->get('teaser_nids');
      if (!empty($items)) {
        $items = array_values($items);
        foreach ($items as $item) {
          foreach ($item as $value) {
            $nodes[] = $value['target_id'];
          }
        }
        foreach ($nodes as $nid) {
          $node = $this->entityTypeManager->getStorage('node')->load($nid);
          if (!is_null($node)) {
            $node->delete();
          }
        }
      }
    }
  }

}
