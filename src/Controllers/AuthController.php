<?php
namespace Src\Controllers;

use Src\Services\AuthService;
use Src\Utils\Response;

final class AuthController {
  private AuthService $authService;

  public function __construct(AuthService $authService) {
    $this->authService = $authService;
  }

  /**
   * POST /auth/login
   * Autentica un admin y retorna un JWT token
   *
   * Expected JSON body:
   * {
   *   "email": "admin@sg-ia.com",
   *   "password": "admin123"
   * }
   */
  public function login(): void {
    // Obtener el body JSON
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['email'], $input['password'])) {
      Response::json(['ok' => false, 'error' => 'Missing email or password'], 400);
      return;
    }

    $result = $this->authService->login($input['email'], $input['password']);

    if (!$result['ok']) {
      Response::json($result, 401);
      return;
    }

    Response::json([
      'ok' => true,
      'token' => $result['token']
    ], 200);
  }
}
