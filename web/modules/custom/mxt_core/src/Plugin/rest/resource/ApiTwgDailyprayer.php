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
      'ES' => 'SP',
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
          $texts[$key] = $this->fixText($part, $langcode);
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
        if (!empty(strip_tags($texts['sr_text'])) && strip_tags($texts['sr_text']) != "\r" . PHP_EOL) {
          $act_seccond = [
            'type' => 'act_second',
            'title' => $texts['sr_title'] ? strip_tags($texts['sr_title']) : '',
            'detail' => $texts['sr_text'] ? strip_tags($texts['sr_text']) : '',
          ];
          array_splice($output_day['readings'], 2, 0, [$act_seccond]);
        }

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

  private function fixText($text, $langcode) {
    $text_to_clean = [
      'de' => [
        'Auszug aus der liturgischen Übersetzung der Bibel<br />Um jeden Morgen das Evangelium per E-Mail zu bekommen melden Sie sich an:<a href="http://evangeliumtagfuertag.org" target="_blank">evangeliumtagfuertag.org</a>',
      ],
      'fr' => [
        'Extrait de la Traduction Liturgique de la Bible - © AELF, Paris<br />- Service offert par l\'Evangile au Quotidien -',
        'Pour recevoir tous les matins l\'Évangile par courriel, <a href="http://levangileauquotidien.org" target="_blank">levangileauquotidien.org</a>',
      ],
      'it' => [
        'Traduzione liturgica della Bibbia<br />Per ricevere il Vangelo ogni mattina per e-mail, iscrivetevi : <a href="http://vangelodelgiorno.org" target="_blank">vangelodelgiorno.org</a>',
      ],
      'nl' => [
        'Bron : Petrus Canisius bijbelvertaling & vernieuwingen<br />Om de bijbellezingen iedere morgen in Uw mailbox te ontvangen, kunt u zich hier inschrijven : <a href="http://dagelijksevangelie.org" target="_blank">dagelijksevangelie.org</a>',
      ],
      'pl' => [
        'Fragment liturgicznego tłumaczenia Biblii Tysiąclecia, &copy; Wydawnictwo Pallottinum<br />By codziennie mogli Państwo otrzymywać tekst Ewangelii, prosimy się zarejestrować :  <a href="http://ewangelia.org" target="_blank">ewangelia.org</a>',
      ],
      'pt' => [
        'Da Bíblia Sagrada - Edição dos Franciscanos Capuchinhos - www.capuchinhos.org<br />Para receber todas as manhã o Evangelho por correio electrónico, inscreva-se:<a href="http://evangelhoquotidiano.org" target="_blank">evangelhoquotidiano.org</a>',
      ],
      'es' => [
        'Extraído de la Biblia: Libro del Pueblo de Dios.<br />Para recibir cada mañana el Evangelio por correo electrónico, registrarse: <a href="http://evangeliodeldia.org" target="_blank">evangeliodeldia.org</a>',
      ],

    ];
    if (!isset($text_to_clean[$langcode])) {
      return $text;
    }
    foreach ($text_to_clean[$langcode] as $find_str) {
      $text = str_replace(stripcslashes($find_str),'', $text);
    }
    return $text;
  }

}
