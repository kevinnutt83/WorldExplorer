<?php
header('Content-Type: application/json');
require_once __DIR__ . '/utils.php';
require_auth();
$act = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD']==='POST'){
  $b = body_json(); $action = $b['action'] ?? '';
  if ($action === 'place'){
    $u = authed_user(); $uid = intval($u['id']); $conn = db();
    $kx = esc($b['kind'] ?? 'hut'); $x = intval($b['x'] ?? 0); $y = intval($b['y'] ?? 0);
    $res = $conn->query("SELECT id FROM characters WHERE user_id=$uid ORDER BY id DESC LIMIT 1");
    if (!$res || !$res->num_rows) { json_out(['ok'=>false,'error'=>'No character']); exit; }
    $cid = intval($res->fetch_assoc()['id']);
    $conn->query("INSERT INTO constructions (char_id,kind,x,y,data) VALUES ($cid,'$kx',$x,$y,'{}')");
    json_out(['ok'=>true]); exit;
  }
}

http_response_code(404); json_out(['ok'=>false,'error'=>'Not found']);
