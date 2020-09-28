<?php

namespace Drupal\mxt_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'TweetPagerBlock' block.
 *
 * @Block(
 *  id = "tweet_pager_block",
 *  admin_label = @Translation("Tweet pager block"),
 * )
 */
class TweetPagerBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\mxt_core\TwgApiHelper definition.
   *
   * @var \Drupal\mxt_core\TwgApiHelper
   */
  protected $mxtCoreTwgApiHelper;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->mxtCoreTwgApiHelper = $container->get('mxt_core.twg_api_helper');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    /** @var \Drupal\node\Entity\Node $current_node */
    if (!$current_node = \Drupal::routeMatch()->getParameter('node')) {
      return [];
    }

    $current_state = node_revision_load($current_node->getRevisionId())->get('moderation_state')->getValue()[0]['value'];
    if ($current_state != 'published') {
      \Drupal::messenger()->addStatus('This is unpublished revision.');
      return [];
    }

    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $node_storage = $this->entityTypeManager->getStorage('node');

    $nids = $node_storage->getQuery()
      ->condition('type', 'tweet_page')
      ->condition('status', 1)
      ->condition('langcode', $langcode)
      ->execute();

    $nodes = [];
    foreach ($node_storage->loadMultiple($nids) as $node) {
      if ($node->hasTranslation($langcode)) {
        $translate = $node->getTranslation($langcode);
        $nodes[$translate->id()] = $this->mxtCoreTwgApiHelper->getPartsFromTitle($translate->label())['number'];
      }
    }

    asort($nodes);
    $pager_ids = $this->getPrevNext($nodes, $current_node->id());

    $content = [];
    foreach ($pager_ids as $index => $id) {
      $node = $node_storage->load($id);
      /** @var \Drupal\node\Entity\Node $translate */
      $translate = $node->getTranslation($langcode);
      $content[$index] = [
        'url' => Link::fromTextAndUrl($index, $translate->toUrl()),
        'title' => $translate->label(),
      ];
    }
    $content['all'] = Link::createFromRoute($this->t('All Tweets'),'view.twg_all_tweets.page_1');

    $build = [];
    $build['#theme'] = 'tweet_pager_block';
    $build['#content'] = $content;
    $build['#cache']['max-age'] = 0;
    return $build;
  }

  protected function getPrevNext($array, $key)
  {
    $keys = array_keys($array); //every element of aKeys is obviously unique
    $indices = array_flip($keys); //so array can be flipped without risk
    $i = $indices[$key]; //index of key in aKeys

    if ($i > 0) {
      $prev_id = $keys[$i - 1]; //use previous key in aArray
    }
    else {
      $prev_id = end($keys);
    }

    if ($i < count($keys)-1) {
      $next_id = $keys[$i+1];
    }
    else {
      $next_id = reset($keys);
    }

    return [
      'prev' => $prev_id,
      'next' => $next_id
    ];
  }

}
