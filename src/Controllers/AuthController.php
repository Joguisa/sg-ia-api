<?php
namespace Src\Controllers;

use Src\Services\AuthService;
use Src\Utils\Response;
use Src\Utils\Translations;
use Src\Utils\LanguageDetector;

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
    $lang = LanguageDetector::detect();
    
    // Obtener el body JSON
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['email'], $input['password'])) {
      Response::json(['ok' => false, 'error' => Translations::get('missing_credentials', $lang)], 400);
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
