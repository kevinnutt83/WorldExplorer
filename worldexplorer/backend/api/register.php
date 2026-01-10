<?php
header('Content-Type: application/json');
require_once __DIR__ . '/utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    json_out(['ok'=>false,'error'=>'POST required']);
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
    json_out(['ok'=>false,'error'=>'Username, email, and password required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    json_out(['ok'=>false,'error'=>'Invalid email']);
    exit;
}

$conn = db();

// Check if username or email already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE username=? OR email=?");
$stmt->bind_param('ss', $username, $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    http_response_code(409);
    json_out(['ok'=>false,'error'=>'Username or email already exists']);
    exit;
}
$stmt->close();

// Check if this is the first user (skip verification for first admin)
$countStmt = $conn->query("SELECT COUNT(*) as cnt FROM users");
$row = $countStmt->fetch_assoc();
$isFirstUser = ($row['cnt'] == 0);
$needsVerification = !$isFirstUser;

// Generate verification token if needed
$verifyToken = $needsVerification ? bin2hex(random_bytes(32)) : null;
$verified = $isFirstUser ? 1 : 0;
$role = $isFirstUser ? 'admin' : 'user';

$hash = password_hash($password, PASSWORD_BCRYPT);
$stmt = $conn->prepare("INSERT INTO users (username, email, password, name, phone, birth, role, verified, verify_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param('sssssssss', $username, $email, $hash, $name, $phone, $birth, $role, $verified, $verifyToken);

if (!$stmt->execute()) {
    http_response_code(500);
    json_out(['ok'=>false,'error'=>'Registration failed']);
    exit;
}

$userId = $stmt->insert_id;
$stmt->close();

// Send verification email if needed
if ($needsVerification && $verifyToken) {
    $cfg = $AFTERLIGHT_CONFIG ?? [];
    $baseUrl = $cfg['base_url'] ?? '';
    $verifyLink = $baseUrl . '?verify=' . $verifyToken;
    
    $subject = 'Verify your Afterlight account';
    $message = "Welcome to Afterlight!\n\nPlease click the link below to verify your email:\n\n" . $verifyLink . "\n\nIf you didn't create this account, please ignore this email.";
    $headers = 'From: noreply@afterlight.game' . "\r\n" . 'Reply-To: noreply@afterlight.game';
    
    @mail($email, $subject, $message, $headers);
}

// Auto-login first user only
if ($isFirstUser) {
    session_start();
    $_SESSION['user_id'] = $userId;
    $user = ['id'=>$userId,'username'=>$username,'email'=>$email,'role'=>$role,'verified'=>1];
    json_out(['ok'=>true,'user'=>$user,'needsVerification'=>false]);
} else {
    json_out(['ok'=>true,'needsVerification'=>true]);
}
