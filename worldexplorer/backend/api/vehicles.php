<?php
header('Content-Type: application/json');
require_once __DIR__ . '/utils.php';
require_auth();

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST'){
  $b = body_json(); $action = $b['action'] ?? '';
  $u = authed_user(); $uid = intval($u['id']); $conn = db();
  $rc = $conn->query("SELECT id,x,y FROM characters WHERE user_id=$uid ORDER BY id DESC LIMIT 1");
  if (!$rc || !$rc->num_rows){ json_out(['ok'=>false,'error'=>'No character']); exit; }
  $char = $rc->fetch_assoc(); $cid = intval($char['id']);

  if ($action === 'spawn'){
    $kind = esc($b['kind'] ?? 'car'); $x = intval($b['x'] ?? $char['x']); $y = intval($b['y'] ?? $char['y']);
    $owned = !empty($b['owned']); $premium = !empty($b['premium']);
    $ownerCol = $owned ? $cid : 'NULL';
    $dataJson = $premium ? '{"premium":true}' : '{}';
    $conn->query("INSERT INTO vehicles (kind,x,y,occupant_char_id,owner_char_id,data) VALUES ('$kind',$x,$y,NULL,$ownerCol,'$dataJson')");
    json_out(['ok'=>true,'vehicle_id'=>$conn->insert_id]); exit;
  }
  if ($action === 'enter'){
  $vid = intval($b['vehicle_id'] ?? 0); if(!$vid){ json_out(['ok'=>false,'error'=>'Missing vehicle_id']); exit; }
    // ensure vacant
    $conn->query("UPDATE vehicles SET occupant_char_id=$cid WHERE id=$vid AND (occupant_char_id IS NULL)");
    if ($conn->affected_rows<=0){ json_out(['ok'=>false,'error'=>'Vehicle occupied or not found']); exit; }
    json_out(['ok'=>true]); exit;
  }
  if ($action === 'exit'){
    // vacate any vehicle occupied by this char
    $conn->query("UPDATE vehicles SET occupant_char_id=NULL WHERE occupant_char_id=$cid");
    json_out(['ok'=>true]); exit;
  }
  if ($action === 'steal'){
    $vid = intval($b['vehicle_id'] ?? 0); if(!$vid){ json_out(['ok'=>false,'error'=>'Missing vehicle_id']); exit; }
    // Allow stealing only if no owner and not occupied
    $res = $conn->query("SELECT owner_char_id, occupant_char_id, JSON_EXTRACT(data,'$.premium') AS premium FROM vehicles WHERE id=$vid");
    if (!$res || !$res->num_rows){ json_out(['ok'=>false,'error'=>'Not found']); exit; }
    $v = $res->fetch_assoc();
    if (!empty($v['premium'])){ json_out(['ok'=>false,'error'=>'Premium vehicles cannot be stolen']); exit; }
    if (!empty($v['owner_char_id']) || !empty($v['occupant_char_id'])){ json_out(['ok'=>false,'error'=>'Cannot steal']); exit; }
    $conn->query("UPDATE vehicles SET owner_char_id=$cid WHERE id=$vid AND owner_char_id IS NULL AND occupant_char_id IS NULL");
    if ($conn->affected_rows<=0){ json_out(['ok'=>false,'error'=>'Steal failed']); exit; }
    json_out(['ok'=>true]); exit;
  }
}

if ($method === 'GET'){
  $res = db()->query("SELECT id,kind,x,y,occupant_char_id,owner_char_id, COALESCE(JSON_UNQUOTE(JSON_EXTRACT(data,'$.premium')),'false') AS premium FROM vehicles");
  $rows=[]; while ($res && ($r=$res->fetch_assoc())) $rows[]=$r; json_out(['ok'=>true,'vehicles'=>$rows]); exit;
}

http_response_code(404); json_out(['ok'=>false,'error'=>'Not found']);
