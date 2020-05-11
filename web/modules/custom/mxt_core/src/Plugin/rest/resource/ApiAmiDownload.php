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
 *   id = "api_ami_download",
 *   label = @Translation("Api ami download"),
 *   uri_paths = {
 *     "canonical" = "/api/ami/v1/download/{code}/{langcode}/{suffix}"
 *   }
 * )
 */
class ApiAmiDownload extends ResourceBase {

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
   * @param string $code
   *   Code part from URL.
   * @param string $langcode
   *   LangCode part from URL.
   * @param string $suffix
   *   Suffix part from URL.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function get($code, $langcode, $suffix) {
    // You must to implement the logic of your REST Resource here.
    // Use current user after pass authentication to validate access.
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    $output = [];
    $langcode = $this->twgApiHelper->prepareLangcode($langcode);

    if ($code == 'testmonies' && $suffix == 'testmonies_v1.json') {
      $output = [
        'testimonies' => $this->downloadTestmonies($langcode),
      ];
    }
    elseif ($code == 'aid' && $suffix == 'aid_v1.json') {
      $output = [
        'aid' => $this->downloadAid($langcode),
      ];
    }
    elseif ($code == 'saints' && $suffix == 'saints_v1.json') {
      $output = [
        'saints' => $this->downloadSaints($langcode),
      ];
    }
    elseif ($code == 'mprayers' && $suffix == 'prayers_v1.json') {
      $output = [
        'prayers' => $this->downloadPrayers($langcode),
      ];
    }
    elseif ($code == 'pquotes' && $suffix == 'pquotes_v1.json') {
      $output = [
        'pquotes' => $this->downloadPquotes($langcode),
      ];
    }
    elseif ($code == 'iquotes' && $suffix == 'iquotes_v1.json') {
      $output = [
        'iquotes' => $this->downloadIquotes($langcode),
      ];
    }
    elseif ($code == 'homilies' && $suffix == 'homilies_v1.json') {
      $output = [
        'homilies' => $this->downloadHomilies($langcode),
      ];
    }
    elseif ($code == 'chaplaincies' && $suffix == 'belgium_v1.json') {
      $output = $this->downloadChaplaincies($langcode);
    }

    $response = (new ModifiedResourceResponse($output, 200));
    $response->headers->set('Content-Length', strlen(Json::encode($output)));
    return $response;
  }

  /**
   * Get data for response.
   *
   * @param string $langcode
   *   Langcode.
   *
   * @return array
   *   Array to response.
   */
  private function downloadChaplaincies($langcode) {
    if ($langcode == 'en') {
      $mxt_config = \Drupal::configFactory()->getEditable('mxt_core.mxt_info');
      $site_config = \Drupal::configFactory()->getEditable('system.site');
    }
    else {
      $mxt_config = \Drupal::languageManager()->getLanguageConfigOverride($langcode, 'mxt_core.mxt_info');
      $site_config = \Drupal::languageManager()->getLanguageConfigOverride($langcode, 'system.site');
    }

    $output = [
      'name' => $site_config->get('name') ?? '',
      'details' => $site_config->get('slogan') ?? '',
      'phone' => $mxt_config->get('phone') ?? '',
      'image_url' => $mxt_config->get('image_url') ?? '',
      'site_url' => $mxt_config->get('site_url') ?? '',
      'news_url' => $mxt_config->get('news_url') ?? '',
      'initiatives_url' => $mxt_config->get('initiatives_url') ?? '',
      'questions_url' => $mxt_config->get('questions_url') ?? '',
      'fb' => $mxt_config->get('fb') ?? '',
      'twitter' => $mxt_config->get('twitter') ?? '',
      'instagram' => $mxt_config->get('instagram') ?? '',
      'youtube' => $mxt_config->get('youtube') ?? '',
    ];
    return $output;
  }

