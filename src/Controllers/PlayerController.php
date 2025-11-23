<?php
namespace Src\Controllers;
use Src\Services\ValidationService;
use Src\Repositories\Interfaces\PlayerRepositoryInterface;
use Src\Utils\Response;

final class PlayerController {
  public function __construct(private PlayerRepositoryInterface $players) {}

  public function create(): void {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    ValidationService::requireFields($data, ['name', 'age']);

    $name = trim($data['name']);
    $age = (int)$data['age'];

    if ($age < 1 || $age > 120) {
      Response::json(['ok'=>false,'error'=>'Age must be between 1 and 120'], 400);
      return;
    }

    $p = $this->players->create($name, $age);
    Response::json(['ok'=>true,'player'=>['id'=>$p->id,'name'=>$p->name,'age'=>$p->age]], 201);
  }

  public function index(): void {
    $list = $this->players->all();
    Response::json(['ok'=>true,'players'=>array_map(fn($p)=>['id'=>$p->id,'name'=>$p->name,'age'=>$p->age,'created_at'=>$p->createdAt],$list)]);
  }
}
