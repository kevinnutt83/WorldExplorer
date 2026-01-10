# WorldExplorer
An open-world RPG prototype built with PHP + vanilla JS (Phaser-ready).

## Quick start (dev)
1. Install PHP 8.1+ with mysqli/ftp enabled.
2. From this folder run: `php -S localhost:8000 -t .`
3. Open http://localhost:8000/install/ to create `backend/config.php` and seed the DB.
4. Use `/admin/` after creating an admin user.

## Project layout
- `backend/` – APIs, DB schema/migrations, admin endpoints.
- `install/` – installer flow and checks.
- `public/` – client assets (JS, CSS, game stubs).
- `admin/` – admin UI.
- `logs/` – server-side log output (gitignored on deploy).

## Maintenance
- Health check: `/backend/api/health.php?action=stats`
- Admin → Maintenance: DB reset/purge (destructive).
- Payments run in test mode by default; configure via admin Monetization.

## Contributing
- Keep PRs small; include testing/repro notes.
- Prefer JSON-safe edits to `config.php` writers and migrations.

---

# Afterlight - Web-based MMO RPG

A browser-based multiplayer RPG featuring procedural world generation, real-time combat, crafting systems, and persistent multiplayer interactions.

## Features

- **Procedural World Generation** - Infinite explorable terrain with cities, towns, NPCs, and resources
- **Real-time Combat** - Skill-based combat system with active abilities and passive buffs
- **Crafting & Economy** - Extensive item crafting with material gathering and player-driven marketplace
- **Party System** - Team up with other players for dungeons and exploration
- **Character Progression** - Level up, unlock skills, and customize your character
- **Chat System** - Multi-channel chat with emoji support and rich text rendering
- **Admin Tools** - Complete admin panel for server management
- **Email Verification** - Secure account registration with email verification
- **Mobile Responsive** - Touch controls with virtual analog stick

## Tech Stack

### Frontend
- **Vanilla JavaScript** - No framework dependencies
- **Bootstrap 5.3** - UI components and responsive grid
- **Font Awesome 6.5** - Icons
- **Phaser 3** (planned) - Game rendering engine

### Backend
- **PHP 8+** - Server-side logic
- **MySQL 5.7+** - Database storage
- **Session-based Auth** - Secure authentication

## Installation

### Requirements
- PHP 8.0 or higher
- MySQL 5.7 or higher (or MariaDB 10.3+)
- Web server (Apache/Nginx)
- Composer (optional, for dependencies)

### Quick Start

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/afterlight.git
   cd afterlight
   ```

2. **Configure web server**
   - Point document root to the project folder
   - Enable mod_rewrite (Apache) or configure rewrites (Nginx)

3. **Run installer**
   - Navigate to `http://yourserver/index.php`
   - Fill in database credentials
   - Set admin email and username
   - Click "Install"
   - Save the generated admin password!

4. **Login**
   - Use the admin credentials to log in
   - First user is automatically verified and has admin privileges

### Manual Installation

If the web installer fails:

