(function () {
  const grid = document.getElementById('inventory-grid');
  const details = document.getElementById('inventory-item-details');

  function renderDetails(itemId) {
    const it = window.AL_ITEMS?.getItem(itemId);
    if (!it || !details) return;
    const isCons = !!it.consumable;
    const eff = it.effects || {};
    const lines = [];
    if (eff.healHp) lines.push(`Heals HP: +${eff.healHp}`);
    if (eff.healStamina) lines.push(`Restores Stamina: +${eff.healStamina}`);
    if (eff.regen) lines.push(`Regen: +${eff.regen.hpPerSec || 0} HP/s for ${eff.regen.secs || 0}s`);
    if (eff.buffs && eff.buffs.length) lines.push('Buffs: ' + eff.buffs.map(b => `${b.stat}+${Math.round(b.value * 100)}% for ${b.secs}s`).join(', '));
    if (!lines.length) lines.push('No special effects.');
    const cd = it.cooldownSecs ? `Cooldown: ${it.cooldownSecs}s` : '';
    details.innerHTML = `
      <div class="d-flex justify-content-between align-items-center">
        <div><strong>${it.name}</strong><div class="text-muted">${lines.join(' • ')} ${cd ? ' • ' + cd : ''}</div></div>
        <div class="d-flex gap-2">
          ${isCons ? `<button class="btn btn-sm btn-success" data-action="use" data-item="${it.id}">Use</button>` : ''}
          <button class="btn btn-sm btn-outline-light" data-action="drop" data-item="${it.id}">Drop</button>
        </div>
      </div>`;
  }

  function onGridClick(e) {
    const el = e.target.closest('[data-item-id]');
    if (!el) return;
    const itemId = el.getAttribute('data-item-id');
    if (!itemId) return;
    renderDetails(itemId);
  }

  function onDetailsClick(e) {
    const btn = e.target.closest('button[data-action]');
    if (!btn) return;
    const itemId = btn.getAttribute('data-item');
    const action = btn.getAttribute('data-action');
    if (action === 'use') {
      window.AL_CONSUMABLES?.consume(itemId);
    } else if (action === 'drop') {
      if (window.AL_API?.inventory?.drop) window.AL_API.inventory.drop(itemId, 1);
      document.dispatchEvent(new CustomEvent('inventory:drop', { detail: { itemId, qty: 1 } }));
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    if (grid) grid.addEventListener('click', onGridClick);
    if (details) details.addEventListener('click', onDetailsClick);
  });

  // Auto-highlight consumables in grid when items render externally
  document.addEventListener('inventory:rendered', () => {
    if (!grid || !window.AL_ITEMS) return;
    grid.querySelectorAll('[data-item-id]').forEach(node => {
      const id = node.getAttribute('data-item-id');
      if (window.AL_ITEMS.isConsumable(id)) {
        node.classList.add('border', 'border-success', 'rounded');
        node.setAttribute('title', 'Consumable');
      }
    });
  });
})();
