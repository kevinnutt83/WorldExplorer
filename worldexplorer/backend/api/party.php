<?php
header('Content-Type: application/json');
require_once __DIR__ . '/utils.php';

// Ensure tables exist for fallback server-based party sync
function party_ensure_tables(){
  $c = db();
  $c->query("CREATE TABLE IF NOT EXISTS parties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room VARCHAR(64) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  $c->query("CREATE TABLE IF NOT EXISTS party_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    party_id INT NOT NULL,
    user_id INT NOT NULL,
    char_id INT NULL,
    role VARCHAR(16) DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_member (party_id, user_id),
    CONSTRAINT fk_pm_party FOREIGN KEY (party_id) REFERENCES parties(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  $c->query("CREATE TABLE IF NOT EXISTS party_positions (
    party_id INT NOT NULL,
    user_id INT NOT NULL,
    char_id INT NULL,
    x INT NOT NULL,
    y INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (party_id, user_id),
    CONSTRAINT fk_pp_party FOREIGN KEY (party_id) REFERENCES parties(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}
party_ensure_tables();

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST'){
  require_auth(); $b = body_json(); $action = $b['action'] ?? '';
  $u = authed_user(); $uid = intval($u['id']);
  $conn = db();

  if ($action === 'host' || $action === 'join'){
    $room = trim($b['room'] ?? 'afterlight'); if ($room===''){ json_out(['ok'=>false,'error'=>'Missing room']); exit; }
    $roomEsc = esc($room);
    $conn->query("INSERT IGNORE INTO parties (room) VALUES ('$roomEsc')");
    $res = $conn->query("SELECT id FROM parties WHERE room='$roomEsc' LIMIT 1");
    $pid = ($res && $res->num_rows) ? intval($res->fetch_assoc()['id']) : 0;
    $rc = $conn->query("INSERT INTO party_members (party_id,user_id,role) VALUES ($pid,$uid,'member') ON DUPLICATE KEY UPDATE role=VALUES(role)");
    json_out(['ok'=>true,'party_id'=>$pid]); exit;
  }
  if ($action === 'leave'){
    $conn->query("DELETE FROM party_members WHERE user_id=$uid");
    json_out(['ok'=>true]); exit;
  }
  if ($action === 'pos'){
    $room = trim($b['room'] ?? 'afterlight'); $roomEsc = esc($room);
    $res = $conn->query("SELECT id FROM parties WHERE room='$roomEsc' LIMIT 1"); if (!$res || !$res->num_rows){ json_out(['ok'=>false,'error'=>'No room']); exit; }
    $pid = intval($res->fetch_assoc()['id']);
    $x = intval($b['x'] ?? 0); $y = intval($b['y'] ?? 0);
    // try find current character
    $rc = $conn->query("SELECT id FROM characters WHERE user_id=$uid ORDER BY id DESC LIMIT 1"); $cid = ($rc&&$rc->num_rows)?intval($rc->fetch_assoc()['id']):null;
    $cidSql = $cid ? (string)$cid : 'NULL';
    $conn->query("INSERT INTO party_positions (party_id,user_id,char_id,x,y) VALUES ($pid,$uid,$cidSql,$x,$y) ON DUPLICATE KEY UPDATE char_id=VALUES(char_id), x=VALUES(x), y=VALUES(y)");
    json_out(['ok'=>true]); exit;
  }
  http_response_code(400); json_out(['ok'=>false,'error'=>'Unknown action']); exit;
}

if ($method === 'GET'){
  $act = $_GET['action'] ?? '';
  if ($act === 'positions'){
    $room = esc(trim($_GET['room'] ?? 'afterlight'));
    $res = db()->query("SELECT p.user_id, p.char_id, p.x, p.y, CONCAT('u',p.user_id) AS id FROM party_positions p JOIN parties r ON r.id=p.party_id WHERE r.room='$room' ORDER BY p.user_id ASC");
    $rows=[]; while ($res && ($r=$res->fetch_assoc())){ $rows[] = ['id'=>$r['id'], 'user_id'=>intval($r['user_id']), 'char_id'=>intval($r['char_id']??0), 'x'=>intval($r['x']), 'y'=>intval($r['y'])]; }
    json_out(['ok'=>true,'positions'=>$rows]); exit;
  }
}

http_response_code(404); json_out(['ok'=>false,'error'=>'Not found']);
