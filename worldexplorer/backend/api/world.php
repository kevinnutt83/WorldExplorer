<?php
header('Content-Type: application/json');
require_once __DIR__ . '/utils.php';

$act = $_GET['action'] ?? '';
if ($act === 'bootstrap'){
  seed_demo_if_empty();
  json_out(['ok'=>true,'world'=>[ 'seed'=>1, 'time'=>time() ]]);
  exit;
}

http_response_code(404); json_out(['ok'=>false,'error'=>'Not found']);
