<?php
return [
  'app' => [
    'env' => getenv('APP_ENV') ?: 'local',
    'debug' => (bool)(getenv('APP_DEBUG') ?: true)
  ],
  'db' => [
    'dsn'  => getenv('DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=sg_ia_db;charset=utf8mb4',
    'user' => getenv('DB_USER') ?: '',
    'pass' => getenv('DB_PASS') ?: '',
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
    'api_key' => getenv('GEMINI_API_KEY') ?: '',
    'enabled' => (bool)(getenv('GEMINI_ENABLED') ?: false)
  ]
];
