<?php
require_once __DIR__ . '/../config.php';
// Validate config presence early to avoid undefined offset errors
if (!isset($AFTERLIGHT_CONFIG) || !is_array($AFTERLIGHT_CONFIG)){
  header('Content-Type: application/json');
  // al_log is not yet defined here; use error_log to avoid fatal errors
  error_log('Afterlight config missing or invalid');
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Configuration missing. Run installer.']);
  exit;
}
if (session_status() === PHP_SESSION_NONE) {
  // Set a stable session cookie with correct path for subdirectory deployments
  $cookiePath = '/';
  if (!empty($AFTERLIGHT_CONFIG['base_url'])){
    $p = parse_url($AFTERLIGHT_CONFIG['base_url'], PHP_URL_PATH);
    if ($p !== null && $p !== false && $p !== '') { $cookiePath = rtrim($p, '/'); if ($cookiePath==='') { $cookiePath = '/'; } }
  } else {
    // Derive from script name (backend/api/.. -> project root)
    $root = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    if ($root !== '' && $root !== '/' && $root !== '\\' && $root !== '.') { $cookiePath = $root; }
  }
  $params = [ 'lifetime' => 0, 'path' => $cookiePath ?: '/', 'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', 'httponly' => true, 'samesite' => 'Lax' ];
  if (PHP_VERSION_ID >= 70300) { session_set_cookie_params($params); } else { session_set_cookie_params($params['lifetime'], $params['path']); }
  session_name('AL_SESS');
  session_start();
}

// ---------- Logging ----------
// Minimal, robust JSON-line logger with levels and rotation by day.
function al_log_dir(): string {
  $dir = __DIR__ . '/../logs';
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  if (!is_writable($dir)) {
    $dir = sys_get_temp_dir() . '/afterlight-logs';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
  }
  return $dir;
}

function al_log(string $level, string $category, string $message, array $context = []): void {
  $dir = al_log_dir();
  $file = $dir . '/' . $category . '-' . date('Y-m-d') . '.log';
  $entry = [
    'ts' => date('c'),
    'lvl' => strtoupper($level),
    'cat' => $category,
    'msg' => $message,
    'ctx' => $context,
    'uid' => $_SESSION['user']['id'] ?? null,
    'ip'  => $_SERVER['REMOTE_ADDR'] ?? null,
    'uri' => $_SERVER['REQUEST_URI'] ?? null,
  ];
  @file_put_contents($file, json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
}

set_error_handler(function($errno, $errstr, $errfile, $errline){
  // Respect @-silencing
  if (!(error_reporting() & $errno)) return false;
  al_log('error', 'php', $errstr, ['errno'=>$errno,'file'=>$errfile,'line'=>$errline]);
  return false; // Keep default handler too
});

set_exception_handler(function($ex){
  al_log('error', 'php', $ex->getMessage(), ['trace'=>$ex->getTraceAsString()]);
});

function db() {
    global $AFTERLIGHT_CONFIG;
    static $conn = null;
    
    if ($conn === null) {
        // Ensure mysqli extension is available before attempting connection
        if (!class_exists('mysqli')) {
            if (ob_get_level()) ob_clean();
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['ok'=>false,'error'=>'PHP mysqli extension is missing. Enable it or re-run installer.']);
            exit;
        }

        // Load config if not already loaded
        if (!isset($AFTERLIGHT_CONFIG)) {
            $configPath = __DIR__ . '/../config.php';
            if (file_exists($configPath)) {
                require_once $configPath;
            } else {
                // If called from endpoint, try loading it
                if (ob_get_level()) ob_clean();
                header('Content-Type: application/json');
                http_response_code(500);
                die(json_encode(['error' => 'Configuration not found']));
            }
        }
        
        $cfg = $AFTERLIGHT_CONFIG['db'] ?? [];
        $conn = @new mysqli(
            $cfg['host'] ?? 'localhost',
            $cfg['user'] ?? 'root',
            $cfg['pass'] ?? '',
            $cfg['name'] ?? 'afterlight',
            $cfg['port'] ?? 3306
        );
        
        if ($conn->connect_errno) {
            if (ob_get_level()) ob_clean();
            header('Content-Type: application/json');
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
        }
        
        $conn->set_charset('utf8mb4');

        // Ensure core schema exists (users table) or attempt migration once
        if (!afterlight_ensure_schema($conn)) {
            if (ob_get_level()) ob_clean();
            header('Content-Type: application/json');
            http_response_code(500);
            die(json_encode(['ok' => false, 'error' => 'Database schema missing or migration incomplete. Run installer/migrate.']));
        }
    }
    
    return $conn;
}

