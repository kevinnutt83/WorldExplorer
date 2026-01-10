<?php
// Inline installer API (fallback when backend/install.php is missing)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['al_install'])) {
    header('Content-Type: application/json');
    try {
        $raw = file_get_contents('php://input');
        $cfg = json_decode($raw, true);
        if (!is_array($cfg)) { 
            echo json_encode(['success'=>false,'error'=>'Invalid JSON payload']); 
            exit; 
        }

        $db = $cfg['db'] ?? [];
        $host = $db['host'] ?? '';
        $user = $db['user'] ?? '';
        $pass = $db['pass'] ?? '';
        $name = $db['name'] ?? '';
        $port = intval($db['port'] ?? 3306);
        
        if (!$host || !$user || !$name) { 
            echo json_encode(['success'=>false,'error'=>'Missing required DB parameters']); 
            exit; 
        }

        // Try DB connection
        $mysqli = @new mysqli($host, $user, $pass, $name, $port);
        if ($mysqli && !$mysqli->connect_errno) {
            $mysqli->close();
        } else {
            $code = $mysqli ? $mysqli->connect_errno : 0;
            if ($mysqli) { @$mysqli->close(); }
            
            // Auto-create database if error 1049
            if ($code === 1049) {
                $tmp = @new mysqli($host, $user, $pass, '', $port);
                if ($tmp && !$tmp->connect_errno) {
                    $escaped = $tmp->real_escape_string($name);
                    @$tmp->query("CREATE DATABASE IF NOT EXISTS `{$escaped}`");
                    @$tmp->close();
                    $mysqli = @new mysqli($host, $user, $pass, $name, $port);
                } else if ($tmp) {
                    @$tmp->close();
                }
            }
            
            if (!$mysqli || $mysqli->connect_errno) {
                $err = $mysqli ? $mysqli->connect_error : 'connection failed';
                echo json_encode(['success'=>false,'error'=>'DB error: '.$err]); 
                exit;
            }
            @$mysqli->close();
        }

        // Write config.php
        $base_url = isset($cfg['base_url']) ? (string)$cfg['base_url'] : '';
        $configDir = __DIR__ . '/backend';
        
        if (!is_dir($configDir)) { 
            if (!@mkdir($configDir, 0775, true)) {
                echo json_encode(['success'=>false,'error'=>'Cannot create backend directory']); 
                exit;
            }
        }
        
        if (!is_writable($configDir)) {
            echo json_encode(['success'=>false,'error'=>'backend directory not writable']); 
            exit;
        }
        
        $file = $configDir . '/config.php';
        $payloadArr = [
            'base_url' => $base_url,
            'theme' => [],
            'db' => ['host'=>$host,'user'=>$user,'pass'=>$pass,'name'=>$name,'port'=>$port]
        ];
        $payload = "<?php\n\$AFTERLIGHT_CONFIG = " . var_export($payloadArr, true) . ";\n";
        
        if (@file_put_contents($file, $payload) === false) { 
            echo json_encode(['success'=>false,'error'=>'Failed to write config.php']); 
            exit; 
        }

        // Run migrations
        if (!file_exists($configDir . '/db/migrate.php')) {
            echo json_encode(['success'=>false,'error'=>'migrate.php not found']); 
            exit;
        }
        
        require_once $configDir . '/db/migrate.php';
        $conn = @new mysqli($host, $user, $pass, $name, $port);
        
        if (!$conn || $conn->connect_errno) {
            echo json_encode(['success'=>false,'error'=>'Cannot reconnect to DB after config write']); 
            exit;
        }
        
        $conn->set_charset('utf8mb4');
        
        if (!function_exists('afterlight_migrate_database')) {
            echo json_encode(['success'=>false,'error'=>'Migration function not found']); 
            exit;
        }
        
        $migrated = afterlight_migrate_database($conn);
        if (!$migrated) {
            echo json_encode(['success'=>false,'error'=>'Database migration failed']); 
            exit;
        }

        // Create admin user
        $admin = $cfg['admin'] ?? [];
        $adminEmail = $admin['email'] ?? '';
        $adminUser = $admin['username'] ?? 'admin';
        $adminPass = $admin['password'] ?? '';
        
        if (!$adminPass) { 
            $adminPass = bin2hex(random_bytes(8)); 
        }
        
        $hash = password_hash($adminPass, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, verified) VALUES (?, ?, ?, 'admin', 1)");
        
        if (!$stmt) {
            echo json_encode(['success'=>false,'error'=>'Failed to prepare admin insert']); 
            exit;
        }
        
        $stmt->bind_param('sss', $adminUser, $adminEmail, $hash);
        
        if (!$stmt->execute()) {
            echo json_encode(['success'=>false,'error'=>'Failed to create admin user']); 
            exit;
        }
        
        $stmt->close();

        // Seed demo data
        if (!empty($cfg['demo'])) {
            if (file_exists($configDir . '/api/utils.php')) {
                require_once $configDir . '/api/utils.php';
                if (function_exists('seed_demo_if_empty')) {
                    seed_demo_if_empty();
                }
            }
        }

        $conn->close();
        echo json_encode(['success'=>true,'adminPassword'=>$adminPass]); 
        
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'error'=>'Exception: '.$e->getMessage()]); 
    }
    exit;
}

