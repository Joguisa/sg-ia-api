<?php
namespace Src\Utils;

final class Router {
  private array $routes = [];
  private array $middlewares = [];

  public function add(string $method, string $pattern, callable $handler, ?callable $middleware = null): void {
    $regex = "#^" . preg_replace('#\{([\w]+)\}#', '(?P<$1>[^/]+)', rtrim($pattern,'/')) . "/?$#";
    $this->routes[] = [$method, $regex, $handler, $middleware];
  }

  public function dispatch(string $method, string $path) {
    foreach ($this->routes as [$m,$regex,$handler,$middleware]) {
      if ($m !== $method) continue;
      if (preg_match($regex, $path, $matches)) {
        // Ejecutar middleware si existe
        if ($middleware) {
          $middleware();
        }

        $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        return $handler($params);
      }
    }
    http_response_code(404);
    echo json_encode(['ok'=>false,'error'=>'Not Found']);
  }
}
