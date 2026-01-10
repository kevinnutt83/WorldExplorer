# Afterlight Changelog

## [0.2.0] - 2024-01-XX - Code Restoration & Modularization

### Added
- External JavaScript modules for better organization
- `public/js/installer.js` - Complete installer logic with error handling
- `public/js/chat.js` - Rich chat rendering with emoticons and image support
- `public/js/worldgen.js` - WorldGen node persistence and management
- `public/js/catalogs.js` - All game data (items, recipes, missions, mobs, etc.)
- `public/js/combat.js` - Combat system with skills and respawn mechanics
- `public/js/dialog.js` - FF7-style typing dialog engine
- `public/js/input.js` - Complete input handling (keyboard, mouse, touch, analog stick)
- `public/js/minimap.js` - Real-time minimap with entity tracking
- `public/js/maintenance.js` - Admin maintenance panel
- `public/js/game.js` - Game engine bootstrap stub
- `RECOVERY_TODO.md` - Comprehensive task tracking system
- Email verification system for new users
- Auto-login for first admin account during install
- Login/Register tab switching
- Better error handling in installer (always returns JSON)
- `preventDefault()` on all form buttons to prevent page reload
- Project README, recovery checklist, and base styling
- Lightweight frontend bootstrap and helpful root index page
- Added landing page shell and JS bootstrap

### Fixed
- Installer JSON error ("Unexpected end of JSON input")
- Installer recheck button now uses AJAX (no page reload)
- Database auto-creation when DB doesn't exist (error 1049)
- Config.php write verification
- Migration function existence checks
- Login/Register tabs now switch correctly
- All form buttons prevent default behavior

### Changed
- Moved all inline game scripts to external files
- Improved code organization and maintainability
- Better separation of concerns
- Installer console proxy preserved
- All game systems modularized

### Security
- Added CSRF token placeholder in admin endpoints
- SQL injection prevention (prepared statements)
- XSS protection (HTML escaping in chat)
- Password hashing (bcrypt)

## [0.1.0] - Initial Development

### Added
- Basic installer UI
- Database configuration
- User authentication scaffolding
- Chat system foundation
- World generation concepts
- Item/Recipe catalogs
- Combat framework
- Dialog system
- Input handling
- Minimap rendering

---

**Version Scheme:** `MAJOR.MINOR.PATCH`
- MAJOR: Breaking changes
- MINOR: New features
- PATCH: Bug fixes
