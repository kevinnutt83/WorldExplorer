(function(){
  const links = document.querySelectorAll('#admin-nav [data-panel]');
  links.forEach(a=>a.addEventListener('click', (e)=>{
    e.preventDefault(); links.forEach(l=>l.classList.remove('active')); a.classList.add('active');
    document.querySelectorAll('[id^=panel-]').forEach(p=>p.classList.add('d-none'));
    document.getElementById('panel-'+a.dataset.panel)?.classList.remove('d-none');
  }));

  const BASE = window.__AL_BASE_PATH__ || '';

  // System: health and general settings
  async function refreshHealth(){
    try{
      const res = await fetch(`${BASE}/backend/api/health.php?action=stats`, { credentials:'include' }); const j = await res.json();
      const elDb = document.getElementById('stat-db'); if (elDb){ elDb.textContent = j.db?.ok ? 'OK' : 'FAIL'; elDb.className = `badge ${j.db?.ok?'bg-success':'bg-danger'}`; }
      const elMs = document.getElementById('stat-db-ms'); if (elMs){ elMs.textContent = j.db ? `(${j.db.ms} ms)` : ''; }
      const elInfo = document.getElementById('stat-db-info'); if (elInfo){ elInfo.textContent = j.db?.info ? `${j.db.info.server_info||''} | ${j.db.info.client_info||''} | ${j.db.info.host_info||''}` : ''; }
  const elRt = document.getElementById('stat-rt'); if (elRt){ const mode = j.services?.realtime || 'php'; elRt.textContent = mode.toUpperCase(); elRt.className = 'badge bg-info'; }
      const elSess = document.getElementById('stat-sess'); if (elSess){ elSess.textContent = `${j.session?.id||''} user=${j.session?.user?.id||'none'} (${j.session?.user?.role||'-'})`; }
      const elPhp = document.getElementById('stat-php'); if (elPhp){ elPhp.textContent = `${j.php?.version||''} mysqli=${j.php?.extensions?.mysqli?'yes':'no'}`; }
    }catch(e){ /* ignore */ }
  }
  document.getElementById('btn-refresh-health')?.addEventListener('click', refreshHealth);
  // Load general settings
  (async()=>{ try{ const r = await fetch(`${BASE}/backend/api/config.php?action=general`, { credentials:'include' }); const j = await r.json(); const opt = j.general||{}; const cb=document.getElementById('opt-admin-redirect'); if (cb) cb.checked = !!opt.admin_redirect_login; }catch(_){ } })();
  document.getElementById('btn-save-general')?.addEventListener('click', async ()=>{
    try{
      const body = { action:'save_general', general: { admin_redirect_login: !!document.getElementById('opt-admin-redirect')?.checked }, csrf:getCsrf() };
      const res = await fetch(`${BASE}/backend/api/admin.php`, { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body) });
      const j = await res.json(); const msg = document.getElementById('general-status'); if (msg) msg.textContent = j.ok?'Saved':'Failed';
    }catch(e){ const msg = document.getElementById('general-status'); if (msg) msg.textContent = e.message; }
  });

  document.getElementById('save-theme')?.addEventListener('click', async ()=>{
    const bg = document.getElementById('theme-bg').value;
    const fg = document.getElementById('theme-fg').value;
    const accent = document.getElementById('theme-accent').value;
    try{
      const token = getCsrf();
      const res = await fetch(`${BASE}/backend/api/admin.php`, { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body: JSON.stringify({ action:'save_theme', theme:{bg,fg,accent}, csrf: token })});
      const j = await res.json(); if (!j.ok) throw new Error(j.error||'Failed');
      alert('Theme saved.');
    }catch(e){ alert(e.message); }
  });

  // Items CRUD with inline edit
  async function refreshItems(){
    const res = await fetch(`${BASE}/backend/api/items.php?action=list`, { credentials:'include' });
    const j = await res.json(); const tb = document.querySelector('#items-list tbody'); tb.innerHTML='';
    (j.items||[]).forEach(it=>{
      const tr=document.createElement('tr');
      // ID column
      tr.innerHTML = `<td>${it.id}</td>`;
      // Name editable
      const tdName = document.createElement('td');
      const inName = document.createElement('input'); inName.className='form-control form-control-sm'; inName.value=it.name||''; tdName.appendChild(inName); tr.appendChild(tdName);
      // Type editable
      const tdType = document.createElement('td');
      const inType = document.createElement('input'); inType.className='form-control form-control-sm'; inType.value=it.type||''; tdType.appendChild(inType); tr.appendChild(tdType);
  // Rarity select
  const tdR = document.createElement('td'); const selR=document.createElement('select'); selR.className='form-select form-select-sm';
      ;['common','uncommon','rare','epic','legendary'].forEach(r=>{ const o=document.createElement('option'); o.value=r; o.selected=(r===(it.rarity||'common')); o.textContent=r; selR.appendChild(o); });
      tdR.appendChild(selR); tr.appendChild(tdR);
  // Consumable checkbox
  const tdC = document.createElement('td'); const cbC = document.createElement('input'); cbC.type='checkbox'; cbC.className='form-check-input'; cbC.checked = !!(parseInt(it.consumable||0,10)); tdC.appendChild(cbC); tr.appendChild(tdC);
  // Effects JSON
  const tdE = document.createElement('td'); const inE=document.createElement('input'); inE.className='form-control form-control-sm'; try{ inE.value = it.effects ? JSON.stringify(JSON.parse(it.effects)) : ''; }catch(_){ inE.value = it.effects||''; } tdE.appendChild(inE); tr.appendChild(tdE);
      // Actions
      const tdAct = document.createElement('td');
      const bSave=document.createElement('button'); bSave.className='btn btn-sm btn-success me-2'; bSave.textContent='Save';
      const bDel=document.createElement('button'); bDel.className='btn btn-sm btn-danger'; bDel.textContent='Delete';
      tdAct.appendChild(bSave); tdAct.appendChild(bDel); tr.appendChild(tdAct);
      bSave.addEventListener('click', async ()=>{
  let effects = null; try{ effects = inE.value.trim()? JSON.parse(inE.value.trim()) : null; }catch(_){ alert('Effects must be valid JSON'); return; }
  const body = { action:'update', id: it.id, name: inName.value.trim(), type: inType.value.trim(), rarity: selR.value, consumable: cbC.checked?1:0, effects, csrf: getCsrf() };
        const pr = await fetch(`${BASE}/backend/api/items.php`, { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body: JSON.stringify(body) });
        const pj=await pr.json(); if(!pj.ok){ alert(pj.error||'Failed to save item'); } else { refreshItems(); }
      });
      bDel.addEventListener('click', async ()=>{
        if (!confirm('Delete this item?')) return;
        const pr = await fetch(`${BASE}/backend/api/items.php`, { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body: JSON.stringify({ action:'delete', id: it.id, csrf: getCsrf() })});
        const pj=await pr.json(); if(pj.ok){ refreshItems(); } else { alert(pj.error||'Failed to delete'); }
      });
      tb.appendChild(tr);
    });
  }
  document.getElementById('btn-refresh-items')?.addEventListener('click', refreshItems);
  document.getElementById('form-item')?.addEventListener('submit', async (e)=>{
    e.preventDefault(); const fd = new FormData(e.target); const body = { action:'create', name:fd.get('name'), type:fd.get('type'), rarity:fd.get('rarity'), csrf:getCsrf() };
    const res = await fetch(`${BASE}/backend/api/items.php`, { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body: JSON.stringify(body) });
    const j = await res.json(); if(!j.ok) { alert(j.error||'Failed'); return; } e.target.reset(); refreshItems();
  });

  // World generation and list
  async function refreshNodes(){
    const res = await fetch(`${BASE}/backend/api/nodes.php?action=list`, { credentials:'include' });
    const j = await res.json(); const tb = document.querySelector('#nodes-list tbody'); tb.innerHTML='';
    (j.nodes||[]).forEach(n=>{ 
      const tr=document.createElement('tr');
      tr.innerHTML = `<td>${n.id}</td>`;
      const tdKind = document.createElement('td'); const sel = document.createElement('select'); sel.className='form-select form-select-sm'; ['tree','ore','water','scrap'].forEach(k=>{ const o=document.createElement('option'); o.value=k; o.selected = (k===n.kind); o.textContent=k; sel.appendChild(o); }); tdKind.appendChild(sel); tr.appendChild(tdKind);
      const tdX = document.createElement('td'); const ix = document.createElement('input'); ix.type='number'; ix.className='form-control form-control-sm'; ix.value=n.x; tdX.appendChild(ix); tr.appendChild(tdX);
      const tdY = document.createElement('td'); const iy = document.createElement('input'); iy.type='number'; iy.className='form-control form-control-sm'; iy.value=n.y; tdY.appendChild(iy); tr.appendChild(tdY);
      const tdAct = document.createElement('td');
      const btnSave = document.createElement('button'); btnSave.className='btn btn-sm btn-success me-2'; btnSave.textContent='Save';
      const btnDel = document.createElement('button'); btnDel.className='btn btn-sm btn-danger'; btnDel.textContent='Delete';
      tdAct.appendChild(btnSave); tdAct.appendChild(btnDel); tr.appendChild(tdAct);
      btnSave.addEventListener('click', async ()=>{
        const pr = await fetch(`${BASE}/backend/api/nodes.php`, { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body: JSON.stringify({ action:'update', id:n.id, kind: sel.value, x: parseInt(ix.value||'0',10), y: parseInt(iy.value||'0',10) })});
        const pj = await pr.json(); if(!pj.ok){ alert(pj.error||'Update failed'); }
      });
      btnDel.addEventListener('click', async ()=>{
        const pr = await fetch(`${BASE}/backend/api/nodes.php`, { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body: JSON.stringify({ action:'delete', id:n.id })});
        const pj = await pr.json(); if(pj.ok){ refreshNodes(); }
      });
      tb.appendChild(tr);
    });
  }
  document.getElementById('btn-gen-world')?.addEventListener('click', async ()=>{
    const res = await fetch(`${BASE}/backend/api/nodes.php?action=generate`, { credentials:'include' });
    const j = await res.json(); if (!j.ok) { alert(j.error||'Failed'); return; } refreshNodes();
  });
  document.getElementById('btn-clear-world')?.addEventListener('click', async ()=>{
    if (!confirm('Delete all nodes?')) return;
    // naive clear: call delete for first 500 listed
    const res = await fetch(`${BASE}/backend/api/nodes.php?action=list`, { credentials:'include' }); const j = await res.json();
    for (const n of (j.nodes||[])){
      await fetch(`${BASE}/backend/api/nodes.php`, { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body: JSON.stringify({ action:'delete', id:n.id })});
    }
    refreshNodes();
  });

  function getCsrf(){ const m = document.querySelector('meta[name="al-csrf"]'); return m?m.getAttribute('content'):''; }

  // initial loads
  refreshHealth();
  refreshItems();
  refreshNodes();

  // Recipes admin with inline edit
  async function refreshRecipes(){
    const res = await fetch(`${BASE}/backend/api/recipes.php?action=list`, { credentials:'include' });
    const j = await res.json(); const tb = document.querySelector('#recipes-list tbody'); tb.innerHTML='';
    (j.recipes||[]).forEach(r=>{ const tr=document.createElement('tr');
      tr.innerHTML = `<td>${r.id}</td>`;
      const tdN=document.createElement('td'); const inN=document.createElement('input'); inN.className='form-control form-control-sm'; inN.value=r.name||''; tdN.appendChild(inN); tr.appendChild(tdN);
      const tdI=document.createElement('td'); const inI=document.createElement('input'); inI.type='number'; inI.className='form-control form-control-sm'; inI.value=r.result_item_id; tdI.appendChild(inI); tr.appendChild(tdI);
      const tdQ=document.createElement('td'); const inQ=document.createElement('input'); inQ.type='number'; inQ.className='form-control form-control-sm'; inQ.value=r.result_qty; tdQ.appendChild(inQ); tr.appendChild(tdQ);
      const tdA=document.createElement('td'); const bS=document.createElement('button'); bS.className='btn btn-sm btn-success me-2'; bS.textContent='Save'; const bD=document.createElement('button'); bD.className='btn btn-sm btn-danger'; bD.textContent='Delete'; tdA.appendChild(bS); tdA.appendChild(bD); tr.appendChild(tdA);
      bS.addEventListener('click', async ()=>{ const pr = await fetch(`${BASE}/backend/api/recipes.php`, { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body: JSON.stringify({ action:'update', id:r.id, name:inN.value.trim(), result_item_id: parseInt(inI.value||'0',10), result_qty: parseInt(inQ.value||'1',10) })}); const pj=await pr.json(); if(pj.ok){ refreshRecipes(); } else { alert(pj.error||'Failed to save'); } });
      bD.addEventListener('click', async ()=>{ if(!confirm('Delete this recipe?')) return; const pr = await fetch(`${BASE}/backend/api/recipes.php`, { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body: JSON.stringify({ action:'delete', id:r.id })}); const pj=await pr.json(); if(pj.ok){ refreshRecipes(); } else { alert(pj.error||'Failed to delete'); } });
      tb.appendChild(tr);
    });
  }
  document.getElementById('btn-refresh-recipes')?.addEventListener('click', refreshRecipes);
  document.getElementById('form-recipe')?.addEventListener('submit', async (e)=>{
    e.preventDefault(); const fd = new FormData(e.target); const body = { action:'create', name:fd.get('name'), result_item_id: parseInt(fd.get('result_item_id')||'0',10), result_qty: parseInt(fd.get('result_qty')||'1',10) };
    const res = await fetch(`${BASE}/backend/api/recipes.php`, { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body: JSON.stringify(body) }); const j = await res.json(); if (j.ok){ e.target.reset(); refreshRecipes(); } else { alert(j.error||'Failed'); }
  });
  refreshRecipes();

  // Factions admin
  async function refreshFactions(){
    const res = await fetch(`${BASE}/backend/api/factions.php?action=list`, { credentials:'include' }); const j = await res.json();
    const tb = document.querySelector('#factions-list tbody'); if(!tb) return; tb.innerHTML='';
    (j.factions||[]).forEach(f=>{ const tr=document.createElement('tr'); tr.innerHTML = `<td>${f.id}</td><td>${f.name}</td><td><button class='btn btn-sm btn-danger'>Delete</button></td>`; tr.querySelector('button').addEventListener('click', async()=>{ const pr = await fetch(`${BASE}/backend/api/factions.php`, { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body: JSON.stringify({ action:'delete', id:f.id })}); const pj = await pr.json(); if(pj.ok){ refreshFactions(); } }); tb.appendChild(tr); });
  }
  document.getElementById('btn-refresh-factions')?.addEventListener('click', refreshFactions);
  document.getElementById('form-faction')?.addEventListener('submit', async (e)=>{
    e.preventDefault(); const fd = new FormData(e.target); const body = { action:'create', name:fd.get('name'), description:fd.get('description') };
    const res = await fetch(`${BASE}/backend/api/factions.php`, { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body: JSON.stringify(body) }); const j = await res.json(); if(j.ok){ e.target.reset(); refreshFactions(); } else { alert(j.error||'Failed'); }
  });
  refreshFactions();

  // Missions admin with inline edit
  async function refreshMissions(){
    const res = await fetch(`${BASE}/backend/api/missions.php?action=list`, { credentials:'include' }); const j = await res.json();
    const tb = document.querySelector('#missions-list tbody'); if(!tb) return; tb.innerHTML='';
    (j.missions||[]).forEach(m=>{ const tr=document.createElement('tr'); tr.innerHTML = `<td>${m.id}</td>`;
      const tdT=document.createElement('td'); const inT=document.createElement('input'); inT.className='form-control form-control-sm'; inT.value=m.title||''; tdT.appendChild(inT); tr.appendChild(tdT);
      const tdD=document.createElement('td'); const inD=document.createElement('input'); inD.className='form-control form-control-sm'; inD.value=m.description||''; tdD.appendChild(inD); tr.appendChild(tdD);
      // Type select
      const tdType=document.createElement('td'); const selType=document.createElement('select'); selType.className='form-select form-select-sm';
      ['fetch','collection','location','harvest','assassination','conquer','escort','defend','craft','delivery'].forEach(t=>{ const o=document.createElement('option'); o.value=t; o.selected=(t===(m.type||'fetch')); o.textContent=t; selType.appendChild(o); });
      tdType.appendChild(selType); tr.appendChild(tdType);
      const tdA=document.createElement('td'); const bS=document.createElement('button'); bS.className='btn btn-sm btn-success me-2'; bS.textContent='Save'; const bAdv=document.createElement('button'); bAdv.className='btn btn-sm btn-warning me-2'; bAdv.textContent='Details'; const bE=document.createElement('button'); bE.className='btn btn-sm btn-secondary me-2'; bE.textContent='Steps'; const bD=document.createElement('button'); bD.className='btn btn-sm btn-danger'; bD.textContent='Delete'; tdA.appendChild(bS); tdA.appendChild(bAdv); tdA.appendChild(bE); tdA.appendChild(bD); tr.appendChild(tdA);
      bS.addEventListener('click', async()=>{ const pr = await fetch(`${BASE}/backend/api/missions.php`, { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body: JSON.stringify({ action:'update', id:m.id, title: inT.value.trim(), description: inD.value.trim(), type: selType.value })}); const pj=await pr.json(); if(!pj.ok){ alert(pj.error||'Failed to save'); } });
  bAdv.addEventListener('click', ()=>{ openMissionDetails(m); });
      bE.addEventListener('click', (e)=>{ e.preventDefault(); selectMission(m.id, inT.value.trim()||`Mission #${m.id}`); document.querySelector('[data-panel="missions"]').click(); });
      bD.addEventListener('click', async()=>{ if(!confirm('Delete this mission?')) return; const pr = await fetch(`${BASE}/backend/api/missions.php`, { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body: JSON.stringify({ action:'delete', id:m.id })}); const pj = await pr.json(); if(pj.ok){ refreshMissions(); } else { alert(pj.error||'Failed to delete'); } });
      tb.appendChild(tr); });

  // Advanced mission editor modal handlers
  let missionModal, bsModal;
  function ensureModal(){ if (!missionModal){ missionModal = document.getElementById('missionDetailsModal'); if (missionModal){ bsModal = new bootstrap.Modal(missionModal); } } }
  window.openMissionDetails = function(m){
    ensureModal(); if (!missionModal) return;
    missionModal.querySelector('input[name="id"]').value = m.id;
    const sel = missionModal.querySelector('select[name="type"]'); if (sel){ sel.value = m.type||'fetch'; }
    const pre = missionModal.querySelector('textarea[name="prerequisites"]'); pre.value = cleanJson(m.prerequisites);
    const rew = missionModal.querySelector('textarea[name="rewards"]'); rew.value = cleanJson(m.rewards);
    const fx = missionModal.querySelector('textarea[name="faction_effects"]'); fx.value = cleanJson(m.faction_effects);
    bsModal?.show();
  }
  function cleanJson(v){ if (!v) return ''; try{ return JSON.stringify(JSON.parse(v), null, 2); }catch(_){ return String(v||''); } }
  document.getElementById('btn-save-mission-adv')?.addEventListener('click', async ()=>{
    ensureModal(); if (!missionModal) return; const id = parseInt(missionModal.querySelector('input[name="id"]').value||'0',10); if(!id) return;
    const type = missionModal.querySelector('select[name="type"]').value;
    function parseOrNull(q){ const el=missionModal.querySelector(q); const t=(el?.value||'').trim(); if(!t) return null; try{ return JSON.parse(t); }catch(_){ alert('Invalid JSON in one of the fields'); throw new Error('bad_json'); } }
    let prerequisites, rewards, faction_effects; try{ prerequisites = parseOrNull('textarea[name="prerequisites"]'); rewards = parseOrNull('textarea[name="rewards"]'); faction_effects = parseOrNull('textarea[name="faction_effects"]'); }catch(_){ return; }
    const pr = await fetch(`${BASE}/backend/api/missions.php`, { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body: JSON.stringify({ action:'update', id, type, prerequisites, rewards, faction_effects })});
    const pj = await pr.json(); if (pj.ok){ bsModal?.hide(); refreshMissions(); } else { alert(pj.error||'Save failed'); }
  });
  }
  document.getElementById('btn-refresh-missions')?.addEventListener('click', refreshMissions);
  document.getElementById('form-mission')?.addEventListener('submit', async (e)=>{
    e.preventDefault(); const fd = new FormData(e.target); const body = { action:'create', title:fd.get('title') };
    const res = await fetch(`${BASE}/backend/api/missions.php`, { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body: JSON.stringify(body) }); const j = await res.json(); if(j.ok){ e.target.reset(); refreshMissions(); } else { alert(j.error||'Failed'); }
  });
  refreshMissions();

  // Mission steps
  async function loadSteps(mid, title){
    const res = await fetch(`${BASE}/backend/api/missions.php?action=steps&mission_id=${encodeURIComponent(mid)}`, { credentials:'include' }); const j = await res.json();
    const tb = document.querySelector('#steps-list tbody'); tb.innerHTML='';
    (j.steps||[]).forEach(s=>{ const tr=document.createElement('tr'); tr.innerHTML = `<td>${s.step_no}</td><td>${s.description||''}</td><td><button class='btn btn-sm btn-danger'>Delete</button></td>`; tr.querySelector('button').addEventListener('click', async()=>{ const pr = await fetch(`${BASE}/backend/api/missions.php`, { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body: JSON.stringify({ action:'step_delete', id:s.id })}); const pj = await pr.json(); if(pj.ok){ loadSteps(mid,title); } }); tb.appendChild(tr); });
  }
  function selectMission(mid, title){
    document.querySelector('#steps-mission-title').textContent = `${title} (#${mid})`;
    document.querySelector('#form-step [name="mission_id"]').value = mid;
    loadSteps(mid, title);
  }
  document.getElementById('form-step')?.addEventListener('submit', async (e)=>{
    e.preventDefault(); const fd = new FormData(e.target); const mid = parseInt(fd.get('mission_id')||'0',10); if(!mid) return;
    const body = { action:'step_create', mission_id: mid, step_no: parseInt(fd.get('step_no')||'1',10), description: fd.get('description')||'' };
    const res = await fetch(`${BASE}/backend/api/missions.php`, { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body: JSON.stringify(body) }); const j = await res.json(); if(j.ok){ loadSteps(mid, document.querySelector('#steps-mission-title').textContent); } else { alert(j.error||'Failed'); }
  });

  // Users admin
  async function refreshUsers(){
    const tb = document.querySelector('#users-list tbody'); if(!tb) return; tb.innerHTML='';
    try{
      const res = await fetch(`${BASE}/backend/api/users.php?action=list`, { credentials:'include' }); const j = await res.json();
      const roles = ['subscriber','player','moderator','admin','super_admin'];
      (j.users||[]).forEach(u=>{
        const tr=document.createElement('tr');
        tr.innerHTML = `<td>${u.id}</td><td>${u.username}</td><td>${u.email||''}</td>`;
        const sel = document.createElement('select'); sel.className='form-select form-select-sm'; roles.forEach(r=>{ const o=document.createElement('option'); o.value=r; o.selected = (u.role===r); o.textContent=r; sel.appendChild(o); });
        const tdRole = document.createElement('td'); tdRole.appendChild(sel);
        const tdCreated = document.createElement('td'); tdCreated.textContent = u.created_at||'';
        const tdAct = document.createElement('td'); const btn = document.createElement('button'); btn.className='btn btn-sm btn-primary'; btn.textContent='Save';
        btn.addEventListener('click', async ()=>{
          const pr = await fetch(`${BASE}/backend/api/users.php`, { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'set_role', user_id: u.id, role: sel.value })});
          const pj = await pr.json(); if(!pj.ok){ alert(pj.error||'Failed'); }
        });
        tdAct.appendChild(btn);
        tr.appendChild(tdRole); tr.appendChild(tdCreated); tr.appendChild(tdAct);
        tb.appendChild(tr);
      });
    }catch(_){ }
  }
  document.getElementById('btn-refresh-users')?.addEventListener('click', refreshUsers);
  refreshUsers();

  // FTP
  document.getElementById('btn-ftp-test')?.addEventListener('click', async ()=>{
    const res = await fetch(`${BASE}/backend/api/ftp.php?action=test`, { credentials:'include' }); const j = await res.json();
    document.getElementById('ftp-result').textContent = j.ok ? 'FTP OK' : (j.error || 'FTP failed');
  });
  document.getElementById('form-ftp-upload')?.addEventListener('submit', async (e)=>{
    e.preventDefault(); const fd = new FormData(e.target); fd.append('csrf', getCsrf());
    const res = await fetch(`${BASE}/backend/api/ftp.php?action=upload`, { method:'POST', credentials:'include', body: fd });
    const j = await res.json(); document.getElementById('ftp-result').textContent = j.ok ? 'Uploaded' : (j.error||'Upload failed');
  });

  // Monetization config
  document.getElementById('btn-save-payments')?.addEventListener('click', async ()=>{
    const f = document.getElementById('form-payments'); const fd = new FormData(f);
    const payload = {
      currency: fd.get('currency') || 'USD',
      test_mode: fd.get('test_mode') === '1',
      paypal: { client: fd.get('paypal_client')||'', secret: fd.get('paypal_secret')||'', env: fd.get('paypal_env')||'sandbox' },
      authorize_net: { login: fd.get('anet_login')||'', key: fd.get('anet_key')||'', env: fd.get('anet_env')||'sandbox' },
      packs: (()=>{ try { return JSON.parse(fd.get('packs')||'[]'); } catch(_) { return []; } })()
    };
    try{
      const res = await fetch(`${BASE}/backend/api/admin.php`, { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body: JSON.stringify({ action:'save_payments', payments: payload, csrf: getCsrf() })});
      const j = await res.json(); document.getElementById('payments-status').textContent = j.ok ? 'Saved.' : (j.error||'Failed');
    } catch(e){ document.getElementById('payments-status').textContent = e.message; }
  });

  // Logs viewer
  async function loadLogsList(){
    const res = await fetch(`${BASE}/backend/api/logs.php?action=list`, { credentials:'include' }); const j = await res.json();
    const sel = document.getElementById('log-file'); if (!sel) return; sel.innerHTML = '';
    (j.files||[]).sort((a,b)=> (b.mtime||0)-(a.mtime||0)).forEach(f=>{ const o=document.createElement('option'); o.value=f.name; o.textContent=`${f.name} (${Math.round((f.size||0)/1024)} KB)`; sel.appendChild(o); });
  }
  document.getElementById('btn-refresh-logs')?.addEventListener('click', loadLogsList);
  document.getElementById('btn-tail-log')?.addEventListener('click', async ()=>{
    const name = document.getElementById('log-file').value; const limit = document.getElementById('log-limit').value||'500';
    const res = await fetch(`${BASE}/backend/api/logs.php?action=tail&name=${encodeURIComponent(name)}&limit=${encodeURIComponent(limit)}`, { credentials:'include' }); const j = await res.json();
    document.getElementById('log-output').textContent = (j.lines||[]).join('\n');
  });
  loadLogsList();

  // Maintenance actions
  document.getElementById('btn-reinstall')?.addEventListener('click', async ()=>{
    if (!confirm('This will DROP and recreate all tables. Proceed?')) return;
    const res = await fetch(`${BASE}/backend/api/admin.php`, { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body: JSON.stringify({ action:'reinstall', csrf: getCsrf() })});
    const j = await res.json(); document.getElementById('maint-status').textContent = j.ok ? 'Database reset completed.' : (j.error||'Failed');
  });
  document.getElementById('btn-purge')?.addEventListener('click', async ()=>{
    if (!confirm('Purge generated world/vehicles/market data and reset characters to defaults?')) return;
    const res = await fetch(`${BASE}/backend/api/admin.php`, { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body: JSON.stringify({ action:'purge_data', csrf: getCsrf() })});
    const j = await res.json(); document.getElementById('maint-status').textContent = j.ok ? 'Purge complete.' : (j.error||'Failed');
  });
  document.getElementById('btn-upgrade-db')?.addEventListener('click', async ()=>{
    const res = await fetch(`${BASE}/backend/api/admin.php`, { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body: JSON.stringify({ action:'db_upgrade', csrf: getCsrf() })});
    const j = await res.json(); document.getElementById('maint-status').textContent = j.ok ? 'DB upgrade applied.' : (j.error||'Failed');
  });
})();
