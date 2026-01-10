class WorldScene extends Phaser.Scene {
  constructor(){ super('WorldScene'); this.player = null; this.target = null; this.speed = 160; this.worldSize = { w:4096, h:4096 }; this.biomes=null; this.minimapCtx=null; }
  create(){
    const w = this.scale.width; const h = this.scale.height;
    // Build biome background once
    this.generateBiomes();
    this.buildBiomeLayer();
    // Player sprite at world center
    this.player = this.physics.add.image(this.worldSize.w/2, this.worldSize.h/2, 'player');
    this.player.setCollideWorldBounds(true);
    this.player.setDepth(this.player.y);
    // Camera follow and bounds
    this.cameras.main.startFollow(this.player, true, 0.1, 0.1);
    this.physics.world.setBounds(0,0,this.worldSize.w,this.worldSize.h);
    this.cameras.main.setBounds(0,0,this.worldSize.w,this.worldSize.h);
    // Equip overlay icons
    this.equipIcons = { weapon: null, tool: null, armor: null };
    const refreshEquip = async ()=>{
      try { const me = await fetch((window.AFTERLIGHT_CONFIG?.baseUrl||'') + '/backend/api/user.php?action=me', { credentials:'include' }).then(r=>r.json());
        const data = (me?.user?.activeCharacter?.data ? JSON.parse(me.user.activeCharacter.data) : {}) || {};
        this.updateEquipVisuals(data.equip||{});
      } catch(_){ }
    };
    refreshEquip();

    // Input: click-to-move
    this.input.on('pointerdown', (pointer)=>{
      this.target = new Phaser.Math.Vector2(pointer.worldX, pointer.worldY);
      this.updateVelocity();
      this.events.emit('coords', { x: Math.round(this.player.x), y: Math.round(this.player.y) });
    });

    // Auth-gated loops (vehicles polling, position saving)
    const startAuthedLoops = ()=>{
      // Avoid starting twice
      if (this.__authedStarted) return; this.__authedStarted = true;
      // Save position periodically
      this.time.addEvent({ delay: 5000, loop: true, callback: async ()=>{
        try{ await window.api.savePosition(Math.round(this.player.x), Math.round(this.player.y)); }catch(e){ /* ignore */ }
      }});
      // Vehicles polling
      this.vehicles = new Map();
      this.vehiclesGroup = this.add.group();
      const loadVehicles = ()=>{
        fetch((window.AFTERLIGHT_CONFIG?.baseUrl||'') + '/backend/api/vehicles.php', { credentials:'include' })
          .then(r=>r.json()).then(j=>{ if(!j.ok) return; this.syncVehicles(j.vehicles||[]); });
      };
      loadVehicles();
      this.time.addEvent({ delay: 4000, loop: true, callback: loadVehicles });
    };

    // Load world nodes
    this.nodesGroup = this.add.group();
    fetch((window.AFTERLIGHT_CONFIG?.baseUrl||'') + '/backend/api/nodes.php?action=list', { credentials:'include' })
      .then(r=>r.json()).then(j=>{ if (!j.ok) return; (j.nodes||[]).forEach(n=>this.spawnNode(n)); });

    // Start auth-only features when authenticated
    if (window.currentUser) startAuthedLoops();
    else { window.addEventListener('afterlight:authenticated', startAuthedLoops, { once: true }); }

    // Notify UI scene
    this.scene.launch('UIScene');
    this.events.on('console', (cmd)=>this.handleConsole(cmd));

    // Party P2P position sync
    this.partyMarkers = new Map();
    if (window.p2p && window.p2p.onMessage){
      window.p2p.onMessage((data)=>{
        try{ const msg = JSON.parse(data); if (msg.t==='pos'){ this.updatePartyMarker(msg.id, msg.x, msg.y); } }catch(_){ }
      });
    }
    this.time.addEvent({ delay: 1000, loop: true, callback: ()=>{
      try{
        const st = window.p2p?.state?.(); if (st && st.ready){
          window.p2p.send(JSON.stringify({ t:'pos', id:'peer', x:Math.round(this.player.x), y:Math.round(this.player.y) }));
        }
      }catch(_){ }
    }});

    // Minimap
    this.drawMinimap();
    // Mobile D-Pad
    this.initDpad();
  }
  updateEquipVisuals(equip){
    const offsets = { weapon: {x:12,y:6,txt:'🗡️'}, tool:{x:-12,y:6,txt:'🔧'}, armor:{x:0,y:-18,txt:'🛡️'} };
    for (const slot of Object.keys(this.equipIcons)){
      const ex = offsets[slot];
      if (equip && equip[slot]){
        if (!this.equipIcons[slot]){ this.equipIcons[slot] = this.add.text(this.player.x+ex.x, this.player.y+ex.y, ex.txt, { fontSize:'14px' }).setDepth(this.player.depth+1); }
      } else {
        if (this.equipIcons[slot]){ this.equipIcons[slot].destroy(); this.equipIcons[slot]=null; }
      }
    }
  }
  updatePartyMarker(id,x,y){
    let m = this.partyMarkers.get(id);
    if (!m){ m = this.add.circle(x,y,6,0x55ff55).setStrokeStyle(2,0x003300); this.partyMarkers.set(id,m); }
    m.x = x; m.y = y; m.depth = y;
  }
  syncVehicles(list){
    const seen = new Set();
    list.forEach(v=>{
      seen.add(v.id);
      let sprite = this.vehicles.get(v.id);
      if (!sprite){
        const key = this.textures.exists('vehicle_'+(v.kind||'')) ? ('vehicle_'+v.kind) : (this.textures.exists('vehicle_default')?'vehicle_default':null);
        if (key){
          const img = this.add.image(v.x, v.y, key).setOrigin(0.5);
          img.setInteractive({ useHandCursor:true });
          img.on('pointerdown', ()=> this.tryEnterVehicle(v));
          this.vehicles.set(v.id, img); this.vehiclesGroup.add(img);
          sprite = img;
        } else {
          const rect = this.add.rectangle(v.x, v.y, 28, 16, 0x888888).setStrokeStyle(2, 0x000000).setOrigin(0.5);
          rect.setInteractive({ useHandCursor:true });
          rect.on('pointerdown', ()=> this.tryEnterVehicle(v));
          this.vehicles.set(v.id, rect); this.vehiclesGroup.add(rect);
          sprite = rect;
        }
      }
      sprite.x = v.x; sprite.y = v.y; sprite.depth = v.y;
      const premium = (v.premium === 'true');
      const occupied = !!v.occupant_char_id; const owned = !!v.owner_char_id;
      if (sprite.fillColor !== undefined){
        // rectangle fallback tint
        sprite.fillColor = occupied ? 0x4444aa : (premium ? 0xaa8844 : 0x888888);
      } else if (sprite.setTint){
        sprite.clearTint && sprite.clearTint();
        const tint = occupied ? 0x4444aa : (premium ? 0xaa8844 : 0xffffff);
        sprite.setTint(tint);
      }
      sprite.setData('vehicle', v);
    });
    // remove stale
    for (const [id, spr] of this.vehicles.entries()){
      if (!seen.has(id)){ spr.destroy(); this.vehicles.delete(id); }
    }
  }
  async tryEnterVehicle(v){
    const dist = Phaser.Math.Distance.Between(this.player.x, this.player.y, v.x, v.y);
    if (dist > 80) return;
    try{ await fetch((window.AFTERLIGHT_CONFIG?.baseUrl||'') + '/backend/api/vehicles.php', { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'enter', vehicle_id: v.id })}); this.speed = 280; }catch(e){}
  }
  spawnNode(n){
    const key = this.textures.exists('node_'+(n.kind||'')) ? ('node_'+n.kind) : null;
    let obj;
    if (key){
      obj = this.add.image(n.x, n.y, key).setOrigin(0.5);
    } else {
      const color = { tree:0x2ecc71, ore:0x95a5a6, water:0x3498db, scrap:0xf1c40f }[n.kind] || 0xffffff;
      obj = this.add.circle(n.x, n.y, 10, color).setStrokeStyle(2, 0x000000);
    }
    obj.setInteractive({ useHandCursor:true });
    obj.on('pointerdown', ()=> this.tryHarvest(n, obj));
    obj.depth = n.y;
    obj.setData('node', n);
    this.nodesGroup.add(obj);
  }
  async tryHarvest(n, obj){
    const dist = Phaser.Math.Distance.Between(this.player.x, this.player.y, n.x, n.y);
    if (dist > 100){ return; }
    try{
      const r = await fetch((window.AFTERLIGHT_CONFIG?.baseUrl||'') + '/backend/api/nodes.php', { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'harvest', id:n.id })});
      const j = await r.json(); if (j.ok){ obj.destroy(); }
    }catch(e){}
  }
  handleConsole(cmd){
    const [name, ...args] = cmd.trim().split(/\s+/);
    if (name === 'tp'){
      const x= parseInt(args[0]||'0',10), y=parseInt(args[1]||'0',10);
      if (!isNaN(x) && !isNaN(y)){ this.player.setPosition(x,y); this.player.setDepth(y); this.target=null; this.player.setVelocity(0); }
    }
    if (name === 'attack'){
      // Simple combat: award XP via backend
      fetch((window.AFTERLIGHT_CONFIG?.baseUrl||'') + '/backend/api/combat.php', { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'attack' })})
        .then(r=>r.json()).then(j=>{ console.log('attack:', j); });
    }
    if (name === 'craft'){
      const rid = parseInt(args[0]||'0',10); if (!rid) return;
      fetch((window.AFTERLIGHT_CONFIG?.baseUrl||'') + '/backend/api/recipes.php', { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'craft', recipe_id: rid })})
        .then(r=>r.json()).then(j=>{ console.log('craft:', j); });
    }
    if (name === 'spawn_vehicle'){
      const kind = args[0]||'car'; const premium = args.includes('--premium'); const owned = args.includes('--owned');
      fetch((window.AFTERLIGHT_CONFIG?.baseUrl||'') + '/backend/api/vehicles.php', { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'spawn', kind, x:Math.round(this.player.x), y:Math.round(this.player.y), premium, owned })});
    }
    if (name === 'enter_vehicle'){
      const id = parseInt(args[0]||'0',10);
      fetch((window.AFTERLIGHT_CONFIG?.baseUrl||'') + '/backend/api/vehicles.php', { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'enter', vehicle_id:id })}).then(()=>{ this.speed = 280; });
    }
    if (name === 'exit_vehicle'){
      fetch((window.AFTERLIGHT_CONFIG?.baseUrl||'') + '/backend/api/vehicles.php', { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'exit' })}).then(()=>{ this.speed = 160; });
    }
    if (name === 'host'){
      const room = args[0]||'al'; window.p2p.host(room);
    }
    if (name === 'join'){
      const room = args[0]||'al'; window.p2p.join(room);
    }
  }
  update(time, delta){
    if (this.target){
      const dist = Phaser.Math.Distance.Between(this.player.x, this.player.y, this.target.x, this.target.y);
      if (dist < 4){ this.player.setVelocity(0); this.target = null; }
      else {
        const angle = Math.atan2(this.target.y - this.player.y, this.target.x - this.player.x);
        this.player.setVelocity(Math.cos(angle)*this.speed, Math.sin(angle)*this.speed);
      }
    }
    // depth for 2.5D look
    this.player.setDepth(this.player.y);
    // Follow equip icons to player
    if (this.equipIcons){
      const p = this.player; const offs = { weapon:{x:12,y:6}, tool:{x:-12,y:6}, armor:{x:0,y:-18} };
      for (const k of Object.keys(this.equipIcons)){
        const t = this.equipIcons[k]; if (t){ t.x = p.x + offs[k].x; t.y = p.y + offs[k].y; t.depth = p.depth + 1; }
      }
    }
    // Update minimap view rectangle
    this.updateMinimap();
  }
  updateVelocity(){
    if (!this.target) return;
    const angle = Math.atan2(this.target.y - this.player.y, this.target.x - this.player.x);
    this.player.setVelocity(Math.cos(angle)*this.speed, Math.sin(angle)*this.speed);
  }

  generateBiomes(){
    const cols = 128, rows = 128; const cellW = this.worldSize.w/cols, cellH = this.worldSize.h/rows;
    this.biomes = { cols, rows, cellW, cellH, data: new Array(cols*rows) };
    for (let y=0;y<rows;y++){
      for (let x=0;x<cols;x++){
        const n = fbmNoise(x*0.15,y*0.15);
        let biome='forest';
        if (n<0.2) biome='ocean'; else if (n<0.35) biome='beach'; else if (n<0.5) biome='desert'; else if (n<0.65) biome='plains'; else if (n<0.8) biome='forest'; else if (n<0.92) biome='tundra'; else biome='city';
        this.biomes.data[y*cols+x] = biome;
      }
    }
  }
  buildBiomeLayer(){
    if (!this.biomes) return; const g = this.add.graphics();
    for (let y=0;y<this.biomes.rows;y++){
      for (let x=0;x<this.biomes.cols;x++){
        g.fillStyle(Phaser.Display.Color.HexStringToColor(biomeColor(this.biomes.data[y*this.biomes.cols+x])).color, 1);
        g.fillRect(x*this.biomes.cellW, y*this.biomes.cellH, this.biomes.cellW+1, this.biomes.cellH+1);
      }
    }
    const key='biomeLayer'; g.generateTexture(key, this.worldSize.w, this.worldSize.h); g.destroy(); this.add.image(0,0,key).setOrigin(0).setDepth(-1000);
  }
  drawMinimap(){ const canvas = document.getElementById('minimap'); if (!canvas||!this.biomes) return; const ctx=canvas.getContext('2d'); this.minimapCtx=ctx; const w=canvas.width=160, h=canvas.height=160; const pxW=w/this.biomes.cols, pxH=h/this.biomes.rows; for (let y=0;y<this.biomes.rows;y++){ for (let x=0;x<this.biomes.cols;x++){ ctx.fillStyle = biomeColor(this.biomes.data[y*this.biomes.cols+x]); ctx.fillRect(x*pxW, y*pxH, pxW+1, pxH+1); } } }
  updateMinimap(){ if(!this.minimapCtx) return; const ctx=this.minimapCtx; const c=this.cameras.main; const canvas=ctx.canvas; const w=canvas.width, h=canvas.height; const rx=c.worldView.x/this.worldSize.w*w; const ry=c.worldView.y/this.worldSize.h*h; const rw=c.worldView.width/this.worldSize.w*w; const rh=c.worldView.height/this.worldSize.h*h; this.drawMinimap(); ctx.strokeStyle='#fff'; ctx.lineWidth=1; ctx.strokeRect(rx,ry,rw,rh); }
  initDpad(){ const el=document.getElementById('dpad'); if(!el) return; const btns=el.querySelectorAll('button[data-dir]'); let active=new Set(); const apply=()=>{ let vx=0,vy=0; if(active.has('left'))vx-=1; if(active.has('right'))vx+=1; if(active.has('up'))vy-=1; if(active.has('down'))vy+=1; const len=Math.hypot(vx,vy)||1; this.player.setVelocity((vx/len)*this.speed, (vy/len)*this.speed); }; btns.forEach(b=>{ const dir=b.getAttribute('data-dir'); const on=()=>{ active.add(dir); apply(); }; const off=()=>{ active.delete(dir); apply(); }; ['pointerdown','touchstart'].forEach(ev=>b.addEventListener(ev,(e)=>{ e.preventDefault(); on(); })); ['pointerup','pointerleave','pointercancel','touchend'].forEach(ev=>b.addEventListener(ev, off)); }); }
}

// Lightweight noise & colors
function fbmNoise(x,y){ let value=0, amplitude=0.5, frequency=1; for(let o=0;o<4;o++){ value += amplitude*valueNoise(x*frequency,y*frequency); amplitude*=0.5; frequency*=2; } return value; }
function valueNoise(x,y){ const xi=Math.floor(x), yi=Math.floor(y); const xf=x-xi, yf=y-yi; const a=rand2d(xi,yi), b=rand2d(xi+1,yi), c=rand2d(xi,yi+1), d=rand2d(xi+1,yi+1); const u=xf*xf*(3-2*xf), v=yf*yf*(3-2*yf); const lerp=(p,q,t)=>p+(q-p)*t; const x1=lerp(a,b,u), x2=lerp(c,d,u); return lerp(x1,x2,v); }
function rand2d(x,y){ const s = Math.sin(x*127.1 + y*311.7) * 43758.5453; return s - Math.floor(s); }
function biomeColor(b){ switch(b){ case 'ocean': return '#164e63'; case 'beach': return '#ca8a04'; case 'desert': return '#a16207'; case 'plains': return '#166534'; case 'forest': return '#065f46'; case 'tundra': return '#64748b'; case 'city': return '#4b5563'; default: return '#0f172a'; } }
