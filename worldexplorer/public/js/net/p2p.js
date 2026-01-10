// Server-polled party sync
(function(){
  const BASE = (window.AFTERLIGHT_CONFIG?.baseUrl)||'';
  const P2P = { ready:false, room:null, onmsg:null };
  let poll = null;

  async function host(room){
    P2P.room = room; await fetch(`${BASE}/backend/api/party.php`, { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'host', room })});
    P2P.ready = true; startPolling();
  }
  async function join(room){
    P2P.room = room; await fetch(`${BASE}/backend/api/party.php`, { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'join', room })});
    P2P.ready = true; startPolling();
  }
  function startPolling(){ if (poll) return; poll = setInterval(async ()=>{
      try{
        const url = new URL(`${BASE}/backend/api/party.php?action=positions`); if (P2P.room) url.searchParams.set('room', P2P.room);
        const j = await fetch(url.toString(), { credentials:'include' }).then(r=>r.json());
        (j.positions||[]).forEach(p=>{ const msg = JSON.stringify({ t:'pos', id:`${p.id||('c-'+p.char_id)}`, x: p.x, y: p.y }); if(P2P.onmsg) try{ P2P.onmsg(msg); }catch(_){ } });
      }catch(_){ }
    }, 1200);
  }
  async function send(msg){
    try{ const data = JSON.parse(msg); if (data.t==='pos'){ await fetch(`${BASE}/backend/api/party.php`, { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'pos', room:P2P.room, x: data.x, y: data.y })}); } }
    catch(_){ }
  }
  function onMessage(fn){ P2P.onmsg = fn; }
  window.p2p = { host, join, state: ()=>({ ...P2P }), send, onMessage };
})();
