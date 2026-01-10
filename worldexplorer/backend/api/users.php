<?php
header('Content-Type: application/json');
require_once __DIR__ . '/utils.php';
$u = authed_user(); if (!$u || !is_admin_or_super($u)){ http_response_code(403); json_out(['ok'=>false,'error'=>'Admin required']); exit; }

$act = $_GET['action'] ?? '';
if ($act === 'list'){
  $conn = db(); $rows=[]; $res = $conn->query("SELECT id,username,email,role,created_at FROM users ORDER BY id ASC LIMIT 1000");
  while ($res && ($r=$res->fetch_assoc())) $rows[]=$r;
  json_out(['ok'=>true,'users'=>$rows]); exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST'){
  $b = body_json(); $action = $b['action'] ?? '';
  if ($action === 'set_role'){
    $target = intval($b['user_id'] ?? 0); $role = trim($b['role'] ?? '');
    if (!$target || !$role){ json_out(['ok'=>false,'error'=>'Missing fields']); exit; }
    // Permissions: only super admins can set admin/super_admin or change other admins
    $allowedRoles = ['subscriber','player','moderator','admin','super_admin'];
    if (!in_array($role, $allowedRoles, true)){ json_out(['ok'=>false,'error'=>'Invalid role']); exit; }
    $conn = db(); $cur = $conn->query("SELECT id,role FROM users WHERE id=$target"); if(!$cur||!$cur->num_rows){ json_out(['ok'=>false,'error'=>'User not found']); exit; }
    $current = $cur->fetch_assoc();
    $selfSuper = is_super_admin($u);
    if (!$selfSuper){
      if (in_array($role, ['admin','super_admin'], true)) { json_out(['ok'=>false,'error'=>'Only super admin can set admin roles']); exit; }
      if (in_array($current['role'], ['admin','super_admin'], true)) { json_out(['ok'=>false,'error'=>'Only super admin can change admin roles']); exit; }
    }
    $roleEsc = esc($role);
    $ok = $conn->query("UPDATE users SET role='$roleEsc' WHERE id=$target");
    json_out(['ok'=> (bool)$ok]); exit;
  }
}

http_response_code(404); json_out(['ok'=>false,'error'=>'Not found']);
