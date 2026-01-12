<?php
declare(strict_types=1);

namespace Src\Controllers;

use Src\Repositories\Interfaces\CategoryRepositoryInterface;
use Src\Utils\Response;
use Src\Utils\Translations;
use Src\Utils\LanguageDetector;

final class CategoryController {
  public function __construct(private CategoryRepositoryInterface $categories) {}

  /**
   * Listar todas las categorías
   *
   * Endpoint: GET /admin/categories
   *
   * @return void
   */
  public function list(): void {
    $lang = LanguageDetector::detect();
    try {
      $categories = $this->categories->findAll();

      Response::json([
        'ok' => true,
        'categories' => $categories
      ], 200);
    } catch (\Exception $e) {
      Response::json(['ok'=>false,'error'=>Translations::get('failed_to_fetch_categories', $lang) . ': ' . $e->getMessage()], 500);
    }
  }
}
