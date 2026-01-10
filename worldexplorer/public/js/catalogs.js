(function(){
  const add=(arr,items)=>items.forEach(i=>{ if(!arr.find(x=>x.id===i.id)) arr.push(i); });

  // Items
  window.AFTERLIGHT_ITEMS = window.AFTERLIGHT_ITEMS || [];
  const mats=['wood','stone','iron','steel','obsidian','leather','cloth'];
  const tiers={wood:1,stone:2,cloth:1,leather:2,iron:3,steel:4,obsidian:5};
  const weapons=['sword','axe','mace','dagger','spear','bow','staff'];
  
  mats.forEach(m=>{
    add(window.AFTERLIGHT_ITEMS,[{id:`${m}_scrap`,name:`${m[0].toUpperCase()+m.slice(1)} Scrap`,kind:'material',rarity:'common'}]);
    if(['iron','steel','obsidian'].includes(m)) add(window.AFTERLIGHT_ITEMS,[{id:`${m}_ingot`,name:`${m[0].toUpperCase()+m.slice(1)} Ingot`,kind:'material',rarity:'uncommon'}]);
    weapons.forEach(wt=>{
      const isRanged=['bow','staff'].includes(wt);
      add(window.AFTERLIGHT_ITEMS,[{id:`${m}_${wt}`,name:`${m[0].toUpperCase()+m.slice(1)} ${wt[0].toUpperCase()+wt.slice(1)}`,kind:'weapon',slot:'weapon',type:isRanged?'ranged':'melee',dmg:8+tiers[m]*5,speed:Math.max(0.6,1.2-tiers[m]*0.05),crit:0.05+tiers[m]*0.01}]);
    });
    add(window.AFTERLIGHT_ITEMS,[
      {id:`${m}_helm`,name:`${m[0].toUpperCase()+m.slice(1)} Helm`,kind:'armor',slot:'head',armor:5+tiers[m]*2},
      {id:`${m}_chest`,name:`${m[0].toUpperCase()+m.slice(1)} Chestplate`,kind:'armor',slot:'chest',armor:10+tiers[m]*4},
      {id:`${m}_legs`,name:`${m[0].toUpperCase()+m.slice(1)} Greaves`,kind:'armor',slot:'legs',armor:8+tiers[m]*3},
      {id:`${m}_gloves`,name:`${m[0].toUpperCase()+m.slice(1)} Gloves`,kind:'armor',slot:'hand_left',armor:4+tiers[m]*1},
      {id:`${m}_boots`,name:`${m[0].toUpperCase()+m.slice(1)} Boots`,kind:'armor',slot:'hand_right',armor:4+tiers[m]*1}
    ]);
  });
  
  add(window.AFTERLIGHT_ITEMS,[
    { id:'health_potion_small', name:'Health Potion (S)', kind:'consumable', rarity:'common', effects:[{type:'heal',stat:'hp',amount:50}] },
    { id:'health_potion_medium', name:'Health Potion (M)', kind:'consumable', rarity:'uncommon', effects:[{type:'heal',stat:'hp',amount:150}] },
    { id:'mana_potion_small', name:'Mana Potion (S)', kind:'consumable', rarity:'common', effects:[{type:'heal',stat:'mana',amount:30}] },
    { id:'food_bread', name:'Bread', kind:'consumable', rarity:'common', effects:[{type:'regen',stat:'hp',rate:2,duration:10}] },
    { id:'food_meat', name:'Cooked Meat', kind:'consumable', rarity:'common', effects:[{type:'regen',stat:'hp',rate:5,duration:10}] },
    { id:'decor_torch', name:'Wall Torch', kind:'decoration', rarity:'common' },
    { id:'decor_chair', name:'Wooden Chair', kind:'decoration', rarity:'common' }
  ]);
  
  window.AFTERLIGHT_WEAPONS = window.AFTERLIGHT_ITEMS.filter(i=>i.kind==='weapon');

  // Recipes
  window.AFTERLIGHT_RECIPES = window.AFTERLIGHT_RECIPES || [];
  add(window.AFTERLIGHT_RECIPES,[
    { id:'craft_plank', name:'Craft Plank', inputs:[{id:'wood_scrap',qty:2}], outputs:[{id:'wood_plank',qty:1}] },
    { id:'craft_iron_ingot', name:'Smelt Iron Ingot', inputs:[{id:'iron_scrap',qty:2}], outputs:[{id:'iron_ingot',qty:1}] },
    { id:'craft_wood_sword', name:'Craft Wooden Sword', inputs:[{id:'wood_plank',qty:2}], outputs:[{id:'wood_sword',qty:1}] },
    { id:'craft_iron_sword', name:'Craft Iron Sword', inputs:[{id:'iron_ingot',qty:2},{id:'leather_scrap',qty:1}], outputs:[{id:'iron_sword',qty:1}] },
    { id:'craft_hp_pot_s', name:'Brew Healing Potion (S)', inputs:[{id:'herb_red',qty:2}], outputs:[{id:'health_potion_small',qty:1}] }
  ]);

  // Missions
  window.AFTERLIGHT_MISSIONS = window.AFTERLIGHT_MISSIONS || [];
  for(let lvl=1; lvl<=20; lvl++){
    add(window.AFTERLIGHT_MISSIONS,[
      { id:`hunt_wolf_${lvl}`, name:`Cull Wolves (L${lvl})`, type:'hunt', prerequisites:[{type:'level',min:lvl}], rewards:[{type:'xp',amount:50*lvl},{type:'credits',amount:10*lvl}], target:{ mob:'mob_wolf', qty:2+lvl } },
      { id:`bounty_raider_${lvl}`, name:`Clear Raiders (L${lvl})`, type:'bounty', prerequisites:[{type:'level',min:lvl}], rewards:[{type:'xp',amount:70*lvl},{type:'credits',amount:15*lvl}], factionEffects:[{faction:'raiders',delta:-5}] },
      { id:`explore_ruins_${lvl}`, name:`Explore Ruins (L${lvl})`, type:'exploration', prerequisites:[{type:'level',min:lvl}], rewards:[{type:'xp',amount:40*lvl}] },
      { id:`escort_merchant_${lvl}`, name:`Escort Merchant (L${lvl})`, type:'escort', prerequisites:[{type:'level',min:lvl}], rewards:[{type:'credits',amount:50*lvl}], factionEffects:[{faction:'traders',delta:5}] },
      { id:`dungeon_crypt_${lvl}`, name:`Clear Crypt (L${lvl})`, type:'dungeon', prerequisites:[{type:'level',min:lvl}], rewards:[{type:'xp',amount:100*lvl},{type:'item',id:'iron_sword',qty:1}], dungeon:`crypt_lvl_${lvl}` }
    ]);
  }

  // Mobs/Bosses/Dungeons
  window.AFTERLIGHT_MOBS = window.AFTERLIGHT_MOBS || [
    { id:'mob_wolf', name:'Ash Wolf', kind:'mob', level:4, hp:140, dmg:16, tags:['beast'], loot:[{id:'health_potion_small',chance:0.18}] },
    { id:'boss_raider_warlord', name:'Warlord Krell', kind:'boss', level:8, hp:1100, dmg:55, crit:0.15, tags:['raider','boss'], loot:[{id:'scrap_rifle',chance:0.2},{id:'shield_cell',chance:0.3}] }
  ];
  
  window.AFTERLIGHT_DUNGEONS = window.AFTERLIGHT_DUNGEONS || [
    { id:'dn_tunnels', name:'Forgotten Tunnels', theme:'ruins', minLevel:2, boss:'boss_raider_warlord', rooms:8, seed:'tunnels', tags:['beast'] }
  ];

  // Skills
  window.AFTERLIGHT_SKILLS = window.AFTERLIGHT_SKILLS || [
    { id:'sk_power_strike', name:'Power Strike', type:'active', cooldown:5, effect:{kind:'damage_mult',value:1.5} },
    { id:'sk_fireball', name:'Fireball', type:'active', cooldown:8, cost:{mana:15}, effect:{kind:'damage',value:60,element:'fire'} },
    { id:'pk_thick_skin', name:'Thick Skin', type:'passive', effect:{kind:'armor_flat',value:10} }
  ];

  // Classes
  window.AFTERLIGHT_CLASSES = window.AFTERLIGHT_CLASSES || [
    { id:'warrior', name:'Warrior', desc:'Melee powerhouse.', skills:['sk_power_strike','pk_thick_skin'] },
    { id:'mage', name:'Mage', desc:'Master of elements.', skills:['sk_fireball'] },
    { id:'rogue', name:'Rogue', desc:'Swift and deadly.', skills:[] }
  ];

  // Factions
  window.AFTERLIGHT_FACTIONS = window.AFTERLIGHT_FACTIONS || [
    { id:'raiders', name:'Raiders', desc:'Hostile scavengers.', traits:['aggressive'], buffs:[] },
    { id:'traders', name:'Traders Guild', desc:'Merchants and caravans.', traits:['peaceful'], buffs:[{stat:'barter',amount:10}] }
  ];

  // Notify engine
  document.dispatchEvent(new CustomEvent('al:data:catalogs-ready', {
    detail:{
      items: window.AFTERLIGHT_ITEMS,
      weapons: window.AFTERLIGHT_WEAPONS,
      missions: window.AFTERLIGHT_MISSIONS,
      recipes: window.AFTERLIGHT_RECIPES,
      skills: window.AFTERLIGHT_SKILLS,
      classes: window.AFTERLIGHT_CLASSES,
      mobs: window.AFTERLIGHT_MOBS,
      dungeons: window.AFTERLIGHT_DUNGEONS,
      factions: window.AFTERLIGHT_FACTIONS
    }
  }));
})();
