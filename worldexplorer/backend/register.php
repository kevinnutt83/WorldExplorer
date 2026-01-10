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

// Check if first user
$countStmt = $conn->query("SELECT COUNT(*) as cnt FROM users");
$row = $countStmt->fetch_assoc();
$isFirstUser = ($row['cnt'] == 0);
$needsVerification = !$isFirstUser;

$verifyToken = $needsVerification ? bin2hex(random_bytes(32)) : null;
$verified = $isFirstUser ? 1 : 0;
$role = $isFirstUser ? 'admin' : 'user';

$hash = password_hash($password, PASSWORD_BCRYPT);
$stmt = $conn->prepare("INSERT INTO users (username, email, password, name, phone, birth, role, verified, verify_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param('sssssssss', $username, $email, $hash, $name, $phone, $birth, $role, $verified, $verifyToken);

if (!$stmt->execute()) {
    http_response_code(500);
    ob_clean();
    echo json_encode(['ok'=>false,'error'=>'Registration failed']);
    exit;
}

$userId = $stmt->insert_id;
$stmt->close();

// Send verification email
if ($needsVerification && $verifyToken) {
    global $AFTERLIGHT_CONFIG;
    $baseUrl = $AFTERLIGHT_CONFIG['base_url'] ?? '';
    $verifyLink = $baseUrl . '/backend/api/verify?token=' . $verifyToken;
    
    $subject = 'Verify your Afterlight account';
    $message = "Welcome to Afterlight!\n\nPlease click the link below to verify your email:\n\n" . $verifyLink . "\n\nIf you didn't create this account, please ignore this email.";
    $headers = 'From: noreply@afterlight.game' . "\r\n" . 'Reply-To: noreply@afterlight.game';
    
    @mail($email, $subject, $message, $headers);
}

// Auto-login first user
if ($isFirstUser) {
    session_start();
    $_SESSION['user_id'] = $userId;
    $user = ['id'=>$userId,'username'=>$username,'email'=>$email,'role'=>$role,'verified'=>1,'isAdmin'=>true];
    ob_clean();
    echo json_encode(['ok'=>true,'user'=>$user,'needsVerification'=>false]);
} else {
    ob_clean();
    echo json_encode(['ok'=>true,'needsVerification'=>true]);
}
exit;
