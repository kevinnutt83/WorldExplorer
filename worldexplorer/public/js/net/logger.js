// Afterlight client logger (batched)
(function(){
  const queue = [];
  const maxBatch = 20;
  const flushInterval = 5000;
  const base = (window.AFTERLIGHT_CONFIG && window.AFTERLIGHT_CONFIG.baseUrl) || '';

  function enqueue(level, category, message, context){
    queue.push({ level, category, message, context, ts: Date.now() });
    if (queue.length >= maxBatch) flush();
  }

  async function flush(){
    if (queue.length === 0) return;
    const batch = queue.splice(0, maxBatch);
    try {
      await fetch(`${base}/backend/api/log.php`, {
        method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ entries: batch })
      });
    } catch(e){ /* ignore */ }
  }

  setInterval(flush, flushInterval);

  window.ALLog = {
    info: (cat,msg,ctx)=>enqueue('info',cat,msg,ctx||{}),
    warn: (cat,msg,ctx)=>enqueue('warn',cat,msg,ctx||{}),
    error: (cat,msg,ctx)=>enqueue('error',cat,msg,ctx||{}),
  };

  window.addEventListener('error', function(ev){
    try { enqueue('error','js', ev.message || 'error', { file: ev.filename, line: ev.lineno, col: ev.colno }); } catch(_){ }
  });
  window.addEventListener('unhandledrejection', function(ev){
    try { enqueue('error','promise', (ev.reason && (ev.reason.message||ev.reason)) || 'unhandledrejection', {}); } catch(_){ }
  });
})();
