<?php
namespace Src\Controllers;
use Src\Utils\Response;
use Src\Database\Connection;

final class StatisticsController {
  public function __construct(private Connection $db) {}

  public function session(array $params): void {
    $st = $this->db->pdo()->prepare("SELECT * FROM v_session_stats WHERE session_id=:id");
    $st->execute([':id'=>(int)$params['id']]);
    $r = $st->fetch();
    if (!$r) Response::json(['ok'=>false,'error'=>'Sin datos'],404);
    else Response::json(['ok'=>true,'stats'=>$r]);
  }
}
