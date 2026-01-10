// Payments client: fetch config, list packs, and create orders (test mode auto-credits)
(function(){
  const base = (window.AFTERLIGHT_CONFIG && window.AFTERLIGHT_CONFIG.baseUrl) || '';

  async function getConfig(){
    const res = await fetch(`${base}/backend/api/payments.php?action=config`, { credentials:'include' });
    return res.json();
  }
  async function getWallet(){
    const res = await fetch(`${base}/backend/api/payments.php?action=wallet`, { credentials:'include' });
    return res.json();
  }
  async function createOrderCurrency(packId){
    const res = await fetch(`${base}/backend/api/payments.php`, { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'create_order', type:'currency', pack_id: packId }) });
    return res.json();
  }
  async function buyVehicle(kind){
    const res = await fetch(`${base}/backend/api/payments.php`, { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'create_order', type:'vehicle', kind }) });
    return res.json();
  }

  async function refreshShop(){
    try{
      const cfg = await getConfig();
      const packs = (cfg.config && cfg.config.packs) || [];
      const list = document.getElementById('shop-packs'); if (!list) return;
      list.innerHTML = '';
      packs.forEach(p=>{
        const a = document.createElement('a'); a.href='#'; a.className='list-group-item list-group-item-action d-flex justify-content-between align-items-center';
        a.innerHTML = `<span>${p.name} <small class='text-muted'>+${p.amount} credits</small></span><span>$${(p.price/100).toFixed(2)}</span>`;
        a.addEventListener('click', async (e)=>{ e.preventDefault(); const j = await createOrderCurrency(p.id); if (j.ok){
          ALLog.info('shop','purchase_ok',{pack:p.id});
          refreshWallet();
          alert('Purchase complete. Wallet credited.');
        } else { alert(j.error||'Purchase failed'); }
        });
        list.appendChild(a);
      });
    }catch(e){ /* ignore */ }
  }
  async function refreshWallet(){
    try{ const j = await getWallet(); if (j.ok){ document.getElementById('wallet-balance').textContent = j.balance; } } catch(_){ }
  }

  document.getElementById('btn-shop')?.addEventListener('click', ()=>{
    document.getElementById('panel-shop').style.display = 'block';
    refreshWallet(); refreshShop();
  });
  document.querySelector('[data-close="panel-shop"]')?.addEventListener('click', ()=>{ document.getElementById('panel-shop').style.display = 'none'; });
  document.getElementById('btn-refresh-wallet')?.addEventListener('click', refreshWallet);

  document.getElementById('shop-buy-vehicle')?.addEventListener('click', async ()=>{
    const kind = document.getElementById('shop-vehicle-kind').value || 'car';
    const j = await buyVehicle(kind); if (j.ok){ alert('Vehicle granted (test mode). Check nearby!'); } else { alert(j.error||'Failed'); }
  });

})();
