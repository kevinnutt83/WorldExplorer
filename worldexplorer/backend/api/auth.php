<?php
header('Content-Type: application/json');
require_once __DIR__ . '/utils.php';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST'){
  if (!rate_limit('auth_post', 20)) { http_response_code(429); json_out(['ok'=>false,'error'=>'Too many requests']); exit; }
  $b = body_json();
  $action = $b['action'] ?? '';
  if ($action === 'register') do_register($b);
  else if ($action === 'login') do_login($b);
  else if ($action === 'logout') do_logout();
  else json_out(['ok'=>false,'error'=>'Unknown action']);
  exit;
}

json_out(['ok'=>false,'error'=>'Unsupported']);

function do_register($b){
  $u = trim($b['username'] ?? ''); $e = trim($b['email'] ?? ''); $p = (string)($b['password'] ?? '');
  if (!$u || !$p) { json_out(['ok'=>false,'error'=>'Missing fields']); return; }
  $conn = db();
  $uEsc = esc($u); $eEsc = esc($e); $h = esc(hash_pass($p));
  $res = $conn->query("INSERT INTO users (username,email,passhash) VALUES ('$uEsc','$eEsc','$h')");
  if (!$res) { json_out(['ok'=>false,'error'=>'User exists or DB error']); return; }
  json_out(['ok'=>true]);
}

function do_login($b){
  $u = trim($b['username'] ?? ''); $p = (string)($b['password'] ?? '');
  if (!$u || !$p) { json_out(['ok'=>false,'error'=>'Missing fields']); return; }
  $conn = db(); $uEsc = esc($u);
  $res = $conn->query("SELECT * FROM users WHERE username='$uEsc' LIMIT 1");
  if (!$res || !$res->num_rows) { json_out(['ok'=>false,'error'=>'Invalid credentials']); return; }
  $row = $res->fetch_assoc();
  if (!verify_pass($p, $row['passhash'])){ json_out(['ok'=>false,'error'=>'Invalid credentials']); return; }
  $_SESSION['user'] = ['id'=>$row['id'],'username'=>$row['username'],'role'=>$row['role']];
  json_out(['ok'=>true,'user'=>$_SESSION['user']]);
}

function do_logout(){
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
      $params['path'], $params['domain'], $params['secure'], $params['httponly']
    );
  }
  session_destroy();
  json_out(['ok'=>true]);
}
