<?php
header('Content-Type: application/json');
require_once __DIR__ . '/utils.php';
require_auth();
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST'){
  $b = body_json(); $action = $b['action'] ?? '';
  if ($action === 'create') create_char($b);
  else if ($action === 'save_pos') save_pos($b);
  else if ($action === 'equip') equip_item($b);
  else if ($action === 'unequip') unequip_item($b);
  else if ($action === 'select') select_char($b);
  else if ($action === 'delete') delete_char($b);
  else json_out(['ok'=>false,'error'=>'Unknown action']);
  exit;
}

if ($method === 'GET'){
  $act = $_GET['action'] ?? '';
  if ($act === 'list') { list_chars(); exit; }
}

http_response_code(405); json_out(['ok'=>false,'error'=>'Not allowed']);

function create_char($b){
  $u = authed_user(); $uid = intval($u['id']);
  $name = esc(trim($b['name'] ?? '')); $arch = esc(trim($b['arch'] ?? 'survivor'));
  if (!$name) { json_out(['ok'=>false,'error'=>'Name required']); return; }
  $conn = db();
  $res = $conn->query("INSERT INTO characters (user_id,name,arch) VALUES ($uid,'$name','$arch')");
  if (!$res) { json_out(['ok'=>false,'error'=>'DB error']); return; }
  json_out(['ok'=>true]);
}

function save_pos($b){
  $u = authed_user(); $uid = intval($u['id']);
  $x = intval($b['x'] ?? 0); $y = intval($b['y'] ?? 0);
  $conn = db();
  $res = $conn->query("SELECT id FROM characters WHERE user_id=$uid ORDER BY id DESC LIMIT 1");
  if (!$res || !$res->num_rows) { json_out(['ok'=>false,'error'=>'No character']); return; }
  $row = $res->fetch_assoc(); $cid = intval($row['id']);
  $conn->query("UPDATE characters SET x=$x,y=$y WHERE id=$cid");
  json_out(['ok'=>true]);
}

function get_current_char(){
  $u = authed_user(); $uid = intval($u['id']);
  if (!empty($_SESSION['char_id'])){
    $cid = intval($_SESSION['char_id']);
    $res = db()->query("SELECT * FROM characters WHERE id=$cid AND user_id=$uid LIMIT 1");
    if ($res && $res->num_rows) return $res->fetch_assoc();
  }
  $res = db()->query("SELECT * FROM characters WHERE user_id=$uid ORDER BY id DESC LIMIT 1");
  return $res && $res->num_rows ? $res->fetch_assoc() : null;
}

function equip_item($b){
  $slot = preg_replace('/[^a-z]/','', $b['slot'] ?? ''); $item_id = intval($b['item_id'] ?? 0);
  if (!$slot || !$item_id){ json_out(['ok'=>false,'error'=>'Missing slot or item']); return; }
  $char = get_current_char(); if(!$char){ json_out(['ok'=>false,'error'=>'No character']); return; }
  // Validate item type vs slot (simple)
  $it = db()->query("SELECT type FROM items WHERE id=$item_id"); if(!$it||!$it->num_rows){ json_out(['ok'=>false,'error'=>'Item not found']); return; }
  $type = $it->fetch_assoc()['type']; if (($slot==='weapon' && $type!=='weapon')||($slot==='tool'&&$type!=='tool')){ json_out(['ok'=>false,'error'=>'Incompatible']); return; }
  // Store in character data JSON
  $cid = intval($char['id']);
  db()->query("UPDATE characters SET data = JSON_SET(COALESCE(data,'{}'), '$.equip.$slot', $item_id) WHERE id=$cid");
  json_out(['ok'=>true]);
}

function unequip_item($b){
  $slot = preg_replace('/[^a-z]/','', $b['slot'] ?? ''); if(!$slot){ json_out(['ok'=>false,'error'=>'Missing slot']); return; }
  $char = get_current_char(); if(!$char){ json_out(['ok'=>false,'error'=>'No character']); return; }
  $cid = intval($char['id']);
  db()->query("UPDATE characters SET data = JSON_REMOVE(COALESCE(data,'{}'), '$.equip.$slot') WHERE id=$cid");
  json_out(['ok'=>true]);
}

function list_chars(){
  $u = authed_user(); $uid = intval($u['id']);
  $rows = []; $res = db()->query("SELECT id,name,arch,level,xp,hp,x,y, CAST(data AS CHAR) AS data FROM characters WHERE user_id=$uid ORDER BY id DESC");
  while ($res && ($r=$res->fetch_assoc())){ $rows[] = $r; }
  json_out(['ok'=>true,'characters'=>$rows,'selected'=>($_SESSION['char_id']??null)]);
}

function select_char($b){
  $u = authed_user(); $uid = intval($u['id']); $cid = intval($b['char_id'] ?? 0);
  if (!$cid){ json_out(['ok'=>false,'error'=>'Missing char_id']); return; }
  $res = db()->query("SELECT id FROM characters WHERE id=$cid AND user_id=$uid LIMIT 1"); if(!$res || !$res->num_rows){ json_out(['ok'=>false,'error'=>'Not found']); return; }
  $_SESSION['char_id'] = $cid; json_out(['ok'=>true]);
}

function delete_char($b){
  $u = authed_user(); $uid = intval($u['id']); $cid = intval($b['char_id'] ?? 0);
  if (!$cid){ json_out(['ok'=>false,'error'=>'Missing char_id']); return; }
  // Optional: prevent deleting selected
  if (!empty($_SESSION['char_id']) && intval($_SESSION['char_id']) === $cid) unset($_SESSION['char_id']);
  db()->query("DELETE FROM characters WHERE id=$cid AND user_id=$uid");
  json_out(['ok'=>true]);
}
