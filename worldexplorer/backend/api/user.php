<?php
header('Content-Type: application/json');
require_once __DIR__ . '/utils.php';

$act = $_GET['action'] ?? '';
if ($act === 'me') {
  $u = authed_user();
  if (!$u) { json_out(['ok'=>false]); exit; }
  // find active character if any
  $conn = db(); $uid = intval($u['id']);
  if (!empty($_SESSION['char_id'])){
    $cid = intval($_SESSION['char_id']);
    $res = $conn->query("SELECT id,name,arch,level,xp,hp,x,y, CAST(data AS CHAR) AS data FROM characters WHERE id=$cid AND user_id=$uid LIMIT 1");
  } else {
    $res = $conn->query("SELECT id,name,arch,level,xp,hp,x,y, CAST(data AS CHAR) AS data FROM characters WHERE user_id=$uid ORDER BY id DESC LIMIT 1");
  }
  $char = $res && $res->num_rows ? $res->fetch_assoc() : null;
  $u['activeCharacter'] = $char;
  json_out(['ok'=>true,'user'=>$u]);
  exit;
}

http_response_code(404); json_out(['ok'=>false,'error'=>'Not found']);
