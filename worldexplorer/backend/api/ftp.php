<?php
header('Content-Type: application/json');
require_once __DIR__ . '/utils.php';
$u = authed_user(); if (!$u || $u['role'] !== 'admin'){ http_response_code(403); json_out(['ok'=>false,'error'=>'Admin required']); exit; }
$act = $_GET['action'] ?? '';

if ($act === 'test'){
  global $AFTERLIGHT_CONFIG; $f = $AFTERLIGHT_CONFIG['ftp'] ?? [];
  if (!function_exists('ftp_connect')){ json_out(['ok'=>false,'error'=>'FTP extension not enabled']); exit; }
  $conn = @ftp_connect($f['host'] ?? '', 21, 10); if (!$conn){ json_out(['ok'=>false,'error'=>'Connect failed']); exit; }
  $login = @ftp_login($conn, $f['user'] ?? '', $f['pass'] ?? '');
  if (!$login){ json_out(['ok'=>false,'error'=>'Login failed']); ftp_close($conn); exit; }
  ftp_close($conn); json_out(['ok'=>true]); exit;
}

if ($act === 'upload' && $_SERVER['REQUEST_METHOD']==='POST'){
  if (!csrf_check($_POST['csrf'] ?? '')){ http_response_code(400); json_out(['ok'=>false,'error'=>'Bad CSRF']); exit; }
  global $AFTERLIGHT_CONFIG; $f = $AFTERLIGHT_CONFIG['ftp'] ?? [];
  if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK){ json_out(['ok'=>false,'error'=>'No file']); exit; }
  if (!function_exists('ftp_connect')){ json_out(['ok'=>false,'error'=>'FTP extension not enabled']); exit; }
  $conn = @ftp_connect($f['host'] ?? '', 21, 10); if (!$conn){ json_out(['ok'=>false,'error'=>'Connect failed']); exit; }
  $login = @ftp_login($conn, $f['user'] ?? '', $f['pass'] ?? ''); if (!$login){ json_out(['ok'=>false,'error'=>'Login failed']); ftp_close($conn); exit; }
  $remotePath = rtrim($f['path'] ?? '/', '/') . '/' . basename($_FILES['file']['name']);
  $ok = @ftp_put($conn, $remotePath, $_FILES['file']['tmp_name'], FTP_BINARY);
  ftp_close($conn);
  json_out(['ok'=>$ok ? true:false, 'path'=>$ok ? $remotePath: null]); exit;
}

http_response_code(404); json_out(['ok'=>false,'error'=>'Not found']);
