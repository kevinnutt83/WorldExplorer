(function(){
  const box=document.getElementById('dialog-box');
  const textEl=document.getElementById('dialog-text');
  const optsEl=document.getElementById('dialog-options');
  
  let typing=false, cur='', idx=0, timer=null, onDone=null;

  function typeTo(str, speed){
    typing=true; cur=''; idx=0;
    textEl.textContent='';
    clearInterval(timer);
    timer=setInterval(()=>{
      cur += str[idx++]||'';
      textEl.textContent = cur;
      if(idx>=str.length){ 
        clearInterval(timer); 
        typing=false; 
        onDone && onDone(); 
      }
    }, Math.max(10, speed||18));
  }
  
  function close(){ 
    box.style.display='none'; 
    textEl.textContent=''; 
    optsEl.innerHTML=''; 
    typing=false; 
    clearInterval(timer); 
    onDone=null; 
  }
  
  document.addEventListener('keydown', e=>{ 
    if(box.style.display!=='none' && e.key==='Escape'){ 
      close(); 
    } 
  });

  document.addEventListener('al:dialog:show', e=>{
    const d=e.detail||{};
    const lines=d.lines||[String(d.text||'...')];
    const speed=d.speed||18;
    const options=d.options||[];
    
    let i=0; 
    box.style.display='block'; 
    optsEl.innerHTML='';
    
    const next=()=>{ 
      if(i>=lines.length){ 
        if(options.length){ 
          optsEl.innerHTML=''; 
          options.forEach(o=>{ 
            const b=document.createElement('button'); 
            b.className='btn btn-sm btn-outline-info'; 
            b.textContent=o.text; 
            b.onclick=()=>{ 
              document.dispatchEvent(new CustomEvent('al:dialog:choice',{detail:{choice:o}})); 
              close(); 
            }; 
            optsEl.appendChild(b); 
          }); 
        } else { 
          setTimeout(close, 300); 
        } 
        return; 
      }
      typeTo(lines[i++], speed); 
      onDone = () => { /* wait for input or auto-advance */ };
    };
    
    next();
    
    box.onclick = ()=>{ 
      if(typing){ 
        // Fast-forward
        clearInterval(timer); 
        textEl.textContent = lines[Math.max(0,i-1)]; 
        typing=false; 
      } else { 
        next(); 
      } 
    };
  });
  
  document.addEventListener('al:dialog:hide', close);
})();
