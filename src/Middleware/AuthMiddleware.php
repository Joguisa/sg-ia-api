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
    // Obtener el header Authorization desde múltiples fuentes
    $authHeader = $this->getAuthorizationHeader();

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

  /**
   * Obtiene el header Authorization desde múltiples fuentes
   * Compatible con Apache CGI/FastCGI y otros servidores
   */
  private function getAuthorizationHeader(): ?string {
    // 1. Fuente estándar
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
      return $_SERVER['HTTP_AUTHORIZATION'];
    }

    // 2. Apache con mod_rewrite (SetEnvIf)
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
      return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    // 3. Apache con apache_request_headers()
    if (function_exists('apache_request_headers')) {
      $headers = apache_request_headers();
      if (!empty($headers['Authorization'])) {
        return $headers['Authorization'];
      }
      // Algunos servidores lo pasan en minúsculas
      if (!empty($headers['authorization'])) {
        return $headers['authorization'];
      }
    }

    // 4. Nginx con getallheaders()
    if (function_exists('getallheaders')) {
      $headers = getallheaders();
      if (!empty($headers['Authorization'])) {
        return $headers['Authorization'];
      }
    }

    return null;
  }
}
