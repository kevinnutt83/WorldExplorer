<?php
header('Content-Type: application/json');
// Standalone installer check endpoint: do NOT include utils.php or config.php
// POST { host, port, name, user, pass }
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Method not allowed']); http_response_code(405); exit; }
$raw = file_get_contents('php://input'); $b = json_decode($raw, true) ?: [];
$host = trim($b['host'] ?? '');
$port = intval($b['port'] ?? 3306);
$name = trim($b['name'] ?? '');
$user = trim($b['user'] ?? '');
$pass = (string)($b['pass'] ?? '');
if (!$host || !$name || !$user){ echo json_encode(['ok'=>false,'error'=>'Missing required fields']); exit; }
$started = microtime(true);
$mysqli = @new mysqli($host, $user, $pass, $name, $port);
if ($mysqli->connect_errno){ echo json_encode(['ok'=>false,'error'=>'DB connection failed: '.$mysqli->connect_error]); exit; }
$ok = $mysqli->ping();
$info = [ 'host_info'=>$mysqli->host_info, 'server_info'=>$mysqli->server_info, 'client_info'=>mysqli_get_client_info(), 'charset'=>$mysqli->character_set_name() ];
$ms = round((microtime(true)-$started)*1000);
$mysqli->close();
echo json_encode(['ok'=>$ok, 'ms'=>$ms, 'info'=>$info]);
