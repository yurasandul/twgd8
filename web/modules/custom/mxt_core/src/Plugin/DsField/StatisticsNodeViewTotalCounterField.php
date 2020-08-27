<?php

namespace Drupal\mxt_core\Plugin\DsField;

use Drupal\ds\Plugin\DsField\DsFieldBase;

/**
 * Test field plugin.
 *
 * @DsField(
 *   id = "statistic_node_view_total",
 *   title = @Translation("Node View Total Counter"),
 *   entity_type = "node",
 *   ui_limit = {"*|*"}
 * )
 */
class StatisticsNodeViewTotalCounterField extends DsFieldBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    /** @var \Drupal\statistics\StatisticsViewsResult $statistics */
    $statistics = \Drupal::service('statistics.storage.node')->fetchView($this->entity()->id());
    return ['#markup' => $statistics ? $statistics->getTotalCount() : 0 ];
  }

}