// If not installed, render installer inline
$installed = file_exists(__DIR__ . '/backend/config.php');
$reinstall_error = '';
if ($installed) {
    require_once __DIR__ . '/backend/config.php';
    // Quick DB preflight: if DB can't be reached, fall back to installer view with guidance
    $cfg = $AFTERLIGHT_CONFIG['db'] ?? [];
    $host = $cfg['host'] ?? '';
    $user = $cfg['user'] ?? '';
    $pass = $cfg['pass'] ?? '';
    $name = $cfg['name'] ?? '';
    $port = intval($cfg['port'] ?? 3306);
    if (!$host || !$name || $user === null) {
      $installed = false;
      $reinstall_error = 'DB configuration incomplete. Please complete setup.';
    } else {
      $__test = @new mysqli($host, $user, $pass, $name, $port);
      if ($__test && !$__test->connect_errno) {
        $__test->close();
      } else {
        $installed = false;
        $reinstall_error = 'Database connection failed. Please verify credentials and re-run setup.';
      }
    }
    if ($installed) {
      $clientConfig = [
        'baseUrl' => $AFTERLIGHT_CONFIG['base_url'] ?? '',
        'theme' => $AFTERLIGHT_CONFIG['theme'] ?? [],
      ];
    }
}
// Compute base path for subdirectory deployments like /worldexplorer
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if ($basePath === '/' || $basePath === '\\' || $basePath === '.') { $basePath = ''; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Perf + UX polish -->
  <meta name="theme-color" content="<?php echo $installed && isset($AFTERLIGHT_CONFIG['theme']['bg']) ? $AFTERLIGHT_CONFIG['theme']['bg'] : '#0f1115'; ?>">
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
  <title>Afterlight</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo $basePath; ?>/public/css/styles.css">
  <link rel="icon" href="<?php echo $basePath; ?>/public/assets/images/favicon.png">
  <?php if ($installed): ?>
  <script>
    window.__AL_BASE_PATH__ = <?php echo json_encode($basePath); ?>;
  </script>
  <script>
    window.AFTERLIGHT_CONFIG = <?php $clientConfig['baseUrl'] = $basePath; echo json_encode($clientConfig); ?>;
    (function(cfg, base){
      cfg.meEndpoint = base + '/backend/me';
      cfg.loginEndpoint = base + '/backend/login';
      cfg.registerEndpoint = base + '/backend/register';
      cfg.contentEndpoint = base + '/backend/admin/content';
      cfg.themeEndpoint = base + '/backend/admin/theme';
      cfg.reinstallEndpoint = base + '/backend/admin/reinstall';
      cfg.upgradeEndpoint = base + '/backend/admin/upgrade';
      cfg.purgeEndpoint = base + '/backend/admin/purge';
    })(window.AFTERLIGHT_CONFIG = window.AFTERLIGHT_CONFIG||{}, window.__AL_BASE_PATH__||'');
  </script>
  <?php endif; ?>
  <style>
    :root {
      --al-bg: <?php echo $installed && isset($AFTERLIGHT_CONFIG['theme']['bg']) ? $AFTERLIGHT_CONFIG['theme']['bg'] : '#0f1115'; ?>;
      --al-fg: <?php echo $installed && isset($AFTERLIGHT_CONFIG['theme']['fg']) ? $AFTERLIGHT_CONFIG['theme']['fg'] : '#e1e6ef'; ?>;
      --al-accent: <?php echo $installed && isset($AFTERLIGHT_CONFIG['theme']['accent']) ? $AFTERLIGHT_CONFIG['theme']['accent'] : '#4cc9f0'; ?>;
      --al-font-ui: <?php
        $font = $installed && isset($AFTERLIGHT_CONFIG['theme']['font']) ? $AFTERLIGHT_CONFIG['theme']['font'] : 'Inter, system-ui, Arial, sans-serif';
        echo $font ? $font : 'Inter, system-ui, Arial, sans-serif';
      ?>;
      --al-header-bg: <?php echo $installed && isset($AFTERLIGHT_CONFIG['theme']['header_bg']) ? $AFTERLIGHT_CONFIG['theme']['header_bg'] : '#0f1115'; ?>;
      --al-footer-bg: <?php echo $installed && isset($AFTERLIGHT_CONFIG['theme']['footer_bg']) ? $AFTERLIGHT_CONFIG['theme']['footer_bg'] : '#0f1115'; ?>;
      --al-header-h: <?php echo $installed && isset($AFTERLIGHT_CONFIG['theme']['header_h']) ? intval($AFTERLIGHT_CONFIG['theme']['header_h']) . 'px' : '0px'; ?>;
      --al-footer-h: <?php echo $installed && isset($AFTERLIGHT_CONFIG['theme']['footer_h']) ? intval($AFTERLIGHT_CONFIG['theme']['footer_h']) . 'px' : '0px'; ?>;
    }
    body, .al-panel, .al-chat, .btn, input, select, textarea { font-family: var(--al-font-ui); }
    header.al-site { height: var(--al-header-h); background: var(--al-header-bg); }
    footer.al-site { height: var(--al-footer-h); background: var(--al-footer-bg); }
    [data-requires-role="admin"] { display: none !important; }
    .is-admin [data-requires-role="admin"] { display: initial !important; }
    .al-chat-line { padding: 2px 0; }
    .al-chat-line .time { color:#7a8495; margin-right:6px; font-variant-numeric: tabular-nums; }
    .al-chat-line a { color: var(--al-accent); text-decoration: none; }
    .al-chat-line a:hover { text-decoration: underline; }
    .al-chat-line img { max-width:100%; max-height:180px; border:1px solid #2a2f3a; border-radius:4px; display:block; margin:4px 0; }
    @media (max-width: 576px) {
      .al-hud .btn { padding: 0.35rem 0.5rem; }
      .al-panel { width: 96vw !important; max-width: 96vw; left: 50%; transform: translateX(-50%); }
      .al-chat { width: 96vw !important; bottom: 8px; right: 2vw; left: 2vw; }
      #phaser-container.al-canvas { height: calc(100vh - 120px); }
      .al-minimap { transform: scale(0.9); transform-origin: bottom right; }
      .al-dpad { bottom: 10px; left: 10px; gap: 4px; }
      .al-dpad button { width: 44px; height: 44px; font-size: 18px; }
    }
  </style>
</head>
<body class="al-body">
<?php if ($installed): ?>
  <!-- ALL GAME SCRIPTS PRESERVED BELOW - DO NOT REMOVE -->
  
  <!-- DOM Scaffolding -->
  <script>
  (function(){
    function ensure(id, creator){
      let el=document.getElementById(id);
      if(!el){ el=creator(); el.id=id; document.body.appendChild(el); }
      return el;
    }
    ensure('phaser-container', ()=>{ const d=document.createElement('div'); d.className='al-canvas'; Object.assign(d.style,{width:'100%',height:'calc(100vh - var(--al-footer-h) - var(--al-header-h))',background:'#0f1115'}); return d; });
    ensure('al-chat', ()=>{ const w=document.createElement('div'); w.className='al-chat card bg-dark border-secondary'; w.style.cssText='position:fixed; right:12px; bottom:12px; width:340px; max-height:40vh; z-index:60;';
      w.innerHTML = '<div class="card-header py-1 d-flex justify-content-between align-items-center"><span>Chat</span><button id="chat-toggle" class="btn btn-sm btn-outline-secondary">Toggle</button></div>'
      +'<div class="card-body p-2"><div id="chat-log" class="al-chat" style="overflow:auto; height:24vh;"></div></div>'
      +'<div class="card-footer p-2 d-flex gap-1"><select id="chat-channel" class="form-select form-select-sm" style="max-width:90px"><option value="global">Global</option><option value="local">Local</option><option value="party">Party</option></select><textarea id="chat-input" rows="1" class="form-control form-control-sm" placeholder="Type..." style="resize:vertical"></textarea><button id="chat-send" class="btn btn-sm btn-primary">Send</button></div>';
      return w;
    }).id='al-chat';
    ensure('minimap', ()=>{ const c=document.createElement('canvas'); c.style.cssText='position:fixed; right:12px; top:12px; z-index:50; border:1px solid #1e2633;'; return c; });
    ensure('dpad', ()=>{ const d=document.createElement('div'); d.style.cssText='position:fixed; left:12px; bottom:12px; z-index:70;'; return d; });
    ensure('console', ()=>{ const d=document.createElement('div'); d.className='card bg-dark border-secondary'; d.style.cssText='position:fixed; left:12px; top:12px; z-index:40; width:360px; max-height:38vh;'; d.innerHTML='<div class="card-header py-1">Console</div><pre id="console-log" style="margin:0; padding:8px; overflow:auto; height:32vh; background:#0b0d12; color:#c9d1d9"></pre>'; return d; });
    ensure('panel-maintenance', ()=>{ const d=document.createElement('div'); d.className='card bg-dark border-secondary'; d.style.cssText='position:fixed; left:12px; bottom:12px; z-index:45; width:360px;'; d.innerHTML='<div class="card-header py-1">Maintenance</div><div class="card-body p-2"><div class="d-grid gap-2"></div><pre id="maint-log" style="margin:0; padding:6px; max-height:16vh; overflow:auto; background:#0b0d12; color:#9fb3c8"></pre></div>'; return d; });
    ensure('wg-log', ()=>{ const p=document.createElement('pre'); p.style.cssText='position:fixed; right:12px; top:240px; z-index:40; width:340px; max-height:20vh; overflow:auto; background:#0b0d12; color:#a3b8cc; padding:6px; border:1px solid #1e2633;'; return p; });
    ensure('dialog-box', ()=>{ const d=document.createElement('div'); d.style.cssText='position:fixed; left:50%; transform:translateX(-50%); bottom:12vh; width:70vw; max-width:900px; background:rgba(10,12,16,0.9); color:#e1e6ef; padding:12px 16px; border:1px solid #2a2f3a; border-radius:6px; z-index:80; display:none;'; d.innerHTML='<div id="dialog-text" style="min-height:56px; font-family:monospace; white-space:pre-wrap;"></div><div id="dialog-options" class="mt-2 d-flex gap-2 flex-wrap"></div>'; return d; });
    ensure('overlay', ()=>{ 
      const o=document.createElement('div'); 
      o.style.cssText='position:fixed; inset:0; background:rgba(0,0,0,0.85); z-index:100; display:none;'; 
      o.innerHTML = 
      '<div class="container h-100 d-flex align-items-center justify-content-center">'
        +'<div class="card bg-dark border-secondary" style="width:520px;">'
          +'<div class="card-header d-flex gap-2">'
            +'<button id="login-tab" class="btn btn-sm btn-outline-light active">Login</button>'
            +'<button id="register-tab" class="btn btn-sm btn-outline-light">Register</button>'
          +'</div>'
          +'<div class="card-body">'
            +'<form id="login" class="vstack gap-2">'
              +'<input class="form-control" id="lg-username" placeholder="Username or Email">'
              +'<input type="password" class="form-control" id="lg-password" placeholder="Password">'
              +'<button type="button" id="lg-submit" class="btn btn-primary">Sign In</button>'
            +'</form>'
            +'<form id="register" class="vstack gap-2 d-none">'
              +'<input class="form-control" id="rg-name" placeholder="Full name">'
              +'<input class="form-control" id="rg-username" placeholder="Username">'
              +'<input class="form-control" id="rg-email" type="email" placeholder="Email">'
              +'<input class="form-control" id="rg-phone" placeholder="Phone (optional)">'
              +'<input type="date" class="form-control" id="rg-birth">'
              +'<input type="password" class="form-control" id="rg-password" placeholder="Password">'
              +'<button type="button" id="rg-submit" class="btn btn-success">Create Account</button>'
            +'</form>'
            +'<div id="char-create" class="d-none">Welcome. Create your character in-game.</div>'
          +'</div>'
        +'</div>'
      +'</div>'; 
      return o; 
    });
    const ov=document.getElementById('overlay'); ov.style.display='block';
    document.addEventListener('al:game:ready',()=>{ ov.style.display='block'; });
  })();
  </script>

  <!-- Console Proxy -->
  <script>
  (function(){
    const logEl = document.getElementById('console-log');
    if (!logEl) return;
    function write(kind, args){
      const ts=new Date().toISOString().split('T')[1].replace('Z','');
      const msg = `[${ts}] ${kind}: `+Array.from(args).map(a=>typeof a==='object'?JSON.stringify(a):String(a)).join(' ');
      logEl.textContent += (logEl.textContent?'\n':'') + msg;
      if (logEl.textContent.length > 200000) logEl.textContent = logEl.textContent.slice(-200000);
      logEl.scrollTop = logEl.scrollHeight;
    }
    ['log','info','warn','error'].forEach(k=>{
      const orig=console[k]; console[k]=function(){ try{ write(k, arguments); }catch{}; orig.apply(console, arguments); };
    });
    window.addEventListener('error', e=>write('ERROR', [e.message, e.filename+':'+e.lineno+':'+e.colno]));
    window.addEventListener('unhandledrejection', e=>write('PROMISE', [e.reason]));
    console.log('Console proxy active.');
  })();
  </script>

  <!-- Game Ready Fallback -->
  <script>
  (function(){
    function fire(tag){ if(window.__AL_READY_FIRED__) return; window.__AL_READY_FIRED__=true; document.dispatchEvent(new CustomEvent('al:game:ready',{detail:{fallback:true, tag}})); }
    document.addEventListener('al:game:ready',()=>{ window.__AL_READY_FIRED__=true; });
    document.addEventListener('DOMContentLoaded',()=>setTimeout(()=>!window.__AL_READY_FIRED__&&fire('dom'),1500));
    window.addEventListener('load',()=>setTimeout(()=>!window.__AL_READY_FIRED__&&fire('load'),3000));
    setTimeout(()=>!window.__AL_READY_FIRED__&&fire('timeout'),5000);
  })();
  </script>

  <!-- Persist Login -->
  <script>
  (function(){
    const cfg=window.AFTERLIGHT_CONFIG||{};
    const base=window.__AL_BASE_PATH__||'';
    const meURL=cfg.meEndpoint||(base?base+'/backend/me':null);
    async function fetchMe(){
      if(!meURL) return null;
      try{
        const r=await fetch(meURL,{credentials:'include'});
        if(!r.ok) return null;
        const ct=(r.headers.get('content-type')||'').toLowerCase();
        if(ct.includes('application/json')) return await r.json();
        const body=await r.text();
        console.info('Non-JSON /backend/me response length:', body?.length||0);
        return null;
      }catch{return null;}
    }
    function gateOverlay(user){
      const overlay=document.getElementById('overlay');
      if(!overlay) return;
      const loginTab=document.getElementById('login-tab');
      const registerTab=document.getElementById('register-tab');
      const login=document.getElementById('login');
      const register=document.getElementById('register');
      const cc=document.getElementById('char-create');
      if(user && (user.id||user.username)){
        loginTab?.classList.add('d-none'); registerTab?.classList.add('d-none');
        login?.classList.add('d-none'); register?.classList.add('d-none');
        cc?.classList.remove('d-none');
      } else {
        loginTab?.classList.remove('d-none'); registerTab?.classList.remove('d-none');
        login?.classList.remove('d-none'); register?.classList.remove('d-none');
        cc?.classList.add('d-none');
      }
    }
    (async()=>{ const me=await fetchMe(); if(me){ window.AFTERLIGHT_CONFIG={...(window.AFTERLIGHT_CONFIG||{}),user:me}; } gateOverlay((window.AFTERLIGHT_CONFIG||{}).user); })();
    document.addEventListener('al:user:updated',e=>gateOverlay((e.detail||{}).user));
  })();
  </script>

  <!-- Auth Forms -->
  <script>
  (function(){
    const cfg=window.AFTERLIGHT_CONFIG||{};
    const ov=document.getElementById('overlay');
    const loginTab=document.getElementById('login-tab'), registerTab=document.getElementById('register-tab');
    const login=document.getElementById('login'), register=document.getElementById('register');
    
    // Ensure login form is visible by default on page load
    if(login && register && loginTab && registerTab) {
      login.classList.remove('d-none');
      register.classList.add('d-none');
      loginTab.classList.add('active');
      registerTab.classList.remove('active');
    }
    
    function showTab(which){ 
      if(which==='login'){ 
        login.classList.remove('d-none'); 
        register.classList.add('d-none'); 
        loginTab.classList.add('active');
        registerTab.classList.remove('active');
      } else { 
        register.classList.remove('d-none'); 
        login.classList.add('d-none'); 
        registerTab.classList.add('active');
        loginTab.classList.remove('active');
      } 
    }
    
    loginTab?.addEventListener('click',()=>showTab('login'));
    registerTab?.addEventListener('click',()=>showTab('register'));
    
    document.getElementById('lg-submit')?.addEventListener('click', async ()=>{
      try{
        const r=await fetch(cfg.loginEndpoint,{method:'POST',headers:{'Content-Type':'application/json'},credentials:'include',body:JSON.stringify({u:document.getElementById('lg-username').value,p:document.getElementById('lg-password').value})});
        
        if(!r.ok) {
          const errorText = await r.text();
          throw new Error(errorText);
        }
        
        const meRes = await fetch(cfg.meEndpoint,{credentials:'include'});
        if(!meRes.ok) {
          throw new Error('Session verification failed');
        }
        
        const ct=(meRes.headers.get('content-type')||'').toLowerCase();
        if (!ct.includes('application/json')) {
          console.error('Expected JSON from /backend/me, got:', ct);
          throw new Error('Server returned invalid response format');
        }
        
        const me = await meRes.json();
        if (!me || !me.id) {
          throw new Error('Invalid session data');
        }
        
        window.AFTERLIGHT_CONFIG.user=me; 
        document.dispatchEvent(new CustomEvent('al:user:updated',{detail:{user:me}}));
        ov.style.display='none';
        
      }catch(e){ 
        console.error('Login error:', e); 
        alert('Login failed: ' + (e?.message||'Unknown error')); 
      }
    });
    
    document.getElementById('rg-submit')?.addEventListener('click', async ()=>{
      try{
        const payload={
          name:document.getElementById('rg-name').value,
          username:document.getElementById('rg-username').value,
          email:document.getElementById('rg-email').value,
          phone:document.getElementById('rg-phone').value,
          birth:document.getElementById('rg-birth').value,
          password:document.getElementById('rg-password').value
        };
        const r=await fetch(cfg.registerEndpoint,{method:'POST',headers:{'Content-Type':'application/json'},credentials:'include',body:JSON.stringify(payload)});
        if(!r.ok) throw new Error(await r.text());
        const result = await r.json();
        if(result.needsVerification){
          alert('Account created! Please check your email to verify your account.');
          showTab('login');
        } else {
          alert('Account created and logged in!');
          const me = result.user || (await (await fetch(cfg.meEndpoint,{credentials:'include'})).json());
          window.AFTERLIGHT_CONFIG.user=me;
          document.dispatchEvent(new CustomEvent('al:user:updated',{detail:{user:me}}));
          ov.style.display='none';
        }
      }catch(e){ console.error(e); alert('Registration failed: ' + (e?.message||'Unknown error')); }
    });
    
    document.addEventListener('al:user:updated',e=>{
      const u=(e.detail||{}).user; ov.style.display = u ? 'none' : 'block';
    });
  })();
  </script>

  <!-- Chat System (RESTORED) -->
  <script src="<?php echo $basePath; ?>/public/js/chat.js"></script>

  <!-- WorldGen + Catalogs (RESTORED) -->
  <script src="<?php echo $basePath; ?>/public/js/worldgen.js"></script>
  <script src="<?php echo $basePath; ?>/public/js/catalogs.js"></script>

  <!-- Combat System (RESTORED) -->
  <script src="<?php echo $basePath; ?>/public/js/combat.js"></script>

  <!-- Dialog System (RESTORED) -->
  <script src="<?php echo $basePath; ?>/public/js/dialog.js"></script>

  <!-- Input Handling (RESTORED) -->
  <script src="<?php echo $basePath; ?>/public/js/input.js"></script>

  <!-- Minimap (RESTORED) -->
  <script src="<?php echo $basePath; ?>/public/js/minimap.js"></script>

  <!-- Maintenance Panel (RESTORED) -->
  <script src="<?php echo $basePath; ?>/public/js/maintenance.js"></script>

  <!-- Game Engine Bootstrap -->
  <script src="<?php echo $basePath; ?>/public/js/game.js"></script>

<?php else: ?>
  <script>
    window.__AL_BASE_PATH__ = <?php echo json_encode($basePath); ?>;
  </script>
  <style>
    body.al-body {
      background: radial-gradient(circle at 25% 25%, #182029, #0f1319 60%, #0b0e12);
      color:#e1e6ef;
      font-family: system-ui, Arial, sans-serif;
    }
    .al-install-card { max-width:760px; margin:40px auto; }
    .al-install-log { background:#0b0f14; border:1px solid #1d2732; padding:8px 10px; height:180px; overflow:auto; font-size:12px; font-family:monospace; line-height:1.35; color:#c9d1d9; }
    .form-floating > label { color:#6c7a89; }
  </style>
  <div class="container py-4">
    <div class="al-install-card card bg-dark border-secondary shadow">
      <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Afterlight Installer</strong>
        <span class="badge bg-warning text-dark">Setup Required</span>
      </div>
      <div class="card-body">
        <?php if ($reinstall_error): ?>
          <div class="alert alert-danger py-2"><?php echo htmlspecialchars($reinstall_error); ?></div>
        <?php endif; ?>
        <p class="mb-3 small text-secondary">
          Provide database credentials and base URL. The installer will create all required tables and a default admin account.
        </p>
        <form id="al-install-form" class="row g-3">
          <div class="col-md-6 form-floating">
            <input id="db-host" class="form-control form-control-sm" placeholder="localhost" value="localhost">
            <label for="db-host">DB Host</label>
          </div>
          <div class="col-md-3 form-floating">
            <input id="db-port" class="form-control form-control-sm" placeholder="3306" value="3306">
            <label for="db-port">Port</label>
          </div>
          <div class="col-md-3 form-floating">
            <input id="db-name" class="form-control form-control-sm" placeholder="afterlight">
            <label for="db-name">Database</label>
          </div>
          <div class="col-md-6 form-floating">
            <input id="db-user" class="form-control form-control-sm" placeholder="user">
            <label for="db-user">DB User</label>
          </div>
          <div class="col-md-6 form-floating">
            <input id="db-pass" type="password" class="form-control form-control-sm" placeholder="password">
            <label for="db-pass">DB Password</label>
          </div>
          <div class="col-md-6 form-floating">
            <input id="base-url" class="form-control form-control-sm" placeholder="https://example.com/worldexplorer">
            <label for="base-url">Base URL (public)</label>
          </div>
          <div class="col-md-6 form-floating">
            <input id="admin-email" type="email" class="form-control form-control-sm" placeholder="admin@example.com">
            <label for="admin-email">Admin Email</label>
          </div>
          <div class="col-md-6 form-floating">
            <input id="admin-user" class="form-control form-control-sm" placeholder="admin" value="admin">
            <label for="admin-user">Admin Username</label>
          </div>
          <div class="col-md-6 form-floating">
            <input id="admin-pass" type="password" class="form-control form-control-sm" placeholder="(auto)">
            <label for="admin-pass">Admin Password (leave blank to auto-generate)</label>
          </div>
          <div class="col-12">
            <div class="form-check">
              <input id="opt-demo" class="form-check-input" type="checkbox" checked>
              <label for="opt-demo" class="form-check-label small">Populate demo world data (recommended first run)</label>
            </div>
          </div>
          <div class="col-12 d-flex gap-2">
            <button type="button" id="btn-install" class="btn btn-primary btn-sm">
              <i class="fa fa-hammer me-1"></i>Install
            </button>
            <button type="button" id="btn-recheck" class="btn btn-outline-secondary btn-sm">
              Re‑Check
            </button>
            <button type="button" id="btn-clear-config" class="btn btn-outline-warning btn-sm">
              Clear Form
            </button>
          </div>
        </form>
        <hr class="my-3 border-secondary">
        <div class="al-install-log" id="install-log" aria-live="polite"></div>
        <div class="small text-secondary mt-2">
          Installation log appears above. If issues persist, check server error logs.
        </div>
      </div>
      <div class="card-footer text-end">
        <span class="text-muted small">Afterlight Setup Wizard v1.0</span>
      </div>
    </div>
  </div>
  <script src="<?php echo $basePath; ?>/public/js/installer.js"></script>
<?php endif; ?>
</body>
</html>
