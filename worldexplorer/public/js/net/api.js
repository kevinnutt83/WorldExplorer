// Simple API client for Afterlight
(function(){
  const cfg = (typeof window !== 'undefined' && window.AFTERLIGHT_CONFIG) ? window.AFTERLIGHT_CONFIG : {};
  const base = cfg.baseUrl || '';

  async function request(path, options={}){
    const res = await fetch(`${base}/backend/api/${path}`, {
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      ...options
    });
    if (!res.ok) throw new Error(`API ${path} failed: ${res.status}`);
    return res.json();
  }

  window.api = {
    login: (username, password) => request('auth.php', { method:'POST', body: JSON.stringify({ action:'login', username, password }) }),
    logout: () => request('auth.php', { method:'POST', body: JSON.stringify({ action:'logout' }) }),
    register: (username, email, password) => request('auth.php', { method:'POST', body: JSON.stringify({ action:'register', username, email, password }) }),
    me: () => request('user.php?action=me'),
    createCharacter: (name, arch) => request('character.php', { method:'POST', body: JSON.stringify({ action:'create', name, arch }) }),
    savePosition: (x,y) => request('character.php', { method:'POST', body: JSON.stringify({ action:'save_pos', x, y }) }),
    loadWorld: () => request('world.php?action=bootstrap'),
    inventory: () => request('inventory.php?action=list'),
    consume: (item_id) => request('inventory.php', { method:'POST', body: JSON.stringify({ action:'consume', item_id }) }),
    theme: () => request('config.php?action=theme'),
  };
})();
