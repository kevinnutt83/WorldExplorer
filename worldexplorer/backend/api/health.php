<?php
header('Content-Type: application/json');
require_once __DIR__ . '/utils.php';
require_auth();

$act = $_GET['action'] ?? 'stats';
if ($act === 'stats'){
  $out = [ 'ok'=>true ];
  $started = microtime(true);
  $dbOk = false; $dbMsg=''; $dbInfo=[];
  try{
    $c = db();
    $dbOk = $c && $c->ping();
    $dbInfo = [
      'host_info'=>$c->host_info,
      'server_info'=>$c->server_info,
      'client_info'=>mysqli_get_client_info(),
      'thread_id'=>$c->thread_id,
      'charset'=>$c->character_set_name(),
      'db'=>($GLOBALS['AFTERLIGHT_CONFIG']['db']['name']??null),
    ];
  }catch(Throwable $e){ $dbMsg = $e->getMessage(); }
  $elapsed = round((microtime(true)-$started)*1000);
  $out['db'] = [ 'ok'=>$dbOk, 'ms'=>$elapsed, 'msg'=>$dbMsg, 'info'=>$dbInfo ];
  $cfg = $GLOBALS['AFTERLIGHT_CONFIG'] ?? [];
  $out['services'] = [ 'realtime' => 'php' ];
  $out['session'] = [ 'user'=> authed_user(), 'id'=>session_id() ];
  $out['php'] = [ 'version'=>PHP_VERSION, 'extensions'=> [ 'mysqli'=>extension_loaded('mysqli'), 'json'=>extension_loaded('json'), 'openssl'=>extension_loaded('openssl') ] ];
  json_out($out); exit;
}

http_response_code(404); json_out(['ok'=>false,'error'=>'Not found']);
