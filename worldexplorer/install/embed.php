<?php
// Embedded installer content for index.php
require_once __DIR__ . '/lib.php';
$error = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['__al_install'])){
  if (afterlight_handle_install($_POST, __DIR__ . '/../backend', $error)){
    // Redirect back to the app's base path (supports subdirectory installs)
    $basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    if ($basePath === '/' || $basePath === '\\' || $basePath === '.') { $basePath = ''; }
    header('Location: ' . ($basePath ?: '/'));
    exit;
  }
}
?>
<h1 class="mb-3">Afterlight – First-time Setup</h1>
<?php if($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<form method="post" class="row g-3">
  <input type="hidden" name="__al_install" value="1">
  <div class="col-12"><h5>General</h5></div>
  <div class="col-md-6"><label class="form-label">Base URL</label><input name="base_url" id="base_url" class="form-control" placeholder="https://example.com/subdir" required></div>

  <div class="col-12"><h5 class="mt-3">MySQL</h5></div>
  <div class="col-md-4"><label class="form-label">Host</label><input name="db_host" id="db_host" class="form-control" value="localhost" required></div>
  <div class="col-md-2"><label class="form-label">Port</label><input name="db_port" id="db_port" type="number" class="form-control" value="3306"></div>
  <div class="col-md-3"><label class="form-label">Database</label><input name="db_name" id="db_name" class="form-control" value="afterlight" required></div>
  <div class="col-md-2"><label class="form-label">User</label><input name="db_user" id="db_user" class="form-control" required></div>
  <div class="col-md-1"><label class="form-label">Pass</label><input name="db_pass" id="db_pass" type="password" class="form-control"></div>
  <div class="col-12 d-flex align-items-center gap-2">
    <button type="button" id="btn-test-db" class="btn btn-outline-light btn-sm">Test DB Connection</button>
    <span id="db-status" class="small text-muted">Not tested</span>
  </div>

  <div class="col-12"><h5 class="mt-3">FTP</h5></div>
  <div class="col-md-3"><label class="form-label">Host</label><input name="ftp_host" id="ftp_host" class="form-control"></div>
  <div class="col-md-3"><label class="form-label">User</label><input name="ftp_user" id="ftp_user" class="form-control"></div>
  <div class="col-md-3"><label class="form-label">Pass</label><input name="ftp_pass" id="ftp_pass" type="password" class="form-control"></div>
  <div class="col-md-3"><label class="form-label">Path</label><input name="ftp_path" id="ftp_path" class="form-control" value="/public/assets"></div>
  <div class="col-12 d-flex align-items-center gap-2">
    <button type="button" id="btn-test-ftp" class="btn btn-outline-light btn-sm">Test FTP Connection</button>
    <span id="ftp-status" class="small text-muted">Optional</span>
  </div>

  

  <div class="col-12"><h5 class="mt-3">Admin</h5></div>
  <div class="col-md-6"><label class="form-label">Username</label><input name="admin_user" class="form-control" value="admin" required></div>
  <div class="col-md-6"><label class="form-label">Password</label><input name="admin_pass" type="password" class="form-control" required></div>

  <div class="col-12 d-grid"><button id="btn-install" class="btn btn-primary btn-lg" disabled>Install</button></div>
</form>
<script>
(function(){
  const status = document.getElementById('db-status');
  const btn = document.getElementById('btn-test-db');
  const submit = document.getElementById('btn-install');
  const ftpBtn = document.getElementById('btn-test-ftp');
  const ftpStatus = document.getElementById('ftp-status');
  let okDb = false; let okFtp = true; let timer = null; // FTP is optional
  function updateSubmit(){ submit.disabled = !(okDb && okFtp); }
  async function test(){
    const host = document.getElementById('db_host').value.trim();
    const port = parseInt(document.getElementById('db_port').value||'3306',10);
    const name = document.getElementById('db_name').value.trim();
    const user = document.getElementById('db_user').value.trim();
    const pass = document.getElementById('db_pass').value;
    if (!host || !name || !user){ status.textContent = 'Fill required DB fields'; status.className='small text-danger'; okDb=false; updateSubmit(); return; }
    status.textContent = 'Testing...'; status.className='small text-muted'; okDb=false; updateSubmit();
    try{
      const res = await fetch('backend/api/install_check.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ host, port, name, user, pass })});
      const j = await res.json();
      if (j && j.ok){ status.textContent = `DB OK (${j.ms} ms) ${j.info?.server_info||''}`; status.className='small text-success'; okDb=true; updateSubmit(); }
      else { status.textContent = j?.error || 'DB failed'; status.className='small text-danger'; okDb=false; updateSubmit(); }
    }catch(e){ status.textContent = e.message; status.className='small text-danger'; okDb=false; updateSubmit(); }
  }
  function debounced(){ clearTimeout(timer); timer=setTimeout(test, 500); }
  btn?.addEventListener('click', test);
  ['db_host','db_port','db_name','db_user','db_pass'].forEach(id=>{ document.getElementById(id)?.addEventListener('input', debounced); });
  // FTP test (optional). If host/user provided, require it to pass; otherwise ignore.
  async function testFtp(){
    const host = document.getElementById('ftp_host').value.trim();
    const user = document.getElementById('ftp_user').value.trim();
    const pass = document.getElementById('ftp_pass').value;
    const path = document.getElementById('ftp_path').value.trim();
    if (!host && !user && !pass && !path){ ftpStatus.textContent = 'Optional'; ftpStatus.className='small text-muted'; okFtp = true; updateSubmit(); return; }
    if (!host || !user){ ftpStatus.textContent = 'Fill FTP Host and User or leave blank'; ftpStatus.className='small text-danger'; okFtp=false; updateSubmit(); return; }
    ftpStatus.textContent = 'Testing...'; ftpStatus.className='small text-muted'; okFtp=false; updateSubmit();
    try{
      const res = await fetch('backend/api/ftp_check.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ host, user, pass, path, passive:true })});
      const j = await res.json();
      if (j && j.ok){ ftpStatus.textContent = 'FTP OK'; ftpStatus.className='small text-success'; okFtp = true; updateSubmit(); }
      else { ftpStatus.textContent = j?.error || 'FTP failed'; ftpStatus.className='small text-danger'; okFtp=false; updateSubmit(); }
    }catch(e){ ftpStatus.textContent = e.message; ftpStatus.className='small text-danger'; okFtp=false; updateSubmit(); }
  }
  ftpBtn?.addEventListener('click', testFtp);
  ['ftp_host','ftp_user','ftp_pass','ftp_path'].forEach(id=>{ document.getElementById(id)?.addEventListener('input', ()=>{ clearTimeout(timer); timer=setTimeout(testFtp, 500); }); });
  // Prevent submit if DB not validated (always) or FTP required but not valid
  document.querySelector('form')?.addEventListener('submit', (e)=>{ if(!(okDb && okFtp)){ e.preventDefault(); test(); testFtp(); } });
  // Auto-populate Base URL and FTP host using current host and subdirectory
  const baseInput = document.getElementById('base_url');
  const ftpHost = document.getElementById('ftp_host');
  if (baseInput && !baseInput.value){
    const p = window.location.pathname || '/';
    const idx = p.indexOf('/install/');
    let root = idx !== -1 ? p.slice(0, idx) : (p.endsWith('/') ? p.slice(0,-1) : p);
    baseInput.value = window.location.protocol + '//' + window.location.host + (root || '');
  }
  if (ftpHost && !ftpHost.value){ ftpHost.value = window.location.hostname; }
})();
</script>
