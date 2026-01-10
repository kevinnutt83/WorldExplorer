<?php
header('Content-Type: application/json');
require_once __DIR__ . '/utils.php';
require_auth();
$act = $_GET['action'] ?? '';

if ($act === 'list'){
  $res = db()->query("SELECT r.id,r.name,r.result_item_id,r.result_qty FROM recipes r ORDER BY r.id DESC");
  $list = [];
  while ($res && ($row=$res->fetch_assoc())) $list[]=$row;
  json_out(['ok'=>true,'recipes'=>$list]); exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST'){
  $b = body_json(); $action = $b['action'] ?? '';
  if ($action === 'craft') { do_craft($b); exit; }
  // Admin CRUD
  $u = authed_user(); if ($u && is_admin_or_super($u)){
    if ($action === 'create'){
      $name = esc(trim($b['name']??'')); $result_item_id = intval($b['result_item_id']??0); $result_qty = intval($b['result_qty']??1);
      if (!$name || !$result_item_id){ json_out(['ok'=>false,'error'=>'Missing fields']); exit; }
      db()->query("INSERT INTO recipes (name,result_item_id,result_qty,data) VALUES ('$name',$result_item_id,$result_qty,'{}')");
      json_out(['ok'=>true]); exit;
    }
    if ($action === 'update'){
      $id = intval($b['id']??0); $name = esc(trim($b['name']??'')); $result_item_id = intval($b['result_item_id']??0); $result_qty = intval($b['result_qty']??1);
      if (!$id || !$name || !$result_item_id){ json_out(['ok'=>false,'error'=>'Missing fields']); exit; }
      db()->query("UPDATE recipes SET name='$name', result_item_id=$result_item_id, result_qty=$result_qty WHERE id=$id"); json_out(['ok'=>true]); exit;
    }
    if ($action === 'delete'){
      $id = intval($b['id']??0); if(!$id){ json_out(['ok'=>false,'error'=>'Missing id']); exit; }
      db()->query("DELETE FROM recipe_ingredients WHERE recipe_id=$id");
      db()->query("DELETE FROM recipes WHERE id=$id");
      json_out(['ok'=>true]); exit;
    }
  }
}

http_response_code(404); json_out(['ok'=>false,'error'=>'Not found']);

function do_craft($b){
  $rid = intval($b['recipe_id'] ?? 0); if (!$rid){ json_out(['ok'=>false,'error'=>'Missing recipe_id']); return; }
  $u = authed_user(); $uid = intval($u['id']); $conn = db();
  // Find character
  $rc = $conn->query("SELECT id FROM characters WHERE user_id=$uid ORDER BY id DESC LIMIT 1");
  if (!$rc || !$rc->num_rows){ json_out(['ok'=>false,'error'=>'No character']); return; }
  $cid = intval($rc->fetch_assoc()['id']);

  // Get recipe ingredients
  $ing = $conn->query("SELECT item_id, qty FROM recipe_ingredients WHERE recipe_id=$rid");
  $need = []; while ($ing && ($row=$ing->fetch_assoc())) $need[intval($row['item_id'])] = intval($row['qty']);
  if (!$need){ json_out(['ok'=>false,'error'=>'Invalid recipe']); return; }

  // Check inventory
  foreach ($need as $itemId=>$qty){
    $res = $conn->query("SELECT SUM(qty) q FROM inventories WHERE char_id=$cid AND item_id=$itemId");
    $have = ($res && ($r=$res->fetch_assoc())) ? intval($r['q']) : 0;
    if ($have < $qty){ json_out(['ok'=>false,'error'=>'Insufficient materials']); return; }
  }

  // Consume ingredients
  foreach ($need as $itemId=>$qty){
    $conn->query("UPDATE inventories SET qty = GREATEST(qty - $qty,0) WHERE char_id=$cid AND item_id=$itemId");
    $conn->query("DELETE FROM inventories WHERE char_id=$cid AND item_id=$itemId AND qty<=0");
  }

  // Give result
  $rr = $conn->query("SELECT result_item_id,result_qty FROM recipes WHERE id=$rid");
  if (!$rr || !$rr->num_rows){ json_out(['ok'=>false,'error'=>'Recipe not found']); return; }
  $row = $rr->fetch_assoc(); $resItem = intval($row['result_item_id']); $resQty = intval($row['result_qty']);
  $conn->query("INSERT INTO inventories (char_id,item_id,qty) VALUES ($cid,$resItem,$resQty)");
  json_out(['ok'=>true]);
}
