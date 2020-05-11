<?php

namespace Drupal\mxt_core\Plugin\rest\resource;

use Drupal\Component\Serialization\Json;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "notifications_resource",
 *   label = @Translation("Notifications resource"),
 *   uri_paths = {
 *     "create" = "/api/twg/v1/savepushtoken"
 *   }
 * )
 */
class NotificationsResource extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Entity Notification storage.
   *
   * @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage
   */
  protected $notificationStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->logger = $container->get('logger.factory')->get('mxt_core');
    $instance->currentUser = $container->get('current_user');
    $instance->notificationStorage = $container->get('entity_type.manager')->getStorage('notification');
    return $instance;
  }

  /**
   * Responds to POST requests.
   *
   * @param array $entity_data
   *   Array with post parameters.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function post(array $entity_data) {
    if (!$this->currentUser->hasPermission('add notification entities')) {
      throw new AccessDeniedHttpException();
    }

    try {
      $count = $this->notificationStorage->getQuery()
        ->condition('token', $entity_data['token'])
        ->count()
        ->execute();
      if (!$count) {
        $entity_data['type'] = 'notification';
        $notification = \Drupal::entityTypeManager()
          ->getStorage('notification')
          ->create($entity_data);
        $notification->save();

        $output = $notification->toArray();
        $response = (new ModifiedResourceResponse($output, 200));
        $response->headers->set('Content-Length', strlen(Json::encode($output)));
        return $response;
      }
      else {
        $output = ['Not unique token value.'];
        $response = (new ModifiedResourceResponse($output, 400));
        $response->headers->set('Content-Length', strlen(Json::encode($output)));
        return $response;
      }
    }
    catch (\Exception $e) {
      $output = ['Something went wrong during entity creation. Check your data.'];
      $response = (new ModifiedResourceResponse($output, 400));
      $response->headers->set('Content-Length', strlen(Json::encode($output)));
      return $response;
    }
  }

}
