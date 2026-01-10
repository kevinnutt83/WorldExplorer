<?php
header('Content-Type: application/json');
require_once __DIR__ . '/utils.php';
require_auth();
$act = $_GET['action'] ?? '';

if ($act === 'list'){
  $res = db()->query("SELECT id,name,description FROM factions ORDER BY id ASC");
  $rows=[]; while ($res && ($r=$res->fetch_assoc())) $rows[]=$r; json_out(['ok'=>true,'factions'=>$rows]); exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST'){
  $b = body_json(); $action = $b['action'] ?? '';
  $u = authed_user(); $uid = intval($u['id']); $conn = db();
  $rc = $conn->query("SELECT id FROM characters WHERE user_id=$uid ORDER BY id DESC LIMIT 1");
  if (!$rc || !$rc->num_rows){ json_out(['ok'=>false,'error'=>'No character']); exit; }
  $cid = intval($rc->fetch_assoc()['id']);

  if ($action === 'join'){
    $fid = intval($b['faction_id'] ?? 0); if(!$fid){ json_out(['ok'=>false,'error'=>'Missing faction_id']); exit; }
    $conn->query("INSERT IGNORE INTO faction_members (faction_id,char_id,role) VALUES ($fid,$cid,'member')");
    json_out(['ok'=>true]); exit;
  }
  if ($action === 'leave'){
    $fid = intval($b['faction_id'] ?? 0); if(!$fid){ json_out(['ok'=>false,'error'=>'Missing faction_id']); exit; }
    $conn->query("DELETE FROM faction_members WHERE faction_id=$fid AND char_id=$cid");
    json_out(['ok'=>true]); exit;
  }
  // Admin CRUD
  if ($u && is_admin_or_super($u)){
    if ($action === 'create'){
      $name = esc(trim($b['name']??'')); $desc = esc(trim($b['description']??''));
      if (!$name){ json_out(['ok'=>false,'error'=>'Missing name']); exit; }
      $conn->query("INSERT INTO factions (name,description,data) VALUES ('$name','$desc','{}')"); json_out(['ok'=>true]); exit;
    }
    if ($action === 'delete'){
      $id = intval($b['id']??0); if(!$id){ json_out(['ok'=>false,'error'=>'Missing id']); exit; }
      $conn->query("DELETE FROM faction_members WHERE faction_id=$id");
      $conn->query("DELETE FROM factions WHERE id=$id"); json_out(['ok'=>true]); exit;
    }
  }
}

http_response_code(404); json_out(['ok'=>false,'error'=>'Not found']);
