<?php
return [
  'app' => [
    'env' => $_ENV['APP_ENV'] ?? 'local',
    'debug' => (bool)($_ENV['APP_DEBUG'] ?? true)
  ],
  'db' => [
    'dsn'  => $_ENV['DB_DSN'] ?? 'mysql:host=127.0.0.1;dbname=sg_ia_db;charset=utf8mb4',
    'user' => $_ENV['DB_USER'] ?? '',
    'pass' => $_ENV['DB_PASS'] ?? '',
    'opts' => [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ],
  ],
  'cors' => [
    'origins' => ['http://localhost:4200'],
    'methods' => ['GET','POST','PUT','DELETE','OPTIONS','PATCH'],
    'headers' => ['Content-Type','Authorization']
  ],
  'gemini' => [
    'api_key' => $_ENV['GEMINI_API_KEY'] ?? '',
    'enabled' => (bool)($_ENV['GEMINI_ENABLED'] ?? false)
  ]
];
