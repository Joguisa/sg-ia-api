<?php
declare(strict_types=1);

namespace Src\Middleware;

use Src\Services\AuthService;
use Src\Utils\Response;

/**
 * SuperAdminMiddleware
 * Validates that the authenticated user has superadmin role
 * Includes authentication validation (no need to chain with AuthMiddleware)
 */
final class SuperAdminMiddleware {
  private AuthService $authService;
  private AuthMiddleware $authMiddleware;

  public function __construct(AuthService $authService) {
    $this->authService = $authService;
    $this->authMiddleware = new AuthMiddleware($authService);
  }

  /**
   * Validates authentication and superadmin role
   *
   * @return bool|void Returns true if valid, exits with 401/403 if not
   */
  public function validate(): bool {
    // First, validate authentication (this sets $_SERVER['ADMIN'])
    $this->authMiddleware->validate();

    // Get authenticated admin
    $admin = $_SERVER['ADMIN'] ?? null;

    // Check if admin has superadmin role
    if (($admin['role'] ?? '') !== 'superadmin') {
      Response::json(['ok' => false, 'error' => 'Superadmin access required'], 403);
      exit;
    }

    return true;
  }
}
