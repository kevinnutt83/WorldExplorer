(function(){
  // Console proxy (installer context)
  (function(){
    const logBox=document.getElementById('install-log');
    if(!logBox) return;
    function w(kind,args){
      const ts=new Date().toISOString().split('T')[1].replace('Z','');
      const msg='['+ts+'] '+kind+': '+Array.from(args).map(a=>typeof a==='object'?JSON.stringify(a):String(a)).join(' ');
      logBox.textContent += (logBox.textContent?'\n':'') + msg;
      if(logBox.textContent.length>180000) logBox.textContent = logBox.textContent.slice(-160000);
      logBox.scrollTop = logBox.scrollHeight;
    }
    ['log','info','warn','error'].forEach(k=>{
      const o=console[k]; console[k]=function(){ try{ w(k,arguments);}catch{}; o.apply(console,arguments); };
    });
    window.addEventListener('error',e=>w('ERROR',[e.message,e.filename+':'+e.lineno]));
    window.addEventListener('unhandledrejection',e=>w('REJECT',[e.reason]));
    console.log('Installer console proxy active.');
  })();

  const $=id=>document.getElementById(id);
  const log=(...a)=>console.log(...a);
  const btnInstall=$('btn-install'), btnRecheck=$('btn-recheck'), btnClear=$('btn-clear-config');

  // Prevent form submit reload
  const form = $('al-install-form');
  form?.addEventListener('submit', (e)=>{ e.preventDefault(); });

  function gather(){
    return {
      db:{
        host:$('db-host').value.trim(),
        port:parseInt($('db-port').value||'3306',10),
        name:$('db-name').value.trim(),
        user:$('db-user').value.trim(),
        pass:$('db-pass').value
      },
      base_url:$('base-url').value.trim(),
      admin:{
        email:$('admin-email').value.trim(),
        username:$('admin-user').value.trim(),
        password:$('admin-pass').value
      },
      demo: $('opt-demo').checked
    };
  }

  function validate(cfg){
    const miss=[];
    if(!cfg.db.host) miss.push('DB Host');
    if(!cfg.db.name) miss.push('DB Name');
    if(!cfg.db.user) miss.push('DB User');
    if(!cfg.base_url) miss.push('Base URL');
    if(!cfg.admin.email) miss.push('Admin Email');
    if(!cfg.admin.username) miss.push('Admin Username');
    return miss;
  }

  async function postJSON(url, payload){
    try{
      const r = await fetch(url, {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        cache:'no-store',
        body: JSON.stringify(payload)
      });
      const ct=(r.headers.get('content-type')||'').toLowerCase();
      const body = ct.includes('application/json') ? await r.json() : await r.text();
      return { ok:r.ok, ct, body };
    }catch(e){
      return { ok:false, ct:'', body:(e && (e.message||e.stack)) || String(e) };
    }
  }

  async function attemptInstall(){
    const cfg=gather();
    const miss=validate(cfg);
    if(miss.length){ log('Validation failed: missing -> '+miss.join(', ')); return; }
    log('Submitting install request...');
    btnInstall.disabled=true;
    try{
      const sep = location.search ? '&' : '?';
      const fallback = location.pathname + location.search + sep + 'al_install=1';
      const res = await postJSON(fallback, cfg);

      if(res.ok && typeof res.body==='object' && res.body.success){
        log('✓ Install success!');
        if(res.body.adminPassword){
          log('Admin password (save this): ' + res.body.adminPassword);
        }
        log('Reloading in 2s...');
        setTimeout(()=>location.reload(),2000);
      }else{
        log('✗ Install failed:', typeof res.body==='string'? res.body : JSON.stringify(res.body));
      }
    }catch(e){
      console.error('Install error:', (e && (e.message||e.stack)) || e);
    }finally{
      btnInstall.disabled=false;
    }
  }

  async function recheck(){
    log('Rechecking for config...');
    try{
      const probe=(window.__AL_BASE_PATH__||'') + '/backend/config.php?ts=' + Date.now();
      const r=await fetch(probe,{cache:'no-store'});
      if(r.ok){
        log('config.php detected. Reloading...');
        setTimeout(()=>location.reload(),1000);
      }else{
        log('Still not installed (HTTP '+r.status+').');
      }
    }catch(e){
      console.warn('Recheck failed', e);
    }
  }

  btnInstall?.addEventListener('click', (e)=>{ e.preventDefault?.(); attemptInstall(); });
  btnRecheck?.addEventListener('click', (e)=>{ e.preventDefault?.(); recheck(); });
  btnClear?.addEventListener('click',(e)=>{
    e.preventDefault?.();
    ['db-host','db-port','db-name','db-user','db-pass','base-url','admin-email','admin-user','admin-pass'].forEach(id=>{ 
      const el=$(id); 
      if(el) el.value='';
    });
    $('opt-demo').checked=true;
    log('Form cleared.');
  });

  // Autofill base URL guess
  if(!$('base-url').value){
    $('base-url').value = location.origin + (window.__AL_BASE_PATH__||'');
  }

  log('Installer ready. Fill the form and click Install.');
})();
