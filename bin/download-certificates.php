<?php
/**
 * Script para descargar certificados SSL de Mozilla
 * Ejecutar: php bin/download-certificates.php
 *
 * Este script garantiza que tu proyecto tenga certificados válidos
 * para conexiones HTTPS en desarrollo y producción.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ParagonIE\Certainty\RemoteFetch;
use ParagonIE\Certainty\Fetch;

echo "Descargando certificados SSL de Mozilla...\n";
echo str_repeat("-", 60) . "\n";

try {
  $certDir = __DIR__ . '/../certs';

  if (!is_dir($certDir)) {
    mkdir($certDir, 0755, true);
    echo "[OK] Directorio /certs creado\n";
  }

  $fetch = new RemoteFetch();
  $bundlePath = $fetch->getLatestBundle();

  if (!$bundlePath || !file_exists($bundlePath)) {
    throw new RuntimeException("RemoteFetch no retornó un bundle válido");
  }

  $destPath = $certDir . '/cacert.pem';
  copy($bundlePath, $destPath);
  chmod($destPath, 0644);

  $fileSize = filesize($destPath);
  $timestamp = date('Y-m-d H:i:s');

  echo "[OK] Certificados descargados exitosamente\n";
  echo "     Ubicación: $destPath\n";
  echo "     Tamaño: " . formatBytes($fileSize) . "\n";
  echo "     Descargado: $timestamp\n";
  echo "\nVerificación:\n";

  $count = countCertificates($destPath);
  echo "     Total de certificados: $count\n";

  echo "\n" . str_repeat("-", 60) . "\n";
  echo "Estado: LISTO PARA PRODUCCIÓN\n";

  exit(0);

} catch (Throwable $e) {
  echo "[ERROR] " . $e->getMessage() . "\n";
  echo "Soluciones:\n";
  echo "1. Verifica conexión a internet\n";
  echo "2. Instala paragonie/certainty: composer require paragonie/certainty\n";
  echo "3. Si estás offline, descarga manualmente desde:\n";
  echo "   https://curl.se/ca/cacert.pem\n";
  echo "   Y coloca el archivo en: certs/cacert.pem\n";
  exit(1);
}

function formatBytes(int $bytes): string {
  $units = ['B', 'KB', 'MB'];
  $bytes = max($bytes, 0);
  $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
  $pow = min($pow, count($units) - 1);
  $bytes /= (1 << (10 * $pow));
  return round($bytes, 2) . ' ' . $units[$pow];
}

function countCertificates(string $filePath): int {
  $content = file_get_contents($filePath);
  return substr_count($content, '-----BEGIN CERTIFICATE-----');
}
