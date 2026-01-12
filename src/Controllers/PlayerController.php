<?php
namespace Src\Controllers;
use Src\Services\ValidationService;
use Src\Repositories\Interfaces\PlayerRepositoryInterface;
use Src\Utils\Response;
use Src\Utils\Translations;
use Src\Utils\LanguageDetector;

final class PlayerController {
  public function __construct(private PlayerRepositoryInterface $players) {}

  public function create(): void {
    $lang = LanguageDetector::detect();
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    ValidationService::requireFields($data, ['name', 'age']);

    $name = trim($data['name']);
    $age = (int)$data['age'];

    if ($age < 1 || $age > 120) {
      Response::json(['ok'=>false,'error'=>Translations::get('age_must_be_valid', $lang)], 400);
      return;
    }

    // Usar getOrCreate para evitar duplicados
    $p = $this->players->getOrCreate($name, $age);

    // Verificar si el jugador ya existía
    $wasExisting = $this->players->findByNameAndAge($name, $age)->id === $p->id;
    $statusCode = $wasExisting ? 200 : 201;

    Response::json([
      'ok' => true,
      'player' => [
        'id' => $p->id,
        'name' => $p->name,
        'age' => $p->age
      ],
      'existing' => $wasExisting
    ], $statusCode);
  }

  public function index(): void {
    $list = $this->players->all();
    Response::json(['ok'=>true,'players'=>array_map(fn($p)=>['id'=>$p->id,'name'=>$p->name,'age'=>$p->age,'created_at'=>$p->createdAt],$list)]);
  }
}
