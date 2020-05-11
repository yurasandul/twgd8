<?php

namespace Drupal\mxt_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Provides a 'FieldCollectionMXT' migrate process plugin.
 *
 * @MigrateProcessPlugin(
 *  id = "field_collection_mxt"
 * )
 */
class FieldCollectionMXT extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $language = $row->getDestinationProperty('langcode');
    if (isset($this->configuration["translations"])) {
      $list_values = $row->get($destination_property);
      $list_values = array_column($list_values, 'value');
      $index = array_search($value['value'], $list_values);;

      $node = Node::load($row->getDestinationProperty('nid'));
      $paragraphs = $node->get($destination_property)->referencedEntities();

      if (!isset($paragraphs[$index])) {
        $message = 'Not exist index: ' . $index;
        throw new MigrateSkipRowException($message);
      }

      /** @var \Drupal\paragraphs\Entity\Paragraph $source_paragraph */
      $source_paragraph = $paragraphs[$index];
      if ($source_paragraph->hasTranslation($language)) {
        $paragraph = $source_paragraph->getTranslation($language);
      }
      else {
        $paragraph = $source_paragraph->addTranslation($language, []);
      }
    }
    else {
      /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
      $paragraph = Paragraph::create(['type' => $this->configuration['paragraph_destination'],]);
      $paragraph->isNew();
      $paragraph->set('langcode', $language);
    }

    foreach ($this->configuration["field_map"] as $field_destination => $field_source) {
      $source_value = $this->getFieldValues($field_source, $value["value"], $value["revision_id"]);
      $paragraph->set($field_destination, $source_value);
    }
    $paragraph->save();

    $result = array(
      'target_id' => $paragraph->id(),
      'target_revision_id' => $paragraph->getRevisionId(),
    );
    return $result;
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
  protected function getFieldValues($source, $entity_id, $revision_id = NULL, $language = NULL) {
    if (empty($entity_id)) {
      return [NULL];
    }

    $field = is_array($source) ? $source['source'] : $source;

    $table = 'field_data_' . $field;
    /** @var \Drupal\Core\Database\Database $migrate_db */
    $migrate_db = \Drupal\Core\Database\Database::getConnection('default', 'migrate');
    $query = $migrate_db->select($table, 't')
      ->fields('t')
      ->condition('entity_type', 'field_collection_item')
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
        $delta = $row->delta;
        if (strpos($key, $field) === 0) {
          $column = substr($key, strlen($field) + 1);
          $values[$delta][$column] = $value;
        }
      }
    }
    \Drupal\Core\Database\Database::setActiveConnection();

    if (!empty($values) && is_array($source)) {
      $values = $this->processMXTPlugin($values, $source);
    }

    return $values;
  }

  /**
   * Custom field process
   *
   * @param $value
   * @param $source
   *
   * @return array
   */
  protected function processMXTPlugin($value, $source) {
    $return = [];
    switch ($source['mxt_plugin']) {
      case 'migration':
        $table = 'migrate_map_' . $source['migration'];
        $result = \Drupal::database()->select($table, 't')
          ->condition('t.sourceid1', $value[0][$source['mxt_source']])
          ->fields('t', ['destid1'])
          ->execute()->fetchAll();
        if ($result) {
          $return = [
            $source['target'] => $result[0]->destid1,
          ];
        }
        break;
      case 'mapping':
        $key = $source['key_map'];
        foreach ($value as &$row) {
          $row[$key] = $source["map"][$row[$key]];
        }
        $return = $value;
        break;
    }
    return $return;
  }
}
