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
 *   id = "api_ami_download_questions_resource",
 *   label = @Translation("Api ami download questions resource"),
 *   uri_paths = {
 *     "canonical" = "/api/cmc/v1/download/questions/{langcode}/{suffix}"
 *   }
 * )
 */
class ApiAmiDownloadQuestionsResource extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

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
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->twgApiHelper = $container->get('mxt_core.twg_api_helper');
    return $instance;
  }

  /**
   * Responds to GET requests.
   *
   * @param string $langcode
   *   Langcode.
   * @param string $suffix
   *   Suffix endpoint.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function get($langcode, $suffix) {
    // You must to implement the logic of your REST Resource here.
    // Use current user after pass authentication to validate access.
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }
    $suffix_military = 'tweet_v1_m_' . $langcode . '.json';
    $suffix_non_military = 'tweet_v1_' . $langcode . '.json';

    if ($suffix == $suffix_non_military) {
      $output = $this->twgApiHelper->getQuestions($langcode, FALSE);
    }
    elseif ($suffix == $suffix_military) {
      $output = $this->twgApiHelper->getQuestions($langcode, TRUE);
    }
    else {
      $output = ['Bad suffix, wrong request.'];
    }

    $response = (new ModifiedResourceResponse($output, 200));
    $response->headers->set('Content-Length', strlen(Json::encode($output)));
    return $response;
  }

}
