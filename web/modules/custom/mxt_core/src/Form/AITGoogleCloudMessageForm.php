<?php

namespace Drupal\mxt_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class AITGoogleCloudMessageForm.
 */
class AITGoogleCloudMessageForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'mxt_core.ait_google_cloud_message',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ait_google_cloud_message_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('mxt_core.ait_google_cloud_message');
    $form['ait_gcm_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API key'),
      '#description' => $this->t('The API key for GCM'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('ait_gcm_api_key'),
    ];

    $form['ait_gcm_log_mail'] = [
      '#type' => 'checkbox',
      '#title' => t('Enable sending mails after push notification'),
      '#description' => t('If disabled, no emails will be sent after a push notification goes out the door.'),
      '#default_value' => $config->get('ait_gcm_log_mail'),
    ];

    $form['ait_gcm_log_address'] = [
      '#type' => 'textfield',
      '#title' => t('E-mailaddress for logs'),
      '#description' => t('E-mailaddress to send the push message logs to'),
      '#default_value' => $config->get('ait_gcm_log_address'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('mxt_core.ait_google_cloud_message')
      ->set('ait_gcm_api_key', $form_state->getValue('ait_gcm_api_key'))
      ->set('ait_gcm_log_mail', $form_state->getValue('ait_gcm_log_mail'))
      ->set('ait_gcm_log_address', $form_state->getValue('ait_gcm_log_address'))
      ->save();
  }

}
