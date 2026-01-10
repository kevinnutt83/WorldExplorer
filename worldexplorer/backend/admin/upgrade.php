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

$conn = db();
$ok = afterlight_schema_upgrades($conn);

if (!$ok){ 
    http_response_code(500); 
    json_out(['ok'=>false,'error'=>'Upgrade failed']); 
    exit; 
}

json_out(['ok'=>true,'message'=>'Schema upgraded']);
