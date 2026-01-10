<?php
header('Content-Type: application/json');
require_once __DIR__ . '/utils.php';

$method = $_SERVER['REQUEST_METHOD'];

// Ensure table exists (idempotent)
function chat_ensure_tables(){
  $c = db();
  $c->query("CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel VARCHAR(64) NOT NULL,
    user_id INT NULL,
    char_id INT NULL,
    username VARCHAR(64) NULL,
    message TEXT NOT NULL,
    ts BIGINT NOT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  // Helpful index for channel queries
  @mysqli_query($c, "CREATE INDEX idx_messages_channel_ts ON messages(channel, ts)");
}
chat_ensure_tables();

if ($method === 'POST'){
  require_auth();
  $b = body_json(); $action = $b['action'] ?? 'send';
  if ($action === 'send'){
    if (!rate_limit('chat_send', 30)) { http_response_code(429); json_out(['ok'=>false,'error'=>'Slow down']); exit; }
    $u = authed_user(); $uid = intval($u['id']);
    $channel = esc(trim($b['channel'] ?? 'global'));
    $msg = trim($b['message'] ?? ''); if ($msg===''){ json_out(['ok'=>false,'error'=>'Empty']); exit; }
    // Resolve current character id and username
    $cid = null; $cname = $u['username'] ?? 'user';
    $rc = db()->query("SELECT c.id, u.username FROM characters c JOIN users u ON u.id=c.user_id WHERE c.user_id=$uid ORDER BY c.id DESC LIMIT 1");
    if ($rc && $rc->num_rows){ $row=$rc->fetch_assoc(); $cid = intval($row['id']); $cname = $row['username'] ?: $cname; }
    $ts = round(microtime(true)*1000);
    $c = db(); $msgEsc = esc($msg);
    $c->query("INSERT INTO messages (channel,user_id,char_id,username,message,ts) VALUES ('$channel',$uid,"
      .($cid?intval($cid):'NULL').",'".esc($cname)."','$msgEsc',$ts)");
    json_out(['ok'=>true,'ts'=>$ts]); exit;
  }
  if ($action === 'purge'){
    // Admin-only: purge a channel (for moderation)
    $u = authed_user(); if (!is_admin_or_super($u)){ http_response_code(403); json_out(['ok'=>false,'error'=>'Admin required']); exit; }
    $channel = esc(trim($b['channel'] ?? 'global'));
    db()->query("DELETE FROM messages WHERE channel='$channel'");
    json_out(['ok'=>true]); exit;
  }
  http_response_code(400); json_out(['ok'=>false,'error'=>'Unknown action']); exit;
}

if ($method === 'GET'){
  $act = $_GET['action'] ?? 'list';
  $channel = esc(trim($_GET['channel'] ?? 'global'));
  if ($act === 'list' || $act==='history'){
    $since = isset($_GET['since']) ? max(0, intval($_GET['since'])) : 0;
    $limit = 50;
    if ($since > 0){
      $res = db()->query("SELECT channel, username, message, ts FROM messages WHERE channel='$channel' AND ts > $since ORDER BY ts ASC LIMIT $limit");
    } else {
      $res = db()->query("SELECT channel, username, message, ts FROM (SELECT channel, username, message, ts FROM messages WHERE channel='$channel' ORDER BY ts DESC LIMIT $limit) x ORDER BY ts ASC");
    }
    $rows=[]; while ($res && ($r=$res->fetch_assoc())) $rows[]=$r; json_out(['ok'=>true,'messages'=>$rows]); exit;
  }
}

http_response_code(404); json_out(['ok'=>false,'error'=>'Not found']);
