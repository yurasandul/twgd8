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
 *   id = "api_twg_twg",
 *   label = @Translation("Api twg-cmc twg"),
 *   uri_paths = {
 *     "canonical" = "/api/{code}/v1/twg/{langcode}"
 *   }
 * )
 */
class ApiTwgTwg extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Helper for resource API.
   *
   * @var \Drupal\mxt_core\TwgApiHelper
   */
  protected $twgApiHelper;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->logger = $container->get('logger.factory')->get('mxt_core');
    $instance->currentUser = $container->get('current_user');
    $instance->twgApiHelper = $container->get('mxt_core.twg_api_helper');
    return $instance;
  }

  /**
   * Responds to GET requests.
   *
   * @param string $langcode
   *   Langcode.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   */
  public function get($code, $langcode) {
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    // NID About Tweeting With God.
    $langcode = $this->twgApiHelper->prepareLangcode($langcode);

    $config = \Drupal::service('config.factory')->getEditable('mxt_core.ami_contact_details');
    $nid = $config->get('node_twg_twg') ?? 12765;

    if (!in_array($code, ['twg', 'cmc'])) {
      $output = ['Error url parameters'];
    }
    else {
      $output = $this->twgApiHelper->twgApiAbout($nid, $langcode);
    }

    $response = (new ModifiedResourceResponse($output, 200));
    $response->headers->set('Content-Length', strlen(Json::encode($output)));
    return $response;
  }

}
