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
 *   id = "api_twg_checkupdate",
 *   label = @Translation("Api twg checkupdate"),
 *   uri_paths = {
 *     "canonical" = "/api/twg/v1/checkupdate"
 *   }
 * )
 */
class ApiTwgCheckupdate extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Node Storage.
   *
   * @var \Drupal\node\NodeStorage
   */
  protected $nodeStorage;

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
    $instance->nodeStorage = $container->get('entity_type.manager')->getStorage('node');
    $instance->twgApiHelper = $container->get('mxt_core.twg_api_helper');
    return $instance;
  }

  /**
   * Responds to GET requests.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function get() {
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    $languages = \Drupal::languageManager()->getLanguages();
    $langcodes = array_keys($languages);
    $output = [];
    $lang_version = [];
    foreach ($langcodes as $langcode) {
      $key = 'json_version__tweet_page__' . $langcode;
      $lang_version[] = [
        'name' => $this->twgApiHelper->prepareLangcode($langcode, TRUE),
        'ver' => \Drupal::state()->get($key, '1.0'),
      ];
    }
    $output['tweet']['lang_version'] = $lang_version;

    $node_type = 'rites';
    $nids = $this->nodeStorage->getQuery()
      ->condition('type', 'rites')
//      ->condition('status', 1)
      ->execute();
    if (!empty($nids)) {
      $lang_version = [];
      foreach ($this->nodeStorage->loadMultiple($nids) as $node) {
        $key = $key = 'json_version__rites__' . $node->id();
        $lang_version[] = [
          'name' => $node->get('field_taal')->value,
          'ver' => \Drupal::state()->get($key, '1.0'),
        ];
      }
    }
    $output['mass']['lang_version'] = $lang_version;
    $output['catholic']['lang_version'] = $lang_version;
    $output['byzantine']['lang_version'] = $lang_version;

    $response = (new ModifiedResourceResponse($output, 200));
    $response->headers->set('Content-Length', strlen(Json::encode($output)));
    return $response;
  }

}
