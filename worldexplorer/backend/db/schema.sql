-- Afterlight DB schema (minimal MVP)
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(120) NULL,
  passhash VARCHAR(255) NOT NULL,
  role ENUM('admin','player') NOT NULL DEFAULT 'player',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token VARCHAR(64) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user (user_id),
  CONSTRAINT fk_sess_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS characters (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  name VARCHAR(50) NOT NULL,
  arch VARCHAR(30) NOT NULL,
  level INT NOT NULL DEFAULT 1,
  xp INT NOT NULL DEFAULT 0,
  hp INT NOT NULL DEFAULT 100,
  x INT NOT NULL DEFAULT 100,
  y INT NOT NULL DEFAULT 100,
  data JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user (user_id),
  CONSTRAINT fk_char_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  type VARCHAR(40) NOT NULL,
  rarity VARCHAR(20) NOT NULL,
  data JSON NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  char_id INT NOT NULL,
  item_id INT NOT NULL,
  qty INT NOT NULL DEFAULT 1,
  data JSON NULL,
  KEY idx_char (char_id),
  KEY idx_item (item_id),
  CONSTRAINT fk_inv_char FOREIGN KEY (char_id) REFERENCES characters(id) ON DELETE CASCADE,
  CONSTRAINT fk_inv_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS world_nodes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kind VARCHAR(40) NOT NULL,
  x INT NOT NULL,
  y INT NOT NULL,
  data JSON NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS messages (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  from_user INT NOT NULL,
  to_user INT NOT NULL,
  body TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_to (to_user),
  CONSTRAINT fk_msg_from FOREIGN KEY (from_user) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_to FOREIGN KEY (to_user) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Resources and crafting
CREATE TABLE IF NOT EXISTS resources (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  tier INT NOT NULL DEFAULT 1,
  data JSON NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS recipes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  result_item_id INT NOT NULL,
  result_qty INT NOT NULL DEFAULT 1,
  data JSON NULL,
  KEY idx_result_item (result_item_id),
  CONSTRAINT fk_recipe_item FOREIGN KEY (result_item_id) REFERENCES items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS recipe_ingredients (
  recipe_id INT NOT NULL,
  item_id INT NOT NULL,
  qty INT NOT NULL DEFAULT 1,
  PRIMARY KEY (recipe_id, item_id),
  CONSTRAINT fk_ing_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
  CONSTRAINT fk_ing_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Missions and factions
CREATE TABLE IF NOT EXISTS missions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(160) NOT NULL,
  description TEXT NULL,
  data JSON NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mission_steps (
  id INT AUTO_INCREMENT PRIMARY KEY,
  mission_id INT NOT NULL,
  step_no INT NOT NULL,
  description TEXT NULL,
  data JSON NULL,
  KEY idx_mission (mission_id),
  CONSTRAINT fk_step_mission FOREIGN KEY (mission_id) REFERENCES missions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mission_progress (
  char_id INT NOT NULL,
  mission_id INT NOT NULL,
  current_step INT NOT NULL DEFAULT 0,
  status ENUM('active','completed','failed') NOT NULL DEFAULT 'active',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (char_id, mission_id),
  CONSTRAINT fk_mp_char FOREIGN KEY (char_id) REFERENCES characters(id) ON DELETE CASCADE,
  CONSTRAINT fk_mp_mission FOREIGN KEY (mission_id) REFERENCES missions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS factions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  description TEXT NULL,
  data JSON NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS faction_members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  faction_id INT NOT NULL,
  char_id INT NOT NULL,
  role VARCHAR(40) NOT NULL DEFAULT 'member',
  UNIQUE KEY uniq_member (faction_id, char_id),
  CONSTRAINT fk_fm_faction FOREIGN KEY (faction_id) REFERENCES factions(id) ON DELETE CASCADE,
  CONSTRAINT fk_fm_char FOREIGN KEY (char_id) REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Player constructions (buildings)
CREATE TABLE IF NOT EXISTS constructions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  char_id INT NOT NULL,
  kind VARCHAR(60) NOT NULL,
  x INT NOT NULL,
  y INT NOT NULL,
  data JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_char (char_id),
  CONSTRAINT fk_construct_char FOREIGN KEY (char_id) REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Market listings
CREATE TABLE IF NOT EXISTS market_listings (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  seller_char_id INT NOT NULL,
  item_id INT NOT NULL,
  qty INT NOT NULL,
  price INT NOT NULL,
  status ENUM('active','sold','cancelled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_status (status),
  KEY idx_item (item_id),
  CONSTRAINT fk_ml_char FOREIGN KEY (seller_char_id) REFERENCES characters(id) ON DELETE CASCADE,
  CONSTRAINT fk_ml_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Parties for group play
CREATE TABLE IF NOT EXISTS parties (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  leader_char_id INT NOT NULL,
  data JSON NULL,
  CONSTRAINT fk_party_leader FOREIGN KEY (leader_char_id) REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS party_members (
  party_id INT NOT NULL,
  char_id INT NOT NULL,
  role VARCHAR(40) NOT NULL DEFAULT 'member',
  PRIMARY KEY (party_id, char_id),
  CONSTRAINT fk_pm_party FOREIGN KEY (party_id) REFERENCES parties(id) ON DELETE CASCADE,
  CONSTRAINT fk_pm_char FOREIGN KEY (char_id) REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Vehicles
CREATE TABLE IF NOT EXISTS vehicles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kind VARCHAR(60) NOT NULL,
  x INT NOT NULL,
  y INT NOT NULL,
  occupant_char_id INT NULL,
  owner_char_id INT NULL,
  data JSON NULL,
  CONSTRAINT fk_vehicle_occ FOREIGN KEY (occupant_char_id) REFERENCES characters(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Economy: wallets and payments (monetization)
CREATE TABLE IF NOT EXISTS wallets (
  user_id INT PRIMARY KEY,
  balance INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_wallet_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payments (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  gateway VARCHAR(40) NOT NULL,
  ext_id VARCHAR(128) NULL,
  amount INT NOT NULL,
  currency VARCHAR(10) NOT NULL DEFAULT 'USD',
  status ENUM('created','pending','succeeded','failed','refunded') NOT NULL DEFAULT 'created',
  payload JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL,
  KEY idx_user (user_id),
  KEY idx_gateway (gateway),
  CONSTRAINT fk_payment_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type ENUM('currency','item','vehicle','event') NOT NULL,
  item_id INT NULL,
  qty INT NOT NULL DEFAULT 1,
  amount INT NOT NULL DEFAULT 0,
  currency VARCHAR(10) NOT NULL DEFAULT 'USD',
  status ENUM('created','paid','cancelled','fulfilled') NOT NULL DEFAULT 'created',
  data JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user (user_id),
  KEY idx_type (type),
  CONSTRAINT fk_order_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Entitlements (premium access, vehicles, passes)
CREATE TABLE IF NOT EXISTS entitlements (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  kind VARCHAR(40) NOT NULL,
  ref_id INT NULL,
  data JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user (user_id),
  KEY idx_kind (kind),
  CONSTRAINT fk_ent_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
