<?php

namespace Drupal\mxt_core\Form;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CopyTranslationForm.
 */
class CopyTranslationForm extends FormBase {

  /**
   * Batch Builder.
   *
   * @var \Drupal\Core\Batch\BatchBuilder
   */
  protected $batchBuilder;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->batchBuilder = new BatchBuilder();
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'copy_translation_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $content_options = [
      'page' => 'Basic Page',
      'tweet_page' => 'Tweet Page',
    ];
    $form['content_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Content Type'),
      '#options' => $content_options,
      '#size' => 1,
      '#weight' => '0',
      '#required' => TRUE,
    ];

    $languages = \Drupal::languageManager()->getLanguages();
    $language_options = [];
    foreach ($languages as $langcode => $language) {
      $language_options[$langcode] = $language->getName();
    }

    $form['source_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Source language'),
      '#options' => $language_options,
      '#size' => 1,
      '#weight' => '1',
      '#required' => TRUE,
    ];

    $form['destination_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Destination language'),
      '#options' => $language_options,
      '#size' => 1,
      '#weight' => '2',
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#weight' => '99',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $content_type = $form_state->getValue('content_type');
    $source_language = $form_state->getValue('source_language');
    $destination_language = $form_state->getValue('destination_language');

    $nids = $this->entityTypeManager->getStorage('node')
      ->getQuery()
      ->condition('type', $content_type)
      ->condition('langcode', $source_language)
      ->condition('status', 1)
      ->sort('nid', 'ASC')
      ->execute();

    $this->batchBuilder->addOperation([$this, 'processItems'], [
      $nids,
      $source_language,
      $destination_language,
    ]);
    $this->batchBuilder->setFinishCallback([$this, 'finished']);
    batch_set($this->batchBuilder->toArray());
  }

  /**
   * Processor for batch operations.
   *
   * @param int $items
   *   Array entity ID.
   * @param string $source_language
   *   Langcode.
   * @param string $destination_language
   *   Langcode.
   * @param array $context
   *   Array context batch.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function processItems($items, $source_language, $destination_language, array &$context) {
    $limit = 1;

    if (empty($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = count($items);
    }

    if (empty($context['sandbox']['items'])) {
      $context['sandbox']['items'] = $items;
    }

    $counter = 0;
    if (!empty($context['sandbox']['items'])) {
      // Remove already processed items.
      if ($context['sandbox']['progress'] != 0) {
        array_splice($context['sandbox']['items'], 0, $limit);
      }

      foreach ($context['sandbox']['items'] as $item) {
        if ($counter != $limit) {
          $this->processItem($item, 'node', $source_language, $destination_language);

          $counter++;
          $context['sandbox']['progress']++;

          $context['message'] = $this->t('Now processing node :progress of :count', [
            ':progress' => $context['sandbox']['progress'],
            ':count' => $context['sandbox']['max'],
          ]);

          $context['results']['processed'] = $context['sandbox']['progress'];
        }
      }
    }

    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
  }

  /**
   * Finished callback for batch.
   */
  public function finished($success, $results, $operations) {
    $message = $this->t('Number of nodes affected by batch: @count', [
      '@count' => $results['processed'],
    ]);

    $this->messenger()
      ->addStatus($message);
  }

  /**
   * Process single item.
   *
   * @param int|string $id
   *   An id of Node.
   */

  /**
   * Process single item.
   *
   * @param int $id
   *   Entity ID.
   * @param string $entity_type
   *   Entity type.
   * @param string $source_language
   *   Langcode.
   * @param string $destination_language
   *   Langcode.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function processItem($id, $entity_type, $source_language, $destination_language) {
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($id);
    if (!$entity->hasTranslation($source_language)) {
      return;
    }
    $entity_source = $entity->getTranslation($source_language);
    if ($entity->hasTranslation($destination_language)) {
      $entity_dest = $entity->getTranslation($destination_language);
    }
    else {
      $entity_dest = $entity->addTranslation($destination_language);
    }

    $fields = $entity->getFields();
    foreach ($fields as $field_name => $field) {
      $is_translatable = $field->getFieldDefinition()->isTranslatable();
      $fix_name = in_array($field_name, ['title', 'body', 'name']);
      $custom_name = (strpos($field_name, 'field_') !== FALSE);

      if (!($fix_name || $custom_name)) {
        continue;
      }

      if (!$is_translatable && !in_array($field->getFieldDefinition()->getType(), ['entity_reference_revisions'])) {
        continue;
      }

      if (!$is_translatable && in_array($field->getFieldDefinition()->getType(), ['entity_reference_revisions'])) {
        $referensed_entities = $entity_source->get($field_name)->referencedEntities();
        foreach ($referensed_entities as $referensed_entity) {
          $this->processItem($referensed_entity->id(), $referensed_entity->getEntityTypeId(), $source_language, $destination_language);
        }
      }

      $value = $entity_source->get($field_name)->getValue();
      $entity_dest->set($field_name, $value);
    }
    $entity_dest->save();
  }

}
