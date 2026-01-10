<?php
header('Content-Type: application/json');
require_once __DIR__ . '/utils.php';
$u = authed_user(); if (!$u || !is_admin_or_super($u)){ http_response_code(403); json_out(['ok'=>false,'error'=>'Admin required']); exit; }

$act = $_GET['action'] ?? '';
if ($act === 'list'){
  $res = db()->query("SELECT id,name,type,rarity, consumable, CAST(effects AS CHAR) AS effects FROM items ORDER BY id DESC LIMIT 500");
  $items=[]; while ($res && ($r=$res->fetch_assoc())) $items[]=$r; json_out(['ok'=>true,'items'=>$items]); exit;
}

if ($_SERVER['REQUEST_METHOD']=== 'POST'){
  $b = body_json(); if (!csrf_check($b['csrf'] ?? '')){ http_response_code(400); json_out(['ok'=>false,'error'=>'Bad CSRF']); exit; }
  $action = $b['action'] ?? '';
  if ($action === 'create'){
    $name = esc(trim($b['name'] ?? '')); $type = esc(trim($b['type'] ?? '')); $rarity = esc(trim($b['rarity'] ?? 'common'));
    $consumable = !empty($b['consumable']) ? 1 : 0;
    $effects = $b['effects'] ?? null; $effectsJson = $effects ? esc(json_encode($effects)) : null;
    if (!$name || !$type){ json_out(['ok'=>false,'error'=>'Missing fields']); exit; }
    if ($effectsJson===null){ db()->query("INSERT INTO items (name,type,rarity,consumable,effects,data) VALUES ('$name','$type','$rarity',$consumable,NULL,'{}')"); }
    else { db()->query("INSERT INTO items (name,type,rarity,consumable,effects,data) VALUES ('$name','$type','$rarity',$consumable,JSON_EXTRACT('$effectsJson', '$'),'{}')"); }
    json_out(['ok'=>true]); exit;
  }
  if ($action === 'update'){
    $id = intval($b['id']??0); $name = esc(trim($b['name'] ?? '')); $type = esc(trim($b['type'] ?? '')); $rarity = esc(trim($b['rarity'] ?? 'common'));
    $consumable = !empty($b['consumable']) ? 1 : 0; $effects = $b['effects'] ?? null; $effectsJson = $effects ? esc(json_encode($effects)) : null;
    if (!$id || !$name || !$type){ json_out(['ok'=>false,'error'=>'Missing fields']); exit; }
    if ($effectsJson===null){ db()->query("UPDATE items SET name='$name', type='$type', rarity='$rarity', consumable=$consumable, effects=NULL WHERE id=$id"); }
    else { db()->query("UPDATE items SET name='$name', type='$type', rarity='$rarity', consumable=$consumable, effects=JSON_EXTRACT('$effectsJson', '$') WHERE id=$id"); }
    json_out(['ok'=>true]); exit;
  }
  if ($action === 'delete'){
    $id = intval($b['id']??0); if(!$id){ json_out(['ok'=>false,'error'=>'Missing id']); exit; }
    db()->query("DELETE FROM items WHERE id=$id"); json_out(['ok'=>true]); exit;
  }
}

http_response_code(404); json_out(['ok'=>false,'error'=>'Not found']);