  /**
   * Get data for response.
   *
   * @param string $langcode
   *   Langcode.
   *
   * @return array
   *   Array to response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function downloadIquotes($langcode) {
    $return = [];
    $nids = $this->getAllNodeIds('inspirational_quotes', $langcode);
    if (empty($nids)) {
      return $return;
    }

    /** @var \Drupal\node\Entity\Node $node */
    foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($nids) as $node) {
      $node_translation = $node->getTranslation($langcode);
      $img_uri = !$node_translation->get('field_image_4_3')
        ->isEmpty() ? $node_translation->get('field_image_4_3')->entity->getFileUri() : '';

      $return[] = [
        'id' => $node_translation->id(),
        'created_at' => date('Y-M-D\TH:i:s.u', $node_translation->get('created')->value),
        'title' => $node_translation->label(),
        'details' => $node_translation->get('body')->value,
        'img_url' => $img_uri ? file_create_url($img_uri) : '',
        'author' => $node_translation->get('field_author')->value,
      ];
    }
    return $return;
  }

  /**
   * Get data for response.
   *
   * @param string $langcode
   *   Langcode.
   *
   * @return array
   *   Array to response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function downloadHomilies($langcode) {
    $return = [];
    $nids = $this->getAllNodeIds('homilies_reflections', $langcode);
    if (empty($nids)) {
      return $return;
    }

    /** @var \Drupal\node\Entity\Node $node */
    foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($nids) as $node) {
      $node_translation = $node->getTranslation($langcode);
      $img_uri = !$node_translation->get('field_image_4_3')
        ->isEmpty() ? $node_translation->get('field_image_4_3')->entity->getFileUri() : '';

      $return[] = [
        'id' => $node_translation->id(),
        'created_at' => date('Y-M-D\TH:i:s.u', $node_translation->get('created')->value),
        'title' => $node_translation->label(),
        'details' => $node_translation->get('body')->value,
        'img_url' => $img_uri ? file_create_url($img_uri) : '',
        'author' => $node_translation->get('field_author')->value,
      ];
    }
    return $return;
  }

  /**
   * Get data for response.
   *
   * @param string $langcode
   *   Langcode.
   *
   * @return array
   *   Array to response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function downloadPquotes($langcode) {
    $return = [];
    $nids = $this->getAllNodeIds('papal_quotes', $langcode);
    if (empty($nids)) {
      return $return;
    }

    /** @var \Drupal\node\Entity\Node $node */
    foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($nids) as $node) {
      $node_translation = $node->getTranslation($langcode);
      $img_uri = !$node_translation->get('field_image_4_3')
        ->isEmpty() ? $node_translation->get('field_image_4_3')->entity->getFileUri() : '';

      $return[] = [
        'id' => $node_translation->id(),
        'created_at' => date('Y-M-D\TH:i:s.u', $node_translation->get('created')->value),
        'title' => $node_translation->label(),
        'details' => $node_translation->get('body')->value,
        'img_url' => $img_uri ? file_create_url($img_uri) : '',
        'author' => $node_translation->get('field_author')->value,
      ];
    }
    return $return;
  }

  /**
   * Get data for response.
   *
   * @param string $langcode
   *   Langcode.
   *
   * @return array
   *   Array to response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function downloadPrayers($langcode) {
    $return = [];
    $nids = $this->getAllNodeIds('prayers', $langcode);
    if (empty($nids)) {
      return $return;
    }

    /** @var \Drupal\node\Entity\Node $node */
    foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($nids) as $node) {
      $node_translation = $node->getTranslation($langcode);
      $img_uri = !$node_translation->get('field_image_4_3')
        ->isEmpty() ? $node_translation->get('field_image_4_3')->entity->getFileUri() : '';

      $return[] = [
        'id' => $node_translation->id(),
        'created_at' => date('Y-M-D\TH:i:s.u', $node_translation->get('created')->value),
        'title' => $node_translation->label(),
        'details' => $node_translation->get('body')->value,
        'img_url' => $img_uri ? file_create_url($img_uri) : '',
        'author' => $node_translation->get('field_author_origin')->value,
      ];
    }
    return $return;
  }

  /**
   * Get list node ids.
   *
   * @param string $type
   *   Node type.
   * @param string $langcode
   *   LangCode.
   *
   * @return array|int
   *   List nids.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getAllNodeIds($type, $langcode) {
    $nids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', $type)
      ->condition('langcode', $langcode)
      ->condition('status', 1)
      ->execute();
    return $nids;
  }

  /**
   * Get data for response.
   *
   * @param string $langcode
   *   Parameter langcode.
   *
   * @return array
   *   Array to response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function downloadSaints($langcode) {
    $return = [];
    $nids = $this->getAllNodeIds('military_saints', $langcode);
    if (empty($nids)) {
      return $return;
    }

    /** @var \Drupal\node\Entity\Node $node */
    foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($nids) as $node) {
      $node_translation = $node->getTranslation($langcode);
      $img_uri = !$node_translation->get('field_image')
        ->isEmpty() ? $node_translation->get('field_image')->entity->getFileUri() : '';

      $return[] = [
        'id' => $node_translation->id(),
        'name' => $node_translation->label(),
        'details' => $node_translation->get('body')->value,
        'img_URL' => $img_uri ? file_create_url($img_uri) : '',
        'year' => $node_translation->get('field_year')->value,
      ];
    }
    return $return;
  }

  /**
   * Get data for response.
   *
   * @param string $langcode
   *   Parameter langcode.
   *
   * @return array
   *   Array to response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function downloadTestmonies($langcode) {
    $return = [];
    $nids = $this->getAllNodeIds('testimonies', $langcode);
    if (empty($nids)) {
      return $return;
    }

    /** @var \Drupal\node\Entity\Node $node */
    foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($nids) as $node) {
      $node_translation = $node->getTranslation($langcode);
      $img_uri = !$node_translation->get('field_image_4_3')
        ->isEmpty() ? $node_translation->get('field_image_4_3')->entity->getFileUri() : '';

      $return[] = [
        'id' => $node_translation->id(),
        'created_at' => date('Y-M-D\TH:i:s.u', $node_translation->get('created')->value),
        'title' => $node_translation->label(),
        'details' => $node_translation->get('body')->value,
        'video_url' => $node_translation->get('field_video')->value,
        'img_url' => $img_uri ? file_create_url($img_uri) : '',
        'author' => $node_translation->get('field_author')->value,
      ];
    }
    return $return;
  }

  /**
   * Get data for response.
   *
   * @param string $langcode
   *   Parameter langcode.
   *
   * @return array
   *   Array to response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function downloadAid($langcode) {
    $return = [];
    $nids = $this->getAllNodeIds('spiritual_first_aid', $langcode);
    if (empty($nids)) {
      return $return;
    }

    /** @var \Drupal\node\Entity\Node $node */
    foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($nids) as $node) {
      $node_translation = $node->getTranslation($langcode);
      $img_uri = !$node_translation->get('field_image_4_3')
        ->isEmpty() ? $node_translation->get('field_image_4_3')->entity->getFileUri() : '';

      $return[] = [
        'id' => $node_translation->id(),
        'created_at' => date('Y-M-D\TH:i:s.u', $node_translation->get('created')->value),
        'title' => $node_translation->label(),
        'details' => $node_translation->get('body')->value,
        'video_url' => $node_translation->get('field_video')->value,
        'img_url' => $img_uri ? file_create_url($img_uri) : '',
        'author' => $node_translation->get('field_author')->value,
      ];
    }
    return $return;
  }

}
