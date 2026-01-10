<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../api/utils.php';

$u = authed_user(); 
if (!$u || !is_admin_or_super($u)){ 
    http_response_code(403); 
    json_out(['ok'=>false,'error'=>'Admin required']); 
    exit; 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = body_json();
    $items = $body['items'] ?? [];
    
    if (!$items) {
        http_response_code(400);
        json_out(['ok'=>false,'error'=>'No items provided']);
        exit;
    }
    
    $conn = db();
    $stmt = $conn->prepare("INSERT INTO items (id, name, kind, rarity, data_json) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), kind=VALUES(kind), rarity=VALUES(rarity), data_json=VALUES(data_json)");
    
    foreach ($items as $item) {
        $id = $item['id'] ?? '';
        $name = $item['name'] ?? '';
        $kind = $item['kind'] ?? 'material';
        $rarity = $item['rarity'] ?? 'common';
        $data = json_encode($item);
        
        if (!$id) continue;
        
        $stmt->bind_param('sssss', $id, $name, $kind, $rarity, $data);
        $stmt->execute();
    }
    
    $stmt->close();
    json_out(['ok'=>true,'count'=>count($items)]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = $_GET['id'] ?? '';
    if (!$id) {
        http_response_code(400);
        json_out(['ok'=>false,'error'=>'ID required']);
        exit;
    }
    
    $conn = db();
    $stmt = $conn->prepare("DELETE FROM items WHERE id = ?");
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $stmt->close();
    
    json_out(['ok'=>true]);
    exit;
}

http_response_code(405);
json_out(['ok'=>false,'error'=>'Method not allowed']);
