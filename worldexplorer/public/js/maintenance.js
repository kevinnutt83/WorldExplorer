(function(){
  document.addEventListener('al:game:ready',()=>{
    const panel=document.getElementById('panel-maintenance'); 
    if(!panel) return;
    const grid=panel.querySelector('.d-grid.gap-2'); 
    if(!grid) return;
    
    function addBtn(id,text,cls){
      if(document.getElementById(id)){ return; }
      const b=document.createElement('button'); 
      b.id=id; 
      b.className=`btn ${cls} btn-sm`; 
      b.textContent=text; 
      grid.appendChild(b);
    }
    
    addBtn('btn-reinstall','Reinstall (Fresh)','btn-outline-danger');
    addBtn('btn-db-update','Database Update','btn-outline-info');
    addBtn('btn-purge','Purge Data','btn-outline-secondary');
    addBtn('btn-populate-demo','Populate Demo','btn-outline-primary');
    addBtn('btn-rebuild-world','Rebuild World','btn-outline-warning');
    
    const logEl=document.getElementById('maint-log'); 
    const log=m=>{ 
      if(!logEl)return; 
      logEl.textContent+=`\n${m}`; 
      logEl.scrollTop=logEl.scrollHeight; 
    };
    
    const cfg=window.AFTERLIGHT_CONFIG||{}; 
    const base=window.__AL_BASE_PATH__||'';
    const reinstallEP=cfg.reinstallEndpoint||(base?base+'/backend/admin/reinstall':null);
    const upgradeEP=cfg.upgradeEndpoint||(base?base+'/backend/admin/upgrade':null);
    const purgeEP=cfg.purgeEndpoint||(base?base+'/backend/admin/purge':null);
    
    const post=async(url,body)=>{ 
      try{ 
        const r=await fetch(url,{
          method:'POST',
          headers:{'Content-Type':'application/json'},
          credentials:'include',
          body:JSON.stringify(body||{})
        }); 
        return {ok:r.ok,text:await r.text()}; 
      }catch(e){ 
        return {ok:false,text:String(e)}; 
      } 
    };
    
    document.getElementById('btn-reinstall')?.addEventListener('click',async()=>{
      if(!reinstallEP){ log('No reinstall endpoint.'); return; }
      if(!confirm('Fresh reinstall will WIPE all tables. Continue?')) return;
      log('Reinstalling...'); 
      const r=await post(reinstallEP,{}); 
      log(r.ok?'Reinstall done. Reloading...':'Reinstall failed: '+r.text); 
      if(r.ok) location.reload();
    });
    
    document.getElementById('btn-db-update')?.addEventListener('click',async()=>{
      if(!upgradeEP){ log('No upgrade endpoint.'); return; }
      log('Applying DB updates...'); 
      const r=await post(upgradeEP,{}); 
      log(r.ok?'DB updated.':'DB update failed: '+r.text);
    });
    
    document.getElementById('btn-purge')?.addEventListener('click',async()=>{
      if(!purgeEP){ log('No purge endpoint.'); return; }
      if(!confirm('Purge demo/world data?')) return;
      log('Purging...'); 
      const r=await post(purgeEP,{}); 
      log(r.ok?'Purge complete.':'Purge failed: '+r.text);
    });
    
    document.getElementById('btn-populate-demo')?.addEventListener('click',()=>{
      document.dispatchEvent(new CustomEvent('al:content:populate',{detail:{scope:'demo'}}));
      log('Demo content populate requested.');
    });
    
    document.getElementById('btn-rebuild-world')?.addEventListener('click',()=>{
      ['city','town','npcs'].forEach(k=>document.dispatchEvent(new CustomEvent('al:worldgen:generate',{
        detail:{kind:k,count:k==='city'?2:k==='town'?5:40}
      })));
      log('World rebuild requested.');
    });
  });
})();
