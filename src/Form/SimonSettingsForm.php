<?php

namespace Drupal\simon\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure SIMON settings.
 */
class SimonSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['simon.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simon_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('simon.settings');

    // Module Settings Section
    $form['module_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Module Settings'),
    ];

    $form['module_settings']['auth_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Auth Key'),
      '#description' => $this->t('Authentication key for SIMON API. This must be set before any other functions will work.'),
      '#default_value' => $config->get('auth_key') ?? '',
      '#required' => TRUE,
    ];

    $form['module_settings']['api_url'] = [
      '#type' => 'url',
      '#title' => $this->t('SIMON API URL'),
      '#description' => $this->t('Base URL of the SIMON API server (e.g., http://localhost:3000)'),
      '#default_value' => $config->get('api_url') ?? '',
      '#required' => TRUE,
    ];

    $form['module_settings']['enable_cron'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable automatic submission via cron'),
      '#description' => $this->t('Automatically submit site data when cron runs'),
      '#default_value' => $config->get('enable_cron') ?? FALSE,
    ];

    $form['module_settings']['cron_interval'] = [
      '#type' => 'select',
      '#title' => $this->t('Cron submission interval'),
      '#description' => $this->t('How often to submit data via cron'),
      '#options' => [
        3600 => $this->t('Every hour'),
        21600 => $this->t('Every 6 hours'),
        86400 => $this->t('Daily'),
        604800 => $this->t('Weekly'),
      ],
      '#default_value' => $config->get('cron_interval') ?? 86400,
      '#states' => [
        'visible' => [
          ':input[name="enable_cron"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Module Settings action button
    $form['module_settings']['module_actions'] = [
      '#type' => 'actions',
    ];

    $form['module_settings']['module_actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Configuration'),
      '#button_type' => 'primary',
      '#submit' => ['::submitForm'],
    ];

    // Information about other configuration pages
    $client_url = \Drupal\Core\Url::fromRoute('simon.client')->toString();
    $site_url = \Drupal\Core\Url::fromRoute('simon.site')->toString();
    $form['navigation_info'] = [
      '#type' => 'markup',
      '#markup' => strtr('<div class="messages messages--status"><p>@intro</p><ul><li><a href="@client_url">@client_title</a> - @client_desc</li><li><a href="@site_url">@site_title</a> - @site_desc</li></ul></div>', [
        '@intro' => $this->t('After configuring the API settings above, you can configure:'),
        '@client_url' => $client_url,
        '@client_title' => $this->t('Client Configuration'),
        '@client_desc' => $this->t('Set up your client/organization information'),
        '@site_url' => $site_url,
        '@site_title' => $this->t('Site Configuration'),
        '@site_desc' => $this->t('Configure this site after creating a client'),
      ]),
      '#weight' => 100,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('simon.settings');
    $config
      ->set('auth_key', $form_state->getValue('auth_key'))
      ->set('api_url', $form_state->getValue('api_url'))
      ->set('enable_cron', $form_state->getValue('enable_cron'))
      ->set('cron_interval', $form_state->getValue('cron_interval'))
      ->save();

    $this->messenger()->addStatus($this->t('Configuration saved successfully.'));
    parent::submitForm($form, $form_state);
  }


}

