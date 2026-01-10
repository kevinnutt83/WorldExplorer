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

$conn = db();

// Truncate ephemeral tables
$conn->query("TRUNCATE TABLE world_nodes");
$conn->query("TRUNCATE TABLE vehicles");
$conn->query("TRUNCATE TABLE market_listings");
$conn->query("TRUNCATE TABLE constructions");
$conn->query("TRUNCATE TABLE party_members");
$conn->query("TRUNCATE TABLE parties");
$conn->query("TRUNCATE TABLE messages");

// Reset characters
$conn->query("UPDATE characters SET x=0, y=0, hp=max_hp, xp=0");

json_out(['ok'=>true,'message'=>'Data purged']);
