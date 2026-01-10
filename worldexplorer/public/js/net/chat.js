// Simple PHP-backed chat client
(function(){
  const BASE = (window.AFTERLIGHT_CONFIG?.baseUrl)||'';
  let enabled = true;
  let subs = new Map(); // channel -> { cb, since }
  let pollTimer = null;

  async function init(){
    if (pollTimer) return;
    pollTimer = setInterval(tick, 2000);
  }

  async function tick(){
    for (const [ch, state] of subs.entries()){
      try{
        const url = new URL(BASE + '/backend/api/chat.php');
        url.searchParams.set('action','list');
        url.searchParams.set('channel', ch);
        if (state.since) url.searchParams.set('since', String(state.since));
        const j = await fetch(url.toString(), { credentials:'include' }).then(r=>r.json());
        if (j && j.messages){
          j.messages.forEach(m=>{ state.since = Math.max(state.since||0, m.ts||0); try{ state.cb(m); }catch(_){ } });
        }
      }catch(_){ }
    }
  }

  async function send(channel, message){
    const body = { action:'send', channel, message };
    await fetch(BASE + '/backend/api/chat.php', { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body) });
  }

  function subscribe(channel, onMsg){ subs.set(channel, { cb:onMsg, since:0 }); // initial history
    (async()=>{
      try{
        const url = new URL(BASE + '/backend/api/chat.php'); url.searchParams.set('action','history'); url.searchParams.set('channel', channel);
        const j = await fetch(url.toString(), { credentials:'include' }).then(r=>r.json());
        if (j && j.messages){ j.messages.forEach(m=>{ subs.get(channel).since = Math.max(subs.get(channel).since||0, m.ts||0); try{ onMsg(m); }catch(_){ } }); }
      }catch(_){ }
    })();
    return ()=>{ subs.delete(channel); };
  }

  window.chat = { enabled: ()=>enabled, init, send, subscribe };
})();
