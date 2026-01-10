(function () {
  const $ = sel => document.querySelector(sel);

  function fmtPrereqs(p) {
    if (!p) return 'None';
    const parts = [];
    if (p.level) parts.push(`Level ≥ ${p.level}`);
    if (p.missions && p.missions.length) parts.push(`Missions: ${p.missions.join(', ')}`);
    if (p.factionRep) {
      parts.push('Faction rep: ' + Object.entries(p.factionRep).map(([f, v]) => `${f} ≥ ${v}`).join(', '));
    }
    return parts.length ? parts.join(' • ') : 'None';
    }

  function fmtRewards(r) {
    if (!r) return 'None';
    const parts = [];
    if (r.xp) parts.push(`XP +${r.xp}`);
    if (r.currency) parts.push(`Coins +${r.currency}`);
    if (r.items && r.items.length) parts.push('Items: ' + r.items.map(i => `${i.id} x${i.qty}`).join(', '));
    if (r.faction && r.faction.length) parts.push('Faction: ' + r.faction.map(f => `${f.faction} +${f.rep}`).join(', '));
    return parts.join(' • ');
  }

  function fmtFactions(effects) {
    if (!effects || !effects.length) return 'None';
    return effects.map(e => `${e.faction} ${e.delta >= 0 ? '+' : ''}${e.delta}`).join(' • ');
  }

  function renderSelect(player) {
    const select = $('#missions-all');
    if (!select || !window.AL_MISSIONS) return;
    const list = window.AL_MISSIONS.availableMissions(player);
    select.innerHTML = '';
    list.forEach(m => {
      const opt = document.createElement('option');
      opt.value = m.id;
      opt.textContent = `${m.name} (${m.type})`;
      select.appendChild(opt);
    });
    if (list[0]) updateDetails(list[0].id);
  }

  function updateDetails(id) {
    const m = window.AL_MISSIONS.getMissionById(id);
    if (!m) return;
    const byId = s => document.getElementById(s);
    byId('mission-field-type').textContent = m.type;
    byId('mission-field-prereqs').textContent = fmtPrereqs(m.prereqs);
    byId('mission-field-rewards').textContent = fmtRewards(m.rewards);
    byId('mission-field-factions').textContent = fmtFactions(m.factionEffects);
  }

  function wireHandlers() {
    const select = $('#missions-all');
    if (select) {
      select.addEventListener('change', () => updateDetails(select.value));
    }
    const startBtn = $('#btn-mission-start');
    if (startBtn) {
      startBtn.addEventListener('click', async () => {
        const id = $('#missions-all')?.value;
        if (!id) return;
        try {
          // Prefer backend if available
          if (window.AL_API?.missions?.start) {
            await window.AL_API.missions.start(id);
          }
          document.dispatchEvent(new CustomEvent('missions:started', { detail: { id } }));
        } catch (e) {
          console.warn('Mission start failed (fallback to client only):', e);
        }
      });
    }
    const advBtn = $('#btn-mission-advance');
    if (advBtn) {
      advBtn.addEventListener('click', async () => {
        const id = $('#missions-all')?.value;
        if (!id) return;
        try {
          if (window.AL_API?.missions?.advance) {
            await window.AL_API.missions.advance(id);
          }
          document.dispatchEvent(new CustomEvent('missions:advanced', { detail: { id } }));
        } catch (e) {
          console.warn('Mission advance failed (client fallback):', e);
        }
      });
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    const player = window.AL_STATE?.player || { level: 1, completedMissions: [], factionRep: {} };
    renderSelect(player);
    wireHandlers();
  });

  document.addEventListener('missions:data:ready', () => {
    const player = window.AL_STATE?.player || { level: 1, completedMissions: [], factionRep: {} };
    renderSelect(player);
  });
})();
