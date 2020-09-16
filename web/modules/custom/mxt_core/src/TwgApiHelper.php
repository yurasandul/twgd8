<?php

namespace Drupal\mxt_core;

use DOMDocument;
use Drupal\Core\Database\Database;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\node\Entity\Node;

/**
 * Class TwgApiHelper.
 */
class TwgApiHelper {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Session\AccountProxyInterface definition.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Drupal\Core\Entity\EntityStorageInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * Constructs a new TwgApiHelper object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $current_user) {
    $this->entityTypeManager = $entity_type_manager;
    $this->nodeStorage = $entity_type_manager->getStorage('node');
    $this->currentUser = $current_user;
  }

  /**
   * Get all tweets for language.
   *
   * @param string $lang
   *   Language node to request.
   * @param bool $sorted
   *   Parameter to sorting node.
   *
   * @return mixed
   *   Array with node.
   */
  public function twgApiGetTweets($lang, $sorted = FALSE) {
    $query = $this->nodeStorage->getQuery()
      ->condition('type', 'tweet_page')
      ->condition('status', 1)
      ->condition('langcode', $lang);
    if ($sorted) {
      $query->sort('field_subject.entity:taxonomy_term.weight', 'ASC');
    }
    $nids = $query->execute();
    return $this->nodeStorage->loadMultiple($nids);
  }

  /**
   * Parse a tweet and localize some stuff.
   *
   * @param \Drupal\node\Entity\Node $tweetnode
   *   Node.
   * @param string $lang
   *   Langcode.
   *
   * @return array
   *   Array response.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function twgProcessTweetNode(Node $tweetnode, $lang) {
    if (!$tweetnode->get('field_subject')->isEmpty()) {
      /** @var \Drupal\taxonomy\Entity\Term $term */
      $term = $tweetnode->get('field_subject')->entity;
      $term_translate = \Drupal::service('entity.repository')->getTranslationFromContext($term, $lang);
      $tid = $term_translate->id();
      $subject = $term_translate->getName();
      $book = $term_translate->get('field_book')->first()->value;
    }
    else {
      $tid = 0;
      $subject = '';
      $book = '';
    }

    $imgurl = !$tweetnode->get('field_image')->isEmpty() ? $tweetnode->get('field_image')->entity->getFileUri() : '';
    $tweet = [
      'title' => $tweetnode->getTitle(),
      'img-url' => ImageStyle::load('twg_api_appimage')->buildUrl($imgurl),
      'url' => $tweetnode->getTranslation($lang)->toUrl()->setAbsolute()->toString(),
      'nid' => $tweetnode->id(),
      'tid' => $tid,
      'subject' => $subject,
      'book' => $book,
    ];
    return $tweet;
  }

  /**
   * Render the top tweets view.
   *
   * @param string $lang
   *   Langcode.
   *
   * @return array
   *   Array nids.
   */
  public function twgTranslateTopTweets($lang) {
    $now = new DrupalDateTime('now');
    $user_roles = \Drupal::currentUser()->getRoles();

    /** @var \Drupal\Core\Entity\Query\Sql\Query $query */
    $query = $this->nodeStorage->getQuery();
    $query->condition('type', 'tweet_page');
    $query->condition('langcode', $lang);
    $query->condition('field_sticky_sort_date', $now->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT), '<=');
    if (!in_array('administrator', $user_roles)) {
      $query->condition('status', 1);
    }
    $query->sort('sticky', 'DESC');
    $query->sort('field_sticky_sort_date', 'DESC');
    $query->range(0, 5);
    $query->accessCheck(FALSE);
    $nids = $query->execute();

    return $nids;
  }

  /**
   * Response data for rites node.
   *
   * @param string $field
   *   Field name.
   * @param string $lang
   *   Langcode.
   *
   * @return array
   *   Response array.
   */
  public function twgApiGetRites($field = '', $lang = '') {
    $output = [];
    $output['rites'] = [];
    $output['order_of_mass'] = [];
    $output['byzantine_rites'] = [];

    $nids = $this->nodeStorage->getQuery()
      ->condition('type', 'rites')
      ->condition('status', 1)
      ->execute();

    /** @var \Drupal\node\Entity\Node $node */
    foreach ($this->nodeStorage->loadMultiple($nids) as $node) {
      if ($node->hasField('field_taal') && !$node->get('field_taal')->isEmpty()) {
        $nodeLang = $node->get('field_taal')->getString();

        // Rites.
        $texts = [];
        foreach ($node->get('field_texts')->getValue() as $text) {
          $texts[] = $text['value'];
        }

        if (count($texts) > 0) {
          $output['rites'][$nodeLang] = $texts;
        }

        // Order of mass.
        $texts = [];
        foreach ($node->get('field_order_of_mass')->getValue() as $text) {
          $texts[] = $text['value'];
        }

        if (count($texts) > 0) {
          $output['order_of_mass'][$nodeLang] = $texts;
        }

        // Byzantine rites.
        $texts = [];
        foreach ($node->get('field_byzantine_rites')->getValue() as $text) {
          $texts[] = $text['value'];
        }

        if (count($texts) > 0) {
          $output['byzantine_rites'][$nodeLang] = $texts;
        }
      }
    }

    if ($field) {
      $output = [
        $field => $output[$field],
      ];
    }

    if ($field && $lang) {
      $output = [
        $field => [
          $lang => $output[$field][$lang],
        ],
      ];
    }

    return $output;
  }

  /**
   * Sanitize input.
   *
   * @param string $string
   *   String to clean.
   *
   * @return string
   *   Cleaned string.
   */
  public function cleanJsonString($string) {
    $s = trim($string);
    // Drop all non utf-8 characters.
    $s = iconv("UTF-8", "UTF-8//IGNORE", $s);

    // This is some bad utf-8 byte sequence that makes mysql
    // complain - control and formatting i think.
    $s = preg_replace('/(?>[\x00-\x1F]|\xC2[\x80-\x9F]|\xE2[\x80-\x8F]{2}|\xE2\x80[\xA4-\xA8]|\xE2\x81[\x9F-\xAF])/', ' ', $s);

    return $s;
  }

  /**
   * Get message from remote server.
   *
   * @param string $url
   *   Url to request.
   *
   * @return bool|string
   *   Return data request.
   */
  public function twgApiFetchDailyPrayer($url) {
    $client = \Drupal::httpClient();
    $request = $client->request('GET', $url);
    if ($request->getStatusCode() == 200) {
      $response = $request->getBody();
      $text = $response->getContents();
      return $text;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Get data from paragraph.
   *
   * @param array $paragraphs
   *   Array Paragraphs.
   * @param array $fields
   *   Array field names.
   * @param string $langcode
   *   Langcode.
   *
   * @return array
   *   Array paragraph value.
   */
  public function getFromParagraphs(array $paragraphs, array $fields, $langcode) {
    $return = [];
    foreach ($paragraphs as $paragraph) {
      $translation = $paragraph->getTranslation($langcode);
      $paragraph_data = [];
      foreach ($fields as $key => $field_name) {
        if (!$translation->get($field_name)->isEmpty()) {
          $paragraph_data[$key] = preg_replace('|\s+|', ' ', strip_tags(htmlspecialchars_decode($translation->get($field_name)->value)));
        }
      }
      if (!empty($paragraph_data)) {
        $return[] = $paragraph_data;
      }
    }
    return $return;
  }

  /**
   * Prepare title to sortable.
   *
   * @param string $title
   *   Tweet title.
   *
   * @return array|mixed|string
   *   Divide title tweets to number & string.
   */
  public function getPartsFromTitle($title) {
    $parts = explode(' ', $title);
    if (is_numeric($parts[0])) {
      $return = [
        'number' => $this->weightTweet($parts[0]),
        'text' => trim(mb_substr($title, mb_strlen($parts[0]))),
      ];
    }
    else {
      $return = [
        'number' => '',
        'text' => $title,
      ];

    }
    return $return;
  }

  /**
   * Get array with video data.
   *
   * @param \Drupal\node\Entity\Node $node
   *   Entity node.
   * @param string $field
   *   Video field name.
   *
   * @return array
   *   Array video data (teaser & url video).
   */
  public function getVideoData(Node $node, $field) {
    $videos = $node->$field->view('default');
    $return = [];
    if (isset($videos['#items'])) {
      foreach ($videos['#items'] as $index => $value) {
        $uri = $videos[$index]['#uri'];
        $return[] = [
          'link' => $value->value,
          'image' => ImageStyle::load('content_large')->buildUrl($uri),
        ];
      }
    }
    return $return;
  }

  /**
   * Get translated value from field paragraph.
   *
   * @param \Drupal\Core\Field\FieldItemList $field_paragraph
   *   Field from paragraph.
   * @param string $field
   *   Field name.
   * @param string $langcode
   *   Langcode.
   *
   * @return string
   *   Field value from paragraph.
   */
  public function getFieldFromParagraph(FieldItemList $field_paragraph, $field, $langcode) {
    if (empty($field_paragraph) || empty($field_paragraph->entity) || !$field_paragraph->entity->hasTranslation($langcode)) {
      return '';
    }
    $translations = $field_paragraph->entity->getTranslation($langcode);
    if ($translations->get($field)->isEmpty()) {
      return '';
    }
    return $translations->get('field_reference_body')->value;
  }

  /**
   * Get related tweets data.
   *
   * @param \Drupal\node\Entity\Node $source_node
   *   Entity node.
   * @param array $nids
   *   Array nids.
   * @param string $langcode
   *   Langcode.
   *
   * @return array
   *   Array with node data.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getSubjectRelated(Node $source_node, array $nids, $langcode) {
    $return = [];
    foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($nids) as $node) {

      if ($source_node->id() == $node->id()) {
        continue;
      }

      $node_translation = $node->getTranslation($langcode);
      $uri = !$node_translation->get('field_image')
        ->isEmpty() ? $node_translation->get('field_image')->entity->getFileUri() : '';
      $return[] = [
        'id' => $node_translation->id(),
        'number' => $this->getPartsFromTitle($node_translation->label())['number'],
        'title' => $this->getPartsFromTitle($node_translation->label())['text'],
        'img_url' => $uri ? file_create_url($uri) : '',
        'thumbnail' => $uri ? ImageStyle::load('tweet_related_teaser')->buildUrl($uri) : '',
      ];

    }
    return $return;
  }

  /**
   * ABOUT TWG resource.
   *
   * @param int $nid
   *   Node id.
   * @param string $langcode
   *   Langcode.
   *
   * @return array
   *   Output array.
   */
  public function twgApiAbout($nid, $langcode) {
    /** @var \Drupal\node\Entity\Node $node */
    $node = $this->nodeStorage->load($nid);
    if (!$node) {
      return ['Not found "About Tweeting With God"'];
    }
    if ($node->hasTranslation($langcode)) {
      $node = $node->getTranslation($langcode);
    }
    $img_uri = !$node->get('field_logo')
      ->isEmpty() ? $node->get('field_logo')->entity->getFileUri() : '';

    $output = [
      'details' => strip_tags($node->get('body')->value ?? ''),
      'img_url' => $img_uri ? file_create_url($img_uri) : '',
      'site_url' => $node->get('field_url')->uri ?? '',
    ];
    return $output;
  }

  /**
   * Get data to end-point.
   *
   * @param string $langcode
   *   Langcode.
   * @param bool $military
   *   Military request.
   *
   * @return array
   *   Return array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getQuestions($langcode, $military = FALSE) {
    $langcode = $this->prepareLangcode($langcode);
    $return = [];
    $output = [];
    $m_key = $military ? '1' : '0';

    $cid_key = 'json_version_tweet_page_ami_' . $langcode . '_' . $m_key;

    $cid = basename(__FILE__, '.module') . ':' . $cid_key;
    if ($cache = \Drupal::cache()->get($cid)) {
      $output = $cache->data;
    }
    else {
      $node_storage = $this->entityTypeManager->getStorage('node');
      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
      $tids = $term_storage->getQuery()
        ->condition('vid', 'subject')
        ->condition('langcode', $langcode)
        ->sort('name', 'ASC')
        ->execute();

      foreach ($term_storage->loadMultiple($tids) as $term) {
        $translated_term = \Drupal::service('entity.repository')
          ->getTranslationFromContext($term, $langcode);

        $query = $node_storage->getQuery()
          ->condition('type', 'tweet_page')
          ->condition('field_subject', $term->id())
          ->condition('langcode', $langcode)
          ->condition('status', 1)
          ->sort('nid', 'ASC');

        if ($military) {
          $query->condition('field_military', TRUE);
        }
        else {
          $orGroup = $query->orConditionGroup()
            ->condition('field_military', 0)
            ->condition('field_military', NULL, 'IS NULL');
          $query->condition($orGroup);
        }

        $nids = $query->execute();

        $tweets = [];
        foreach ($node_storage->loadMultiple($nids) as $node) {
          $node_translation = $node->getTranslation($langcode);
          $uri = !$node_translation->get('field_image')
            ->isEmpty() ? $node_translation->get('field_image')->entity->getFileUri() : '';

          $wisdom = $this->getFromParagraphs($node_translation->get('field_references_to_text')
            ->referencedEntities(), [
              'title' => 'field_reference_heading',
              'details' => 'field_reference_body',
            ],
            $langcode);

          $content = $this->transformLangcode($node_translation->get('body')->value, $langcode);
          $short_codes_content = $this->parseShortCodes($content);
          $short_codes_content['content'] = $this->prepareLinkTweetContent($short_codes_content['content']);

          $tweet = [
            'id' => $node_translation->id(),
            'date' => date('Y-M-D\TH:i:s.u', $node_translation->get('created')->value),
            'number' => $this->getPartsFromTitle($node_translation->label())['number'],
            'img_url' => $uri ? file_create_url($uri) : '',
            'thumbnail' => $uri ? ImageStyle::load('tweet_related_teaser')
              ->buildUrl($uri) : '',
            'title' => $this->getPartsFromTitle($node_translation->label())['text'],
            'tweet_text' => $node_translation->get('field_tweetbox')->value,
            'is_daily_hidden' => FALSE,
            'church_father' => $this->getFieldFromParagraph($node_translation->get('field_references_to_church_fathe'), 'field_reference_body', $langcode),
            'pope_say' => $this->getFieldFromParagraph($node_translation->get('field_references_to_the_popes'), 'field_reference_body', $langcode),
            'wisdom' => $wisdom,
            'related' => $this->getSubjectRelated($node, $nids, $langcode),
          ];

          $tweet += $short_codes_content;

          $tweets[] = $tweet;
        }
        $output[] = [
          'name' => $translated_term->label(),
          'tweets' => $tweets,
        ];
      }

      \Drupal::cache()->set($cid, $output, (time() + JSON_DATA_CACHE_MAX_AGE), [
        'json_api:node:tweet_page',
        'json_api:texonomy_term:subject',
      ]);

    }

    $output_sort = $this->sortTweets($output);
    $version_key = 'json_version__tweet_page__' . $langcode;

    return [
      'Lang' => $this->prepareLangcode($langcode, TRUE),
      'military' => $military,
      'ver' => \Drupal::state()->get($version_key, '1.0'),
      'sections' => $output_sort,
    ];
  }

  /**
   * Get taxonomy term of value "field_code".
   *
   * @param string $code
   *   Language code.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Taxonomy term.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getCounryByCode($code) {
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $tids = $term_storage->getQuery()
      ->condition('vid', 'countries')
      ->condition('field_code', $code)
      ->execute();
    return $this->entityTypeManager->getStorage('taxonomy_term')->load(reset($tids));
  }

  /**
   * Replace langcode.
   *
   * @param string $langcode
   *   Old langcode.
   * @param bool $dispaly
   *   Conversion direction.
   *
   * @return string
   *   Langcode.
   */
  public function prepareLangcode($langcode, $dispaly = FALSE) {
    $lang_substitution = [
      'pt' => 'pt-pt',
      'sl' => 'sk',
    ];

    $lang_display = [
      'pt-pt' => 'pt',
      'sk' => 'sk',
    ];
    if (!$dispaly) {
      if (isset($lang_substitution[$langcode])) {
        return $lang_substitution[$langcode];
      }
    }
    else {
      if (isset($lang_display[$langcode])) {
        return $lang_display[$langcode];
      }
    }
    return $langcode;
  }

  /**
   * Replace language prefix from text.
   *
   * @param string $text
   *   Text for preparing.
   * @param string $langcode
   *   Langcode.
   *
   * @return string|string[]
   *   Return text.
   */
  public function transformLangcode($text, $langcode) {
    $langcode_new = $this->prepareLangcode($langcode, TRUE);
    if ($langcode_new == $langcode) {
      return $text;
    }
    $text = str_replace('/' . $langcode_new . '/', '/' . $langcode . '/', $text);
    return $text;
  }

  /**
   * Sorting categories as tweet weights.
   *
   * @param array $data
   *   Array with categories to sort.
   *
   * @return array
   *   Sorting categories.
   */
  public function sortTweets(array $data) {
    $sort_subject = [];
    foreach ($data as $index_subject => $subject) {
      $sort_subject[strval($index_subject)] = number_format($this->meanOfNumbersTweets($subject), 2);

      // Sorting tweets in subject.
      $sort_tweet = [];
      foreach ($subject['tweets'] as $index_tweets => $tweet) {
        $sort_tweet[strval($index_tweets)] = number_format($tweet['number'], 2);
      }
      asort($sort_tweet);
      $sort_tweet_data = [];
      foreach ($sort_tweet as $key => $value) {
        $sort_tweet_data[] = $subject['tweets'][$key];
      }
      $data[$index_subject]['tweets'] = $sort_tweet_data;
    }
    asort($sort_subject);

    // Sorting "Subject".
    $sort_data = [];
    foreach ($sort_subject as $key => $value) {
      $sort_data[] = $data[$key];
    }

    return $sort_data;
  }

  /**
   * Mean of numbers tweets.
   *
   * @param array $tweets
   *   Array of tweets.
   *
   * @return float
   *   Mean of numbers tweets.
   */
  public function meanOfNumbersTweets(array $tweets) {
    $summ = 0;
    foreach ($tweets['tweets'] as $tweet) {
      $summ += $this->weightTweet($tweet['number']);
    }
    return round($summ / count($tweets['tweets']), 2);
  }

  /**
   * Transform tweet number to weight.
   *
   * @param string $number
   *   Tweet number - string from title.
   *
   * @return string
   *   Number tweet.
   */
  public function weightTweet($number) {
    if (empty($number) || !is_numeric($number)) {
      return 0;
    }
    $parts = explode('.', $number);
    if (!isset($parts[1])) {
      $parts[1] = '0';
    }
    return $parts[0] . '.' . (strlen($parts[1]) < 2 ? str_pad($parts[1], 2, '0', STR_PAD_LEFT) : $parts[1]);
  }

  /**
   * Prepare HTML content.
   *
   * @param string $source_text
   *   Clean HTML content.
   *
   * @return string
   *   Content.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function prepareLinkTweetContent($source_text) {
    $html = new DOMDocument();

    $html->loadHTML('<?xml encoding="UTF-8">' . $source_text);

    $link_to_delete = [];

    /** @var \DOMElement $link */
    foreach ($html->getElementsByTagName('a') as $link) {
      $old_link = $link->getAttribute("href");

      if ($node = $this->getNodeFromHref($old_link)) {
        $title = $node->label();
        $number = $this->getPartsFromTitle($title)['number'];
        if ($number) {
          $link->setAttribute('href', $number);
        }
        else {
          $link_to_delete[] = $link;
        }
      }
    }

    for($i = count($link_to_delete); $i > 0; $i--) {
      $link_to_delete[$i-1]->parentNode->removeChild($link_to_delete[$i-1]);
    }

    $html->saveHtml();
    $elements = $html->getElementsByTagName('body');

    $out = htmlspecialchars_decode($this->getInnerHtml($elements->item(0)));
    $out = strip_tags($out, '<a>');
    return $out;
  }

  /**
   * Get Entity node fron uri.
   *
   * @param string $alias
   *   Uri.
   *
   * @return bool|\Drupal\Core\Entity\EntityInterface|null
   *   Entity Node.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getNodeFromHref($alias) {
    $languages = \Drupal::languageManager()->getLanguages();
    $langcodes = array_keys($languages);
    $keys = explode('/', $alias);
    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    if (isset($keys[1]) && [$keys[1], $langcodes]) {
      $count = 1;
      $langcode = $keys[1];
      $alias = str_replace('/' . $keys[1] . '/', '/', $alias, $count);
    }
    else {
      return FALSE;
    }

    if (!$node = $this->getNodeByUri($alias, $langcode)) {
      if (!$node = $this->getNodeByAlias($alias, $langcode)) {
        $node = $this->getNodeByRedirect($alias);
      }
    }

    return $node;
  }

  /**
   * Get destination node by redirect path.
   *
   * @param string $alias
   *   Path alias.
   *
   * @return bool|\Drupal\Core\Entity\EntityInterface|null
   *   Entity node.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getNodeByRedirect($alias) {
    $alias = substr($alias, 1);
    $redirects = \Drupal::service('redirect.repository')->findBySourcePath($alias);
    if (!$redirects) {
      return FALSE;
    }

    $redirect = array_shift($redirects);
    $path = $redirect->getRedirectUrl()->toString();

    preg_match('/node\/(\d+)/', $path, $matches);
    if (!isset($matches[1])) {
      return FALSE;
    }
    $nids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('nid', $matches[1])
      ->execute();
    if ($nids) {
      return $this->entityTypeManager->getStorage('node')
        ->load(array_shift($nids));
    }
    else {
      $connection = Database::getConnection();
      $data = $connection->select('migrate_map_d7_node_tweet_page__translation', 'm')
        ->fields('m', ['destid1', 'destid2'])
        ->condition('m.sourceid1', $matches[1])
        ->execute();

      $result = $data->fetchAll(\PDO::FETCH_OBJ);
      if ($result) {
        /** @var \Drupal\node\Entity\Node $node */
        return $this->entityTypeManager->getStorage('node')
          ->load($result[0]->destid1)
          ->getTranslation($result[0]->destid2);
      }
    }
    return FALSE;
  }

  /**
   * Get Entity node by alias.
   *
   * @param string $alias
   *   Path alias.
   * @param string $langcode
   *   Langcode.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   An entity object. NULL if no matching entity is found.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getNodeByAlias($alias, $langcode) {
    /** @var \Drupal\path_alias\AliasManager $alias_manager */
    $alias_manager = \Drupal::service('path.alias_manager');
    $path = $alias_manager->getPathByAlias($alias, $langcode);
    return $this->getNodeByUri($path, $langcode);
  }

  /**
   * Get node by URI.
   *
   * @param string $uri
   *   Uri.
   * @param string $langcode
   *   Langcode.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   An entity object. NULL if no matching entity is found.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getNodeByUri($uri, $langcode = '') {
    if (!\Drupal::service('path.validator')->isValid($uri)) {
      return NULL;
    }

    try {
      $params = Url::fromUri("internal:" . $uri)->getRouteParameters();
    }
    catch (\Exception $e) {
      return NULL;
    }

    $params = Url::fromUri("internal:" . $uri)->getRouteParameters();
    if (!isset($params['node'])) {
      return NULL;
    }
    $entity_type = key($params);
    $entity = $this->entityTypeManager->getStorage('node')->load($params[$entity_type]);
    if ($langcode) {
      return $entity->getTranslation($langcode);
    }
    return $entity;
  }

  /**
   * Inner HTML From DomElement.
   *
   * @param \DOMElement $element
   *   Dom element.
   *
   * @return string
   *   Inner HTML srting.
   */
  public function getInnerHtml(\DOMElement $element) {
    $doc = $element->ownerDocument;
    $doc->encoding = 'UTF-8';
    $html = '';
    foreach ($element->childNodes as $node) {
      $html .= $doc->saveHTML($node);
    }
    return $html;
  }

  /**
   * Outer HTML From DomElement.
   *
   * @param \DOMElement $element
   *   Dom element.
   *
   * @return string
   *   Outer HTML srting.
   */
  public function getOuterHtml(\DOMElement $element) {
    $doc = new DOMDocument();
    $doc->encoding = 'UTF-8';
    $doc->appendChild($doc->importNode($element, TRUE));
    return $doc->saveHTML($doc->documentElement);
  }

  /**
   * Parsing shortcodes.
   *
   * @param string $content
   *   Content.
   *
   * @return array
   *   Data array.
   */
  public function parseShortCodes($content) {
    // QTip shortcodes.
    $data = $this->parseShortCodesQtip($content);
    $return = $data;

    // Youtube link.
    $data = $this->parseYoutube($return['content']);
    $return += $data;

    return $return;
  }

  /**
   * Parse youtube link.
   *
   * @param string $content
   *   Content.
   *
   * @return array
   *   Data array.
   */
  public function parseYoutube($content) {
    $html = new DOMDocument();
    $html->loadHTML('<?xml encoding="UTF-8">' . $content);

    $iframes = $html->getElementsByTagName('iframe');
    if (!$iframes->length) {
      return ['content' => $content];
    }

    $video = [];
    foreach ($iframes as $iframe) {
      $link = $iframe->getAttribute("src");
      if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/\s]{11})%i', $link, $match)) {
        $video[] = [
          'link' => $link,
          'image' => 'http://img.youtube.com/vi/' . $match[1] . '/sddefault.jpg',
        ];
        $iframe->parentNode->removeChild($iframe);
        $html->saveHtml();
      }
    }

    $elements = $html->getElementsByTagName('body');
    $content = ($this->getInnerHtml($elements->item(0)));

    $data = [
      'content' => $content,
      'video' => $video,
    ];
    return $data;
  }

  /**
   * Parse shortcode "qtip".
   *
   * @param string $content
   *   Content.
   *
   * @return array
   *   Data array.
   */
  public function parseShortCodesQtip($content, $jq_ui_tooltip = FALSE) {
    preg_match_all("/\[qtip:(.*?)\]/", $content, $matches);
    $tips = [];
    if (empty($matches[0])) {
      return [
        'content' => $content,
      ];
    }

    foreach ($matches[0] as $index => $short_cod) {
      $qtip_parts = explode('|', $matches[1][$index]);
      if (count($qtip_parts) > 1) {
        if ($jq_ui_tooltip) {
          $link = '<a href="#" title="' . $qtip_parts[1] . '" onclick="return false">' . $qtip_parts[0] . '</a>';
        }
        else {
          $link = '<a href="t' . $index . '"><b>' . $qtip_parts[0] . '</b></a>';
        }
        $content = str_replace($matches[0][$index], $link, $content);
        $tips[] = $qtip_parts[1];
      }
    }

    return [
      'content' => $content,
      'tip' => $tips,
    ];
  }

  public function aitGcmGetNodes($today = NULL) {
    if (!$today) {
      $today = date('Y-m-d');
    }

    $query = $this->nodeStorage->getQuery();
    $nids = $query->condition('type', 'push_message')
      ->condition('status', 1)
      ->condition('field_date', $today . '%', 'LIKE')
      ->execute();

    $nodes = [];
    foreach ($this->nodeStorage->loadMultiple($nids) as $node) {
      if (!(!$node->get('field_sent')->isEmpty() && $node->get('field_sent')->value == 1)) {
        $nodes[] = $node;
      }
    }

    return $nodes;
  }

  public function aitGcmSendNotificationNode(Node $node) {
    $nid = $node->id();
    $title = $node->getTitle();
    $message = !$node->get('body')->isEmpty() ? $node->get('body')->value : '';
    $lang = $node->get('field_language')->value;
    $state = $node->get('field_state')->value;

    $channel = '/topics/' . $lang;
    $params = [];

    switch ($state) {
      case 'prayers':
        $params = [
          'kind' => $node->get('field_prayertype')->value,
          'page' => $node->get('field_prayerpage')->value,
        ];
        break;

      case 'iframe':
        $params = [
          'url' => $node->get('field_link')->url->value()
        ];
        break;

      case 'page':
        /** @var \Drupal\node\Entity\Node $pagenode */
        $pagenode = $node->get('field_page')->entity;
        $params = [
          'key' => $pagenode->alias
        ];
        break;
    }

    $data = [
      'state' => [
      'name' => $state,
        'params' => $params
      ]
    ];

    // Set status of the node as 'sent'
    $node->set('field_sent', 1);
    $node->save();

    // Send the actual message using the GCM
    return $this->aitGcmSendMessage($title, $message, $data, $channel);
  }

  public function aitGcmSendMessage($title, $message, $data, $channel = 0) {
    if (!$channel) {
      \Drupal::logger('push')->error('Failed to send push message because no channel was selected (%title)',
        [
          '%title' => $title,
        ]);
      return FALSE;
    }

    $config = \Drupal::configFactory()->getEditable('mxt_core.ait_google_cloud_message');

    $logUsingMail = (bool) $config->get('ait_gcm_log_mail');
    $logMailAddress = $config->get('ait_gcm_log_address');

    $fields = [];
    $fields['priority'] = 'high';
    $fields['collapse_key'] = 'TweetingWithGod';

    // Android pushing
    $fields['data'] = $data;
    $fields['data']['title'] = $title;
    $fields['data']['message'] = $message;
    $fields['data']['content-available'] = 1;
    $fields['data']['icon'] = 'pushicon';
    $fields['data']['iconColor'] = '#31b2ea';
    $fields['data']['image'] = 'www/assets/images/icon-push.png';
    $fields['data']['vibrationPattern'] = [50, 150, 50, 150, 50, 200];
    $fields['data']['ledColor'] = [0, 49, 178, 234];
    $sub_channel = str_replace('/topics/', '/topics/android-', $channel);
    $fields['to'] = $sub_channel;

    $androidResult = $this->aitGcmSendMessageCore($fields);

    $logInfo = [
      'channel' => $sub_channel,
      'title' => $title,
      'message' => $message,
      'platform' => 'android'
    ];
    if ($androidResult !== FALSE) {
      \Drupal::messenger()->addStatus('Push message successfully sent to ' . $sub_channel . ' (' . $title . ')');
      \Drupal::logger('push')->error('Push message sent to %channel titled "%title" with message: %msg', [
        '%id' => $androidResult->message_id,
        '%channel' => $sub_channel,
        '%msg' => $message,
        '%title' => $title
      ]);

      if ($logUsingMail) {
        $logInfo['message_id'] = $androidResult->message_id;
        \Drupal::service('plugin.manager.mail')->mail('ait_gcm', 'success', $logMailAddress, 'nl', $logInfo, NULL, TRUE);
      }
    }
    else {
      \Drupal::messenger()->addError(t('Push message FAILED to sent to ' . $sub_channel . ' (' . $title . ')'));
      \Drupal::logger('push')->error('Failed to send push message to %channel titled "%title" with message: %msg', [
        '%channel' => $sub_channel,
        '%msg' => $message,
        '%title' => $title
      ]);
      if ($logUsingMail) {
        \Drupal::service('plugin.manager.mail')->mail('ait_gcm', 'failed', $logMailAddress, 'nl', $logInfo, NULL, TRUE);
      }
    }

    // iOS pushing
    $sub_channel = str_replace('/topics/', '/topics/ios-', $channel);
    $fields['to'] = $sub_channel;
    $fields['notification'] = [
      'title' => $title,
      'body' => $message,
      'sound' => 'default',
      'badge' => '1'
    ];
    $fields['data'] = $data;
    $fields['data']['content-available'] = 1;

    $iosResult = $this->aitGcmSendMessageCore($fields);

    $logInfo = [
      'channel' => $sub_channel,
      'title' => $title,
      'message' => $message,
      'platform' => 'ios'
    ];
    if ($iosResult !== FALSE) {
      \Drupal::messenger()->addStatus('Push message successfully sent to ' . $sub_channel . ' (' . $title . ')');
      \Drupal::logger('push')->error('Push message sent to %channel (ID: %id) titled "%title" with message: %msg', [
        '%id' => $androidResult->message_id,
        '%channel' => $sub_channel,
        '%msg' => $message,
        '%title' => $title
      ]);

      $logInfo['message_id'] = $iosResult->message_id;

      if ($logUsingMail) {
        \Drupal::service('plugin.manager.mail')->mail('ait_gcm', 'success', $logMailAddress, 'nl', $logInfo, NULL, TRUE);
      }
    }
    else {
      \Drupal::messenger()->addError(t('Push message FAILED to sent to ' . $sub_channel . ' (' . $title . ')'));
      \Drupal::logger('push')->error('Failed to send push message to %channel titled "%title" with message: %msg', [
        '%channel' => $sub_channel,
        '%msg' => $message,
        '%title' => $title
      ]);
      if ($logUsingMail) {
        \Drupal::service('plugin.manager.mail')->mail('ait_gcm', 'failed', $logMailAddress, 'nl', $logInfo, NULL, TRUE);
      }
    }

    return $androidResult && $iosResult;
  }

  public function aitGcmSendMessageCore($fields) {
    $config = \Drupal::configFactory()->getEditable('mxt_core.ait_google_cloud_message');

    $headers = [
      'Authorization: key=' . $config->get('ait_gcm_api_key'),
      'Content-Type: application/json'
    ];

    $ch = curl_init();
//    curl_setopt($ch, CURLOPT_URL, 'https://gcm-http.googleapis.com/gcm/send');
    curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    $result = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($statusCode == 200) {
      return json_decode($result);
    }

    return FALSE;
  }

}
