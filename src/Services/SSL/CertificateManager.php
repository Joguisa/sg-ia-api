<?php
namespace Src\Services\SSL;

class CertificateManager {
  private static ?string $cacertPath = null;

  public static function getCertificatePath(): string {
    if (self::$cacertPath !== null) {
      return self::$cacertPath;
    }

    self::$cacertPath = self::resolveCertificatePath();
    return self::$cacertPath;
  }

  private static function resolveCertificatePath(): string {
    $projectRoot = dirname(dirname(dirname(__DIR__)));

    $possiblePaths = [
      $projectRoot . '/certs/cacert.pem',
      __DIR__ . '/../../certs/cacert.pem',
      '/etc/ssl/certs/ca-certificates.crt',
      '/etc/ssl/certs/ca-bundle.crt',
      '/etc/pki/tls/certs/ca-bundle.crt',
      '/usr/local/share/ca-certificates/ca-bundle.crt',
      '/etc/ssl/ca-bundle.pem',
    ];

    foreach ($possiblePaths as $path) {
      if (file_exists($path) && is_readable($path) && filesize($path) > 0) {
        return realpath($path);
      }
    }

    throw new \RuntimeException(
      "No valid SSL certificate bundle found. " .
      "Download from: https://curl.se/ca/cacert.pem and save to: certs/cacert.pem\n" .
      "Or run: mkdir -p certs && curl -o certs/cacert.pem https://curl.se/ca/cacert.pem"
    );
  }

  public static function isValidCertificate(string $path): bool {
    return file_exists($path) && is_readable($path) && filesize($path) > 0;
  }
}
