(function(){
  // Admin gating
  (function(){
    const cfg=window.AFTERLIGHT_CONFIG||{};
    const isAdmin = !!(cfg.user && (cfg.user.isAdmin || cfg.user.role==='admin')) || 
                    localStorage.getItem('al_is_admin')==='1' || 
                    location.search.includes('admin=1');
    if (isAdmin) document.body.classList.add('is-admin');
  })();

  // World scale default
  if(!localStorage.getItem('al_world_scale')) localStorage.setItem('al_world_scale','2');

  // WorldGen nodes persistence
  const NODES_KEY='al_world_nodes', AUTO_APPLY_KEY='al_world_nodes_autoapply';
  const wgLog=document.getElementById('wg-log'); 
  let LAST_NODES=[];
  
  const safeLog=(msg)=>{ 
    if(wgLog){ 
      wgLog.textContent+=`\n${msg}`; 
      wgLog.scrollTop=wgLog.scrollHeight; 
    } 
  }

  function saveNodes(nodes){ 
    try{ 
      localStorage.setItem(NODES_KEY, JSON.stringify(nodes||[])); 
      safeLog(`Saved ${nodes?.length||0} nodes.`);
    }catch(e){ 
      console.warn(e); 
    } 
  }
  
  function loadNodes(){ 
    try{ 
      return JSON.parse(localStorage.getItem(NODES_KEY)||'[]'); 
    }catch{ 
      return []; 
    } 
  }
  
  function applyNodes(nodes){
    const list = nodes || loadNodes();
    if(!list.length){ safeLog('No nodes to apply.'); return; }
    list.forEach(node => document.dispatchEvent(new CustomEvent('al:spawn',{detail:node})));
    safeLog(`Applied ${list.length} nodes to world.`);
  }

  document.addEventListener('al:worldgen:nodes', e => {
    const nodes=(e.detail&&e.detail.nodes)||[]; 
    LAST_NODES=nodes; 
    safeLog(`Received ${nodes.length} generated nodes.`);
    if (localStorage.getItem(AUTO_APPLY_KEY)==='1') applyNodes(nodes);
  });

  // Save/apply buttons
  document.getElementById('wg-save-nodes')?.addEventListener('click', ()=>{
    if(!LAST_NODES.length){ 
      document.dispatchEvent(new CustomEvent('al:worldgen:request-nodes')); 
      safeLog('Requested nodes from engine. Click Save again after generation.'); 
      return; 
    }
    saveNodes(LAST_NODES);
  });
  
  document.getElementById('wg-apply-nodes')?.addEventListener('click', ()=>applyNodes());
  
  const toggleAuto=document.getElementById('wg-auto-apply');
  if(toggleAuto){ 
    toggleAuto.checked = localStorage.getItem(AUTO_APPLY_KEY)==='1'; 
    toggleAuto.addEventListener('change',()=>localStorage.setItem(AUTO_APPLY_KEY, toggleAuto.checked?'1':'0')); 
  }

  document.addEventListener('al:game:ready', ()=>{
    if (localStorage.getItem(AUTO_APPLY_KEY)==='1'){ 
      const nodes=loadNodes(); 
      if(nodes.length) applyNodes(nodes); 
    }
  });
})();
