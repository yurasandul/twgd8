<?php

namespace Drupal\mxt_core\Plugin\rest\resource;

use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "api_twg_dailyprayer",
 *   label = @Translation("Api twg dailyprayer"),
 *   uri_paths = {
 *     "canonical" = "/api/twg/v1/dailyprayer/{langcode}"
 *   }
 * )
 */
class ApiTwgDailyprayer extends ResourceBase {

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
   *   Langcode from URL.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function get($langcode) {
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    $output = $this->dailyPrayer($langcode);
    return new ModifiedResourceResponse($output, 200);
  }

  /**
   * Get data for response.
   *
   * @param string $langcode
   *   Langcode.
   *
   * @return array
   *   Response array.
   */
  private function dailyPrayer($langcode) {
    $time = time();
    $lang = strtoupper($langcode);

    $convertlangs = [
      'EN' => 'AM',
    ];
    foreach ($convertlangs as $from => $to) {
      if ($lang == $from) {
        $lang = $to;
      }
    }

    $true_languages = [
      'AM',
      'AR',
      'DE',
      'FR',
      'GR',
      'IT',
      'MG',
      'NL',
      'PL',
      'PT',
      'SP',
      'TR',
    ];

    if (!in_array($lang, $true_languages)) {
      $langcode = 'en';
      $lang = 'AM';
    }

    $start_date = date('Y-m-d', time());
    $dates = [];
    for ($i = 0; $i <= 6; $i++) {
      $get_time = strtotime($start_date . ' +' . $i . ' day');
      $dates[$get_time] = date("Ymd", $get_time);
    }

    $output = [];
    foreach ($dates as $time => $date) {
      $cid_day = basename(__FILE__, '.module') . ':' . 'json__dailyprayer__day_' . $date . '__' . $langcode;
      if ($cache = \Drupal::cache()->get($cid_day)) {
        $output_day = $cache->data;
      }
      else {
        $url = 'http://feed.evangelizo.org/v2/reader.php?date=' . $date . '&lang=' . $lang;
        $parts = [
          'liturgic' => 'content=FR&type=liturgic_t',
          'fr_title' => 'content=FR&type=reading_lt',
          'fr_text' => 'content=FR&type=reading',
          'ps_title' => 'content=PS&type=reading_lt',
          'ps_text' => 'content=PS&type=reading',
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
          $texts[$key] = $part;
        }

        $output_day = [
          'date' => \Drupal::service('date.formatter')
            ->format($time, 'custom', 'Y-m-d'),
          'church_date' => $texts['liturgic'] ? $texts['liturgic'] : '',
          'readings' => [
            [
              'type' => 'act',
              'title' => $texts['fr_title'] ? strip_tags($texts['fr_title']) : '',
              'detail' => $texts['fr_text'] ? strip_tags($texts['fr_text']) : '',
            ],
            [
              'type' => 'psalm',
              'title' => $texts['ps_title'] ? strip_tags($texts['ps_title']) : '',
              'detail' => $texts['ps_text'] ? strip_tags($texts['ps_text']) : '',
            ],
            [
              'type' => 'gospel',
              'title' => $texts['gsp_title'] ? strip_tags($texts['gsp_title']) : '',
              'detail' => $texts['gsp_text'] ? strip_tags($texts['gsp_text']) : '',
            ],
          ],
        ];

        if (!array_search(FALSE, $texts)) {
          \Drupal::cache()->set($cid_day, $output_day, (time() + 86400));
        }
      }

      $output[] = $output_day;
    }

    $data = [
      'lang' => $langcode,
      'daily_prayers' => $output,
    ];
    return $data;
  }

}
