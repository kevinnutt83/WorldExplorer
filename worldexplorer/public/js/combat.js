(function(){
  const STATE = { 
    combats:new Map(), 
    playerStats:{level:1,hp:120,mana:60,armor:5,crit:0.05,speed:1}, 
    knownEntities:[] 
  };

  // Track entities for respawn
  document.addEventListener('al:spawn', e=>{ 
    const d=e.detail||{}; 
    if(d && typeof d.x==='number' && typeof d.y==='number'){ 
      STATE.knownEntities.push(d); 
      if(STATE.knownEntities.length>1000) STATE.knownEntities.shift(); 
    } 
  });

  function nearestTown(pos){
    const nodes = STATE.knownEntities.filter(n=>n.type==='city'||n.type==='town');
    if (!nodes.length) return {x:0,y:0};
    let best=nodes[0], bd=Infinity;
    for(const n of nodes){ 
      const d=Math.hypot((n.x||0)-pos.x,(n.y||0)-pos.y); 
      if(d<bd){bd=d; best=n;} 
    }
    return {x:best.x||0, y:best.y||0};
  }

  function applyPassives(base, passives){
    const out={...base};
    (passives||[]).forEach(id=>{
      const sk=(window.AFTERLIGHT_SKILLS||[]).find(s=>s.id===id);
      if(!sk || sk.type!=='passive') return;
      const eff=sk.effect||{};
      if(eff.kind==='armor_flat') out.armor=(out.armor||0)+eff.value;
      if(eff.kind==='speed_mult') out.speed=(out.speed||1)*eff.value;
    });
    return out;
  }

  function computeHit(att, def, skill){
    const base = att.dmg || 10;
    const crit = (att.crit||0.05);
    let dmg = base + (skill?.effect?.bonus_damage||0);
    if (skill?.effect?.kind==='damage_mult') dmg *= (skill.effect.value||1);
    dmg = Math.max(1, Math.round(dmg - (def.armor||0)*0.6));
    const isCrit = Math.random() < crit;
    if (isCrit) dmg = Math.round(dmg*1.5);
    return { dmg, isCrit };
  }

  function startCombat(a,b){
    const id = 'cmb_'+Math.random().toString(36).slice(2);
    const A = applyPassives({...a}, a.passives);
    const B = applyPassives({...b}, b.passives);
    STATE.combats.set(id,{id,A,B,turn:'A',log:[]});
    document.dispatchEvent(new CustomEvent('al:combat:started',{detail:{id,A,B}}));
    return id;
  }

  function attack(id, who, skillId){
    const c=STATE.combats.get(id); if(!c) return;
    const skill = (window.AFTERLIGHT_SKILLS||[]).find(s=>s.id===skillId);
    const atk = who==='A'?c.A:c.B, def = who==='A'?c.B:c.A;
    const hit = computeHit(atk, def, skill);
    def.hp = Math.max(0, (def.hp||100) - hit.dmg);
    const rec={who, skill:skillId, dmg:hit.dmg, crit:hit.isCrit, targetHp:def.hp};
    c.log.push(rec);
    document.dispatchEvent(new CustomEvent('al:combat:turn',{detail:{id,record:rec}}));
    if(def.hp<=0){
      document.dispatchEvent(new CustomEvent('al:combat:ended',{detail:{id,winner:who}}));
      STATE.combats.delete(id);
    }else{
      c.turn = who==='A'?'B':'A';
    }
  }

  document.addEventListener('al:combat:engage', e=>{
    const d=e.detail||{}; 
    startCombat(d.A||STATE.playerStats, d.B||{name:'Enemy',hp:100,dmg:8,armor:2,crit:0.03});
  });
  
  document.addEventListener('al:combat:attack', e=>{
    const d=e.detail||{}; 
    attack(d.id, d.who||'A', d.skill||null);
  });

  // Player death -> respawn
  document.addEventListener('al:player:dead', e=>{
    const pos=e.detail?.pos||{x:0,y:0};
    const at=nearestTown(pos);
    document.dispatchEvent(new CustomEvent('al:player:respawn',{detail:{x:at.x,y:at.y}}));
    console.warn('Player respawned at', at);
  });
})();
