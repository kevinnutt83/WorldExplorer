<?php
require_once __DIR__ . '/utils.php';

$token = $_GET['token'] ?? '';
if (!$token) {
    header('Location: ../../index.php?error=invalid_token');
    exit;
}

$conn = db();
$stmt = $conn->prepare("SELECT id, username, email FROM users WHERE verify_token=? AND verified=0");
$stmt->bind_param('s', $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: ../../index.php?error=invalid_token');
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

// Mark verified
$stmt = $conn->prepare("UPDATE users SET verified=1, verify_token=NULL WHERE id=?");
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$stmt->close();

// Auto-login
session_start();
$_SESSION['user_id'] = $user['id'];

// Redirect
header('Location: ../../index.php?verified=1');
exit;
