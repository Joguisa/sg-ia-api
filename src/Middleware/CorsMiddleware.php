<?php
namespace Src\Middleware;

final class CorsMiddleware
{
  public static function handle(array $cfg): void
  {
    if (in_array('*', $cfg['origins'], true)) {
      header("Access-Control-Allow-Origin: *");
    } else {
      $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
      if (in_array($origin, $cfg['origins'], true)) {
        header("Access-Control-Allow-Origin: {$origin}");
        header('Vary: Origin');
      }
    }
    header('Access-Control-Allow-Methods: ' . implode(',', $cfg['methods']));
    header('Access-Control-Allow-Headers: ' . implode(',', $cfg['headers']));
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
      http_response_code(204);
      exit;
    }
  }
}
