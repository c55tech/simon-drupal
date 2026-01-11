<?php

namespace Drupal\simon\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * SIMON site configuration form.
 */
class SimonSiteForm extends FormBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a SimonSiteForm object.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simon_site_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('simon.settings');
    $auth_key = $config->get('auth_key');
    $api_url = $config->get('api_url');
    $client_id = $config->get('client_id');
    $site_id = $config->get('site_id');

    // Check auth_key first - this is required
    if (empty($auth_key)) {
      $form['message'] = [
        '#type' => 'markup',
        '#markup' => '<div class="messages messages--error"><p><strong>' . $this->t('Auth Key Required') . '</strong></p><p>' . $this->t('You must configure the Auth Key in <a href="@url">SIMON Settings</a> before you can use any other features.', [
          '@url' => \Drupal\Core\Url::fromRoute('simon.settings')->toString(),
        ]) . '</p></div>',
      ];
      return $form;
    }

    if (empty($api_url)) {
      $form['message'] = [
        '#type' => 'markup',
        '#markup' => '<div class="messages messages--error"><p>' . $this->t('Please configure the API URL in <a href="@url">SIMON Settings</a> first.', [
          '@url' => \Drupal\Core\Url::fromRoute('simon.settings')->toString(),
        ]) . '</p></div>',
      ];
      return $form;
    }

    if (empty($client_id)) {
      $form['message'] = [
        '#type' => 'markup',
        '#markup' => '<div class="messages messages--error"><p>' . $this->t('Please create a client in <a href="@url">SIMON Client Configuration</a> first.', [
          '@url' => \Drupal\Core\Url::fromRoute('simon.client')->toString(),
        ]) . '</p></div>',
      ];
      return $form;
    }

    $form['site_info'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Site Information'),
    ];

    $form['site_info']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Site Name'),
      '#description' => $this->t('Name for this site'),
      '#default_value' => $config->get('site_name') ?? \Drupal::config('system.site')->get('name'),
      '#required' => TRUE,
    ];

    $form['site_info']['url'] = [
      '#type' => 'url',
      '#title' => $this->t('Site URL'),
      '#description' => $this->t('Full URL of this site'),
      '#default_value' => $config->get('site_url') ?? \Drupal::request()->getSchemeAndHttpHost(),
      '#required' => TRUE,
    ];

    $form['site_info']['slack_webhook'] = [
      '#type' => 'url',
      '#title' => $this->t('Slack Webhook URL'),
      '#description' => $this->t('Optional: Slack webhook URL to receive site-specific notifications. This overrides the client-level webhook for this site.'),
      '#default_value' => $config->get('slack_webhook') ?? '',
    ];

    if ($site_id) {
      $site_info = '<p><strong>' . $this->t('Current Site ID: @id', ['@id' => $site_id]) . '</strong></p>';
      $form['current_site'] = [
        '#type' => 'markup',
        '#markup' => $site_info,
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['create'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create/Update Site'),
      '#button_type' => 'primary',
      '#submit' => ['::submitSiteToApi'],
    ];

    if ($site_id) {
      $form['actions']['clear'] = [
        '#type' => 'submit',
        '#value' => $this->t('Clear Site ID'),
        '#submit' => ['::clearSiteId'],
      ];
    }

    $form['actions']['submit_now'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit Data Now'),
      '#submit' => ['::submitData'],
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Default submit handler - calls create/update site
    $this->submitSiteToApi($form, $form_state);
  }

  /**
   * Submit site data to SIMON API.
   */
  public function submitSiteToApi(array &$form, FormStateInterface $form_state) {
    // First save the form data locally
    $config = $this->configFactory->getEditable('simon.settings');
    $config->set('site_name', $form_state->getValue('name'));
    $config->set('site_url', $form_state->getValue('url'));
    $config->set('slack_webhook', $form_state->getValue('slack_webhook') ?? '');
    $config->save();

    $auth_key = $config->get('auth_key');
    $api_url = $config->get('api_url');
    $client_id = $config->get('client_id');
    $site_id = $config->get('site_id');

    $site_data = [
      'client_id' => (int) $client_id,
      'cms' => 'drupal',
      'name' => $form_state->getValue('name'),
      'url' => $form_state->getValue('url'),
      'auth_key' => $auth_key,
      'slack_webhook' => $form_state->getValue('slack_webhook') ?? '',
    ];
    
    // Add site_id to payload if it exists
    if (!empty($site_id)) {
      $site_data['site_id'] = (int) $site_id;
    }

    try {
      $client = \Drupal::httpClient();
      $url = rtrim($api_url, '/') . '/api/sites';
      
      $response = $client->post($url, [
        'json' => $site_data,
        'headers' => [
          'X-Auth-Key' => $auth_key,
        ],
        'timeout' => 30,
      ]);

      $status_code = $response->getStatusCode();
      $response_body_content = $response->getBody()->getContents();
      
      if ($status_code === 201 || $status_code === 409 || ($status_code >= 200 && $status_code < 300)) {
        $body = json_decode($response_body_content, TRUE);
        $site_id = $body['site_id'] ?? NULL;

        if ($site_id) {
          $config->set('site_id', $site_id);
          $config->save();

          $this->messenger()->addStatus($this->t('Site created/updated successfully! Site ID: @id', [
            '@id' => $site_id,
          ]));
        }
      } else {
        // Debug: Show full error response
        $error_message = $this->t('Failed to create site. Status: @status', [
          '@status' => $status_code,
        ]);
        
        $this->messenger()->addError($error_message);
        
        // Show detailed error response for debugging
        $this->messenger()->addWarning($this->t('DEBUG - Error Response:<br><strong>Status Code:</strong> @status<br><strong>Response Body:</strong><pre>@body</pre>', [
          '@status' => $status_code,
          '@body' => htmlspecialchars($response_body_content),
        ]));
        
        // Log to watchdog
        \Drupal::logger('simon')->error('Failed to create site. Status: @status. Response: @response', [
          '@status' => $status_code,
          '@response' => $response_body_content,
        ]);
      }
    } catch (\Exception $e) {
      $error_message = $e->getMessage();
      
      $this->messenger()->addError($this->t('Error creating site: @message', [
        '@message' => $error_message,
      ]));
      
      // Show detailed exception for debugging
      $this->messenger()->addWarning($this->t('DEBUG - Exception Details:<br><strong>Message:</strong> @message<br><strong>File:</strong> @file<br><strong>Line:</strong> @line', [
        '@message' => htmlspecialchars($error_message),
        '@file' => $e->getFile(),
        '@line' => $e->getLine(),
      ]));
      
      // Log to watchdog
      \Drupal::logger('simon')->error('Error creating site: @message | File: @file | Line: @line', [
        '@message' => $error_message,
        '@file' => $e->getFile(),
        '@line' => $e->getLine(),
      ]);
    }
    
    // Redirect to show updated status
    $form_state->setRedirect('simon.site');
  }

  /**
   * Clear site ID.
   */
  public function clearSiteId(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('simon.settings');
    $config->clear('site_id');
    $config->save();
    $this->messenger()->addStatus($this->t('Site ID cleared.'));
    $form_state->setRedirect('simon.site');
  }

  /**
   * Submit data now.
   */
  public function submitData(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('simon.settings');
    $api_url = $config->get('api_url');
    $client_id = $config->get('client_id');
    $site_id = $config->get('site_id');

    if (empty($api_url) || empty($client_id) || empty($site_id)) {
      $this->messenger()->addError($this->t('Missing configuration. Please ensure API URL, Client ID, and Site ID are set.'));
      return;
    }

    $result = _simon_submit_data($api_url, $client_id, $site_id);
    if ($result) {
      $this->messenger()->addStatus($this->t('Site data submitted successfully!'));
    } else {
      $this->messenger()->addError($this->t('Failed to submit site data. Check the logs and recent watchdog entries for details.'));
      
      // Retrieve and display the most recent error from watchdog
      $database = \Drupal::database();
      try {
        $query = $database->select('watchdog', 'w')
          ->fields('w', ['wid', 'type', 'message', 'variables', 'timestamp', 'severity'])
          ->condition('w.type', 'simon')
          ->condition('w.severity', [4, 5, 6], 'IN') // ERROR, CRITICAL, ALERT
          ->orderBy('w.wid', 'DESC')
          ->range(0, 1);
        
        $log_entry = $query->execute()->fetchObject();
        
        if ($log_entry) {
          // Parse the message variables
          $variables = unserialize($log_entry->variables ?? 'a:0:{}');
          $message = strtr($log_entry->message, $variables);
          
          $this->messenger()->addWarning($this->t('DEBUG - Latest Error from Logs:<br><strong>Message:</strong> @message<br><strong>Time:</strong> @time', [
            '@message' => htmlspecialchars($message),
            '@time' => date('Y-m-d H:i:s', $log_entry->timestamp),
          ]));
        }
      }
      catch (\Exception $e) {
        // If we can't retrieve logs, just show the link
      }
      
      // Show debug message with link to watchdog
      $watchdog_url = \Drupal\Core\Url::fromRoute('dblog.overview', [], ['query' => ['type[]' => 'simon']])->toString();
      $this->messenger()->addWarning($this->t('DEBUG: Check <a href="@url">all recent log entries</a> for detailed error information.', [
        '@url' => $watchdog_url,
      ]));
    }
  }

}

