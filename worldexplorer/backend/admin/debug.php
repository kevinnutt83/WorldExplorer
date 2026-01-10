<?php
header('Content-Type: text/plain; charset=utf-8');
echo "=== DIAGNOSTIC ===\n";
echo "Script: " . $_SERVER['SCRIPT_NAME'] . "\n";
echo "PHP: " . PHP_VERSION . "\n";

$configPath = __DIR__ . '/config.php';
echo "Config: " . (file_exists($configPath) ? 'EXISTS' : 'MISSING') . "\n";

if (file_exists($configPath)) {
    require_once $configPath;
    global $AFTERLIGHT_CONFIG;
    if (isset($AFTERLIGHT_CONFIG['db'])) {
        $db = $AFTERLIGHT_CONFIG['db'];
        $conn = @new mysqli($db['host'], $db['user'], $db['pass'], $db['name'], $db['port'] ?? 3306);
        echo "DB: " . ($conn->connect_errno ? "ERROR" : "OK") . "\n";
        if (!$conn->connect_errno) {
            $result = $conn->query("SELECT COUNT(*) as cnt FROM users");
            if ($result) {
                $row = $result->fetch_assoc();
                echo "Users: " . $row['cnt'] . "\n";
            }
        }
    }
}
echo "\n=== END ===\n";