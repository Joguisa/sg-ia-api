<?php
namespace Src\Controllers;
use Src\Services\ValidationService;
use Src\Repositories\Interfaces\PlayerRepositoryInterface;
use Src\Utils\Response;

final class PlayerController {
  public function __construct(private PlayerRepositoryInterface $players) {}

  public function create(): void {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    ValidationService::requireFields($data, ['name']);
    $p = $this->players->create(trim($data['name']));
    Response::json(['ok'=>true,'player'=>['id'=>$p->id,'name'=>$p->name]], 201);
  }

  public function index(): void {
    $list = $this->players->all();
    Response::json(['ok'=>true,'players'=>array_map(fn($p)=>['id'=>$p->id,'name'=>$p->name,'created_at'=>$p->createdAt],$list)]);
  }
}
