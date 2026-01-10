# Afterlight Recovery & Market Readiness TODO

## CRITICAL - Server Configuration Issues
- [ ] **BLOCKER**: Backend PHP files returning HTML instead of JSON
  - Issue: Apache/ISPConfig routing ALL requests through index.php
  - Need: Create .htaccess files to prevent rewriting
  - Status: Files created in workspace, need upload and test
- [ ] Fix /backend/me.php endpoint (returns game HTML)
- [ ] Fix /backend/login.php endpoint (returns game HTML)
- [ ] Create diagnostic tools to identify routing issue

## Files Created in Workspace - Ready for Upload
- [x] backend/debug.php - Diagnostic report
- [x] backend/.htaccess - Prevent rewriting in backend
- [x] .htaccess (root) - Exclude backend from rewrites
- [ ] Test these files on server after upload

## Critical Issues (Fix Immediately)
- [x] Restore all missing game scripts in index.php
- [x] Fix installer JSON error
- [x] Create external JavaScript files
- [x] Ensure all backend endpoints exist
- [x] Verify database migrations create all required tables
- [ ] **BLOCKED**: Test login/register flow (waiting on server config fix)
- [ ] **BLOCKED**: Test email verification (waiting on server config fix)

## Backend Files Status
- [x] /backend/db/migrate.php
- [x] /backend/api/utils.php
- [x] /backend/me.php (created but not working due to server routing)
- [x] /backend/login.php (created but not working due to server routing)
- [x] /backend/register.php
- [x] /backend/api/verify.php
- [x] /backend/admin/content.php
- [x] /backend/admin/theme.php
- [x] /backend/admin/reinstall.php
- [x] /backend/admin/upgrade.php
- [x] /backend/admin/purge.php
- [x] backend/debug.php (created in workspace)

## Frontend Files Status
- [x] public/js/installer.js
- [x] public/js/chat.js
- [x] public/js/worldgen.js
- [x] public/js/catalogs.js
- [x] public/js/combat.js
- [x] public/js/dialog.js
- [x] public/js/input.js
- [x] public/js/minimap.js
- [x] public/js/maintenance.js
- [x] public/js/game.js

## Server Configuration Needed
- [ ] Upload backend/.htaccess to server
- [ ] Upload .htaccess (root) to server
- [ ] Test: https://movealong.us/worldexplorer/backend/debug.php
  - Should show: Plain text diagnostic report
  - Currently shows: Game HTML (WRONG)
- [ ] If still broken, check ISPConfig virtual host settings
- [ ] Alternative: Contact hosting provider about Apache rewrites

## Next Actions After Server Fix
1. Verify debug.php returns plain text
2. Test /backend/me.php returns JSON
3. Test login flow
4. Test registration flow
5. Test email verification

## User Requests Tracked
- [x] Don't delete files (lesson learned)
- [x] Manage files in workspace, not chat
- [x] Login/register tabs - default to login tab
- [x] Fix "both forms showing" issue
- [ ] Get login working (blocked by server routing)

## Current Blocker
**ROOT CAUSE**: ISPConfig/Apache is routing `/backend/*.php` requests through `/index.php` instead of executing them directly.

**Evidence**: 
- Accessing `/backend/debug.php` loads the game page
- Console shows: "Non-JSON /backend/me response length: 17266"
- 17266 bytes = full index.php HTML output

**Solution**: 
1. Upload .htaccess files
2. Test diagnostic endpoints
3. If still broken, modify ISPConfig virtual host config

**Status**: WAITING FOR SERVER CONFIGURATION FIX
**Last Updated**: 2024-01-XX

# Recovery TODO
- [ ] Run `/install` to generate `backend/config.php` and seed schema.
- [ ] Verify `/backend/api/health.php?action=stats` (DB/session).
- [ ] Update DB credentials in production; restrict `backend/config.php` perms.
- [ ] Smoke-test admin panels: Items, Missions, World, Monetization.
- [ ] Validate chat/log ingestion rate limits under load.
- [ ] Add frontend build pipeline once Phaser scenes are implemented.
