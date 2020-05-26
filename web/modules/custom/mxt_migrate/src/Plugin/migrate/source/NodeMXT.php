<?php

namespace Drupal\mxt_migrate\Plugin\migrate\source;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\d7\FieldableEntity;
use Drupal\node\Plugin\migrate\source\d7\Node as NodeD7;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'NodeMXT' migrate source.
 *
 * @MigrateSource(
 *  id = "d7_node_mxt",
 *  source_module = "node"
 * )
 */
class NodeMXT extends NodeD7 {

  public function query() {
    // Select node in its last revision.
    $query = $this->select('node_revision', 'nr')
      ->fields('n', [
        'nid',
        'type',
        'language',
        'status',
        'created',
        'changed',
        'comment',
        'promote',
        'sticky',
        'tnid',
        'translate',
      ])
      ->fields('nr', [
        'vid',
        'title',
        'log',
        'timestamp',
      ]);
    $query->addField('n', 'uid', 'node_uid');
    $query->addField('nr', 'uid', 'revision_uid');
    $query->innerJoin('node', 'n', static::JOIN);

    // If the content_translation module is enabled, get the source langcode
    // to fill the content_translation_source field.
    if ($this->moduleHandler->moduleExists('content_translation')) {
      $query->leftJoin('node', 'nt', 'n.tnid = nt.nid');
      $query->addField('nt', 'language', 'source_langcode');
    }
    $this->handleTranslations($query);

    if (isset($this->configuration['node_type'])) {
      $query->condition('n.type', $this->configuration['node_type']);
    }

    return $query;
  }


  /**
   * Retrieves field values for a single field of a single entity.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $field
   *   The field name.
   * @param int $entity_id
   *   The entity ID.
   * @param int|null $revision_id
   *   (optional) The entity revision ID.
   * @param string $language
   *   (optional) The field language.
   *
   * @return array
   *   The raw field values, keyed by delta.
   */
  protected function getFieldValues($entity_type, $field, $entity_id, $revision_id = NULL, $language = NULL) {
    if (isset($this->configuration['no_revision']) && $this->configuration['no_revision']) {
      $table = 'field_data_' . $field;
    }
    else {
      $table = (isset($revision_id) ? 'field_revision_' : 'field_data_') . $field;
    }
    $query = $this->select($table, 't')
      ->fields('t')
      ->condition('entity_type', $entity_type)
      ->condition('entity_id', $entity_id)
      ->condition('deleted', 0);
    if (isset($revision_id)) {
      $query->condition('revision_id', $revision_id);
    }
    // Add 'language' as a query condition if it has been defined by Entity
    // Translation.
    if ($language) {
      $query->condition('language', $language);
    }
    $values = [];
    foreach ($query->execute() as $row) {
      foreach ($row as $key => $value) {
        $delta = $row['delta'];
        if (strpos($key, $field) === 0) {
          $column = substr($key, strlen($field) + 1);
          $values[$delta][$column] = $value;
        }
      }
    }

    if (in_array($field, $this->configuration["set_field_max_items"])) {
      $max_items = $this->getMaxItemInFieldCollection($entity_id, $field);
      $count_values = count($values);
      for ($i = $count_values; $i < $max_items; $i++) {
        $values[] = [
          'value' => '',
          'revision_id' => '',
          'index' => $i,
        ];
      }
    }

    return $values;
  }

  /**
   * Count mac number field collection.
   *
   * @param $nid
   * @param $field_name
   *
   * @return int|mixed
   */
  private function getMaxItemInFieldCollection($nid, $field_name) {
    $query = $this->select('node', 'n');
    $query->condition('n.nid', $nid);
    $query->fields('n', ['tnid']);
    $tnid = $query->execute()->fetchCol();

    if ($tnid == 0 ) {
      return 0;
    }

    $query = $this->select('node', 'n');
    $query->condition('n.tnid', $tnid);
    $query->innerJoin('field_data_' . $field_name, 'fd', 'fd.entity_id = n.nid ');
    $query->fields('n', ['language']);
    $query->addExpression('COUNT(n.language)', 'count');
    $query->groupBy('n.language');
    $results = $query->execute()->fetchAll();
    return max(array_column($results, 'count'));
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    // If the item is published, we should set the content moderation state to
    // active.
    if ($row->get('status') == 1) {
      $state = 'published';
    }
    else {
      $state = 'draft';
    }
    // Set the Moderation State on the source for processing.
    $row->setSourceProperty('moderation_state', $state);

    return parent::prepareRow($row);
  }

}
