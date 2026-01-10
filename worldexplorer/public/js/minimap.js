(function(){
  document.addEventListener('al:game:ready',()=>{
    const mini=document.getElementById('minimap'); 
    if(mini && !mini.dataset.wired){
      mini.dataset.wired='1';
      const W=200,H=200; 
      mini.width=W; 
      mini.height=H;
      const ctx=mini.getContext('2d'); 
      const ENT=[];
      
      const colorFor=t=>({
        city:'#4cc9f0',
        town:'#43aa8b',
        npc:'#ffd166',
        vehicle:'#f72585',
        enemy:'#ef476f',
        object:'#adb5bd'
      }[t]||'#ffffff');
      
      const iconFor=t=>({
        city:'C',
        town:'T',
        npc:'N',
        vehicle:'V',
        enemy:'E',
        object:'O'
      }[t]||'•');
      
      const worldScale=parseFloat(localStorage.getItem('al_world_scale')||'2');
      
      function map(x,y){ 
        return { 
          mx:(x/worldScale)%W, 
          my:(y/worldScale)%H 
        }; 
      }
      
      function upsert(d){ 
        const id=d.id||('e_'+Math.random().toString(36).slice(2)); 
        let e=ENT.find(x=>x.id===id); 
        if(!e){ 
          e={id}; 
          ENT.push(e);
        } 
        Object.assign(e,{
          type:d.type||'object',
          x:d.x||0,
          y:d.y||0
        }); 
      }
      
      function remove(id){ 
        const i=ENT.findIndex(x=>x.id===id); 
        if(i>=0) ENT.splice(i,1); 
      }
      
      document.addEventListener('al:spawn',e=>upsert(e.detail||{}));
      document.addEventListener('al:despawn',e=>{ 
        const d=e.detail||{}; 
        if(d.id) remove(d.id); 
      });
      
      (function draw(){
        ctx.clearRect(0,0,W,H);
        ctx.fillStyle='#0b0d12'; 
        ctx.fillRect(0,0,W,H);
        
        // Grid
        ctx.strokeStyle='#1c2230'; 
        for(let i=0;i<=W;i+=20){ 
          ctx.beginPath(); 
          ctx.moveTo(i,0); 
          ctx.lineTo(i,H); 
          ctx.stroke(); 
        }
        for(let j=0;j<=H;j+=20){ 
          ctx.beginPath(); 
          ctx.moveTo(0,j); 
          ctx.lineTo(W,j); 
          ctx.stroke(); 
        }
        
        // Entities
        ENT.forEach(e=>{ 
          const {mx,my}=map(e.x,e.y); 
          ctx.fillStyle=colorFor(e.type); 
          ctx.beginPath(); 
          ctx.arc(mx,my,3,0,Math.PI*2); 
          ctx.fill(); 
          ctx.fillStyle='#c9d1d9'; 
          ctx.font='10px monospace'; 
          ctx.fillText(iconFor(e.type),mx+5,my+4); 
        });
        
        requestAnimationFrame(draw);
      })();
    }
  });
})();
