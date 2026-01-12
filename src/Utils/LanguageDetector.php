<?php
declare(strict_types=1);

namespace Src\Utils;

/**
 * LanguageDetector - Detects the preferred language for API responses
 * 
 * Priority order:
 * 1. Query parameter ?lang=en|es
 * 2. Accept-Language HTTP header
 * 3. System default from environment (SYSTEM_DEFAULT_LANGUAGE)
 */
class LanguageDetector {
  private static ?string $cachedLanguage = null;

  /**
   * Detect the preferred language for the current request
   * 
   * @return string 'es' or 'en'
   */
  public static function detect(): string {
    // Return cached result if already detected
    if (self::$cachedLanguage !== null) {
      return self::$cachedLanguage;
    }

    // 1. Check query parameter ?lang=en|es
    if (isset($_GET['lang'])) {
      $lang = strtolower(trim($_GET['lang']));
      if (in_array($lang, ['es', 'en'])) {
        self::$cachedLanguage = $lang;
        return $lang;
      }
    }

    // 2. Check Accept-Language header
    if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
      $acceptLang = self::parseAcceptLanguage($_SERVER['HTTP_ACCEPT_LANGUAGE']);
      if ($acceptLang !== null) {
        self::$cachedLanguage = $acceptLang;
        return $acceptLang;
      }
    }

    // 3. Fallback to system default from environment or 'es'
    $systemDefault = $_ENV['SYSTEM_DEFAULT_LANGUAGE'] ?? 'es';
    $systemDefault = in_array($systemDefault, ['es', 'en']) ? $systemDefault : 'es';
    
    self::$cachedLanguage = $systemDefault;
    return $systemDefault;
  }

  /**
   * Parse Accept-Language header and return 'es' or 'en' if found
   * 
   * @param string $header Accept-Language header value
   * @return string|null 'es', 'en', or null if not found
   */
  private static function parseAcceptLanguage(string $header): ?string {
    // Parse Accept-Language header (e.g., "en-US,en;q=0.9,es;q=0.8")
    $languages = [];
    $parts = explode(',', $header);

    foreach ($parts as $part) {
      $part = trim($part);
      // Extract language code and quality factor
      if (preg_match('/^([a-z]{2})(?:-[A-Z]{2})?(?:;q=([0-9.]+))?$/', $part, $matches)) {
        $lang = $matches[1];
        $quality = isset($matches[2]) ? (float)$matches[2] : 1.0;
        $languages[$lang] = $quality;
      }
    }

    // Sort by quality factor (descending)
    arsort($languages);

    // Return first match of 'es' or 'en'
    foreach ($languages as $lang => $quality) {
      if ($lang === 'es' || $lang === 'en') {
        return $lang;
      }
    }

    return null;
  }

  /**
   * Reset cached language (useful for testing)
   */
  public static function reset(): void {
    self::$cachedLanguage = null;
  }

  /**
   * Set language explicitly (useful for testing or specific contexts)
   * 
   * @param string $lang 'es' or 'en'
   */
  public static function setLanguage(string $lang): void {
    if (in_array($lang, ['es', 'en'])) {
      self::$cachedLanguage = $lang;
    }
  }
}
