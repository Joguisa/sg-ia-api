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
    'origins' => array_filter(array_map('trim', explode(',', $_ENV['CORS_ORIGIN'] ?? 'http://localhost:4200'))),
    'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH'],
    'headers' => ['Content-Type', 'Authorization']
  ],
  // 'gemini' => [
  //   'api_key' => $_ENV['GEMINI_API_KEY'] ?? '',
  //   'enabled' => (bool)($_ENV['GEMINI_ENABLED'] ?? false)
  // ]
  'ai_providers' => [
    'gemini' => [
        'api_key' => $_ENV['GEMINI_API_KEY'] ?? '',
        'enabled' => filter_var($_ENV['GEMINI_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN)
    ],
    'groq' => [
        'api_key' => $_ENV['GROQ_API_KEY'] ?? '',
        'enabled' => filter_var($_ENV['GROQ_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN)
    ],
    'deepseek' => [
        'api_key' => $_ENV['DEEPSEEK_API_KEY'] ?? '',
        'enabled' => filter_var($_ENV['DEEPSEEK_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN)
    ],
    'fireworks' => [
        'api_key' => $_ENV['FIREWORKS_API_KEY'] ?? '',
        'enabled' => filter_var($_ENV['FIREWORKS_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN)
    ]
]
];
