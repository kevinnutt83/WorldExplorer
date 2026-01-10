class UIScene extends Phaser.Scene {
  constructor(){ super('UIScene'); }
  create(){
    const world = this.scene.get('WorldScene');
    world.events.on('coords', ({x,y})=>{
      const el = document.getElementById('hud-coords'); if (el) el.textContent = `x:${x} y:${y}`;
    });

    // Panel controls
    document.querySelectorAll('.btn-close[data-close]').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const id = btn.getAttribute('data-close');
        const el = document.getElementById(id); if (el) el.style.display='none';
      })
    });
  document.getElementById('btn-inventory')?.addEventListener('click',()=>{ toggle('panel-inventory'); loadInventory(); });
  document.getElementById('btn-console')?.addEventListener('click',()=>toggle('panel-console'));
  document.getElementById('btn-crafting')?.addEventListener('click',()=>{ toggle('panel-crafting'); loadRecipes(); });
  document.getElementById('btn-map')?.addEventListener('click',()=>{ toggle('panel-worldmap'); drawWorldMap(); });
  document.getElementById('btn-market')?.addEventListener('click',()=>{ toggle('panel-market'); loadMarket(); });
  document.getElementById('btn-missions')?.addEventListener('click',()=>{ toggle('panel-missions'); loadMissionUI(); });
  document.getElementById('btn-party')?.addEventListener('click',()=>{ toggle('panel-party'); });

    // Console
    const ci = document.getElementById('console-input');
    const cs = document.getElementById('console-send');
    function pushLog(msg){
      const log = document.getElementById('console-log'); if (!log) return; const d=document.createElement('div'); d.textContent=msg; log.appendChild(d); log.scrollTop=log.scrollHeight;
    }
    cs?.addEventListener('click', ()=>{
      const v = ci.value.trim(); if (!v) return; if (v.startsWith(':')){ world.events.emit('console', v.slice(1)); } else { window.chat.send('global', v); }
      pushLog(`> ${v}`); ci.value='';
    });
    ci?.addEventListener('keydown', (e)=>{ if(e.key==='Enter') cs.click(); });

    // Chat
    (async()=>{
      await window.chat.init();
      if (window.chat.enabled()){
        subscribeChannel(currentChannel());
      }
    })();
    document.getElementById('chat-send')?.addEventListener('click', ()=>{
      const input = document.getElementById('chat-input'); const v = input.value.trim(); if(!v) return; window.chat.send(currentChannel(), v); input.value='';
    });
    document.getElementById('chat-toggle')?.addEventListener('click', ()=>{
      const body = document.querySelector('#panel-chat .card-body'); if(!body) return; const hidden = body.style.display==='none'; body.style.display = hidden? 'block':'none'; const icon = document.querySelector('#chat-toggle i'); if(icon){ icon.className = hidden ? 'fa fa-eye-slash' : 'fa fa-eye'; }
    });
    document.getElementById('chat-channel')?.addEventListener('change', ()=>{ subscribeChannel(currentChannel(true)); });

    function currentChannel(forcePartyCheck){
      const sel = document.getElementById('chat-channel'); const val = sel?.value || 'global';
      if (val==='party'){
        const room = (window.p2p?.state?.()?.room)||''; if (!room && !forcePartyCheck){ const s=document.getElementById('chat-channel'); if (s) s.value='global'; return 'global'; }
        return room ? `party:${room}` : 'global';
      }
      return 'global';
    }
    let unsub = null;
    function subscribeChannel(channel){
      if (unsub) { try{ unsub(); }catch(_){ } unsub=null; }
      unsub = window.chat.subscribe(channel, (m)=>{
        const log = document.getElementById('chat-log'); if (!log) return; const d=document.createElement('div'); d.textContent = `[${new Date(m.ts).toLocaleTimeString()}] ${m.user}: ${m.message}`; log.appendChild(d); log.scrollTop=log.scrollHeight;
      });
    }

    function toggle(id){ const el = document.getElementById(id); if (el) el.style.display = (el.style.display==='none'||!el.style.display)?'block':'none'; }

    // Inventory
    async function loadInventory(){
      try{ const j = await window.api.inventory(); const grid = document.getElementById('inventory-grid'); grid.innerHTML='';
        (j.items||[]).forEach(it=>{
          const col = document.createElement('div'); col.className='col'; const slot=document.createElement('div'); slot.className='slot';
          slot.title = `${it.name} (${it.qty})`;
          slot.setAttribute('draggable','true');
          slot.dataset.itemId = it.item_id || it.id || '';
          slot.dataset.type = it.type || '';
          slot.innerHTML = `<div class="icon">${iconForType(it.type)}</div><div class="small">x${it.qty}</div>`;
          slot.addEventListener('dragstart', (ev)=>{ ev.dataTransfer.setData('text/plain', JSON.stringify({ item_id: slot.dataset.itemId, type: slot.dataset.type })); });
          // Consume on click if consumable type
          slot.addEventListener('click', async ()=>{
            if ((it.type||'') !== 'consumable') return;
            try{ const r = await window.api.consume(parseInt(slot.dataset.itemId||'0',10)); if (r.ok){ loadInventory(); const hp = typeof r.hp==='number'? r.hp : null; if (hp!==null){ const bar=document.getElementById('hud-hp'); if (bar) bar.style.width = `${hp}%`; } } }catch(_){ }
          });
          col.appendChild(slot); grid.appendChild(col);
        });
        // populate transfer character list (excluding current char)
        try{
          const list = await fetch((window.AFTERLIGHT_CONFIG?.baseUrl||'') + '/backend/api/character.php?action=list', { credentials:'include' }).then(r=>r.json());
          const sel = document.getElementById('transfer-to'); if (sel){ sel.innerHTML='';
            (list.characters||[]).forEach(c=>{ if (c.id !== j.char_id){ const o=document.createElement('option'); o.value=c.id; o.textContent = `${c.name} (#${c.id})`; sel.appendChild(o); } });
          }
        }catch(_){ }
      }catch(e){}
    }

    // Crafting
    async function loadRecipes(){
      try{ const res = await fetch((window.AFTERLIGHT_CONFIG?.baseUrl||'') + '/backend/api/recipes.php?action=list', { credentials:'include' }); const j = await res.json();
        const list = document.getElementById('crafting-list'); list.innerHTML='';
        (j.recipes||[]).forEach(r=>{
          const a = document.createElement('a'); a.href='#'; a.className='list-group-item list-group-item-action'; a.textContent = `${r.name} (x${r.result_qty})`;
          a.addEventListener('click', async (ev)=>{ ev.preventDefault(); try{ const pr = await fetch((window.AFTERLIGHT_CONFIG?.baseUrl||'') + '/backend/api/recipes.php', { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'craft', recipe_id: r.id })}); const pj = await pr.json(); if(pj.ok){ loadInventory(); } }catch(e){} });
          list.appendChild(a);
        });
      }catch(e){}
    }

    // Market with search/pagination
    let marketPage = 1; let marketQuery = '';
    async function loadMarket(){
      try{
        const url = new URL((window.AFTERLIGHT_CONFIG?.baseUrl||'') + '/backend/api/market.php?action=list');
        url.searchParams.set('page', String(marketPage)); if (marketQuery) url.searchParams.set('q', marketQuery);
        const res = await fetch(url.toString(), { credentials:'include' }); const j = await res.json();
        const tb = document.querySelector('#market-list tbody'); tb.innerHTML='';
        (j.listings||[]).forEach(m=>{
          const tr=document.createElement('tr');
          tr.innerHTML = `<td>${m.name}</td><td>${m.qty}</td><td>${m.price}</td><td><button class='btn btn-sm btn-primary'>Buy</button></td>`;
          tr.querySelector('button').addEventListener('click', async ()=>{
            const pr = await fetch((window.AFTERLIGHT_CONFIG?.baseUrl||'') + '/backend/api/market.php', { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'buy', id:m.id })}); const pj = await pr.json(); if(pj.ok){ loadMarket(); }
          });
          tb.appendChild(tr);
        });
        const pageEl = document.getElementById('market-page'); const total = j.total||0; const size = j.size||25; const page = j.page||1; const maxPage = Math.max(1, Math.ceil(total/size));
        pageEl.textContent = `Page ${page} / ${maxPage}`;
        document.getElementById('market-prev').onclick = ()=>{ if (marketPage>1){ marketPage--; loadMarket(); } };
        document.getElementById('market-next').onclick = ()=>{ const mp = Math.max(1, Math.ceil(total/size)); if (marketPage<mp){ marketPage++; loadMarket(); } };
        document.getElementById('market-search-btn').onclick = ()=>{ marketQuery = document.getElementById('market-search').value||''; marketPage=1; loadMarket(); };
        document.getElementById('btn-sell')?.addEventListener('click', async ()=>{
          const item_id = parseInt(document.getElementById('sell-item-id').value||'0',10);
          const qty = parseInt(document.getElementById('sell-qty').value||'1',10);
          const price = parseInt(document.getElementById('sell-price').value||'1',10);
          const pr = await fetch((window.AFTERLIGHT_CONFIG?.baseUrl||'') + '/backend/api/market.php', { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'sell', item_id, qty, price })}); const pj = await pr.json(); if(pj.ok){ loadMarket(); }
        });
      }catch(e){}
    }

    function iconForType(t){
      return t==='weapon'?'🗡️': t==='tool'?'🔧': t==='consumable'?'🧪': t==='resource'?'🪵':'🎒'
    }

    // Equip slot drop targets
    document.querySelectorAll('[data-slot]')?.forEach(el=>{
      el.addEventListener('dragover', (ev)=>{ ev.preventDefault(); });
      el.addEventListener('drop', async (ev)=>{
        ev.preventDefault(); try{
          const data = JSON.parse(ev.dataTransfer.getData('text/plain'));
          const slot = el.dataset.slot; const item_id = parseInt(data.item_id||'0',10);
          if (!item_id) return;
          const pr = await fetch((window.AFTERLIGHT_CONFIG?.baseUrl||'') + '/backend/api/character.php', { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'equip', slot, item_id })});
          const pj = await pr.json(); if (pj.ok){ el.classList.add('bg-accent'); setTimeout(()=>el.classList.remove('bg-accent'), 500); }
        }catch(_){ }
      });
    });
    // Party handlers
    document.getElementById('party-host')?.addEventListener('click', ()=>{
      const room = document.getElementById('party-room').value || 'afterlight';
      window.p2p.host(room);
      const sel = document.getElementById('chat-channel'); if (sel) sel.value='party'; subscribeChannel(currentChannel(true));
    });
    document.getElementById('party-join')?.addEventListener('click', ()=>{
      const room = document.getElementById('party-room-join').value || 'afterlight';
      window.p2p.join(room);
      const sel = document.getElementById('chat-channel'); if (sel) sel.value='party'; subscribeChannel(currentChannel(true));
    });

    // Inventory transfer handler
    document.getElementById('btn-transfer')?.addEventListener('click', async ()=>{
      try{
        const to_char_id = parseInt(document.getElementById('transfer-to').value||'0',10);
        const item_id = parseInt(document.getElementById('transfer-item').value||'0',10);
        const qty = parseInt(document.getElementById('transfer-qty').value||'1',10);
        if (!to_char_id || !item_id || qty<=0) return;
        const r = await fetch((window.AFTERLIGHT_CONFIG?.baseUrl||'') + '/backend/api/inventory.php', { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'transfer', to_char_id, item_id, qty })});
        const j = await r.json(); if (j.ok){ loadInventory(); }
      }catch(_){ }
    });

    // Missions UI
    async function loadMissionUI(){
      try{
        // Fill all missions list
        const all = await fetch((window.AFTERLIGHT_CONFIG?.baseUrl||'') + '/backend/api/missions.php?action=list', { credentials:'include' }).then(r=>r.json());
        const sel = document.getElementById('missions-all'); if (sel){ sel.innerHTML=''; (all.missions||[]).forEach(m=>{ const o=document.createElement('option'); o.value=m.id; o.textContent=m.title; sel.appendChild(o); }); }
      }catch(_){ }
      try{
        // Load progress
        const pr = await fetch((window.AFTERLIGHT_CONFIG?.baseUrl||'') + '/backend/api/missions.php?action=progress', { credentials:'include' }).then(r=>r.json());
        const wrap = document.getElementById('missions-progress'); if (wrap){ wrap.innerHTML='';
          (pr.progress||[]).forEach(p=>{ const a=document.createElement('div'); a.className='list-group-item'; a.textContent = `Mission #${p.mission_id}: Step ${p.current_step} (${p.status})`; wrap.appendChild(a); });
        }
      }catch(_){ }
    }
    document.getElementById('btn-mission-start')?.addEventListener('click', async ()=>{
      const mid = parseInt(document.getElementById('missions-all').value||'0',10); if(!mid) return;
      const r = await fetch((window.AFTERLIGHT_CONFIG?.baseUrl||'') + '/backend/api/missions.php', { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'start', mission_id: mid })});
      const j = await r.json(); if (j.ok) loadMissionUI();
    });
    document.getElementById('btn-mission-advance')?.addEventListener('click', async ()=>{
      const mid = parseInt(document.getElementById('missions-all').value||'0',10); if(!mid) return;
      const r = await fetch((window.AFTERLIGHT_CONFIG?.baseUrl||'') + '/backend/api/missions.php', { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'advance', mission_id: mid })});
      const j = await r.json(); if (j.ok) loadMissionUI();
    });
  }
  // Draw world map using minimap colors scaled up
}
function drawWorldMap(){ try{ const cvs = document.getElementById('worldmap-canvas'); const mm = document.getElementById('minimap'); if (!cvs||!mm) return; const ctx=cvs.getContext('2d'); const src=mm; ctx.clearRect(0,0,cvs.width,cvs.height); ctx.imageSmoothingEnabled = false; ctx.drawImage(src, 0,0, cvs.width, cvs.height); }catch(_){ }
}
