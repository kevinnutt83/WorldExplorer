<?php
header('Content-Type: application/json');
require_once __DIR__ . '/utils.php';
require_auth();

$act = $_GET['action'] ?? '';
if ($act === 'list'){
  $uid = intval(authed_user()['id']);
  $conn = db();
  $cid = !empty($_SESSION['char_id']) ? intval($_SESSION['char_id']) : 0;
  if ($cid){
    $resCheck = $conn->query("SELECT id FROM characters WHERE id=$cid AND user_id=$uid"); if(!$resCheck||!$resCheck->num_rows) $cid = 0;
  }
  if (!$cid){ $res = $conn->query("SELECT c.id as char_id FROM characters c WHERE c.user_id=$uid ORDER BY c.id DESC LIMIT 1"); $cid = ($res && $res->num_rows) ? intval($res->fetch_assoc()['char_id']) : 0; }
  $items = [];
  if ($cid){
    $res2 = $conn->query("SELECT i.id as item_id, i.name,i.type,i.rarity,inv.qty FROM inventories inv JOIN items i ON inv.item_id=i.id WHERE inv.char_id=$cid");
    while ($res2 && ($row=$res2->fetch_assoc())) $items[] = $row;
  }
  json_out(['ok'=>true,'char_id'=>$cid,'items'=>$items]);
  exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST'){
  $b = body_json(); $action = $b['action'] ?? '';
  if ($action === 'transfer'){
    $uid = intval(authed_user()['id']); $conn = db();
    $fromCid = !empty($_SESSION['char_id']) ? intval($_SESSION['char_id']) : 0;
    $toCid = intval($b['to_char_id'] ?? 0); $itemId = intval($b['item_id'] ?? 0); $qty = max(1, intval($b['qty'] ?? 1));
    if (!$fromCid || !$toCid || !$itemId){ json_out(['ok'=>false,'error'=>'Missing fields']); exit; }
    // Ensure both chars belong to user
    $r1 = $conn->query("SELECT id FROM characters WHERE id=$fromCid AND user_id=$uid");
    $r2 = $conn->query("SELECT id FROM characters WHERE id=$toCid AND user_id=$uid");
    if(!$r1||!$r1->num_rows||!$r2||!$r2->num_rows){ json_out(['ok'=>false,'error'=>'Not allowed']); exit; }
    // Check qty
    $ri = $conn->query("SELECT qty FROM inventories WHERE char_id=$fromCid AND item_id=$itemId"); if(!$ri||!$ri->num_rows){ json_out(['ok'=>false,'error'=>'Not in inventory']); exit; }
    $have = intval($ri->fetch_assoc()['qty']); if ($have < $qty){ json_out(['ok'=>false,'error'=>'Insufficient quantity']); exit; }
    // Move
    $conn->query("UPDATE inventories SET qty = qty - $qty WHERE char_id=$fromCid AND item_id=$itemId");
    $conn->query("DELETE FROM inventories WHERE char_id=$fromCid AND item_id=$itemId AND qty<=0");
    $conn->query("INSERT INTO inventories (char_id,item_id,qty) VALUES ($toCid,$itemId,$qty) ON DUPLICATE KEY UPDATE qty=qty+VALUES(qty)");
    json_out(['ok'=>true]); exit;
  }
  if ($action === 'consume'){
    $uid = intval(authed_user()['id']); $conn = db();
    $cid = !empty($_SESSION['char_id']) ? intval($_SESSION['char_id']) : 0;
    if (!$cid){ $res = $conn->query("SELECT id FROM characters WHERE user_id=$uid ORDER BY id DESC LIMIT 1"); $cid = ($res && $res->num_rows) ? intval($res->fetch_assoc()['id']) : 0; }
    $itemId = intval($b['item_id'] ?? 0); if(!$cid||!$itemId){ json_out(['ok'=>false,'error'=>'Missing fields']); exit; }
    // Ensure in inventory
  $ri = $conn->query("SELECT qty FROM inventories WHERE char_id=$cid AND item_id=$itemId"); if(!$ri||!$ri->num_rows){ json_out(['ok'=>false,'error'=>'Not in inventory']); exit; }
    $have = intval($ri->fetch_assoc()['qty']); if ($have<=0){ json_out(['ok'=>false,'error'=>'None left']); exit; }
    // Check consumable and get effects
    $it = $conn->query("SELECT consumable, CAST(effects AS CHAR) AS effects FROM items WHERE id=$itemId"); if(!$it||!$it->num_rows){ json_out(['ok'=>false,'error'=>'Item not found']); exit; }
    $row = $it->fetch_assoc(); if (intval($row['consumable'])!==1){ json_out(['ok'=>false,'error'=>'Not consumable']); exit; }
    $effects = null; try{ $effects = $row['effects'] ? json_decode($row['effects'], true) : null; }catch(Exception $_){ $effects = null; }
    // Apply effects (heal only for now)
    $rc = $conn->query("SELECT id,hp FROM characters WHERE id=$cid AND user_id=$uid"); if(!$rc||!$rc->num_rows){ json_out(['ok'=>false,'error'=>'No character']); exit; }
    $char = $rc->fetch_assoc(); $hp = intval($char['hp']);
    $heal = 0; if (is_array($effects)){
      if (isset($effects['heal'])){ $heal += intval($effects['heal']); }
      // Reserve: buffs, resistances, etc.
    }
    $newHp = max(0, min(100, $hp + $heal));
    if ($newHp !== $hp){ $conn->query("UPDATE characters SET hp=$newHp WHERE id=$cid"); }
    // Decrement inventory
    $conn->query("UPDATE inventories SET qty = GREATEST(qty-1,0) WHERE char_id=$cid AND item_id=$itemId");
    $conn->query("DELETE FROM inventories WHERE char_id=$cid AND item_id=$itemId AND qty<=0");
    json_out(['ok'=>true,'hp'=>$newHp]); exit;
  }
}

http_response_code(404); json_out(['ok'=>false,'error'=>'Not found']);
