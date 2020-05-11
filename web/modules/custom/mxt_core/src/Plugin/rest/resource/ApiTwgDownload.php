<?php

namespace Drupal\mxt_core\Plugin\rest\resource;

use DOMDocument;
use Drupal\Component\Serialization\Json;
use Drupal\image\Entity\ImageStyle;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "api_twg_download",
 *   label = @Translation("Api twg download"),
 *   uri_paths = {
 *     "canonical" = "/api/twg/v1/download/{code}/{langcode}/{suffix}"
 *   }
 * )
 */
class ApiTwgDownload extends ResourceBase {

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
   *   Code request from URL.
   * @param string $langcode
   *   Langcode from URL.
   * @param string $suffix
   *   Suffix request from URL.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function get($code, $langcode, $suffix) {
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    if ($code == 'questions' && $suffix == 'tweet_v1.json') {
      $langcode = $this->twgApiHelper->prepareLangcode($langcode);
      $output = $this->downloadQuestions($langcode);
    }
    elseif ($code == 'mass' && $suffix == 'holymass_v1.json') {
      $output = $this->downloadMassByzantine('field_order_of_mass', $langcode);
    }
    elseif ($code == 'divine' && $suffix == 'divine_v1.json') {
      $output = $this->downloadMassByzantine('field_byzantine_rites', $langcode);
    }
    elseif ($code == 'catholic' && $suffix == 'catholic_v1.json') {
      $output = $this->downloadCatholic('field_texts', $langcode);
    }
    elseif ($code == 'versions' && $suffix == 'versions_v1.json') {
      $output = $this->getVersions();
    }
    else {
      $output = ['Error url parameters'];
    }

    $response = (new ModifiedResourceResponse($output, 200));
    $response->headers->set('Content-Length', strlen(Json::encode($output)));
    return $response;
  }

