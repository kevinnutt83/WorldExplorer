<?php
require_once __DIR__ . '/utils.php';
require_auth();
$user = authed_user();
if (($user['role'] ?? 'player') !== 'admin') { http_response_code(403); json_out(['ok'=>false,'error'=>'forbidden']); exit; }
$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');

function logs_dir() { return al_log_dir(); }

if ($action === 'list'){
  $dir = logs_dir();
  $files = @scandir($dir) ?: [];
  $out = [];
  foreach ($files as $f){
    if ($f === '.' || $f === '..') continue;
    if (!preg_match('/\.log$/', $f)) continue;
    $out[] = [ 'name'=>$f, 'size'=>@filesize($dir.'/'.$f), 'mtime'=>@filemtime($dir.'/'.$f) ];
  }
  json_out(['ok'=>true,'files'=>$out]);
  exit;
}

if ($action === 'tail'){
  $name = basename($_GET['name'] ?? '');
  if (!$name || strpos($name, '..') !== false) { http_response_code(400); json_out(['ok'=>false,'error'=>'bad_name']); exit; }
  $file = logs_dir() . '/' . $name;
  if (!is_file($file)) { http_response_code(404); json_out(['ok'=>false,'error'=>'not_found']); exit; }
  $limit = max(10, min(2000, intval($_GET['limit'] ?? 500)));
  $lines = [];
  $fp = fopen($file, 'r');
  if ($fp){
    // naive tail: read last ~128KB
    $size = filesize($file);
    $seek = max(0, $size - 131072);
    fseek($fp, $seek);
    while (($line = fgets($fp)) !== false){ $lines[] = rtrim($line,"\r\n"); }
    fclose($fp);
  }
  $lines = array_slice($lines, -$limit);
  json_out(['ok'=>true,'lines'=>$lines]);
  exit;
}

http_response_code(400);
json_out(['ok'=>false,'error'=>'unknown_action']);
