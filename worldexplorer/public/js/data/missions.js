// Mission schema:
// {
//   id, name, type: 'story'|'side'|'daily',
//   prereqs: { level?: number, missions?: string[], factionRep?: { [faction]: number } },
//   rewards: { xp?: number, currency?: number, items?: [{id, qty}], faction?: [{faction, rep}] },
//   factionEffects?: [{ faction: string, delta: number }],
//   stages: [{ id, name, goal: string }]
// }

(function (global) {
  const MISSIONS = [
    {
      id: 'prologue-escape',
      name: 'Prologue: Escape the Outskirts',
      type: 'story',
      prereqs: { level: 1 },
      rewards: { xp: 100, items: [{ id: 'potion_small', qty: 2 }], currency: 50 },
      factionEffects: [{ faction: 'settlers', delta: 5 }],
      stages: [
        { id: 'find-road', name: 'Find the old road', goal: 'Reach x:100,y:50' },
        { id: 'avoid-scouts', name: 'Avoid patrol scouts', goal: 'Stay undetected for 30s' },
        { id: 'reach-gate', name: 'Reach the city gate', goal: 'Go to x:220,y:75' }
      ]
    },
    {
      id: 'aid-the-inn',
      name: 'Side: Aid the Inn',
      type: 'side',
      prereqs: { missions: ['prologue-escape'], factionRep: { settlers: 5 } },
      rewards: { xp: 60, items: [{ id: 'food_ration', qty: 3 }], faction: [{ faction: 'settlers', rep: 10 }] },
      factionEffects: [{ faction: 'raiders', delta: -3 }, { faction: 'settlers', delta: +2 }],
      stages: [
        { id: 'gather-wood', name: 'Gather supplies', goal: 'Collect 5 wood' },
        { id: 'deliver', name: 'Deliver to Innkeeper', goal: 'Talk to NPC: Innkeeper' }
      ]
    },
    {
      id: 'bounty-daily',
      name: 'Daily: Bounty Board',
      type: 'daily',
      prereqs: { level: 3 },
      rewards: { xp: 40, currency: 35 },
      factionEffects: [{ faction: 'guards', delta: +1 }, { faction: 'raiders', delta: -1 }],
      stages: [{ id: 'clear', name: 'Clear the den', goal: 'Defeat 6 hostiles' }]
    }
  ];

  function meetsPrereqs(player, mission) {
    if (!player) return false;
    const p = mission.prereqs || {};
    if (p.level && (player.level || 1) < p.level) return false;
    if (p.missions && p.missions.some(m => !(player.completedMissions || []).includes(m))) return false;
    if (p.factionRep) {
      for (const k in p.factionRep) {
        const need = p.factionRep[k];
        const have = (player.factionRep && player.factionRep[k]) || 0;
        if (have < need) return false;
      }
    }
    return true;
  }

  function availableMissions(player) {
    return MISSIONS.filter(m => meetsPrereqs(player, m));
  }

  function getMissionById(id) {
    return MISSIONS.find(m => m.id === id) || null;
  }

  function getStage(mission, stageId) {
    return (mission.stages || []).find(s => s.id === stageId) || null;
  }

  global.AL_MISSIONS = {
    list: MISSIONS,
    availableMissions,
    getMissionById,
    getStage
  };

  // Notify listeners when missions data loads
  document.dispatchEvent(new CustomEvent('missions:data:ready', { detail: { count: MISSIONS.length } }));
})(window);
