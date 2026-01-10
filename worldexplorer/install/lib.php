<?php
function afterlight_handle_install(array $post, string $backendDir, string &$error): bool {
  $base_url = trim($post['base_url'] ?? '');
  $db_host = trim($post['db_host'] ?? 'localhost');
  $db_port = intval($post['db_port'] ?? 3306);
  $db_name = trim($post['db_name'] ?? 'afterlight');
  $db_user = trim($post['db_user'] ?? '');
  $db_pass = (string)($post['db_pass'] ?? '');
  $ftp_host = trim($post['ftp_host'] ?? '');
  $ftp_user = trim($post['ftp_user'] ?? '');
  $ftp_pass = (string)($post['ftp_pass'] ?? '');
  $ftp_path = trim($post['ftp_path'] ?? '/');
  $admin_user = trim($post['admin_user'] ?? 'admin');
  $admin_pass = (string)($post['admin_pass'] ?? '');
  if (!$base_url || !$db_host || !$db_name || !$db_user){ $error = 'Please fill in required fields.'; return false; }

  $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
  if ($mysqli->connect_errno){ $error = 'DB connection failed: ' . $mysqli->connect_error; return false; }
  $mysqli->set_charset('utf8mb4');

  $config = [
    'base_url'=>$base_url,
    'db'=>['host'=>$db_host,'port'=>$db_port,'name'=>$db_name,'user'=>$db_user,'pass'=>$db_pass],
  'ftp'=>['host'=>$ftp_host,'user'=>$ftp_user,'pass'=>$ftp_pass,'path'=>$ftp_path],
    'theme'=>['bg'=>'#0f1115','fg'=>'#e1e6ef','accent'=>'#4cc9f0'],
    'security'=>['session_name'=>'AL_SESS','csrf'=>true],
    'general'=>['admin_redirect_login'=>true],
    'super_admins'=>[],
  ];
  $configPhp = "<?php\n$".'AFTERLIGHT_CONFIG'." = ".var_export($config, true).";\n";
  @mkdir($backendDir, 0775, true);
  file_put_contents($backendDir . '/config.php', $configPhp);
  @chmod($backendDir . '/config.php', 0640);

  // Run schema migrations using the verified installer connection so we can surface errors
  require_once $backendDir . '/db/migrate.php';
  $schemaFile = $backendDir . '/db/schema.sql';
  if (!file_exists($schemaFile)) { $error = 'Schema file missing.'; return false; }
  $sql = file_get_contents($schemaFile);
  if (!$mysqli->multi_query($sql)) { $error = 'Schema migration failed: ' . $mysqli->error; return false; }
  while ($mysqli->more_results() && $mysqli->next_result()) { /* flush */ }
  // Apply post-migration upgrades
  @afterlight_schema_upgrades($mysqli);

  // Seed admin user directly using installer connection, avoid API bootstrap during install
  $u = $mysqli->real_escape_string($admin_user);
  $h = $mysqli->real_escape_string(password_hash($admin_pass, PASSWORD_DEFAULT));
  $okInsert = $mysqli->query("INSERT IGNORE INTO users (username,email,passhash,role) VALUES ('$u','admin@example.com','$h','super_admin')");
  if ($okInsert === false) { $error = 'Failed to create admin user: ' . $mysqli->error; return false; }
  $adminId = $mysqli->insert_id ?: (function() use ($mysqli,$u){ $r=$mysqli->query("SELECT id FROM users WHERE username='".$mysqli->real_escape_string($u)."' LIMIT 1"); return ($r&&$r->num_rows)?intval($r->fetch_assoc()['id']):0; })();
  if ($adminId <= 0) { $error = 'Failed to create admin user (no ID returned).'; return false; }
  // Update config with super admin id
  $cfgPath = $backendDir . '/config.php';
  if (file_exists($cfgPath)){
    include $cfgPath; $cfg = $AFTERLIGHT_CONFIG; $cfg['super_admins'] = array_values(array_unique(array_merge($cfg['super_admins']??[], [$adminId])));
    $configPhp2 = "<?php\n$".'AFTERLIGHT_CONFIG'." = ".var_export($cfg, true).";\n";
    file_put_contents($cfgPath, $configPhp2);
  }

  return true;
}
