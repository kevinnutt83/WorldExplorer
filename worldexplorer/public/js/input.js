(function(){
  document.addEventListener('al:game:ready',()=>{
    // HOTKEY ENGINE
    document.addEventListener('keydown',e=>{
      if(['INPUT','TEXTAREA'].includes(e.target.tagName)) return;
      const k=e.key.toLowerCase();
      const map={
        i:'al:inventory:toggle',
        m:'al:map:toggle',
        c:'al:console:toggle',
        j:'al:missions:toggle',
        k:'al:skills:toggle',
        p:'al:party:toggle',
        u:'al:market:toggle',
        y:'al:dungeons:toggle',
        g:'al:worldgen:toggle',
        o:'al:permissions:toggle',
        l:'al:changelog:toggle'
      };
      if(map[k]) document.dispatchEvent(new CustomEvent(map[k]));
      
      // Belt hotkeys (1-6)
      if(k>='1'&&k<='6'){
        const slotEl=document.querySelector(`#belt-${k}[data-item]`);
        const id=slotEl?.getAttribute('data-item');
        if(id) document.dispatchEvent(new CustomEvent('al:item:use',{detail:{id,source:'belt',slot:k}}));
      }
    });

    // CLICK + HOLD MOVE
    const root=document.getElementById('phaser-container');
    if(root){
      const getCanvasPos=(x,y)=>{ 
        const r=root.getBoundingClientRect(); 
        return {sx:x-r.left,sy:y-r.top}; 
      };
      
      const toWorld=(sx,sy)=> {
        try{
          const cam=window.PHASER_GAME?.scene?.keys?.WorldScene?.cameras?.main;
          if(cam){ 
            return { 
              wx: cam.worldView.x + sx/cam.zoom, 
              wy: cam.worldView.y + sy/cam.zoom 
            }; 
          }
        }catch{}
        return { wx:sx, wy:sy };
      };
      
      let holding=false, raf=null, last={sx:0,sy:0,wx:0,wy:0};
      
      const holdLoop=()=>{ 
        if(!holding) return;
        document.dispatchEvent(new CustomEvent('al:input:pointer-hold',{
          detail:{screen:{x:last.sx,y:last.sy},world:{x:last.wx,y:last.wy}}
        }));
        raf=requestAnimationFrame(holdLoop);
      };
      
      function moveTo(clientX,clientY){
        const {sx,sy}=getCanvasPos(clientX,clientY);
        const {wx,wy}=toWorld(sx,sy);
        last={sx,sy,wx,wy};
        document.dispatchEvent(new CustomEvent('al:input:move-to',{
          detail:{screen:{x:sx,y:sy},world:{x:wx,y:wy}}
        }));
        document.dispatchEvent(new CustomEvent('al:input:move-to-world',{
          detail:{x:wx,y:wy}
        }));
      }
      
      root.addEventListener('click',e=>moveTo(e.clientX,e.clientY));
      
      root.addEventListener('mousedown',e=>{
        holding=true; 
        moveTo(e.clientX,e.clientY);
        if(!raf) raf=requestAnimationFrame(holdLoop);
      });
      
      window.addEventListener('mouseup',()=>{ 
        holding=false; 
        if(raf){ 
          cancelAnimationFrame(raf); 
          raf=null; 
        }
      });
      
      root.addEventListener('mousemove',e=>{ 
        if(!holding) return; 
        moveTo(e.clientX,e.clientY); 
      });
      
      // Touch
      root.addEventListener('touchstart',e=>{
        const t=e.touches[0]; 
        holding=true; 
        moveTo(t.clientX,t.clientY);
        if(!raf) raf=requestAnimationFrame(holdLoop);
      },{passive:true});
      
      root.addEventListener('touchmove',e=>{
        if(!holding) return; 
        const t=e.touches[0]; 
        moveTo(t.clientX,t.clientY);
      },{passive:true});
      
      root.addEventListener('touchend',()=>{ 
        holding=false; 
        if(raf){ 
          cancelAnimationFrame(raf); 
          raf=null; 
        }
      },{passive:true});
    }

    // ANALOG STICK
    const dpad=document.getElementById('dpad');
    if(dpad && !dpad.dataset.joystick){
      dpad.dataset.joystick='1'; 
      dpad.innerHTML='';
      Object.assign(dpad.style,{
        width:'120px',
        height:'120px',
        borderRadius:'60px',
        background:'rgba(255,255,255,0.07)',
        position:'fixed',
        bottom:'16px',
        left:'16px'
      });
      
      const knob=document.createElement('div');
      Object.assign(knob.style,{
        width:'60px',
        height:'60px',
        borderRadius:'30px',
        background:'rgba(255,255,255,0.2)',
        position:'absolute',
        left:'30px',
        top:'30px',
        touchAction:'none'
      });
      dpad.appendChild(knob);
      
      const center={x:60,y:60}, radius=45;
      let active=false, rafA=null, cur={x:60,y:60};
      
      const clamp=(x,y)=>{ 
        const dx=x-center.x,dy=y-center.y; 
        const len=Math.hypot(dx,dy)||1; 
        const m=Math.min(len,radius); 
        return { 
          x:center.x+dx/len*m, 
          y:center.y+dy/len*m, 
          mag:Math.min(len/radius,1) 
        }; 
      };
      
      const send=()=>{ 
        if(!active) return; 
        const nx=(cur.x-center.x)/radius, ny=(cur.y-center.y)/radius;
        document.dispatchEvent(new CustomEvent('al:input:analog',{
          detail:{x:nx,y:ny,mag:Math.min(1,Math.hypot(nx,ny))}
        }));
        rafA=requestAnimationFrame(send);
      };
      
      const moveTo=(x,y)=>{ 
        const c=clamp(x,y); 
        cur=c; 
        knob.style.left=(c.x-30)+'px'; 
        knob.style.top=(c.y-30)+'px'; 
      };
      
      const start=(x,y)=>{ 
        active=true; 
        moveTo(x,y); 
        if(!rafA) rafA=requestAnimationFrame(send); 
      };
      
      const end=()=>{ 
        active=false; 
        moveTo(center.x,center.y); 
        if(rafA){
          cancelAnimationFrame(rafA); 
          rafA=null;
        }
        document.dispatchEvent(new CustomEvent('al:input:analog',{
          detail:{x:0,y:0,mag:0}
        }));
      };
      
      const local=(ev)=>{ 
        const r=dpad.getBoundingClientRect(); 
        return { x:ev.clientX-r.left, y:ev.clientY-r.top }; 
      };
      
      dpad.addEventListener('mousedown',e=>start(local(e).x,local(e).y));
      window.addEventListener('mousemove',e=>{ 
        if(!active) return; 
        moveTo(local(e).x,local(e).y); 
      });
      window.addEventListener('mouseup',end);
      
      dpad.addEventListener('touchstart',e=>{ 
        const t=e.touches[0]; 
        const r=dpad.getBoundingClientRect(); 
        start(t.clientX-r.left,t.clientY-r.top); 
      },{passive:true});
      
      dpad.addEventListener('touchmove',e=>{ 
        if(!active) return; 
        const t=e.touches[0]; 
        const r=dpad.getBoundingClientRect(); 
        moveTo(t.clientX-r.left,t.clientY-r.top); 
      },{passive:true});
      
      dpad.addEventListener('touchend',end,{passive:true});
    }
  });
})();
