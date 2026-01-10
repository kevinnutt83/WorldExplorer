<?php
header('Content-Type: application/json');
require_once __DIR__ . '/utils.php';
require_auth();

if ($_SERVER['REQUEST_METHOD']==='POST'){
  $b = body_json(); $action = $b['action'] ?? '';
  if ($action === 'attack'){
    $u = authed_user(); $uid = intval($u['id']); $conn = db();
    $rc = $conn->query("SELECT id, xp FROM characters WHERE user_id=$uid ORDER BY id DESC LIMIT 1");
    if (!$rc || !$rc->num_rows){ json_out(['ok'=>false,'error'=>'No character']); exit; }
    $row = $rc->fetch_assoc(); $cid = intval($row['id']); $xp = intval($row['xp']);
    // Simple: award XP
    $gain = 5; $conn->query("UPDATE characters SET xp = xp + $gain WHERE id=$cid");
    json_out(['ok'=>true,'xp_gain'=>$gain]); exit;
  }
}

http_response_code(404); json_out(['ok'=>false,'error'=>'Not found']);
