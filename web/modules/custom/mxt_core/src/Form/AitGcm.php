<?php

namespace Drupal\mxt_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;

/**
 * Class AitGcm.
 */
class AitGcm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ait_gcm';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\mxt_core\TwgApiHelper $twg_api_helper */
    $twg_api_helper = \Drupal::service('mxt_core.twg_api_helper');
    $today = date('Y-m-d');

    $nodes = $twg_api_helper->aitGcmGetNodes();

    $markup = 'Pushmessages set for today (' . $today . '):<br/>';

    if (count($nodes) > 0) {
      foreach ($nodes as $node) {
        $lang = $node->get('field_language')->value;
        $markup .= $node->id() . ':' . $node->getTitle() . ' - ' . $lang . '<br/>';
      }

      $form['markup'] = [
        '#markup' => $markup,
      ];
      $form['submit'] = [
        '#type' => 'submit',
        '#value' => t('Send')
      ];
    }
    else {
      $markup .= '<p>No push messages found</p>';
      $form['markup'] = [
        '#markup' => $markup,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    foreach ($form_state->getValues() as $key => $value) {
      // @TODO: Validate fields.
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\mxt_core\TwgApiHelper $twg_api_helper */
    $twg_api_helper = \Drupal::service('mxt_core.twg_api_helper');
    $nodes = $twg_api_helper->aitGcmGetNodes();

    foreach ($nodes as $node) {
      $twg_api_helper->aitGcmSendNotificationNode($node);
    }
  }

}
