<?php
require_once __DIR__ . '/utils.php';
header('Content-Type: application/json');
$cfg = $AFTERLIGHT_CONFIG['payments'] ?? [ 'currency'=>'USD', 'test_mode'=>true, 'packs'=>[] ];
$action = $_GET['action'] ?? ($_POST['action'] ?? 'config');

if ($action === 'config'){
  $pub = [
    'currency' => $cfg['currency'] ?? 'USD',
    'test_mode' => (bool)($cfg['test_mode'] ?? true),
    'packs' => $cfg['packs'] ?? []
  ];
  json_out(['ok'=>true,'config'=>$pub]); exit;
}

if ($action === 'wallet'){
  require_auth(); $u = authed_user(); $bal = wallet_get(intval($u['id']));
  json_out(['ok'=>true,'balance'=>$bal]); exit;
}

if ($action === 'create_order'){
  require_auth(); $u = authed_user(); $b = body_json();
  $type = $b['type'] ?? 'currency';
  $packId = $b['pack_id'] ?? '';
  $userId = intval($u['id']);
  $conn = db();
  if ($type === 'currency'){
    $packs = $cfg['packs'] ?? [];
    $pack = null; foreach ($packs as $p){ if (($p['id'] ?? '') === $packId) { $pack = $p; break; } }
    if (!$pack){ http_response_code(400); json_out(['ok'=>false,'error'=>'invalid_pack']); exit; }
    $amount = intval($pack['price'] ?? 0); $currency = $cfg['currency'] ?? 'USD';
    $conn->query("INSERT INTO orders (user_id,type,qty,amount,currency,status,data) VALUES ($userId,'currency',1,$amount,'$currency','created',JSON_OBJECT('pack', '$packId'))");
    $orderId = $conn->insert_id;
    // test mode -> immediate credit
    if ($cfg['test_mode'] ?? true){
      $credit = intval($pack['amount'] ?? 0);
      wallet_add($userId, $credit);
      $conn->query("UPDATE orders SET status='paid' WHERE id=$orderId");
      json_out(['ok'=>true,'order_id'=>$orderId,'status'=>'paid','credited'=>$credit]); exit;
    }
    // else: create gateway intent (placeholder)
    json_out(['ok'=>true,'order_id'=>$orderId,'status'=>'pending']); exit;
  }

  if ($type === 'vehicle'){
    $kind = preg_replace('/[^a-z0-9_-]/i','', $b['kind'] ?? 'car');
    // price lookup could be from config; for test, zero amount
    $amount = 0; $currency = $cfg['currency'] ?? 'USD';
    $conn->query("INSERT INTO orders (user_id,type,qty,amount,currency,status,data) VALUES ($userId,'vehicle',1,$amount,'$currency','created',JSON_OBJECT('kind', '$kind'))");
    $orderId = $conn->insert_id;
    if ($cfg['test_mode'] ?? true){
      // Spawn owned premium vehicle near character
      $rc = $conn->query("SELECT id,x,y FROM characters WHERE user_id=$userId ORDER BY id DESC LIMIT 1");
      if ($rc && $rc->num_rows){ $ch=$rc->fetch_assoc(); $x=intval($ch['x']); $y=intval($ch['y']); $cid=intval($ch['id']);
        $conn->query("INSERT INTO vehicles (kind,x,y,occupant_char_id,owner_char_id,data) VALUES ('$kind',$x,$y,NULL,$cid,'{"."\"premium\":true"."}')");
        $conn->query("INSERT INTO entitlements (user_id,kind,ref_id,data) VALUES ($userId,'vehicle',LAST_INSERT_ID(),JSON_OBJECT('kind','$kind','premium',true))");
      }
      $conn->query("UPDATE orders SET status='paid' WHERE id=$orderId");
      json_out(['ok'=>true,'order_id'=>$orderId,'status'=>'paid']); exit;
    }
    json_out(['ok'=>true,'order_id'=>$orderId,'status'=>'pending']); exit;
  }
  http_response_code(400); json_out(['ok'=>false,'error'=>'unsupported_type']); exit;
}

http_response_code(400); json_out(['ok'=>false,'error'=>'unknown_action']);
