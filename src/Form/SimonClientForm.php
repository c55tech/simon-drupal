<?php

namespace Drupal\simon\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * SIMON client configuration form.
 */
class SimonClientForm extends FormBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a SimonClientForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
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
    return 'simon_client_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('simon.settings');
    $auth_key = $config->get('auth_key');
    $api_url = $config->get('api_url');
    $client_id = $config->get('client_id');

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

    $form['client_info'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Client Information'),
    ];

    $form['client_info']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Name'),
      '#description' => $this->t('Name of your organization/client'),
      '#default_value' => $config->get('client_name') ?? '',
      '#required' => TRUE,
    ];

    $form['client_info']['contact_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Contact Name'),
      '#default_value' => $config->get('contact_name') ?? '',
    ];

    $form['client_info']['contact_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Contact Email'),
      '#default_value' => $config->get('contact_email') ?? '',
    ];

    $form['client_info']['client_slack_webhook'] = [
      '#type' => 'url',
      '#title' => $this->t('Client Slack Webhook URL'),
      '#description' => $this->t('Optional: Slack webhook URL to receive all notifications for this client. All notifications for this client\'s sites will be sent to this webhook unless overridden at the site level.'),
      '#default_value' => $config->get('client_slack_webhook') ?? '',
    ];

    // Check sync status
    $client_name_saved = $config->get('client_name');
    $has_saved_data = !empty($client_name_saved);
    $is_synced = !empty($client_id);

    // Show sync status after the form fields
    if ($has_saved_data || $is_synced) {
      $status_messages = [];
      
      if ($has_saved_data && !$is_synced) {
        $status_messages[] = '<div class="messages messages--warning"><p><strong>' . $this->t('⚠ Client data saved locally but not synced with SIMON API.') . '</strong></p><p>' . $this->t('Click "Send to SIMON API" to sync your data.') . '</p></div>';
      } elseif ($is_synced && $has_saved_data) {
        $status_messages[] = '<div class="messages messages--status"><p><strong>' . $this->t('✓ Client data is saved and synced with SIMON API.') . '</strong></p></div>';
      }
      
      if ($client_id) {
        $client_info = '<div class="simon-client-info"><p><strong>' . $this->t('Current Client ID: @id', ['@id' => $client_id]) . '</strong></p>';
        $client_info .= '</div>';
        $status_messages[] = $client_info;
      }
      
      if (!empty($status_messages)) {
        $form['status'] = [
          '#type' => 'markup',
          '#markup' => implode('', $status_messages),
          '#weight' => 10,
        ];
      }
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    // Save button - saves data locally without sending to API
    $form['actions']['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Client Data'),
      '#button_type' => 'primary',
      '#submit' => ['::saveClientData'],
    ];

    // Send to API button - sends saved data to SIMON API
    // Show if we have saved data, even if already synced (allows re-syncing)
    $form['actions']['send'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send to SIMON API'),
      '#submit' => ['::sendToApi'],
      '#access' => $has_saved_data, // Only show if there's saved data
      '#button_type' => $is_synced ? 'secondary' : NULL, // Make secondary if already synced
    ];

    if ($client_id) {
      $form['actions']['clear'] = [
        '#type' => 'submit',
        '#value' => $this->t('Clear Client ID'),
        '#submit' => ['::clearClientId'],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Default submit handler - redirects to save
    $this->saveClientData($form, $form_state);
  }

  /**
   * Save client data locally without sending to API.
   */
  public function saveClientData(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('simon.settings');
    
    $client_data = [
      'name' => $form_state->getValue('name'),
      'contact_name' => $form_state->getValue('contact_name'),
      'contact_email' => $form_state->getValue('contact_email'),
      'slack_webhook' => $form_state->getValue('slack_webhook') ?? '',
    ];

    // Save client data locally
    $config->set('client_name', $client_data['name']);
    // Save contact fields (including empty values to allow clearing)
    $config->set('contact_name', $client_data['contact_name'] ?? '');
    $config->set('contact_email', $client_data['contact_email'] ?? '');
    $config->set('client_slack_webhook', $form_state->getValue('client_slack_webhook') ?? '');
    $config->save();

    $this->messenger()->addStatus($this->t('Client data saved locally. You can now send it to the SIMON API using the "Send to SIMON API" button.'));
    
    // Redirect to show updated form
    $form_state->setRedirect('simon.client');
  }

  /**
   * Send saved client data to SIMON API.
   */
  public function sendToApi(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('simon.settings');
    $auth_key = $config->get('auth_key');
    $api_url = $config->get('api_url');

    // Get saved client data
    $client_data = [
      'name' => $config->get('client_name'),
      'contact_name' => $config->get('contact_name'),
      'contact_email' => $config->get('contact_email'),
      'slack_webhook' => $config->get('client_slack_webhook') ?? $config->get('slack_webhook') ?? '',
    ];
    
    // Add auth_key to the payload
    $client_data['auth_key'] = $auth_key;

    // Validate that we have required data
    if (empty($client_data['name'])) {
      $this->messenger()->addError($this->t('Cannot send to API: Client name is required. Please save the client data first.'));
      return;
    }

    // Validate API configuration
    if (empty($auth_key)) {
      $this->messenger()->addError($this->t('Cannot send to API: Auth Key is not configured. Please configure it in <a href="@url">SIMON Settings</a>.', [
        '@url' => \Drupal\Core\Url::fromRoute('simon.settings')->toString(),
      ]));
      return;
    }

    if (empty($api_url)) {
      $this->messenger()->addError($this->t('Cannot send to API: API URL is not configured. Please configure it in <a href="@url">SIMON Settings</a>.', [
        '@url' => \Drupal\Core\Url::fromRoute('simon.settings')->toString(),
      ]));
      return;
    }

    // Explicitly add auth_key to the payload after all validations pass
    // (It should already be there from line 215, but ensure it's definitely included)
    $client_data['auth_key'] = $auth_key;

    // Send to API
    try {
      $client = \Drupal::httpClient();
      $url = rtrim($api_url, '/') . '/api/clients';
      
      $response = $client->post($url, [
        'json' => $client_data,
        'headers' => [
          'X-Auth-Key' => $auth_key,
        ],
        'timeout' => 30,
      ]);

      $status_code = $response->getStatusCode();
      $response_body_content = $response->getBody()->getContents();
      
      // Accept 200 (OK), 201 (Created), and 409 (Conflict) as success
      // Also accept any 2xx status code as success
      if ($status_code >= 200 && $status_code < 300) {
        $body = [];
        if (!empty($response_body_content)) {
          $decoded = json_decode($response_body_content, TRUE);
          $body = is_array($decoded) ? $decoded : [];
        }
        
        $client_id = $body['client_id'] ?? NULL;

        if ($client_id) {
          $config->set('client_id', $client_id);
          $config->save();

          $this->messenger()->addStatus($this->t('Client created/updated successfully in SIMON API! Client ID: @id', [
            '@id' => $client_id,
          ]));
          
          \Drupal::logger('simon')->info('Client successfully created/updated in SIMON API. Status: @status, Client ID: @id', [
            '@status' => $status_code,
            '@id' => $client_id,
          ]);
        } else {
          // For successful responses (2xx) without client_id, still log as info
          $this->messenger()->addStatus($this->t('Client information sent successfully to SIMON API (Status: @status). Response: @body', [
            '@status' => $status_code,
            '@body' => !empty($response_body_content) ? $response_body_content : 'Empty response',
          ]));
          
          \Drupal::logger('simon')->info('SIMON API responded with success status @status. Response: @body', [
            '@status' => $status_code,
            '@body' => $response_body_content,
          ]);
        }
      } else {
        // Only log as error if status code is not 2xx
        $this->messenger()->addError($this->t('Failed to create client in SIMON API. Status: @status. Response: @response', [
          '@status' => $status_code,
          '@response' => $response_body_content,
        ]));
        
        \Drupal::logger('simon')->error('Failed to create client in SIMON API. Status: @status. Response: @response', [
          '@status' => $status_code,
          '@response' => $response_body_content,
        ]);
      }
    } catch (\Exception $e) {
      $error_message = $e->getMessage();
      $this->messenger()->addError($this->t('Error sending client data to SIMON API: @message. Your data is saved locally and you can try again.', [
        '@message' => $error_message,
      ]));
      
      \Drupal::logger('simon')->error('Error sending client data to SIMON API: @message', [
        '@message' => $error_message,
      ]);
    }
    
    // Redirect to show updated status
    $form_state->setRedirect('simon.client');
  }

  /**
   * Clear client ID.
   */
  public function clearClientId(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('simon.settings');
    $config->clear('client_id');
    $config->save();
    $this->messenger()->addStatus($this->t('Client ID cleared.'));
  }

}


