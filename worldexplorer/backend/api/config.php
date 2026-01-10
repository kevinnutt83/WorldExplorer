<?php
header('Content-Type: application/json');
require_once __DIR__ . '/utils.php';

$act = $_GET['action'] ?? '';
if ($act === 'theme'){
  global $AFTERLIGHT_CONFIG; $theme = $AFTERLIGHT_CONFIG['theme'] ?? [];
  json_out(['ok'=>true,'theme'=>$theme]);
  exit;
}

if ($act === 'general'){
  global $AFTERLIGHT_CONFIG; $general = $AFTERLIGHT_CONFIG['general'] ?? [];
  json_out(['ok'=>true,'general'=>$general]);
  exit;
}

http_response_code(404); json_out(['ok'=>false,'error'=>'Not found']);