  /**
   * Get array of versions content.
   *
   * @return array[]
   *   array of versions.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getVersions() {
    $node_type = 'rites';
    $nids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', $node_type)
      ->condition('status', 1)
      ->execute();
    $versions_rites = [];
    foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($nids) as $node) {
      $version_key = 'json_version__' . $node_type . '__' . $node->id();
      $langcode = $node->get('field_taal')->value;
      $versions_rites[$langcode . '_v'] = \Drupal::state()->get($version_key, '1.0');
    }

    $versions_tweets = [];
    $langcodes = \Drupal::languageManager()->getLanguages();
    foreach ($langcodes as $langcode => $language) {
      $version_key = 'json_version__tweet_page__' . $langcode;
      $versions_tweets[$this->twgApiHelper->prepareLangcode($langcode, TRUE) . '_v'] = \Drupal::state()->get($version_key, '1.0');
    }

    return [
      'mass_versions' => $versions_rites,
      'catholic_versions' => $versions_rites,
      'byzantine_versions' => $versions_rites,
      'question_versions' => $versions_tweets,
    ];
  }

  /**
   * Compare number open and close tags.
   *
   * @param string $html
   *   HTML string.
   *
   * @return bool
   *   Result validate.
   */
  private function closetags($html) {
    preg_match_all('#<(?!meta|img|br|hr|input\b)\b([a-z][a-z1-6]*)(?: .*)?(?<![/|/ ])>#iU', $html, $result);
    $openedtags = $result[1];
    preg_match_all('#</([a-z][a-z1-6]*)>#iU', $html, $result);
    $closedtags = $result[1];
    $len_opened = count($openedtags);
    if (count($closedtags) == $len_opened) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Get number version.
   *
   * @param string $code
   *   String name code data.
   * @param string $langcode
   *   Langcode.
   *
   * @return string
   *   Number version.
   */
  private function getJsonVersion($code, $langcode) {
    $key_name = 'json_version__' . $code;
    $version = \Drupal::state()->get($key_name, '1.0');
    return $version;
  }

  /**
   * Get response data Catholic request.
   *
   * @param string $field
   *   Field name.
   * @param string $langcode
   *   Lang code.
   *
   * @return array|mixed
   *   Array response data.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function downloadCatholic($field, $langcode) {
    $node_type = 'rites';
    $nids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', $node_type)
      ->condition('field_taal', $langcode)
      ->condition('status', 1)
      ->execute();
    if (empty($nids)) {
      return [];
    }
    $nid = array_shift($nids);

    $data = &drupal_static(__FUNCTION__);
    $version_key = 'json_version__' . $node_type . '__' . $nid;
    $cid = basename(__FILE__, '.module') . ':' . $field . ':' . $version_key;

    if ($cache = \Drupal::cache()->get($cid)) {
      $data = $cache->data;
    }
    else {
      $node = $this->entityTypeManager->getStorage('node')->load($nid);
      if ($node->get($field)->isEmpty()) {
        return [];
      }

      $html_items = $node->get($field)->getValue();
      $output = [];
      foreach ($html_items as $html_item) {
        $source_text = $this->cleanReplaceCode($html_item['value']);
        if ($this->closetags($source_text)) {
          $html = new DOMDocument();
          $html->loadHTML('<?xml encoding="UTF-8">' . $source_text);

          $h1_elements = $this->getTagByAttr($html, 'h1', 'lang', $langcode);
          $h2_elements = $this->getTagByAttr($html, 'h2', 'lang', $langcode);
          $h6_elements = $this->getTagByAttr($html, 'h6', 'lang', $langcode);
          $p_elements = $this->getTagByAttr($html, 'p', '', '', FALSE);
          $text_elements = $p_elements;
          ksort($text_elements);

          $output['name'] = array_shift($h1_elements);
          $h2_offsets = array_keys($h2_elements);
          if ($h2_elements) {
            foreach ($h2_elements as $index => $subtitle) {
              $offset_index = array_search($index, $h2_offsets) + 1;
              $end = ($offset_index <= count($h2_elements) - 1) ? $h2_offsets[$offset_index] : 0;
              $output['sub_sections'][] = [
                'title' => $subtitle,
                'tweet_no' => preg_replace('/[^0-9.]/', '', $this->getElementByRange($h6_elements, $index, $end)),
                'details' => $this->getElementByRange($text_elements, $index, $end),
              ];
            }
          }
          else {
            $output['sub_sections'][] = [
              'title' => '',
              'tweet_no' => $this->getElementByRange($h6_elements, 0, 0),
              'details' => $this->getElementByRange($text_elements, 0, 0),
            ];
          }
        }
        else {
          $output['sub_sections'][] = [
            'title' => $this->t('ERROR: Not valid HTML'),
            'tweet_no' => '',
            'details' => [],
          ];
        }
        $sections_data[] = $output;
      }

      $data['sections'] = $sections_data;
      $data['lang'] = $langcode;
      $data['ver'] = \Drupal::state()->get($version_key, '1.0');
      \Drupal::cache()->set($cid, $data, (time() + JSON_DATA_CACHE_MAX_AGE), [
        'json_api:node:' . $node_type . ':' . $node->id(),
      ]);
    }
    return $data;
  }

  /**
   * Get response data Mass & Byzantine request.
   *
   * @param string $field
   *   Field name.
   * @param string $langcode
   *   Lang code.
   *
   * @return array|mixed
   *   Array response data.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function downloadMassByzantine($field, $langcode) {
    $node_type = 'rites';
    $nids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', $node_type)
      ->condition('field_taal', $langcode)
      ->condition('status', 1)
      ->execute();
    if (empty($nids)) {
      return [];
    }
    $nid = array_shift($nids);

    $data = &drupal_static(__FUNCTION__);
    $version_key = 'json_version__' . $node_type . '__' . $nid;
    $cid = basename(__FILE__, '.module') . ':' . $field . ':' . $version_key;

    if ($cache = \Drupal::cache()->get($cid)) {
      $data = $cache->data;
    }
    else {
      $node = $this->entityTypeManager->getStorage('node')->load($nid);
      if ($node->get($field)->isEmpty()) {
        return [];
      }

      $html_items = $node->get($field)->getValue();
      $output = [];
      foreach ($html_items as $html_item) {
        $source_text = $this->cleanReplaceCode($html_item['value']);
        if ($this->closetags($source_text)) {
          $html = new DOMDocument();
          $html->loadHTML('<?xml encoding="UTF-8">' . $source_text);
          $h1_elements = $this->getTagByAttr($html, 'h1', 'lang', $langcode);
          $h2_elements = $this->getTagByAttr($html, 'h2', 'lang', $langcode);
          $h6_elements = $this->getTagByAttr($html, 'h6', 'lang', $langcode, FALSE);
          $p_elements = $this->getTagByAttr($html, 'p', '', '', FALSE);
          $text_elements = $h6_elements + $p_elements;
          ksort($text_elements);

          $output['name'] = array_shift($h1_elements);
          $text = [];
          $h2_offsets = array_keys($h2_elements);
          $sub_sect = [];
          if ($h2_elements) {
            foreach ($h2_elements as $index => $subtitle) {
              $offset_index = array_search($index, $h2_offsets) + 1;
              $end = ($offset_index <= count($h2_elements) - 1) ? $h2_offsets[$offset_index] : 0;
              $output['sub_sections'][] = [
                'title' => $subtitle,
                'details' => $this->getElementByRange($text_elements, $index, $end),
              ];
            }
          }
          else {
            $output['sub_sections'][] = [
              'title' => '',
              'details' => $this->getElementByRange($text_elements, 0, 0),
            ];
          }
        }
        else {
          $output['sub_sections'][] = [
            'title' => $this->t('ERROR: Not valid HTML'),
            'details' => [],
          ];
        }
        $sections_data[] = $output;
      }
      $data['lang'] = $langcode;
      $data['ver'] = \Drupal::state()->get($version_key, '1.0');
      $data['sections'] = $sections_data;

      \Drupal::cache()->set($cid, $data, (time() + JSON_DATA_CACHE_MAX_AGE), [
        'json_api:node:' . $node_type . ':' . $node->id(),
      ]);
    }
    return $data;
  }

  /**
   * Get elements from array (range key).
   *
   * @param array $elements
   *   Array elements.
   * @param int $start
   *   Start value.
   * @param int $end
   *   End value.
   *
   * @return string
   *   String  HTML.
   */
  private function getElementByRange(array $elements, $start, $end = 0) {
    $output = '';
    foreach ($elements as $offset => $element) {
      if ($offset > $start) {
        if ($end) {
          if ($offset < $end) {
            $output .= $element;
          }
        }
        else {
          $output .= $element;
        }
      }
    }
    return $output;
  }

  /**
   * Get tags from html string.
   *
   * @param \DOMDocument $html
   *   HTML string.
   * @param string $tag
   *   Tag name.
   * @param string $attr
   *   Attribute name.
   * @param string $value_attr
   *   Attribute value.
   * @param bool $inner
   *   TRUE if innerHTML (default), FALSE - outerHTML.
   *
   * @return array
   *   Array of string.
   */
  private function getTagByAttr(DOMDocument $html, $tag, $attr = '', $value_attr = '', $inner = TRUE) {
    $tags = $html->getElementsByTagName($tag);
    $tags->encoding = 'UTF-8';
    $output = [];
    foreach ($tags as $tag) {
      if ((empty($attr) && empty($value_attr)) ||
        (!empty($attr) && empty($value_attr) && $tag->hasAttribute($attr)) ||
        (!empty($attr) && !empty($value_attr) && $tag->hasAttribute($attr) && $tag->getAttribute($attr) == $value_attr)) {
        $output[$tag->getLineNo()] = $inner ? htmlspecialchars_decode($this->twgApiHelper->getInnerHtml($tag)) : htmlspecialchars_decode($this->twgApiHelper->getOuterHtml($tag));
      }
    }
    return $output;
  }

  /**
   * Clean bad tags.
   *
   * @param string $str
   *   Text string.
   *
   * @return string
   *   Return text string.
   */
  private function cleanReplaceCode($str) {
    $str = str_replace('</font color>', '</font>', $str);
    return $str;
  }

  /**
   * Get response data.
   *
   * @param string $langcode
   *   Langcode.
   *
   * @return array|mixed
   *   Array response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function downloadQuestions($langcode) {
    $node_type = 'tweet_page';
    $data = &drupal_static(__FUNCTION__);
    $version_key = 'json_version__' . $node_type . '__' . $langcode;
    $cid = basename(__FILE__, '.module') . ':' . $version_key;

    if ($cache = \Drupal::cache()->get($cid)) {
      $data = $cache->data;
    }
    else {
      $output = [];
      $node_storage = $this->entityTypeManager->getStorage('node');
      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
      $tids = $term_storage->getQuery()
        ->condition('vid', 'subject')
        ->condition('langcode', $langcode)
        ->sort('name', 'ASC')
        ->execute();

      foreach ($term_storage->loadMultiple($tids) as $term) {
        $translated_term = \Drupal::service('entity.repository')->getTranslationFromContext($term, $langcode);

        $nids = $node_storage->getQuery()
          ->condition('type', 'tweet_page')
          ->condition('langcode', $langcode)
          ->condition('field_subject', $term->id())
          ->sort('nid', 'ASC')
          ->execute();

        $tweets = [];
        foreach ($node_storage->loadMultiple($nids) as $node) {
          $node_translation = $node->getTranslation($langcode);
          $uri = !$node_translation->get('field_image')
            ->isEmpty() ? $node_translation->get('field_image')->entity->getFileUri() : '';

          $wisdom = $this->twgApiHelper->getFromParagraphs($node_translation->get('field_references_to_text')
            ->referencedEntities(), [
              'w_title' => 'field_reference_heading',
              'w_content' => 'field_reference_body',
            ], $langcode);

          $content = $this->twgApiHelper->transformLangcode($node_translation->get('body')->value, $langcode);

          $tweet = [
            'id' => $node_translation->id(),
            'date' => date('Y-M-D\TH:i:s.u', $node_translation->get('created')->value),
            'number' => $this->twgApiHelper->getPartsFromTitle($node_translation->label())['number'],
            'img_url' => $uri ? file_create_url($uri) : '',
            'thumbnail' => $uri ? ImageStyle::load('tweet_related_teaser')
              ->buildUrl($uri) : '',
            'title' => $this->twgApiHelper->getPartsFromTitle($node_translation->label())['text'],
            'content' => $this->twgApiHelper->prepareTweetContent($content),
            'tweet_text' => $node_translation->get('field_tweetbox')->value,
            'is_daily_hidden' => FALSE,
            'wisdom' => $wisdom,
            'church_father' => strip_tags($this->twgApiHelper->getFieldFromParagraph($node_translation->get('field_references_to_church_fathe'), 'field_reference_body', $langcode)),
            'pope_say' => strip_tags($this->twgApiHelper->getFieldFromParagraph($node_translation->get('field_references_to_the_popes'), 'field_reference_body', $langcode)),
            'video' => $this->twgApiHelper->getVideoData($node_translation, 'field_video'),
            'related' => $this->twgApiHelper->getSubjectRelated($node, $nids, $langcode),
          ];

          $tweets[] = $tweet;
        }

        $output[] = [
          'id' => $term->id(),
          'name' => $translated_term->label(),
          'tweets' => $tweets,
        ];
      }

      $military = $this->twgApiHelper->getQuestions($langcode, TRUE);
      $sort_output = $this->twgApiHelper->sortTweets($output);

      $data = [
        'lang' => $this->twgApiHelper->prepareLangcode($langcode, TRUE),
        'ver' => \Drupal::state()->get($version_key, '1.0'),
        'category' => $sort_output,
        'military' => $military['sections'],
      ];

      \Drupal::cache()->set($cid, $data, (time() + JSON_DATA_CACHE_MAX_AGE), [
        'json_api:node:' . $node_type,
        'json_api:texonomy_term:subject',
      ]);
    }

    return $data;
  }

  /**
   * Make parameters "suffix" optionally.
   *
   * {@inheritdoc}
   */
  public function routes() {
    $collection = parent::routes();
    // Add defaults for optional parameters.
    $defaults = [
      'suffix' => '',
    ];
    foreach ($collection->all() as $route) {
      $route->addDefaults($defaults);
    }
    return $collection;
  }

}
