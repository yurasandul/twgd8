<?php

namespace Drupal\mxt_notification;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Notification entity.
 *
 * @see \Drupal\mxt_notification\Entity\Notification.
 */
class NotificationAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\mxt_notification\Entity\NotificationInterface $entity */

    switch ($operation) {

      case 'view':

        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished notification entities');
        }

        return AccessResult::allowedIfHasPermission($account, 'view published notification entities');

      case 'update':

        return AccessResult::allowedIfHasPermission($account, 'edit notification entities');

      case 'delete':

        return AccessResult::allowedIfHasPermission($account, 'delete notification entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add notification entities');
  }

}
