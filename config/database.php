<?php
return [
  'dsn'  => getenv('DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=sg_ia_db;charset=utf8mb4',
  'user' => getenv('DB_USER') ?: 'sg_user',
  'pass' => getenv('DB_PASS') ?: 'MiTitulacion2026!',
  'opts' => [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ],
];
