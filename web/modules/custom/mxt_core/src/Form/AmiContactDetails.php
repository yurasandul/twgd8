<?php

namespace Drupal\mxt_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class AmiContactDetails.
 */
class AmiContactDetails extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'mxt_core.ami_contact_details',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ami_contact_details';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('mxt_core.ami_contact_details');

    $form['news'] = [
      '#type' => 'details',
      '#title' => t('Link to news'),
      '#open' => TRUE,
    ];

    $form['news']['news_title'] = [
      '#type'          => 'textfield',
      '#title'         => t('Title'),
      '#default_value' => $config->get('news_title'),
      '#maxlength' => 60,
    ];

    $form['news']['news_url'] = [
      '#type'          => 'textfield',
      '#title'         => t('URL'),
      '#default_value' => $config->get('news_url'),
      '#maxlength' => 2048,
    ];

    $form['member'] = [
      '#type' => 'details',
      '#title' => t('Link to member'),
      '#open' => TRUE,
    ];

    $form['member']['member_title'] = [
      '#type'          => 'textfield',
      '#title'         => t('Title'),
      '#default_value' => $config->get('member_title'),
      '#maxlength' => 60,
    ];

    $form['member']['member_url'] = [
      '#type'          => 'textfield',
      '#title'         => t('URL'),
      '#default_value' => $config->get('member_url'),
      '#maxlength' => 2048,
    ];

    $form['contact'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Contact'),
      '#default_value' => $config->get('contact'),
    ];

    $form['rest'] = [
      '#type' => 'details',
      '#title' => t('Data for endpoints'),
      '#open' => TRUE,
    ];

    $form['rest']['node_twg_ows'] = [
      '#type'          => 'textfield',
      '#title'         => t('Node ID for "/api/twg/v1/ows/{langcode}"'),
      '#default_value' => $config->get('node_twg_ows'),
      '#maxlength' => 10,
    ];

    $form['rest']['node_twg_twg'] = [
      '#type'          => 'textfield',
      '#title'         => t('Node ID for "/api/twg/v1/twg/{langcode}"'),
      '#default_value' => $config->get('node_twg_twg'),
      '#maxlength' => 10,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('mxt_core.ami_contact_details')
      ->set('news_title', $form_state->getValue('news_title'))
      ->set('news_url', $form_state->getValue('news_url'))
      ->set('member_title', $form_state->getValue('member_title'))
      ->set('member_url', $form_state->getValue('member_url'))
      ->set('contact', $form_state->getValue('contact')['value'])
      ->set('node_twg_ows', $form_state->getValue('node_twg_ows'))
      ->set('node_twg_twg', $form_state->getValue('node_twg_twg'))
      ->save();
  }

}
