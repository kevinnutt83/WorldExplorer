<?php
// Client -> server log ingestion (rate-limited, JSON lines)
require_once __DIR__ . '/utils.php';
header('Content-Type: application/json');
if (!rate_limit('client_log', 60)) { http_response_code(429); echo json_encode(['ok'=>false,'error'=>'rate_limited']); exit; }
$body = body_json();
$entries = $body['entries'] ?? [];
if (!is_array($entries)) $entries = [];
$count = 0;
foreach ($entries as $e){
  if (!is_array($e)) continue;
  $lvl = isset($e['level']) ? (string)$e['level'] : 'info';
  $cat = isset($e['category']) ? (string)$e['category'] : 'client';
  $msg = isset($e['message']) ? (string)$e['message'] : '';
  $ctx = isset($e['context']) && is_array($e['context']) ? $e['context'] : [];
  al_log($lvl, $cat, $msg, $ctx);
  $count++;
}
json_out(['ok'=>true,'ingested'=>$count]);
