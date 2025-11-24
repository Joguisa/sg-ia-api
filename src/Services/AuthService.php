<?php
namespace Src\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PDO;

final class AuthService {
  private PDO $pdo;
  private string $jwtSecret;
  private int $tokenExpiry;

  public function __construct(PDO $pdo, string $jwtSecret = null, int $tokenExpirySeconds = 86400) {
    $this->pdo = $pdo;
    $this->jwtSecret = $jwtSecret ?? getenv('JWT_SECRET') ?? 'your-super-secret-key-change-in-production';
    $this->tokenExpiry = $tokenExpirySeconds;
  }

  /**
   * Autentica un admin y retorna un JWT token
   *
   * @param string $email
   * @param string $password
   * @return array{ok: bool, token?: string, error?: string}
   */
  public function login(string $email, string $password): array {
    // Validaciones básicas
    if (empty($email) || empty($password)) {
      return ['ok' => false, 'error' => 'Email and password are required'];
    }

    // Buscar admin en la BD
    try {
      $stmt = $this->pdo->prepare('SELECT id, email, password_hash FROM admins WHERE email = ?');
      $stmt->execute([$email]);
      $admin = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$admin) {
        return ['ok' => false, 'error' => 'Invalid credentials'];
      }

      // Verificar password
      if (!password_verify($password, $admin['password_hash'])) {
        return ['ok' => false, 'error' => 'Invalid credentials'];
      }

      // Generar JWT
      $now = time();
      $payload = [
        'sub' => $admin['id'],
        'email' => $admin['email'],
        'role' => 'admin',
        'iat' => $now,
        'exp' => $now + $this->tokenExpiry
      ];

      $token = JWT::encode($payload, $this->jwtSecret, 'HS256');

      return [
        'ok' => true,
        'token' => $token
      ];
    } catch (\Exception $e) {
      return ['ok' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
  }

  /**
   * Valida y decodifica un JWT token
   *
   * @param string $token
   * @return array{ok: bool, payload?: object, error?: string}
   */
  public function validateToken(string $token): array {
    try {
      $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
      return [
        'ok' => true,
        'payload' => $decoded
      ];
    } catch (\Exception $e) {
      return ['ok' => false, 'error' => 'Invalid token: ' . $e->getMessage()];
    }
  }
}
