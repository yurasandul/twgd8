<?php

namespace Drupal\mxt_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'FrontPageTopBlocks' block.
 *
 * @Block(
 *  id = "front_page_top_blocks_1",
 *  admin_label = @Translation("Front page top blocks 1"),
 * )
 */
class FrontPageTopBlocks1 extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    if (!($node = \Drupal::routeMatch()->getParameter('node')) || $node->bundle() != 'frontpage') {
      return [];
    }
    $blocks = $node->get('field_blocks')->referencedEntities();
    if (isset($blocks[0])) {
      $build =  \Drupal::entityTypeManager()
        ->getViewBuilder('block_content')
        ->view($blocks[0]);
    }
    else {
      $build = [];
    }
    return $build;
  }

}
