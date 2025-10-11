<?php
namespace Src\Services;

final class ValidationService {
  public static function requireFields(array $input, array $fields): array {
    foreach ($fields as $f) {
      if (!isset($input[$f]) || $input[$f] === '') {
        throw new \InvalidArgumentException("Falta campo: $f");
      }
    }
    return $input;
    }
}
