<?php
declare(strict_types=1);

namespace Src\Middleware;

use Src\Services\AuthService;
use Src\Utils\Response;

/**
 * SuperAdminMiddleware
 * Validates that the authenticated user has superadmin role
 * Must be used after AuthMiddleware
 */
final class SuperAdminMiddleware {
  private AuthService $authService;

  public function __construct(AuthService $authService) {
    $this->authService = $authService;
  }

  /**
   * Validates that the current user is a superadmin
   * Requires AuthMiddleware to be run first
   *
   * @return bool|void Returns true if valid, exits with 403 if not
   */
  public function validate(): bool {
    // Check if admin is authenticated (set by AuthMiddleware)
    $admin = $_SERVER['ADMIN'] ?? null;

    if (!$admin) {
      Response::json(['ok' => false, 'error' => 'Authentication required'], 401);
      exit;
    }

    // Check if admin has superadmin role
    if (($admin['role'] ?? '') !== 'superadmin') {
      Response::json(['ok' => false, 'error' => 'Superadmin access required'], 403);
      exit;
    }

    return true;
  }
}
