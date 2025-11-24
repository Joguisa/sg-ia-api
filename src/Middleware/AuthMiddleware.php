<?php
namespace Src\Middleware;

use Src\Services\AuthService;
use Src\Utils\Response;

final class AuthMiddleware {
  private AuthService $authService;

  public function __construct(AuthService $authService) {
    $this->authService = $authService;
  }

  /**
   * Valida el token JWT del header Authorization
   * Si es inválido o no existe, detiene la ejecución con 401
   *
   * @return bool|void Retorna true si es válido, detiene la ejecución si no
   */
  public function validate(): bool {
    // Obtener el header Authorization
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;

    if (!$authHeader) {
      Response::json(['ok' => false, 'error' => 'Missing Authorization header'], 401);
      exit;
    }

    // Extraer el token del formato "Bearer <token>"
    if (!preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
      Response::json(['ok' => false, 'error' => 'Invalid Authorization header format'], 401);
      exit;
    }

    $token = $matches[1];

    // Validar el token
    $result = $this->authService->validateToken($token);

    if (!$result['ok']) {
      Response::json(['ok' => false, 'error' => $result['error']], 401);
      exit;
    }

    // Almacenar el payload del token en una variable global para acceso posterior
    $_SERVER['ADMIN'] = $result['payload'];

    return true;
  }
}
