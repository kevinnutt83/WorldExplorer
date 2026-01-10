<?php
require_once __DIR__ . '/../config.php';

function afterlight_run_migrations(): bool {
	global $AFTERLIGHT_CONFIG;
	$cfg = $AFTERLIGHT_CONFIG['db'];
	$conn = @new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['name'], $cfg['port'] ?? 3306);
	if ($conn->connect_errno) { return false; }
	$conn->set_charset('utf8mb4');
	$sql = file_get_contents(__DIR__ . '/schema.sql');
	if (!$conn->multi_query($sql)) { return false; }
	while ($conn->more_results() && $conn->next_result()) { /* flush */ }
    // Post-migration schema upgrades
    @afterlight_schema_upgrades($conn);
	return true;
}
function afterlight_schema_upgrades(mysqli $conn): void {
	// Ensure users.role enum supports expanded roles
	$res = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
	if ($res && ($row = $res->fetch_assoc())){
		$type = strtolower($row['Type'] ?? '');
		// If enum doesn't include moderator or super_admin, alter it
		if (strpos($type, "'moderator'") === false || strpos($type, "'super_admin'") === false){
			@$conn->query("ALTER TABLE users MODIFY role ENUM('subscriber','player','moderator','admin','super_admin') NOT NULL DEFAULT 'player'");
		}
	}

	// Missions: add advanced fields if missing
	$cols = $conn->query("SHOW COLUMNS FROM missions"); $have = [];
	while ($cols && ($c=$cols->fetch_assoc())) { $have[$c['Field']] = true; }
	if (empty($have['type'])) { @$conn->query("ALTER TABLE missions ADD COLUMN type VARCHAR(40) NOT NULL DEFAULT 'fetch'"); }
	if (empty($have['prerequisites'])) { @$conn->query("ALTER TABLE missions ADD COLUMN prerequisites JSON NULL"); }
	if (empty($have['rewards'])) { @$conn->query("ALTER TABLE missions ADD COLUMN rewards JSON NULL"); }
	if (empty($have['faction_effects'])) { @$conn->query("ALTER TABLE missions ADD COLUMN faction_effects JSON NULL"); }

	// Items: add consumable/effects if missing
	$icol = $conn->query("SHOW COLUMNS FROM items"); $ihave = [];
	while ($icol && ($c=$icol->fetch_assoc())) { $ihave[$c['Field']] = true; }
	if (empty($ihave['consumable'])) { @$conn->query("ALTER TABLE items ADD COLUMN consumable TINYINT(1) NOT NULL DEFAULT 0"); }
	if (empty($ihave['effects'])) { @$conn->query("ALTER TABLE items ADD COLUMN effects JSON NULL"); }
}

