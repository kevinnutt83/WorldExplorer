<?php
header('Content-Type: application/json');
require_once __DIR__ . '/utils.php';
require_auth();
$act = $_GET['action'] ?? '';

if ($act === 'list'){
  $res = db()->query("SELECT id,title,description,type, CAST(prerequisites AS CHAR) AS prerequisites, CAST(rewards AS CHAR) AS rewards, CAST(faction_effects AS CHAR) AS faction_effects, data FROM missions ORDER BY id ASC"); $rows=[]; while($res && ($r=$res->fetch_assoc())) $rows[]=$r; json_out(['ok'=>true,'missions'=>$rows]); exit;
}

if ($act === 'steps'){
  $mid = intval($_GET['mission_id'] ?? 0); if(!$mid){ http_response_code(400); json_out(['ok'=>false,'error'=>'missing_mission']); exit; }
  $res = db()->query("SELECT id, mission_id, step_no, description FROM mission_steps WHERE mission_id=$mid ORDER BY step_no ASC"); $rows=[]; while($res && ($r=$res->fetch_assoc())) $rows[]=$r; json_out(['ok'=>true,'steps'=>$rows]); exit;
}

if ($act === 'progress'){
  $u = authed_user(); if (!$u){ http_response_code(401); json_out(['ok'=>false,'error'=>'auth']); exit; }
  $uid = intval($u['id']); $conn = db();
  $rc = $conn->query("SELECT id FROM characters WHERE user_id=$uid ORDER BY id DESC LIMIT 1"); if(!$rc||!$rc->num_rows){ json_out(['ok'=>false,'error'=>'no_char']); exit; }
  $cid = intval($rc->fetch_assoc()['id']);
  $res = $conn->query("SELECT mission_id,current_step,status FROM mission_progress WHERE char_id=$cid"); $rows=[]; while($res && ($r=$res->fetch_assoc())) $rows[]=$r; json_out(['ok'=>true,'progress'=>$rows]); exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST'){
  $b = body_json(); $action = $b['action'] ?? '';
  $u = authed_user(); $conn = db();
  if ($u && is_admin_or_super($u)){
    if ($action === 'create'){
      $title = esc(trim($b['title']??'')); $desc = esc(trim($b['description']??'')); $type = esc(trim($b['type']??'fetch'));
      $pre = $b['prerequisites'] ?? null; $rew = $b['rewards'] ?? null; $fx = $b['faction_effects'] ?? null;
      if(!$title){ json_out(['ok'=>false,'error'=>'Missing title']); exit; }
      $sql = "INSERT INTO missions (title,description,type,prerequisites,rewards,faction_effects,data) VALUES ('$title','$desc','$type',";
      $sql .= $pre?"JSON_EXTRACT('".esc(json_encode($pre))."', '$')":"NULL"; $sql .= ",";
      $sql .= $rew?"JSON_EXTRACT('".esc(json_encode($rew))."', '$')":"NULL"; $sql .= ",";
      $sql .= $fx?"JSON_EXTRACT('".esc(json_encode($fx))."', '$')":"NULL"; $sql .= ",'{}')";
      $conn->query($sql); json_out(['ok'=>true]); exit;
    }
    if ($action === 'update'){
      $id = intval($b['id']??0); $title = esc(trim($b['title']??'')); $desc = esc(trim($b['description']??'')); $type = esc(trim($b['type']??''));
      $pre = $b['prerequisites'] ?? null; $rew = $b['rewards'] ?? null; $fx = $b['faction_effects'] ?? null;
      if(!$id||!$title){ json_out(['ok'=>false,'error'=>'Missing fields']); exit; }
      $sets = ["title='$title'", "description='$desc'"]; if ($type) $sets[] = "type='$type'";
      $sets[] = is_null($pre)?"prerequisites=NULL":"prerequisites=JSON_EXTRACT('".esc(json_encode($pre))."', '$')";
      $sets[] = is_null($rew)?"rewards=NULL":"rewards=JSON_EXTRACT('".esc(json_encode($rew))."', '$')";
      $sets[] = is_null($fx)?"faction_effects=NULL":"faction_effects=JSON_EXTRACT('".esc(json_encode($fx))."', '$')";
      $conn->query("UPDATE missions SET ".implode(',', $sets)." WHERE id=$id"); json_out(['ok'=>true]); exit;
    }
    if ($action === 'delete'){
      $id = intval($b['id']??0); if(!$id){ json_out(['ok'=>false,'error'=>'Missing id']); exit; }
      $conn->query("DELETE FROM mission_steps WHERE mission_id=$id"); $conn->query("DELETE FROM missions WHERE id=$id"); json_out(['ok'=>true]); exit;
    }
    if ($action === 'step_create'){
      $mid = intval($b['mission_id']??0); $no=intval($b['step_no']??0); $desc=esc($b['description']??''); if(!$mid||!$no){ json_out(['ok'=>false,'error'=>'Missing fields']); exit; }
      $conn->query("INSERT INTO mission_steps (mission_id,step_no,description,data) VALUES ($mid,$no,'$desc','{}')"); json_out(['ok'=>true]); exit;
    }
    if ($action === 'step_delete'){
      $sid = intval($b['id']??0); if(!$sid){ json_out(['ok'=>false,'error'=>'Missing id']); exit; }
      $conn->query("DELETE FROM mission_steps WHERE id=$sid"); json_out(['ok'=>true]); exit;
    }
  }

  // Player mission actions
  if ($action === 'start'){
    $mid = intval($b['mission_id']??0); if(!$mid){ json_out(['ok'=>false,'error'=>'Missing mission_id']); exit; }
    $uid = intval($u['id']); $rc = db()->query("SELECT id FROM characters WHERE user_id=$uid ORDER BY id DESC LIMIT 1"); if(!$rc||!$rc->num_rows){ json_out(['ok'=>false,'error'=>'no_char']); exit; }
    $cid = intval($rc->fetch_assoc()['id']);
    db()->query("INSERT INTO mission_progress (char_id,mission_id,current_step,status) VALUES ($cid,$mid,0,'active') ON DUPLICATE KEY UPDATE status='active'");
    json_out(['ok'=>true]); exit;
  }
  if ($action === 'advance'){
    $mid = intval($b['mission_id']??0); if(!$mid){ json_out(['ok'=>false,'error'=>'Missing mission_id']); exit; }
    $uid = intval($u['id']); $rc = db()->query("SELECT id FROM characters WHERE user_id=$uid ORDER BY id DESC LIMIT 1"); if(!$rc||!$rc->num_rows){ json_out(['ok'=>false,'error'=>'no_char']); exit; }
    $cid = intval($rc->fetch_assoc()['id']);
    db()->query("UPDATE mission_progress SET current_step = current_step + 1 WHERE char_id=$cid AND mission_id=$mid");
    json_out(['ok'=>true]); exit;
  }
}

http_response_code(404); json_out(['ok'=>false,'error'=>'Not found']);
