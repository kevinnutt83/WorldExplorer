// Item schema (subset):
// { id, name, kind: 'weapon'|'tool'|'armor'|'consumable'|'material', stack: number, consumable?: true,
//   effects?: { healHp?: number, healStamina?: number, regen?: { hpPerSec?: number, secs?: number },
//               buffs?: [{ stat: 'spd'|'def'|'atk', value: number, secs: number }] },
//   cooldownSecs?: number }

(function (global) {
  const ITEMS = {
    potion_small: {
      id: 'potion_small', name: 'Minor Health Potion', kind: 'consumable', stack: 20, consumable: true,
      effects: { healHp: 50 }, cooldownSecs: 8
    },
    potion_large: {
      id: 'potion_large', name: 'Greater Health Potion', kind: 'consumable', stack: 10, consumable: true,
      effects: { healHp: 120, regen: { hpPerSec: 5, secs: 10 } }, cooldownSecs: 12
    },
    food_ration: {
      id: 'food_ration', name: 'Field Ration', kind: 'consumable', stack: 20, consumable: true,
      // No hunger meter; small sustain similar to Fallout aid
      effects: { healHp: 20, healStamina: 25 }, cooldownSecs: 6
    },
    elixir_speed: {
      id: 'elixir_speed', name: 'Elixir of Haste', kind: 'consumable', stack: 10, consumable: true,
      effects: { buffs: [{ stat: 'spd', value: 0.15, secs: 60 }] }, cooldownSecs: 20
    }
    // ...extend with weapons, armor, materials, etc.
  };

  function getItem(id) { return ITEMS[id] || null; }
  function isConsumable(id) { return !!(ITEMS[id] && ITEMS[id].consumable); }

  global.AL_ITEMS = { map: ITEMS, getItem, isConsumable };

  document.dispatchEvent(new CustomEvent('items:data:ready', { detail: { count: Object.keys(ITEMS).length } }));
})(window);
