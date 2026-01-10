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

$theme = body_json();

$configPath = __DIR__ . '/../config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    json_out(['ok'=>false,'error'=>'Config not found']);
    exit;
}

require_once $configPath;
global $AFTERLIGHT_CONFIG;

$AFTERLIGHT_CONFIG['theme'] = array_merge($AFTERLIGHT_CONFIG['theme'] ?? [], $theme);

$configPhp = "<?php\n\$AFTERLIGHT_CONFIG = " . var_export($AFTERLIGHT_CONFIG, true) . ";\n";

if (!is_writable(dirname($configPath))) {
    http_response_code(500);
    json_out(['ok'=>false,'error'=>'Config not writable']);
    exit;
}

file_put_contents($configPath, $configPhp);
json_out(['ok'=>true]);
