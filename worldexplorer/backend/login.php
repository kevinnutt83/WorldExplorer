<?php
ob_start();

header('Content-Type: application/json');
require_once __DIR__ . '/api/utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ob_clean();
    echo json_encode(['error' => 'POST required']);
    exit;
}

$body = body_json();
$username_or_email = trim($body['u'] ?? '');
$password = $body['p'] ?? '';

if (!$username_or_email || !$password) {
    http_response_code(400);
    ob_clean();
    echo json_encode(['error' => 'Username/email and password required']);
    exit;
}

$conn = db();
$stmt = $conn->prepare("SELECT id, username, email, password, role, verified FROM users WHERE username = ? OR email = ?");
$stmt->bind_param('ss', $username_or_email, $username_or_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(401);
    ob_clean();
    echo json_encode(['error' => 'Invalid credentials']);
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

if (!password_verify($password, $user['password'])) {
    http_response_code(401);
    ob_clean();
    echo json_encode(['error' => 'Invalid credentials']);
    exit;
}

if (!$user['verified']) {
    http_response_code(403);
    ob_clean();
    echo json_encode(['error' => 'Please verify your email before logging in']);
    exit;
}

// Start session
session_start();
$_SESSION['user_id'] = $user['id'];

// Return user data (exclude password)
unset($user['password']);
$user['isAdmin'] = is_admin_or_super($user);

ob_clean();
echo json_encode($user);
exit;
