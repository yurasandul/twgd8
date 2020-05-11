<?php

namespace Drupal\mxt_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class MXTInfoForm.
 */
class MXTInfoForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'mxt_core.mxt_info',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mxt_info_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('mxt_core.mxt_info');
    $form['phone'] = [
      '#type' => 'tel',
      '#title' => $this->t('Phone'),
      '#default_value' => $config->get('phone'),
    ];
    $form['image_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image URL'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('image_url'),
    ];
    $form['site_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Site URL'),
      '#maxlength' => 128,
      '#size' => 64,
      '#default_value' => $config->get('site_url'),
    ];
    $form['news_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('News URL'),
      '#maxlength' => 128,
      '#size' => 64,
      '#default_value' => $config->get('news_url'),
    ];
    $form['initiatives_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Initiatives URL'),
      '#maxlength' => 128,
      '#size' => 64,
      '#default_value' => $config->get('initiatives_url'),
    ];
    $form['questions_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Questions URL'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('questions_url'),
    ];
    $form['fb'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Facebook page name'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('fb'),
    ];
    $form['twitter'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Twitter user name'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('twitter'),
    ];
    $form['instagram'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Instagram user name'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('instagram'),
    ];
    $form['youtube'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Youtube channel name'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('youtube'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('mxt_core.mxt_info')
      ->set('phone', $form_state->getValue('phone'))
      ->set('image_url', $form_state->getValue('image_url'))
      ->set('site_url', $form_state->getValue('site_url'))
      ->set('news_url', $form_state->getValue('news_url'))
      ->set('initiatives_url', $form_state->getValue('initiatives_url'))
      ->set('questions_url', $form_state->getValue('questions_url'))
      ->set('fb', $form_state->getValue('fb'))
      ->set('twitter', $form_state->getValue('twitter'))
      ->set('instagram', $form_state->getValue('instagram'))
      ->set('youtube', $form_state->getValue('youtube'))
      ->save();
  }

}
