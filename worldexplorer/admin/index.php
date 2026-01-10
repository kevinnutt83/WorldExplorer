<?php
require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/api/utils.php';
// Compute base path relative to admin
$basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($basePath === '/' || $basePath === '\\' || $basePath === '.') { $basePath = ''; }
$u = authed_user(); if (!$u || !is_admin_or_super($u)){ header('Location: ' . ($basePath?:'/')); exit; }
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Afterlight Admin</title>
  <meta name="al-csrf" content="<?php echo htmlspecialchars(csrf_token()); ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo $basePath; ?>/admin/css/admin.css">
</head>
<body>
<nav class="navbar navbar-dark bg-dark"><div class="container-fluid d-flex justify-content-between">
  <span class="navbar-brand">Afterlight Admin</span>
  <div>
    <a class="btn btn-sm btn-outline-light" href="<?php echo $basePath; ?>/">View Site</a>
  </div>
</div></nav>
<div class="container-fluid py-3">
  <div class="row g-3">
    <div class="col-md-3">
      <div class="list-group" id="admin-nav">
        <a href="#" data-panel="system" class="list-group-item list-group-item-action active">System</a>
        <a href="#" data-panel="theme" class="list-group-item list-group-item-action">Theme</a>
  <a href="#" data-panel="recipes" class="list-group-item list-group-item-action">Recipes</a>
  <a href="#" data-panel="items" class="list-group-item list-group-item-action">Items</a>
  <a href="#" data-panel="world" class="list-group-item list-group-item-action">World</a>
        <a href="#" data-panel="users" class="list-group-item list-group-item-action">Users</a>
  <a href="#" data-panel="assets" class="list-group-item list-group-item-action">Assets</a>
  <a href="#" data-panel="factions" class="list-group-item list-group-item-action">Factions</a>
  <a href="#" data-panel="missions" class="list-group-item list-group-item-action">Missions</a>
        <a href="#" data-panel="monetization" class="list-group-item list-group-item-action">Monetization</a>
        <a href="#" data-panel="logs" class="list-group-item list-group-item-action">Logs</a>
        <a href="#" data-panel="maintenance" class="list-group-item list-group-item-action">Maintenance</a>
      </div>
    </div>
    <div class="col-md-9">
      <div id="panel-system" class="card"><div class="card-header d-flex justify-content-between align-items-center"><span>System</span><button id="btn-refresh-health" class="btn btn-sm btn-outline-light">Refresh</button></div><div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <div class="card bg-dark text-light">
              <div class="card-header">Connections</div>
              <div class="card-body">
                <div>DB: <span id="stat-db" class="badge bg-secondary">n/a</span> <small id="stat-db-ms" class="text-muted"></small></div>
                <div class="small mt-2" id="stat-db-info"></div>
                <div class="mt-2">Realtime: <span id="stat-rt" class="badge bg-secondary">n/a</span></div>
                <div class="mt-2">Session: <code id="stat-sess">n/a</code></div>
                <div class="mt-2">PHP: <code id="stat-php">n/a</code></div>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card bg-dark text-light">
              <div class="card-header">General Settings</div>
              <div class="card-body">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="opt-admin-redirect">
                  <label class="form-check-label" for="opt-admin-redirect">Redirect admins to Dashboard on login</label>
                </div>
                <div class="mt-3 d-grid"><button id="btn-save-general" class="btn btn-primary">Save Settings</button></div>
                <div id="general-status" class="small mt-2"></div>
              </div>
            </div>
          </div>
        </div>
      </div></div>

      <div id="panel-theme" class="card d-none"><div class="card-header">Theme</div><div class="card-body">
        <div class="row g-2">
          <div class="col-md-4"><label class="form-label">Background</label><input id="theme-bg" type="color" class="form-control form-control-color" value="#0f1115"></div>
          <div class="col-md-4"><label class="form-label">Foreground</label><input id="theme-fg" type="color" class="form-control form-control-color" value="#e1e6ef"></div>
          <div class="col-md-4"><label class="form-label">Accent</label><input id="theme-accent" type="color" class="form-control form-control-color" value="#4cc9f0"></div>
          <div class="col-12 d-grid"><button id="save-theme" class="btn btn-primary">Save Theme</button></div>
        </div>
      </div></div>

      <div id="panel-items" class="card d-none"><div class="card-header d-flex justify-content-between align-items-center"><span>Items</span><button id="btn-refresh-items" class="btn btn-sm btn-outline-light">Refresh</button></div><div class="card-body">
        <form id="form-item" class="row g-2 mb-3">
          <div class="col-md-3"><input name="name" class="form-control" placeholder="Name" required></div>
          <div class="col-md-3"><input name="type" class="form-control" placeholder="Type" required></div>
          <div class="col-md-3"><input name="rarity" class="form-control" placeholder="Rarity" value="common" required></div>
          <div class="col-md-3 d-grid"><button class="btn btn-primary">Add Item</button></div>
        </form>
        <div id="items-list" class="table-responsive">
          <table class="table table-dark table-sm"><thead><tr><th>ID</th><th>Name</th><th>Type</th><th>Rarity</th><th>Consumable</th><th>Effects (JSON)</th><th></th></tr></thead><tbody></tbody></table>
        </div>
      </div></div>

      <div id="panel-recipes" class="card d-none"><div class="card-header d-flex justify-content-between align-items-center"><span>Recipes</span><button id="btn-refresh-recipes" class="btn btn-sm btn-outline-light">Refresh</button></div><div class="card-body">
        <form id="form-recipe" class="row g-2 mb-3">
          <div class="col-md-4"><input name="name" class="form-control" placeholder="Name" required></div>
          <div class="col-md-4"><input name="result_item_id" class="form-control" placeholder="Result Item ID" required></div>
          <div class="col-md-2"><input name="result_qty" class="form-control" placeholder="Qty" value="1" required></div>
          <div class="col-md-2 d-grid"><button class="btn btn-primary">Add Recipe</button></div>
        </form>
        <div id="recipes-list" class="table-responsive"><table class="table table-dark table-sm"><thead><tr><th>ID</th><th>Name</th><th>Result Item</th><th>Qty</th><th></th></tr></thead><tbody></tbody></table></div>
      </div></div>

      <div id="panel-world" class="card d-none"><div class="card-header d-flex justify-content-between align-items-center"><span>World</span><div class="d-flex gap-2"><button id="btn-gen-world" class="btn btn-sm btn-outline-light">Generate Nodes</button><button id="btn-clear-world" class="btn btn-sm btn-outline-danger">Clear Nodes</button></div></div><div class="card-body">
        <div id="nodes-list" class="table-responsive"><table class="table table-dark table-sm"><thead><tr><th>ID</th><th>Kind</th><th>X</th><th>Y</th><th></th></tr></thead><tbody></tbody></table></div>
      </div></div>

      <div id="panel-users" class="card d-none"><div class="card-header d-flex justify-content-between align-items-center"><span>Users</span><button id="btn-refresh-users" class="btn btn-sm btn-outline-light">Refresh</button></div><div class="card-body">
        <div id="users-list" class="table-responsive"><table class="table table-dark table-sm"><thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Created</th><th></th></tr></thead><tbody></tbody></table></div>
      </div></div>

      <div id="panel-assets" class="card d-none"><div class="card-header">Assets (FTP)</div><div class="card-body">
        <div class="mb-2">
          <button id="btn-ftp-test" class="btn btn-outline-light btn-sm">Test FTP Connection</button>
        </div>
        <form id="form-ftp-upload" class="row g-2" enctype="multipart/form-data">
          <div class="col-md-8"><input name="file" type="file" class="form-control" required></div>
          <div class="col-md-4 d-grid"><button class="btn btn-primary">Upload to FTP</button></div>
        </form>
        <div id="ftp-result" class="mt-2 text-info"></div>
      </div></div>

      <div id="panel-factions" class="card d-none"><div class="card-header d-flex justify-content-between align-items-center"><span>Factions</span><button id="btn-refresh-factions" class="btn btn-sm btn-outline-light">Refresh</button></div><div class="card-body">
        <form id="form-faction" class="row g-2 mb-3">
          <div class="col-md-5"><input name="name" class="form-control" placeholder="Name" required></div>
          <div class="col-md-5"><input name="description" class="form-control" placeholder="Description"></div>
          <div class="col-md-2 d-grid"><button class="btn btn-primary">Add</button></div>
        </form>
        <div id="factions-list" class="table-responsive"><table class="table table-dark table-sm"><thead><tr><th>ID</th><th>Name</th><th></th></tr></thead><tbody></tbody></table></div>
      </div></div>

      <div id="panel-missions" class="card d-none"><div class="card-header d-flex justify-content-between align-items-center"><span>Missions</span><button id="btn-refresh-missions" class="btn btn-sm btn-outline-light">Refresh</button></div><div class="card-body">
        <form id="form-mission" class="row g-2 mb-3">
          <div class="col-md-8"><input name="title" class="form-control" placeholder="Title" required></div>
          <div class="col-md-4 d-grid"><button class="btn btn-primary">Add</button></div>
        </form>
        <div class="row g-3">
          <div class="col-md-6">
            <div id="missions-list" class="table-responsive"><table class="table table-dark table-sm"><thead><tr><th>ID</th><th>Title</th><th>Description</th><th>Type</th><th></th></tr></thead><tbody></tbody></table></div>
          </div>
          <!-- Advanced mission editor modal -->
          <div class="modal fade" id="missionDetailsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
              <div class="modal-content bg-dark text-light">
                <div class="modal-header">
                  <h5 class="modal-title">Mission Details</h5>
                  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  <form id="form-mission-adv" class="row g-3">
                    <input type="hidden" name="id" />
                    <div class="col-md-4">
                      <label class="form-label">Type</label>
                      <select name="type" class="form-select">
                        <option value="fetch">Fetch</option>
                        <option value="collection">Collection</option>
                        <option value="location">Location</option>
                        <option value="harvest">Harvest</option>
                        <option value="assassination">Assassination</option>
                        <option value="conquer">Conquer</option>
                        <option value="escort">Escort</option>
                        <option value="defend">Defend</option>
                        <option value="craft">Craft</option>
                        <option value="delivery">Delivery</option>
                      </select>
                    </div>
                    <div class="col-12">
                      <label class="form-label">Prerequisites (JSON)</label>
                      <textarea name="prerequisites" class="form-control" rows="4" placeholder='{"minLevel":3,"requiresMission":2}'></textarea>
                    </div>
                    <div class="col-12">
                      <label class="form-label">Rewards (JSON)</label>
                      <textarea name="rewards" class="form-control" rows="4" placeholder='{"xp":100,"items":[{"item_id":5,"qty":1}]}'></textarea>
                    </div>
                    <div class="col-12">
                      <label class="form-label">Faction Effects (JSON)</label>
                      <textarea name="faction_effects" class="form-control" rows="4" placeholder='{"faction_id":1,"delta":+10}'></textarea>
                    </div>
                  </form>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                  <button type="button" class="btn btn-primary" id="btn-save-mission-adv">Save</button>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card bg-dark text-light">
              <div class="card-header">Steps for <span id="steps-mission-title">(select a mission)</span></div>
              <div class="card-body">
                <form id="form-step" class="row g-2 mb-2">
                  <input type="hidden" name="mission_id" />
                  <div class="col-md-3"><input name="step_no" type="number" class="form-control" placeholder="#" value="1"></div>
                  <div class="col-md-7"><input name="description" class="form-control" placeholder="Description"></div>
                  <div class="col-md-2 d-grid"><button class="btn btn-secondary">Add</button></div>
                </form>
                <div id="steps-list" class="table-responsive"><table class="table table-dark table-sm"><thead><tr><th>#</th><th>Description</th><th></th></tr></thead><tbody></tbody></table></div>
              </div>
            </div>
          </div>
        </div>
      </div></div>

      <div id="panel-monetization" class="card d-none"><div class="card-header">Monetization</div><div class="card-body">
        <div class="alert alert-info">Configure gateways (sandbox recommended) and currency packs. Non-intrusive in-game UI will surface a Shop button.</div>
        <form id="form-payments" class="row g-3">
          <div class="col-12"><h6>General</h6></div>
          <div class="col-md-3"><label class="form-label">Currency</label><input name="currency" class="form-control" value="USD"></div>
          <div class="col-md-3"><label class="form-label">Test Mode</label><select name="test_mode" class="form-select"><option value="1">Enabled</option><option value="0">Disabled</option></select></div>
          <div class="col-12"><h6>PayPal</h6></div>
          <div class="col-md-4"><label class="form-label">Client ID</label><input name="paypal_client" class="form-control"></div>
          <div class="col-md-4"><label class="form-label">Secret</label><input name="paypal_secret" type="password" class="form-control"></div>
          <div class="col-md-4"><label class="form-label">Environment</label><select name="paypal_env" class="form-select"><option value="sandbox">Sandbox</option><option value="live">Live</option></select></div>
          <div class="col-12"><h6>Authorize.Net</h6></div>
          <div class="col-md-4"><label class="form-label">API Login ID</label><input name="anet_login" class="form-control"></div>
          <div class="col-md-4"><label class="form-label">Transaction Key</label><input name="anet_key" type="password" class="form-control"></div>
          <div class="col-md-4"><label class="form-label">Environment</label><select name="anet_env" class="form-select"><option value="sandbox">Sandbox</option><option value="production">Production</option></select></div>
          <div class="col-12"><h6>Currency Packs</h6></div>
          <div class="col-12">
            <small class="text-muted">Define as JSON array of packs: [{"id":"small","name":"Small Pack","amount":500,"price":499}] (price in cents)</small>
            <textarea name="packs" class="form-control" rows="4">[{"id":"small","name":"Small Pack","amount":500,"price":499},{"id":"medium","name":"Medium Pack","amount":1200,"price":999}]</textarea>
          </div>
          <div class="col-12 d-grid"><button id="btn-save-payments" class="btn btn-primary" type="button">Save Monetization</button></div>
        </form>
        <div id="payments-status" class="mt-2"></div>
      </div></div>

      <div id="panel-logs" class="card d-none"><div class="card-header d-flex justify-content-between align-items-center"><span>Logs</span><button id="btn-refresh-logs" class="btn btn-sm btn-outline-light">Refresh</button></div><div class="card-body">
        <div class="row g-2 align-items-center">
          <div class="col-md-6"><select id="log-file" class="form-select"></select></div>
          <div class="col-md-2"><input id="log-limit" class="form-control" value="500"></div>
          <div class="col-md-2 d-grid"><button id="btn-tail-log" class="btn btn-secondary">Tail</button></div>
        </div>
        <pre id="log-output" class="bg-dark text-light p-2 mt-2" style="max-height:420px; overflow:auto"></pre>
      </div></div>

      <div id="panel-maintenance" class="card d-none"><div class="card-header">Maintenance</div><div class="card-body">
        <div class="alert alert-warning">These actions are destructive. Ensure you have backups.</div>
        <div class="row g-3">
          <div class="col-md-6 d-grid"><button id="btn-reinstall" class="btn btn-danger">Re-run Installer (Reset DB)</button></div>
          <div class="col-md-6 d-grid"><button id="btn-purge" class="btn btn-outline-danger">Purge Generated Data</button></div>
          <div class="col-md-6 d-grid"><button id="btn-upgrade-db" class="btn btn-outline-warning">Run DB Upgrade (Safe)</button></div>
        </div>
        <div id="maint-status" class="mt-3"></div>
      </div></div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.__AL_BASE_PATH__ = <?php echo json_encode($basePath); ?>;</script>
<script src="<?php echo $basePath; ?>/admin/js/admin.js"></script>
</body>
</html>
