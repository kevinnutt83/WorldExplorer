<?php
// Prevent any output before JSON
ob_start();

header('Content-Type: application/json');
require_once __DIR__ . '/api/utils.php';

$user = authed_user();

if (!$user) {
    http_response_code(401);
    ob_clean();
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Add admin flag
$user['isAdmin'] = is_admin_or_super($user);

ob_clean();
echo json_encode($user);
exit;
