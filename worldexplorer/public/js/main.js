// Main UI wiring for auth and character
(function(){
  const overlay = document.getElementById('overlay');
  const msg = document.getElementById('overlay-msg');

  async function attemptAuto(){
    try{ const me = await window.api.me(); if (me?.ok) { window.currentUser = me.user; window.dispatchEvent(new CustomEvent('afterlight:authenticated', { detail: me.user })); await onAuthenticated(); return; } }catch(e){}
  }

  async function onAuthenticated(){
    const me = await window.api.me();
    // Admin link visibility (admin or super_admin)
    const isAdmin = (me?.user?.role === 'admin' || me?.user?.role === 'super_admin');
    const adminLink = document.querySelector('a[href$="/admin/"]');
    if (adminLink) adminLink.style.display = isAdmin ? 'inline-block' : 'none';
    // Admin bar on frontend
  const adminbar = document.getElementById('adminbar');
  if (adminbar) { adminbar.style.display = isAdmin ? 'flex' : 'none'; document.body.classList.toggle('has-adminbar', isAdmin); }
    document.getElementById('ab-logout')?.addEventListener('click', async (e)=>{ e.preventDefault(); try{ await window.api.logout(); location.reload(); }catch(_){ location.reload(); } });
    // Character selection / create
    const list = await fetch((window.AFTERLIGHT_CONFIG?.baseUrl||'') + '/backend/api/character.php?action=list', { credentials:'include' }).then(r=>r.json()).catch(()=>({}));
    const chars = list.characters || [];
    if (chars.length > 0){
      // Build simple character select UI
      const tabs = document.getElementById('authTabs'); if (tabs) tabs.style.display='none';
      const cc = document.getElementById('char-create'); cc.classList.add('d-none');
      const container = document.createElement('div'); container.id='char-select';
      container.innerHTML = `<h5>Select Character</h5><div class="list-group mb-2" id="char-list"></div><div class="d-grid gap-2"><button id="btn-new-char" class="btn btn-outline-light">Create New</button></div>`;
      document.querySelector('.al-overlay-inner .card-body').appendChild(container);
      const lg = container.querySelector('#char-list');
      chars.forEach(c=>{
        const a = document.createElement('a'); a.href='#'; a.className='list-group-item list-group-item-action d-flex justify-content-between align-items-center';
        a.innerHTML = `<span>${c.name} <small class='text-muted'>${c.arch} • Lv ${c.level||1}</small></span><span><button class='btn btn-sm btn-primary'>Play</button> <button class='btn btn-sm btn-outline-danger'>Delete</button></span>`;
  a.querySelector('.btn-primary').addEventListener('click', async (e)=>{ e.preventDefault(); await fetch((window.AFTERLIGHT_CONFIG?.baseUrl||'') + '/backend/api/character.php', { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'select', char_id: c.id })}); overlay.style.display='none'; window.dispatchEvent(new CustomEvent('afterlight:character-selected', { detail: { char_id: c.id } })); });
        a.querySelector('.btn-outline-danger').addEventListener('click', async (e)=>{ e.preventDefault(); if(!confirm('Delete this character?')) return; const pr = await fetch((window.AFTERLIGHT_CONFIG?.baseUrl||'') + '/backend/api/character.php', { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'delete', char_id: c.id })}); const pj=await pr.json(); if(pj.ok){ a.remove(); } });
        lg.appendChild(a);
      });
      container.querySelector('#btn-new-char').addEventListener('click', ()=>{ container.remove(); document.getElementById('char-create').classList.remove('d-none'); });
      // Signal that we're authenticated so game scenes can start auth-only loops
      window.dispatchEvent(new CustomEvent('afterlight:authenticated', { detail: me.user }));
      return;
    }
    // No characters yet: show create form
    document.getElementById('char-create').classList.remove('d-none');
    // Still authenticated; notify listeners
    window.dispatchEvent(new CustomEvent('afterlight:authenticated', { detail: me.user }));
  }

  document.getElementById('btn-login').addEventListener('click', async ()=>{
    const u = document.getElementById('login-username').value.trim();
    const p = document.getElementById('login-password').value;
    try{ const r = await window.api.login(u,p); if(r.ok){ window.currentUser=r.user; 
      // Check general config for redirect
      try{
        const conf = await fetch((window.AFTERLIGHT_CONFIG?.baseUrl||'') + '/backend/api/config.php?action=general', { credentials:'include' }).then(r=>r.json());
        const redirect = !!(conf?.general?.admin_redirect_login);
        if (redirect && (r.user && (r.user.role === 'admin' || r.user.role === 'super_admin'))){ const base = (window.AFTERLIGHT_CONFIG?.baseUrl||''); location.href = base + '/admin/'; return; }
      }catch(_){ /* default to no redirect if config missing */ }
      // Notify listeners that we're authenticated
      window.dispatchEvent(new CustomEvent('afterlight:authenticated', { detail: r.user }));
      await onAuthenticated(); }
      else msg.textContent = r.error || 'Login failed'; }
    catch(e){ msg.textContent = e.message; }
  });

  document.getElementById('btn-register').addEventListener('click', async ()=>{
    const u = document.getElementById('reg-username').value.trim();
    const e = document.getElementById('reg-email').value.trim();
    const p = document.getElementById('reg-password').value;
    try{ const r = await window.api.register(u,e,p); if(r.ok){ msg.textContent = 'Account created. Please login.'; }
      else msg.textContent = r.error || 'Registration failed'; }
    catch(err){ msg.textContent = err.message; }
  });

  document.getElementById('btn-create-char').addEventListener('click', async ()=>{
    const n = document.getElementById('char-name').value.trim();
    const a = document.getElementById('char-arch').value;
    if (!n) { msg.textContent = 'Enter a character name.'; return; }
    try{ const r = await window.api.createCharacter(n,a); if(r.ok){ overlay.style.display='none'; window.dispatchEvent(new CustomEvent('afterlight:character-selected')); } else msg.textContent = r.error || 'Failed to create character'; }
    catch(e){ msg.textContent = e.message; }
  });

  attemptAuto();
})();

(function () {
  if (window.__AL_MAIN_READY__) return;
  window.__AL_MAIN_READY__ = true;

  const base = (function () {
    const p = window.location.pathname || '/';
    const idx = p.indexOf('/index.php');
    return idx >= 0 ? p.slice(0, idx) : '';
  })();

  const status = (id, msg) => {
    const el = document.getElementById(id);
    if (el) el.textContent = msg || '';
  };

  async function apiAuth(action, payload) {
    const res = await fetch(`${base}/backend/api/auth.php`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action, ...payload })
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
  }

  document.getElementById('al-login-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    status('login-status', 'Signing in...');
    const fd = new FormData(e.target);
    try {
      const j = await apiAuth('login', { username: fd.get('username'), password: fd.get('password') });
      status('login-status', j.error ? `Error: ${j.error}` : 'Logged in. Reloading...');
      if (!j.error) location.reload();
    } catch (err) {
      status('login-status', `Login failed: ${err.message}`);
    }
  });

  document.getElementById('al-register-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    status('register-status', 'Registering...');
    const fd = new FormData(e.target);
    try {
      const j = await apiAuth('register', {
        username: fd.get('username'),
        email: fd.get('email'),
        password: fd.get('password')
      });
      status('register-status', j.error ? `Error: ${j.error}` : 'Registered. Check email or login.');
    } catch (err) {
      status('register-status', `Register failed: ${err.message}`);
    }
  });

  window.addEventListener('unhandledrejection', (e) => {
    console.warn('Unhandled promise rejection:', e.reason);
  });
})();
