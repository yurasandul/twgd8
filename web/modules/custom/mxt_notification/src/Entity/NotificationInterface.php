<?php

namespace Drupal\mxt_notification\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Notification entities.
 *
 * @ingroup mxt_notification
 */
interface NotificationInterface extends ContentEntityInterface, EntityChangedInterface, EntityPublishedInterface, EntityOwnerInterface {

  /**
   * Add get/set methods for your configuration properties here.
   */

  /**
   * Gets the Notification token.
   *
   * @return string
   *   Name of the Notification.
   */
  public function getToken();

  /**
   * Sets the Notification token.
   *
   * @param string $token
   *   The Notification name.
   *
   * @return \Drupal\mxt_notification\Entity\NotificationInterface
   *   The called Notification entity.
   */
  public function setToken($token);

  /**
   * Gets the Notification creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Notification.
   */
  public function getCreatedTime();

  /**
   * Sets the Notification creation timestamp.
   *
   * @param int $timestamp
   *   The Notification creation timestamp.
   *
   * @return \Drupal\mxt_notification\Entity\NotificationInterface
   *   The called Notification entity.
   */
  public function setCreatedTime($timestamp);

}