1. **Create database**
   ```sql
   CREATE DATABASE afterlight CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Create config file**
   ```php
   // backend/config.php
   <?php
   $AFTERLIGHT_CONFIG = [
       'base_url' => 'http://yourserver',
       'theme' => [],
       'db' => [
           'host' => 'localhost',
           'user' => 'your_db_user',
           'pass' => 'your_db_password',
           'name' => 'afterlight',
           'port' => 3306
       ]
   ];
   ```

3. **Run migrations**
   ```php
   <?php
   require_once 'backend/config.php';
   require_once 'backend/db/migrate.php';
   
   $conn = new mysqli($AFTERLIGHT_CONFIG['db']['host'], 
                       $AFTERLIGHT_CONFIG['db']['user'],
                       $AFTERLIGHT_CONFIG['db']['pass'],
                       $AFTERLIGHT_CONFIG['db']['name'],
                       $AFTERLIGHT_CONFIG['db']['port']);
   
   afterlight_migrate_database($conn);
   ```

## Directory Structure

```
worldexplorer/
├── index.php                 # Main entry point
├── backend/
│   ├── config.php           # Configuration (created by installer)
│   ├── me.php               # Session endpoint
│   ├── login.php            # Login endpoint
│   ├── register.php         # Registration endpoint
│   ├── db/
│   │   └── migrate.php      # Database migrations
│   ├── api/
│   │   ├── utils.php        # Shared utilities
│   │   └── verify.php       # Email verification handler
│   └── admin/
│       ├── content.php      # Content management
│       ├── theme.php        # Theme customization
│       ├── reinstall.php    # Database reset
│       ├── upgrade.php      # Schema upgrades
│       └── purge.php        # Data cleanup
├── public/
│   ├── css/
│   │   └── styles.css       # Custom styles
│   ├── js/
│   │   ├── installer.js     # Installer logic
│   │   ├── chat.js          # Chat system
│   │   ├── worldgen.js      # World generation
│   │   ├── catalogs.js      # Game data
│   │   ├── combat.js        # Combat engine
│   │   ├── dialog.js        # Dialog system
│   │   ├── input.js         # Input handling
│   │   ├── minimap.js       # Minimap renderer
│   │   ├── maintenance.js   # Admin tools
│   │   └── game.js          # Game bootstrap
│   └── assets/
│       └── images/
│           └── favicon.png
├── CHANGELOG.md             # Version history
├── RECOVERY_TODO.md         # Development tracker
└── README.md                # This file
```

## Configuration

### Theme Customization

Modify theme via admin panel or edit `backend/config.php`:

```php
'theme' => [
    'bg' => '#0f1115',
    'fg' => '#e1e6ef',
    'accent' => '#4cc9f0',
    'font' => 'Inter, system-ui, sans-serif',
    'header_h' => 0,
    'footer_h' => 0,
    'header_bg' => '#0f1115',
    'footer_bg' => '#0f1115'
]
```

### Email Configuration

For production, configure PHP's `mail()` function or use SMTP:

```php
// In backend/register.php, replace mail() with your SMTP library
// Example: PHPMailer, SwiftMailer, etc.
```

## API Endpoints

### Public Endpoints
- `POST /backend/login` - Authenticate user
- `POST /backend/register` - Create account
- `GET /backend/api/verify?token=...` - Verify email

### Authenticated Endpoints
- `GET /backend/me` - Get current user

### Admin Endpoints (require admin role)
- `POST /backend/admin/content` - Manage items/content
- `DELETE /backend/admin/content?id=...` - Delete item
- `POST /backend/admin/theme` - Update theme
- `POST /backend/admin/reinstall` - Reset database
- `POST /backend/admin/upgrade` - Run schema upgrades
- `POST /backend/admin/purge` - Clear ephemeral data

## Database Schema

See `backend/db/migrate.php` for complete schema. Key tables:

- **users** - User accounts
- **sessions** - Active sessions
- **characters** - Player characters
- **items** - Item catalog
- **world_nodes** - Generated world data
- **parties** - Player groups
- **messages** - Chat history
- **missions** - Quest data
- **market_listings** - Player marketplace
- **constructions** - Player-built structures
- **vehicles** - Player vehicles

## Development

### Debug Tools

- **Console (C key)** - View logs
- **Ctrl+F12** - Toggle admin panel
- **window.AL_DEBUG** - Debug helpers:
  ```javascript
  AL_DEBUG.spawnEntity('city', 100, 200)
  AL_DEBUG.startCombat()
  AL_DEBUG.showDialog('Hello world!')
  AL_DEBUG.sendChat('Test message')
  ```

### Adding Content

Items, recipes, missions, etc. are defined in `public/js/catalogs.js`. Edit and reload to see changes.

### Creating Admin Users

Via database:
```sql
UPDATE users SET role='admin' WHERE username='someuser';
```

## Troubleshooting

### Installer Returns Blank Page
- Check PHP error logs
- Ensure `backend/` directory is writable
- Verify MySQL credentials

### Login Not Working
- Clear browser cookies/localStorage
- Check session configuration in `php.ini`
- Verify database connection

### Email Verification Not Sending
- Configure PHP `mail()` or use SMTP library
- Check spam folder
- For development, manually verify:
  ```sql
  UPDATE users SET verified=1 WHERE email='test@example.com';
  ```

## Contributing

1. Fork the repository
2. Create feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open Pull Request

## License

[MIT License](LICENSE) - See LICENSE file for details

## Credits

- **Bootstrap** - UI framework
- **Font Awesome** - Icons
- **Phaser** - Game engine (planned integration)

## Support

- **Issues**: https://github.com/yourusername/afterlight/issues
- **Email**: support@afterlight.game
- **Discord**: https://discord.gg/afterlight

## Roadmap

- [ ] Phaser 3 integration
- [ ] WebSocket real-time multiplayer
- [ ] Mobile app (React Native)
- [ ] Advanced crafting system
- [ ] Guild/clan system
- [ ] PvP arenas
- [ ] Dungeon instances
- [ ] Achievements system
- [ ] Leaderboards
- [ ] Premium cosmetics

---

**Version:** 0.2.0  
**Last Updated:** 2024  
**Status:** Alpha