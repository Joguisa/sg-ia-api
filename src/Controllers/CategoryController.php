<?php
declare(strict_types=1);

namespace Src\Controllers;

use Src\Repositories\Interfaces\CategoryRepositoryInterface;
use Src\Utils\Response;

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
    try {
      $categories = $this->categories->findAll();

      Response::json([
        'ok' => true,
        'categories' => $categories
      ], 200);
    } catch (\Exception $e) {
      Response::json(['ok'=>false,'error'=>'Failed to fetch categories: ' . $e->getMessage()], 500);
    }
  }
}
