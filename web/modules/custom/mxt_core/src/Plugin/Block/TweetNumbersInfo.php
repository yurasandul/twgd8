<?php

namespace Drupal\mxt_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;

/**
 * Provides a 'TweetNumbersInfo' block.
 *
 * @Block(
 *  id = "tweet_numbers_info",
 *  admin_label = @Translation("Tweet numbers info"),
 * )
 */
class TweetNumbersInfo extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $language_id = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $tweet_nunber = \Drupal::service('entity_type.manager')
      ->getStorage('node')
      ->getQuery()
      ->condition('type', 'tweet_page' )
      ->condition('langcode', $language_id)
      ->condition('status', 1)
      ->count()
      ->execute();

    return [
      '#type' => 'link',
      '#title' =>$this->t('All @count Tweets >', ['@count' => $tweet_nunber]),
      '#url' => Url::fromRoute('view.twg_all_tweets.page_1'),
    ];
  }

}
