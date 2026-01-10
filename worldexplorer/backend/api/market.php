<?php
header('Content-Type: application/json');
require_once __DIR__ . '/utils.php';
require_auth();
$act = $_GET['action'] ?? '';

if ($act === 'list'){
  $q = trim($_GET['q'] ?? ''); $page = max(1, intval($_GET['page'] ?? 1)); $size = max(1, min(100, intval($_GET['size'] ?? 25)));
  $off = ($page-1)*$size; $conn = db();
  $where = "WHERE ml.status='active'";
  if ($q !== ''){ $qs = esc('%'. $q .'%'); $where .= " AND i.name LIKE '$qs'"; }
  $count = 0; $cr = $conn->query("SELECT COUNT(*) c FROM market_listings ml JOIN items i ON ml.item_id=i.id $where"); if($cr&&($rr=$cr->fetch_assoc())) $count = intval($rr['c']);
  $res = $conn->query("SELECT ml.id, i.name, ml.qty, ml.price, ml.status FROM market_listings ml JOIN items i ON ml.item_id=i.id $where ORDER BY ml.created_at DESC LIMIT $size OFFSET $off");
  $rows = []; while ($res && ($r=$res->fetch_assoc())) $rows[]=$r; json_out(['ok'=>true,'listings'=>$rows,'total'=>$count,'page'=>$page,'size'=>$size]); exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST'){
  $b = body_json(); $action = $b['action'] ?? '';
  $u = authed_user(); $uid = intval($u['id']); $conn = db();
  $rc = $conn->query("SELECT id FROM characters WHERE user_id=$uid ORDER BY id DESC LIMIT 1");
  if (!$rc || !$rc->num_rows){ json_out(['ok'=>false,'error'=>'No character']); exit; }
  $cid = intval($rc->fetch_assoc()['id']);

  if ($action === 'sell'){
    $item_id = intval($b['item_id'] ?? 0); $qty = intval($b['qty'] ?? 1); $price = intval($b['price'] ?? 1);
    if (!$item_id || $qty<=0 || $price<=0){ json_out(['ok'=>false,'error'=>'Invalid listing']); exit; }
    // Check and deduct inventory
    $ri = $conn->query("SELECT qty FROM inventories WHERE char_id=$cid AND item_id=$item_id");
    if (!$ri || !$ri->num_rows){ json_out(['ok'=>false,'error'=>'Not in inventory']); exit; }
    $have = intval($ri->fetch_assoc()['qty']); if ($have < $qty){ json_out(['ok'=>false,'error'=>'Insufficient quantity']); exit; }
    $conn->query("UPDATE inventories SET qty = qty - $qty WHERE char_id=$cid AND item_id=$item_id");
    // If qty becomes 0, optionally delete row (simple cleanup)
    $conn->query("DELETE FROM inventories WHERE char_id=$cid AND item_id=$item_id AND qty<=0");
    // Create listing
    $conn->query("INSERT INTO market_listings (seller_char_id,item_id,qty,price,status) VALUES ($cid,$item_id,$qty,$price,'active')");
    json_out(['ok'=>true]); exit;
  }
  if ($action === 'buy'){
    $id = intval($b['id'] ?? 0); if(!$id){ json_out(['ok'=>false,'error'=>'Missing id']); exit; }
    // naive: mark sold
    $conn->query("UPDATE market_listings SET status='sold' WHERE id=$id AND status='active'");
    json_out(['ok'=>true]); exit;
  }
}

http_response_code(404); json_out(['ok'=>false,'error'=>'Not found']);
