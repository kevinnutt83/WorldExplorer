<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../api/utils.php';

$u = authed_user(); 
if (!$u || !is_admin_or_super($u)){ 
    http_response_code(403); 
    json_out(['ok'=>false,'error'=>'Admin required']); 
    exit; 
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    json_out(['ok'=>false,'error'=>'POST required']);
    exit;
}

require_once __DIR__ . '/../db/migrate.php';
$ok = afterlight_reset_database();

if (!$ok){ 
    http_response_code(500); 
    json_out(['ok'=>false,'error'=>'Reset failed']); 
    exit; 
}

// Re-seed demo
seed_demo_if_empty();

json_out(['ok'=>true,'message'=>'Database reset complete']);