/**
 * Verify core tables exist; if missing, attempt to run migrations once.
 */
function afterlight_ensure_schema(mysqli $conn): bool {
    static $checked = null;
    if ($checked !== null) return $checked;
    $checked = false;
    $res = @$conn->query("SHOW TABLES LIKE 'users'");
    if ($res && $res->num_rows > 0) { $checked = true; return true; }

    // Try running migrations using existing connection (best effort)
    $ok = false;
    $migratePath = __DIR__ . '/../db/migrate.php';
    if (is_file($migratePath)) {
        require_once $migratePath;
        if (function_exists('afterlight_migrate_database')) {
            try { $ok = afterlight_migrate_database($conn); } catch (Throwable $e) { al_log('error','db','Migration failed', ['err'=>$e->getMessage()]); }
        } elseif (function_exists('afterlight_run_migrations')) {
            try { $ok = afterlight_run_migrations(); } catch (Throwable $e) { al_log('error','db','Migration failed', ['err'=>$e->getMessage()]); }
        }
    }
    $checked = $ok;
    return $ok;
}

function json_out($data) {
    if (ob_get_level()) ob_clean();
    echo json_encode($data);
}

function body_json() {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

function authed_user() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id'])) return null;
    
    $conn = db();
    $stmt = $conn->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
    if (!$stmt) { al_log('error','db','Prepare failed in authed_user', ['error'=>$conn->error]); return null; }
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        unset($_SESSION['user_id']);
        return null;
    }
    
    $row = $result->fetch_assoc();
    $row['verified'] = $row['verified'] ?? 1; // default if column absent
    return $row;
}

function is_admin_or_super($user) {
    if (!$user) return false;
    return in_array($user['role'], ['admin', 'super']);
}

function csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function seed_demo_if_empty() {
    $conn = db();
    
    // Check if items already exist
    $result = $conn->query("SELECT COUNT(*) as cnt FROM items");
    $row = $result->fetch_assoc();
    
    if ($row['cnt'] > 0) return;
    
    // Seed basic items
    $items = [
        ['id' => 'wood_scrap', 'name' => 'Wood Scrap', 'kind' => 'material', 'rarity' => 'common'],
        ['id' => 'iron_scrap', 'name' => 'Iron Scrap', 'kind' => 'material', 'rarity' => 'common'],
        ['id' => 'health_potion_small', 'name' => 'Health Potion (S)', 'kind' => 'consumable', 'rarity' => 'common'],
        ['id' => 'wood_sword', 'name' => 'Wooden Sword', 'kind' => 'weapon', 'rarity' => 'common'],
        ['id' => 'iron_sword', 'name' => 'Iron Sword', 'kind' => 'weapon', 'rarity' => 'uncommon']
    ];
    
    $stmt = $conn->prepare("INSERT INTO items (id, name, kind, rarity) VALUES (?, ?, ?, ?)");
    foreach ($items as $item) {
        $stmt->bind_param('ssss', $item['id'], $item['name'], $item['kind'], $item['rarity']);
        $stmt->execute();
    }
    $stmt->close();
    
    // Seed world nodes
    $nodes = [
        ['id' => 'city_start', 'type' => 'city', 'x' => 0, 'y' => 0],
        ['id' => 'town_north', 'type' => 'town', 'x' => 100, 'y' => -200],
        ['id' => 'town_south', 'type' => 'town', 'x' => -150, 'y' => 180]
    ];
    
    $stmt = $conn->prepare("INSERT INTO world_nodes (id, type, x, y) VALUES (?, ?, ?, ?)");
    foreach ($nodes as $node) {
        $stmt->bind_param('ssdd', $node['id'], $node['type'], $node['x'], $node['y']);
        $stmt->execute();
    }
    $stmt->close();
}

function al_column_exists(mysqli $conn, string $table, string $column): bool {
    $res = @$conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $res && $res->num_rows > 0;
}
