<?php
ob_start();

header('Content-Type: application/json');
require_once __DIR__ . '/api/utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ob_clean();
    echo json_encode(['ok'=>false,'error'=>'POST required']);
    exit;
}

$b = body_json();
$username = trim($b['username'] ?? '');
$email = trim($b['email'] ?? '');
$password = $b['password'] ?? '';
$name = trim($b['name'] ?? '');
$phone = trim($b['phone'] ?? '');
$birth = trim($b['birth'] ?? '');

if (!$username || !$email || !$password) {
    http_response_code(400);
    ob_clean();
    echo json_encode(['ok'=>false,'error'=>'Username, email, and password required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    ob_clean();
    echo json_encode(['ok'=>false,'error'=>'Invalid email']);
    exit;
}

$conn = db();

// Check existing
$stmt = $conn->prepare("SELECT id FROM users WHERE username=? OR email=?");
if (!$stmt) {
    http_response_code(500);
    ob_clean();
    echo json_encode(['ok'=>false,'error'=>'Registration unavailable: database schema missing. Run installer/migrate.']);
    exit;
}
$stmt->bind_param('ss', $username, $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    http_response_code(409);
    ob_clean();
    echo json_encode(['ok'=>false,'error'=>'Username or email already exists']);
    exit;
}
$stmt->close();

// First user becomes admin, others player
$countStmt = $conn->query("SELECT COUNT(*) as cnt FROM users");
$row = $countStmt->fetch_assoc();
$isFirstUser = ($row['cnt'] == 0);
$role = $isFirstUser ? 'admin' : 'player';

$hash = password_hash($password, PASSWORD_BCRYPT);
$stmt = $conn->prepare("INSERT INTO users (username, email, passhash, role, name, phone, birth) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param('sssssss', $username, $email, $hash, $role, $name, $phone, $birth);

if (!$stmt->execute()) {
    http_response_code(500);
    ob_clean();
    echo json_encode(['ok'=>false,'error'=>'Registration failed']);
    exit;
}

$userId = $stmt->insert_id;
$stmt->close();

// Auto-login first user
if ($isFirstUser) {
    session_start();
    $_SESSION['user_id'] = $userId;
    $user = ['id'=>$userId,'username'=>$username,'email'=>$email,'role'=>$role,'verified'=>1,'isAdmin'=>true];
    ob_clean();
    echo json_encode(['ok'=>true,'user'=>$user,'needsVerification'=>false]);
} else {
    ob_clean();
    echo json_encode(['ok'=>true,'needsVerification'=>false]);
}
exit;
