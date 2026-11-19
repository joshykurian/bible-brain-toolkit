<?php

namespace Drupal\bible_brain\Controller;

use Drupal\bible_brain\BibleBrainService;
use Drupal\bible_brain\DrupalCache;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Public, read-only JSON proxy for the Bible Brain API. The API key stays on the server.
 */
class ApiController extends ControllerBase {

  public function handle($endpoint, Request $request) {
    try {
      $core = BibleBrainService::core();
      $ip = (string) $request->getClientIp();
      if (!\BibleBrain\RateLimiter::allow(new DrupalCache(), md5($ip), 300, 300)) {
        return new JsonResponse(['code' => 'bbp_rate', 'message' => 'Too many requests. Please wait a few minutes.'], 429);
      }
      return new JsonResponse($core->handle($endpoint, $request->query->all()));
    }
    catch (\BibleBrain\ApiException $e) {
      return new JsonResponse(['code' => 'bbp_error', 'message' => $e->getMessage()], $e->status);
    }
    catch (\Exception $e) {
      return new JsonResponse(['code' => 'bbp_error', 'message' => 'Server error.'], 500);
    }
  }

}
