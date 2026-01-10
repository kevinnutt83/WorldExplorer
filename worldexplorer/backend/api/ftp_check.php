<?php
header('Content-Type: application/json');
// Standalone FTP check for installer: do NOT include utils.php or config.php
// POST { host, user, pass, port?, passive?, path? }
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Method not allowed']); http_response_code(405); exit; }
if (!function_exists('ftp_connect')){ echo json_encode(['ok'=>false,'error'=>'FTP extension not enabled']); exit; }
$raw = file_get_contents('php://input'); $b = json_decode($raw, true) ?: [];
$host = trim($b['host'] ?? '');
$user = trim($b['user'] ?? '');
$pass = (string)($b['pass'] ?? '');
$port = intval($b['port'] ?? 21);
$passive = !!($b['passive'] ?? true);
$path = trim($b['path'] ?? '');
if (!$host || !$user) { echo json_encode(['ok'=>false,'error'=>'Missing required fields']); exit; }
$conn = @ftp_connect($host, $port, 10);
if (!$conn){ echo json_encode(['ok'=>false,'error'=>'Connect failed']); exit; }
$login = @ftp_login($conn, $user, $pass);
if (!$login){ ftp_close($conn); echo json_encode(['ok'=>false,'error'=>'Login failed']); exit; }
@ftp_pasv($conn, $passive);
$info = [ 'host'=>$host, 'port'=>$port, 'passive'=>$passive ];
if ($path !== ''){
  // Try to verify the path exists by attempting to change directory
  if (!@ftp_chdir($conn, $path)){
    ftp_close($conn);
    echo json_encode(['ok'=>false,'error'=>'Path not accessible','info'=>$info]);
    exit;
  }
}
ftp_close($conn);
echo json_encode(['ok'=>true,'info'=>$info]);