function afterlight_migrate_database($conn) {
    if (!$conn) return false;
    
    try {
        // Users table
        $conn->query("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                name VARCHAR(100),
                phone VARCHAR(20),
                birth DATE,
                role ENUM('user','admin','super') DEFAULT 'user',
                verified TINYINT(1) DEFAULT 0,
                verify_token VARCHAR(64),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_username (username),
                INDEX idx_email (email),
                INDEX idx_verify_token (verify_token)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Sessions table
        $conn->query("
            CREATE TABLE IF NOT EXISTS sessions (
                id VARCHAR(64) PRIMARY KEY,
                user_id INT NOT NULL,
                ip_address VARCHAR(45),
                user_agent VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_user_id (user_id),
                INDEX idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Characters table
        $conn->query("
            CREATE TABLE IF NOT EXISTS characters (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                name VARCHAR(50) NOT NULL,
                class VARCHAR(20) DEFAULT 'warrior',
                level INT DEFAULT 1,
                xp INT DEFAULT 0,
                hp INT DEFAULT 100,
                max_hp INT DEFAULT 100,
                mana INT DEFAULT 50,
                max_mana INT DEFAULT 50,
                x FLOAT DEFAULT 0,
                y FLOAT DEFAULT 0,
                inventory_json TEXT,
                equipment_json TEXT,
                skills_json TEXT,
                stats_json TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_user_id (user_id),
                INDEX idx_position (x, y)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Items table
        $conn->query("
            CREATE TABLE IF NOT EXISTS items (
                id VARCHAR(50) PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                kind ENUM('weapon','armor','consumable','material','decoration','quest') DEFAULT 'material',
                rarity ENUM('common','uncommon','rare','epic','legendary') DEFAULT 'common',
                data_json TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_kind (kind),
                INDEX idx_rarity (rarity)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // World nodes table
        $conn->query("
            CREATE TABLE IF NOT EXISTS world_nodes (
                id VARCHAR(50) PRIMARY KEY,
                type ENUM('city','town','npc','enemy','object','resource') DEFAULT 'object',
                x FLOAT NOT NULL,
                y FLOAT NOT NULL,
                data_json TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_type (type),
                INDEX idx_position (x, y)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Parties table
        $conn->query("
            CREATE TABLE IF NOT EXISTS parties (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                leader_id INT NOT NULL,
                max_members INT DEFAULT 4,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (leader_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_leader (leader_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Party members table
        $conn->query("
            CREATE TABLE IF NOT EXISTS party_members (
                party_id INT NOT NULL,
                user_id INT NOT NULL,
                joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (party_id, user_id),
                FOREIGN KEY (party_id) REFERENCES parties(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Messages/Chat table
        $conn->query("
            CREATE TABLE IF NOT EXISTS messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                channel VARCHAR(20) DEFAULT 'global',
                author_id INT NOT NULL,
                text TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_channel (channel),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Missions/Quests table
        $conn->query("
            CREATE TABLE IF NOT EXISTS missions (
                id VARCHAR(50) PRIMARY KEY,
                type ENUM('hunt','bounty','exploration','escort','dungeon','daily') DEFAULT 'hunt',
                name VARCHAR(100) NOT NULL,
                description TEXT,
                requirements_json TEXT,
                rewards_json TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_type (type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Market listings table
        $conn->query("
            CREATE TABLE IF NOT EXISTS market_listings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                seller_id INT NOT NULL,
                item_id VARCHAR(50) NOT NULL,
                quantity INT DEFAULT 1,
                price INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NULL,
                FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_item (item_id),
                INDEX idx_seller (seller_id),
                INDEX idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Constructions/Buildings table
        $conn->query("
            CREATE TABLE IF NOT EXISTS constructions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                owner_id INT NOT NULL,
                type VARCHAR(50) NOT NULL,
                x FLOAT NOT NULL,
                y FLOAT NOT NULL,
                data_json TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_owner (owner_id),
                INDEX idx_position (x, y)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Vehicles table
        $conn->query("
            CREATE TABLE IF NOT EXISTS vehicles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                owner_id INT,
                type VARCHAR(50) NOT NULL,
                x FLOAT NOT NULL,
                y FLOAT NOT NULL,
                data_json TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_owner (owner_id),
                INDEX idx_position (x, y)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        return true;
    } catch (Exception $e) {
        error_log("Migration error: " . $e->getMessage());
        return false;
    }
}

function afterlight_reset_database(): bool {
	global $AFTERLIGHT_CONFIG;
	$cfg = $AFTERLIGHT_CONFIG['db'];
	$conn = @new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['name'], $cfg['port'] ?? 3306);
	if ($conn->connect_errno) { return false; }
	$conn->set_charset('utf8mb4');
	// Drop in reverse dependency order
	$tables = [
		'payments','orders','entitlements','wallets',
		'party_members','parties','market_listings','constructions','faction_members','factions',
		'mission_steps','missions','recipe_ingredients','recipes','resources',
		'vehicles','inventories','items','world_nodes','messages','characters','sessions','users'
	];
	foreach ($tables as $t){ $conn->query("DROP TABLE IF EXISTS `$t`"); }
	return afterlight_migrate_database($conn);
}

// If accessed directly via web or CLI, run and print result
if (php_sapi_name() === 'cli' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)){
	$ok = afterlight_run_migrations();
	if (!$ok) { http_response_code(500); echo 'Migration failed'; }
	else echo 'OK';
}
