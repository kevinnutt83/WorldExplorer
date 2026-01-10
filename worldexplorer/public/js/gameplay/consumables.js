(function (global) {
  const clamp = (v, min, max) => Math.max(min, Math.min(max, v));

  const STATE = (global.AL_STATE = global.AL_STATE || {
    player: { hp: 100, hpMax: 100, stamina: 50, staminaMax: 50, buffs: {}, level: 1, completedMissions: [], factionRep: {} }
  });

  const cooldowns = new Map(); // itemId -> timestamp(ms)

  function canUse(item) {
    if (!item) return false;
    const until = cooldowns.get(item.id) || 0;
    return Date.now() >= until;
  }

  function setCooldown(item) {
    const secs = item.cooldownSecs || 0;
    if (secs > 0) cooldowns.set(item.id, Date.now() + secs * 1000);
  }

  function applyInstantEffects(eff) {
    const p = STATE.player;
    if (eff.healHp) p.hp = clamp(p.hp + eff.healHp, 0, p.hpMax || 100);
    if (eff.healStamina) p.stamina = clamp(p.stamina + eff.healStamina, 0, p.staminaMax || 100);
    updateHUD();
  }

  function applyRegen(eff) {
    if (!eff.regen) return;
    const { hpPerSec = 0, secs = 0 } = eff.regen;
    if (!hpPerSec || !secs) return;
    let ticks = secs;
    const t = setInterval(() => {
      if (ticks-- <= 0) return clearInterval(t);
      const p = STATE.player;
      p.hp = clamp(p.hp + hpPerSec, 0, p.hpMax || 100);
      updateHUD();
    }, 1000);
  }

  function applyBuffs(eff) {
    if (!eff.buffs || !eff.buffs.length) return;
    eff.buffs.forEach(b => {
      const key = b.stat;
      const until = Date.now() + (b.secs || 0) * 1000;
      STATE.player.buffs[key] = { value: b.value, until };
      setTimeout(() => {
        if (STATE.player.buffs[key] && STATE.player.buffs[key].until <= Date.now()) delete STATE.player.buffs[key];
        document.dispatchEvent(new CustomEvent('player:buffs:changed', { detail: { buffs: STATE.player.buffs } }));
      }, (b.secs || 0) * 1000 + 50);
    });
    document.dispatchEvent(new CustomEvent('player:buffs:changed', { detail: { buffs: STATE.player.buffs } }));
  }

  function updateHUD() {
    const hpBar = document.getElementById('hud-hp');
    const hpPct = Math.round((STATE.player.hp / (STATE.player.hpMax || 100)) * 100);
    if (hpBar) hpBar.style.width = `${clamp(hpPct, 0, 100)}%`;
    // optionally update stamina if present
  }

  async function consume(itemId) {
    const item = global.AL_ITEMS?.getItem(itemId);
    if (!item || !item.consumable) return { ok: false, reason: 'not-consumable' };
    if (!canUse(item)) return { ok: false, reason: 'cooldown' };

    // Optimistic local apply
    const eff = item.effects || {};
    applyInstantEffects(eff);
    applyRegen(eff);
    applyBuffs(eff);
    setCooldown(item);

    // Backend inventory decrement if available
    try {
      if (global.AL_API?.inventory?.consume) {
        await global.AL_API.inventory.consume(itemId, 1);
      } else {
        document.dispatchEvent(new CustomEvent('inventory:consume', { detail: { itemId, qty: 1 } }));
      }
    } catch (e) {
      console.warn('Inventory consume failed; effects already applied locally.', e);
    }

    return { ok: true };
  }

  global.AL_CONSUMABLES = { consume, canUse };
})(window);
