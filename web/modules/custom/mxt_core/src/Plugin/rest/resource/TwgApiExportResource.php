<?php

namespace Drupal\mxt_core\Plugin\rest\resource;

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
 *   id = "get_twg_api_export_resource",
 *   label = @Translation("Get twg api lang resource"),
 *   uri_paths = {
 *     "canonical" = "/twg_api/export/{code}/{langcode}"
 *   }
 * )
 */
class TwgApiExportResource extends ResourceBase {

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
   * The currently active request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->logger = $container->get('logger.factory')->get('mxt_core');
    $instance->currentUser = $container->get('current_user');
    $instance->nodeStorage = $container->get('entity_type.manager')->getStorage('node');
    $instance->twgApiHelper = $container->get('mxt_core.twg_api_helper');
    $instance->currentRequest = $container->get('request_stack')->getCurrentRequest();
    return $instance;
  }

  /**
   * Responds to GET requests.
   *
   * @param string $code
   *   Code parameter from URL.
   * @param string $langcode
   *   Langcode.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   Array response.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function get($code, $langcode) {
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    if ($code == 'lang') {
      $output = $this->twgApiOutputNodes($langcode);
    }
    elseif (in_array($code, ['rites', 'order_of_mass', 'byzantine_rites'])) {
      $output = $this->twgApiHelper->twgApiGetRites($code, $langcode);
    }
    elseif ($code == 'reader') {
      $output = $this->twgApiOutputReader();
    }

    $output['version'] = 1;
    $hash = crc32(json_encode($output));
    // Add hash:
    $output['hash'] = $hash;

    array_walk_recursive($output, function (&$string, &$key) {
      $string = $this->twgApiHelper->cleanJsonString($string);
    });

    $response = (new ModifiedResourceResponse($output, 200));
    $response->headers->set('Etag', $hash);
    $response->headers->set('Content-Type', 'application/json');
    $response->headers->set('Content-Length', strlen(Json::encode($output)));
    return $response;
  }

  /**
   * Get data for response.
   */
  protected function twgApiOutputReader() {
    $date = $this->currentRequest->get('date');
    $lang = strtoupper($this->currentRequest->get('lang'));

    $convertlangs = [
      'EN' => 'AM',
    ];
    foreach ($convertlangs as $from => $to) {
      if ($lang == $from) {
        $lang = $to;
      }
    }

    if ($lang == 'CS' || $lang == 'CZ') {
      $url = 'http://www.misal.mobi/feed/?date=' . $date . '&lang=cs';
      echo $this->twgApiHelper->twgApiFetchDailyPrayer($url);
    }
    else {
      $url = 'http://feed.evangelizo.org/v2/reader.php?date=' . $date . '&type=all&lang=' . $lang;
      $parts = [
        'liturgic' => 'content=FR&type=liturgic_t',
        'title' => 'content=FR&type=reading_lt',
        'text' => 'content=FR&type=reading',
        'ps_title' => 'content=PS&type=reading_lt',
        'ps_text' => 'content=PS&type=reading',
        'sr_title' => 'content=SR&type=reading_lt',
        'sr_text' => 'content=SR&type=reading',
        'gsp_title' => 'content=GSP&type=reading_lt',
        'gsp_text' => 'content=GSP&type=reading',
      ];
      $texts = [];
      foreach ($parts as $key => $part) {
        $part = $this->twgApiHelper->twgApiFetchDailyPrayer($url . '&' . $part);

        // Filter copyright.
        $copyright_start = strpos($part, 'Copyright ©');
        if ($copyright_start !== FALSE) {
          $part = substr($part, 0, $copyright_start);
        }

        // Trim <br>.
        $part = preg_replace('/(<br \/>)+$/', '', $part);

        $texts[$key] = $part;
      }

      $text = '';
      $text .= '<h1>' . $texts['liturgic'] . '</h1>';
      $text .= '<h2>' . $texts['title'] . '</h2>';
      $text .= '<p>' . $texts['text'] . '</p>';
      $text .= '<h2>' . $texts['ps_title'] . '</h2>';
      $text .= '<p>' . $texts['ps_text'] . '</p>';
      $text .= '<h2>' . $texts['sr_title'] . '</h2>';
      $text .= '<p>' . $texts['sr_text'] . '</p>';
      $text .= '<h2>' . $texts['gsp_title'] . '</h2>';
      $text .= '<p>' . $texts['gsp_text'] . '</p>';

      echo $text;
    }
    exit;
  }

  /**
   * Get data for response.
   *
   * @param string $language
   *   Langcode.
   *
   * @return array|mixed
   *   Array to response.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function twgApiOutputNodes($language = '') {
    $output = [];
    if ($language) {
      /** @var \Drupal\Core\Entity\Query\Sql\Query $query */
      $query = $this->nodeStorage->getQuery()
        ->condition('type', 'app_config')
        ->condition('status', 1)
        ->condition('field_taal', $language)
        ->sort('created', 'ASC');
    }
    else {
      $query = $this->nodeStorage->getQuery()
        ->condition('type', 'app_config')
        ->condition('status', 1)
        ->sort('created', 'DESC')
        ->sort('field_taal', 'DESC');
    }
    $result = $query->execute();

    $nodes = [];

    foreach ($nodes = $this->nodeStorage->loadMultiple($result) as $node) {
      $tweets = [];
      $toptweets = [];
      $quotes = [];

      $lang = $node->get('field_taal')->first()->value;

      $tweetnodes = $this->twgApiHelper->twgApiGetTweets($lang);
      $tlang = $lang;
      if (count($tweetnodes) == 0) {
        $tweetnodes = $this->twgApiHelper->twgApiGetTweets('en');
        $tlang = 'en';
      }
      usort($tweetnodes, [$this, 'sortListByTitleNumber']);

      // Get tweets.
      foreach ($tweetnodes as $tweetnode) {
        $tweets[] = $this->twgApiHelper->twgProcessTweetNode($tweetnode, $tlang);
      }

      // Get quotes.
      foreach ($node->get('field_quote_slider')->referencedEntities() as $paragraph) {
        if (!$paragraph->get('field_image')->isEmpty() ||
          !$paragraph->get('field_slogan')->isEmpty() ||
          !$paragraph->get('field_slogan_source')->isEmpty() ||
          !$paragraph->get('field_quotes')->isEmpty()
        ) {
          $uri = !$paragraph->get('field_image')
            ->isEmpty() ? $paragraph->get('field_image')->entity->getFileUri() : '';
          $quotes[] = [
            'slogan' => $paragraph->get('field_slogan')->getString(),
            'source' => $paragraph->get('field_slogan_source')->getString(),
            'quotes' => (bool) $paragraph->get('field_quotes')->getString(),
            'image' => $uri ? ImageStyle::load('content_large')
              ->buildUrl($uri) : '',
          ];
        }
      }

      // Get Top Tweets.
      $results_top = $this->twgApiHelper->twgTranslateTopTweets($tlang);
      foreach ($this->nodeStorage->loadMultiple($results_top) as $top_node) {
        $toptweets[] = $this->twgApiHelper->twgProcessTweetNode($top_node, $tlang);
      }

      // Define pages.
      $pages = [
        [
          'key' => 'over-ons',
          'content' => $node->get('field_over_ons')->first()->value,
        ],
        [
          'key' => 'boeken',
          'content' => $node->get('field_de_boeken')->first()->value,
        ],
        [
          'key' => 'donaties',
          'content' => $node->get('field_donatie')->first()->value,
        ],
        [
          'key' => 'thisapp',
          'content' => $node->get('field_deze_app')->first()->value,
        ],
      ];

      // Define donation URL.
      $url = !$node->get('field_url_donate_button')->isEmpty() ? $node->get('field_url_donate_button')->first()->value : '';
      $output[$lang] = [
        'tweets' => $tweets,
        'toptweets' => $toptweets,
        'quotes' => $quotes,
        'pages' => $pages,
        'donate_url' => $url,
      ];

    }

    if ($language) {
      return $output[$language];
    }

    return $output;
  }

  /**
   * Helper sort function.
   *
   * @param string $a
   *   Str to compare.
   * @param string $b
   *   Str to compare.
   *
   * @return int
   *   Compate str value.
   */
  public function sortListByTitleNumber($a, $b) {
    $aTitle = $a->getTitle();
    $bTitle = $b->getTitle();
    return $this->jp2sortListByTitleNumber($aTitle, $bTitle);
  }

  /**
   * Comprasion title.
   *
   * @param string $a
   *   Str for compare.
   * @param string $b
   *   Str for compare.
   *
   * @return int
   *   Return compare value.
   */
  public function jp2sortListByTitleNumber($a, $b) {
    $a = $this->jp2convertTitleToSortable($a);
    $b = $this->jp2convertTitleToSortable($b);
    if ($a == $b) {
      return 0;
    }
    return ($a < $b) ? -1 : 1;
  }

  /**
   * Prepare title to sortable.
   *
   * @param string $title
   *   Title string for clean.
   *
   * @return array|mixed|string
   *   Clean title.
   */
  public function jp2convertTitleToSortable($title) {
    $title = explode(' ', $title);
    $title = $title[0];
    $parts = explode('.', $title);

    if (isset($parts[1])) {
      $sortable = $parts[0] . '.' . substr('00' . $parts[1], -3);
      return $sortable;
    }
    else {
      return $title;
    }
  }

  /**
   * Make parameters "lang" optionally.
   *
   * {@inheritdoc}
   */
  public function routes() {
    $collection = parent::routes();
    // Add defaults for optional parameters.
    $defaults = [
      'langcode' => '',
    ];
    foreach ($collection->all() as $route) {
      $route->addDefaults($defaults);
    }
    return $collection;
  }

}
