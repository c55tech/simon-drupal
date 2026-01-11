<?php

namespace Drupal\simon\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for SIMON API routes.
 */
class SimonApiController extends ControllerBase {

  /**
   * Clear Drupal cache.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function clearCache(Request $request) {
    // Get parameters
    $client_id = $request->query->get('client_id');
    $site_id = $request->query->get('site_id');

    // Validate parameters
    $validation = $this->validateParameters($client_id, $site_id);
    if (!$validation['valid']) {
      return new JsonResponse($validation, 400);
    }

    // Clear cache
    try {
      // Clear all cache bins
      $cache_bins = ['bootstrap', 'config', 'data', 'default', 'discovery', 'dynamic_page_cache', 'entity', 'menu', 'page', 'render'];
      foreach ($cache_bins as $bin) {
        \Drupal::cache($bin)->deleteAll();
      }
      
      // Rebuild router if needed
      \Drupal::service('router.builder')->rebuild();
      
      \Drupal::logger('simon')->info('Cache cleared via SIMON API endpoint. Client ID: @client_id, Site ID: @site_id', [
        '@client_id' => $client_id,
        '@site_id' => $site_id,
      ]);

      return new JsonResponse([
        'status' => 'success',
        'message' => 'Cache cleared successfully',
        'client_id' => (int) $client_id,
        'site_id' => (int) $site_id,
      ]);
    }
    catch (\Exception $e) {
      \Drupal::logger('simon')->error('Error clearing cache via SIMON API: @message', [
        '@message' => $e->getMessage(),
      ]);

      return new JsonResponse([
        'status' => 'error',
        'message' => 'Failed to clear cache: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Execute Drupal cron.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function executeCron(Request $request) {
    // Get parameters
    $client_id = $request->query->get('client_id');
    $site_id = $request->query->get('site_id');

    // Validate parameters
    $validation = $this->validateParameters($client_id, $site_id);
    if (!$validation['valid']) {
      return new JsonResponse($validation, 400);
    }

    // Execute cron
    try {
      \Drupal::service('cron')->run();
      \Drupal::logger('simon')->info('Cron executed via SIMON API endpoint. Client ID: @client_id, Site ID: @site_id', [
        '@client_id' => $client_id,
        '@site_id' => $site_id,
      ]);

      return new JsonResponse([
        'status' => 'success',
        'message' => 'Cron executed successfully',
        'client_id' => (int) $client_id,
        'site_id' => (int) $site_id,
      ]);
    }
    catch (\Exception $e) {
      \Drupal::logger('simon')->error('Error executing cron via SIMON API: @message', [
        '@message' => $e->getMessage(),
      ]);

      return new JsonResponse([
        'status' => 'error',
        'message' => 'Failed to execute cron: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Heartbeat endpoint for availability monitoring.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with status and timestamp.
   */
  public function heartbeat(Request $request) {
    try {
      // Perform basic health checks
      $status = 'healthy';
      $checks = [];

      // Check database connection
      try {
        $database = \Drupal::database();
        $result = $database->select('users', 'u')
          ->fields('u', ['uid'])
          ->range(0, 1)
          ->execute()
          ->fetchField();
        $checks['database'] = 'ok';
      }
      catch (\Exception $e) {
        $checks['database'] = 'error';
        $status = 'degraded';
      }

      // Get site information
      $site_name = \Drupal::config('system.site')->get('name');
      $site_url = $request->getSchemeAndHttpHost();

      return new JsonResponse([
        'status' => $status,
        'timestamp' => time(),
        'datetime' => date('c'),
        'site_name' => $site_name,
        'site_url' => $site_url,
        'checks' => $checks,
      ], 200);
    }
    catch (\Exception $e) {
      \Drupal::logger('simon')->error('Error in heartbeat endpoint: @message', [
        '@message' => $e->getMessage(),
      ]);

      return new JsonResponse([
        'status' => 'error',
        'timestamp' => time(),
        'datetime' => date('c'),
        'message' => 'Heartbeat check failed',
      ], 500);
    }
  }

  /**
   * Validate client_id and site_id parameters.
   *
   * @param mixed $client_id
   *   Client ID parameter.
   * @param mixed $site_id
   *   Site ID parameter.
   *
   * @return array
   *   Validation result with 'valid' key and optional 'message'.
   */
  protected function validateParameters($client_id, $site_id) {
    // Check if parameters are provided
    if (empty($client_id) || empty($site_id)) {
      return [
        'valid' => FALSE,
        'status' => 'error',
        'message' => 'Missing required parameters: client_id and site_id are required',
      ];
    }

    // Get configuration
    $config = \Drupal::config('simon.settings');
    $configured_client_id = $config->get('client_id');
    $configured_site_id = $config->get('site_id');

    // Validate client_id
    if (empty($configured_client_id)) {
      return [
        'valid' => FALSE,
        'status' => 'error',
        'message' => 'Client ID not configured in SIMON settings',
      ];
    }

    if ((int) $client_id !== (int) $configured_client_id) {
      return [
        'valid' => FALSE,
        'status' => 'error',
        'message' => 'Invalid client_id. Provided: ' . $client_id . ', Expected: ' . $configured_client_id,
      ];
    }

    // Validate site_id
    if (empty($configured_site_id)) {
      return [
        'valid' => FALSE,
        'status' => 'error',
        'message' => 'Site ID not configured in SIMON settings',
      ];
    }

    if ((int) $site_id !== (int) $configured_site_id) {
      return [
        'valid' => FALSE,
        'status' => 'error',
        'message' => 'Invalid site_id. Provided: ' . $site_id . ', Expected: ' . $configured_site_id,
      ];
    }

    return [
      'valid' => TRUE,
    ];
  }

}

