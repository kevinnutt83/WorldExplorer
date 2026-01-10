<?php
header('Content-Type: application/json');
require_once __DIR__ . '/utils.php';
$u = authed_user(); if (!$u || !is_admin_or_super($u)){ http_response_code(403); json_out(['ok'=>false,'error'=>'Admin required']); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST'){
  $b = body_json(); $action = $b['action'] ?? '';
  if (!csrf_check($b['csrf'] ?? '')){ http_response_code(400); json_out(['ok'=>false,'error'=>'Bad CSRF']); exit; }
  if ($action === 'save_theme'){
    $theme = $b['theme'] ?? [];
    // Update config.php file (simple replace of theme array)
    $configPath = __DIR__ . '/../config.php';
    $cfg = $AFTERLIGHT_CONFIG; $cfg['theme'] = $theme;
    $configPhp = "<?php\n$".'AFTERLIGHT_CONFIG'." = ".var_export($cfg, true).";\n";
    if (!is_writable(dirname($configPath))){ json_out(['ok'=>false,'error'=>'Config not writable']); exit; }
    file_put_contents($configPath, $configPhp);
    json_out(['ok'=>true]); exit;
  }
  if ($action === 'save_payments'){
    $payments = $b['payments'] ?? [];
    $configPath = __DIR__ . '/../config.php';
    $cfg = $AFTERLIGHT_CONFIG; $cfg['payments'] = $payments;
    $configPhp = "<?php\n$".'AFTERLIGHT_CONFIG'." = ".var_export($cfg, true).";\n";
    if (!is_writable(dirname($configPath))){ json_out(['ok'=>false,'error'=>'Config not writable']); exit; }
    file_put_contents($configPath, $configPhp);
    json_out(['ok'=>true]); exit;
  }
  if ($action === 'save_general'){
    $general = $b['general'] ?? [];
    $configPath = __DIR__ . '/../config.php';
    $cfg = $AFTERLIGHT_CONFIG; $cfg['general'] = array_merge(($cfg['general']??[]), $general);
    $configPhp = "<?php\n$".'AFTERLIGHT_CONFIG'." = ".var_export($cfg, true).";\n";
    if (!is_writable(dirname($configPath))){ json_out(['ok'=>false,'error'=>'Config not writable']); exit; }
    file_put_contents($configPath, $configPhp);
    json_out(['ok'=>true]); exit;
  }
  if ($action === 'reinstall'){
    require_once __DIR__ . '/../db/migrate.php';
    $ok = afterlight_reset_database();
    if (!$ok){ http_response_code(500); json_out(['ok'=>false,'error'=>'Reset failed']); exit; }
    // seed demo items again
    require_once __DIR__ . '/utils.php';
    seed_demo_if_empty();
    json_out(['ok'=>true]); exit;
  }
  if ($action === 'db_upgrade'){
    require_once __DIR__ . '/../db/migrate.php';
    // Run only the schema upgrades against existing DB
    $cfg = $AFTERLIGHT_CONFIG['db'];
    $conn = @new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['name'], $cfg['port'] ?? 3306);
    if ($conn->connect_errno){ http_response_code(500); json_out(['ok'=>false,'error'=>'DB connect failed']); exit; }
    $conn->set_charset('utf8mb4');
    @afterlight_schema_upgrades($conn);
    json_out(['ok'=>true]); exit;
  }
  if ($action === 'purge_data'){
    $conn = db();
    // Truncate generated/ephemeral tables
    $conn->query("TRUNCATE TABLE world_nodes");
    $conn->query("TRUNCATE TABLE vehicles");
    $conn->query("TRUNCATE TABLE market_listings");
    $conn->query("TRUNCATE TABLE constructions");
    $conn->query("TRUNCATE TABLE party_members");
    $conn->query("TRUNCATE TABLE parties");
    $conn->query("TRUNCATE TABLE messages");
    // Reset characters basic stats/position
    $conn->query("UPDATE characters SET x=100,y=100,hp=100,xp=0");
    json_out(['ok'=>true]); exit;
  }
  json_out(['ok'=>false,'error'=>'Unknown action']); exit;
}

http_response_code(405); json_out(['ok'=>false,'error'=>'Not allowed']);
