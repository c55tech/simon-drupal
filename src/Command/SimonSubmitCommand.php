<?php

namespace Drupal\simon\Command;

use Drush\Commands\DrushCommands;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Drush command for SIMON integration.
 */
class SimonSubmitCommand extends DrushCommands {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a SimonSubmitCommand object.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    parent::__construct();
    $this->configFactory = $config_factory;
  }

  /**
   * Submit site data to SIMON.
   *
   * @command simon:submit
   * @aliases simon-submit
   * @usage drush simon:submit
   *   Submit current site data to SIMON API.
   */
  public function submit() {
    $config = $this->configFactory->get('simon.settings');
    $api_url = $config->get('api_url');
    $client_id = $config->get('client_id');
    $site_id = $config->get('site_id');

    if (empty($api_url)) {
      $this->logger()->error('SIMON API URL not configured. Please configure it at /admin/config/services/simon');
      return;
    }

    if (empty($client_id)) {
      $this->logger()->error('SIMON Client ID not configured. Please create a client at /admin/config/services/simon/client');
      return;
    }

    if (empty($site_id)) {
      $this->logger()->error('SIMON Site ID not configured. Please create a site at /admin/config/services/simon/site');
      return;
    }

    $this->output()->writeln('Submitting site data to SIMON...');

    $result = _simon_submit_data($api_url, $client_id, $site_id);

    if ($result) {
      $this->output()->writeln('✓ Site data submitted successfully!');
    } else {
      $this->logger()->error('Failed to submit site data. Check the logs for details.');
    }
  }

}

















