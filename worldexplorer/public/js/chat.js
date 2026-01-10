(function(){
  if (window.__AL_CHAT_WIRED__) return; 
  window.__AL_CHAT_WIRED__ = true;
  
  const logEl = document.getElementById('chat-log');
  const input = document.getElementById('chat-input');
  const sendBtn = document.getElementById('chat-send');
  const channelSel = document.getElementById('chat-channel');
  const toggleBtn = document.getElementById('chat-toggle');

  if (!logEl || !input || !sendBtn) return;

  const EMOTES=[
    {re:/:-?\)/g,to:'😊'},
    {re:/:-?\(/g,to:'🙁'},
    {re:/;-\)|;\)/g,to:'😉'},
    {re:/:-?D|:D/g,to:'😄'},
    {re:/:-?P|:P/g,to:'😛'},
    {re:/<3/g,to:'❤️'}
  ];
  
  const URL_RE=/(https?:\/\/[^\s<>"']+)/gi;
  const IMG_EXT_RE=/\.(png|jpe?g|gif|webp|svg)(\?.*)?$/i;
  const esc=(s)=>s.replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  
  function renderRich(text){
    const parts=[]; 
    let i=0,m;
    while((m=URL_RE.exec(text))!==null){ 
      if(m.index>i) parts.push({k:'t',v:text.slice(i,m.index)}); 
      parts.push({k:'u',v:m[0]}); 
      i=m.index+m[0].length; 
    }
    if(i<text.length) parts.push({k:'t',v:text.slice(i)});
    
    return parts.map(p=>{
      if(p.k==='t'){ 
        let t=p.v; 
        EMOTES.forEach(e=>t=t.replace(e.re,e.to)); 
        return esc(t).replace(/\n/g,'<br>'); 
      }
      const url=p.v; 
      const safe=(url.startsWith('http://')||url.startsWith('https://'))?url:'#';
      if(IMG_EXT_RE.test(safe)){
        const alt=esc((safe.split('/').pop()||'image')); 
        return `<a href="${safe}" target="_blank" rel="noopener noreferrer nofollow">${esc(url)}</a><img src="${safe}" alt="${alt}" loading="lazy" referrerpolicy="no-referrer">`;
      }
      const label=esc(url.length>80?url.slice(0,77)+'...':url);
      return `<a href="${safe}" target="_blank" rel="noopener noreferrer nofollow">${label}</a>`;
    }).join('');
  }
  
  function appendChat({ channel='global', author='system', text='', ts=Date.now() }){
    const row=document.createElement('div'); 
    row.className='al-chat-line';
    const time=new Date(ts).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'});
    row.innerHTML=`<span class="time">${time}</span><span class="text-muted">[${esc(channel)}]</span> <strong>${esc(author)}:</strong> <span class="msg">${renderRich(text)}</span>`;
    logEl.appendChild(row); 
    logEl.scrollTop=logEl.scrollHeight;
  }
  
  function sendChat(){
    const text=(input.value||'').trim(); 
    if(!text) return;
    const channel=channelSel?.value||'global';
    appendChat({channel, author:'you', text});
    document.dispatchEvent(new CustomEvent('al:chat:send', { detail:{ channel, text }}));
    input.value='';
  }
  
  sendBtn.addEventListener('click', sendChat);
  input.addEventListener('keydown', e=>{ 
    if(e.key==='Enter'&&!e.shiftKey){ 
      e.preventDefault(); 
      sendChat(); 
    }
  });
  
  toggleBtn?.addEventListener('click', ()=>{ 
    const hide = getComputedStyle(logEl).display!=='none'; 
    logEl.style.display = hide ? 'none' : 'block'; 
  });

  document.addEventListener('al:chat:recv', e => {
    const d=e.detail||{}; 
    appendChat({ 
      channel:d.channel||'global', 
      author:d.author||'system', 
      text:d.text||'', 
      ts:d.ts||Date.now() 
    });
  });

  if (!logEl.dataset.tip){ 
    appendChat({
      channel:'system', 
      author:'tip', 
      text:'Links & images supported. Press Enter to send.'
    }); 
    logEl.dataset.tip='1'; 
  }
})();
