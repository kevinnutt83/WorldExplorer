<?php
header('Content-Type: application/json');
require_once __DIR__ . '/utils.php';
$u = authed_user();
$act = $_GET['action'] ?? '';

if ($act === 'generate'){
  // Admin only
  if (!$u || !is_admin_or_super($u)){ http_response_code(403); json_out(['ok'=>false,'error'=>'Admin required']); exit; }
  $conn = db();
  // Simple scatter of nodes around origin
  for ($i=0;$i<100;$i++){
    $x = rand(-2000, 2000); $y = rand(-2000,2000);
    $kinds = ['tree','ore','water','scrap']; $kind = $kinds[array_rand($kinds)];
    $conn->query("INSERT INTO world_nodes (kind,x,y,data) VALUES ('".esc($kind)."',$x,$y,'{}')");
  }
  json_out(['ok'=>true]); exit;
}

if ($act === 'list'){
  $conn = db();
  $res = $conn->query("SELECT id,kind,x,y FROM world_nodes LIMIT 500");
  $nodes = []; while ($res && ($row=$res->fetch_assoc())) $nodes[]=$row;
  json_out(['ok'=>true,'nodes'=>$nodes]); exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST'){
  $b = body_json(); $action = $b['action'] ?? '';
  // Admin edits
  if ($action === 'delete'){
    if (!$u || !is_admin_or_super($u)){ http_response_code(403); json_out(['ok'=>false,'error'=>'Admin required']); exit; }
    $id = intval($b['id'] ?? 0); if(!$id){ json_out(['ok'=>false,'error'=>'Missing id']); exit; }
    db()->query("DELETE FROM world_nodes WHERE id=$id"); json_out(['ok'=>true]); exit;
  }
  if ($action === 'update'){
    if (!$u || !is_admin_or_super($u)){ http_response_code(403); json_out(['ok'=>false,'error'=>'Admin required']); exit; }
    $id = intval($b['id'] ?? 0); $x = intval($b['x'] ?? 0); $y=intval($b['y'] ?? 0); $kind = esc($b['kind'] ?? '');
    if(!$id || !$kind){ json_out(['ok'=>false,'error'=>'Missing fields']); exit; }
    db()->query("UPDATE world_nodes SET kind='$kind', x=$x, y=$y WHERE id=$id"); json_out(['ok'=>true]); exit;
  }
  // Harvesting (player)
  if ($action === 'harvest'){
    require_auth(); $id = intval($b['id'] ?? 0); if(!$id){ json_out(['ok'=>false,'error'=>'Missing id']); exit; }
    $conn = db();
    $res = $conn->query("SELECT kind FROM world_nodes WHERE id=$id"); if(!$res || !$res->num_rows){ json_out(['ok'=>false,'error'=>'Not found']); exit; }
    $kind = $res->fetch_assoc()['kind'];
    // Map node kind to item name
    $map = ['tree'=>'Wood','ore'=>'Ore','water'=>'Water','scrap'=>'Scrap Metal']; $iname = $map[$kind] ?? 'Scrap';
    // Ensure item exists
    $ri = $conn->query("SELECT id FROM items WHERE name='".esc($iname)."' LIMIT 1");
    if ($ri && $ri->num_rows){ $itemId = intval($ri->fetch_assoc()['id']); }
    else { $conn->query("INSERT INTO items (name,type,rarity,data) VALUES ('".esc($iname)."','resource','common','{}')"); $itemId = $conn->insert_id; }
    // Get character
    $u = authed_user(); $uid = intval($u['id']); $rc = $conn->query("SELECT id FROM characters WHERE user_id=$uid ORDER BY id DESC LIMIT 1");
    if (!$rc || !$rc->num_rows){ json_out(['ok'=>false,'error'=>'No character']); exit; }
    $cid = intval($rc->fetch_assoc()['id']);
    // Give 1-3 units
    $qty = rand(1,3);
    $conn->query("INSERT INTO inventories (char_id,item_id,qty) VALUES ($cid,$itemId,$qty)");
    // Remove node (simple)
    $conn->query("DELETE FROM world_nodes WHERE id=$id");
    json_out(['ok'=>true,'gained'=>['item_id'=>$itemId,'qty'=>$qty]]); exit;
  }
}

http_response_code(404); json_out(['ok'=>false,'error'=>'Not found']);
