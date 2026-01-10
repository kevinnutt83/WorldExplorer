(function(){
  console.log('Game engine bootstrap loaded.');
  
  // Wait for DOM + catalogs
  window.addEventListener('load', ()=>{
    console.log('DOM loaded. Waiting for catalogs...');
  });
  
  document.addEventListener('al:data:catalogs-ready', e=>{
    console.log('Catalogs ready:', e.detail);
    
    // TODO: Initialize Phaser game here
    // Example:
    // const config = {
    //   type: Phaser.AUTO,
    //   parent: 'phaser-container',
    //   width: window.innerWidth,
    //   height: window.innerHeight - parseInt(getComputedStyle(document.documentElement).getPropertyValue('--al-footer-h')),
    //   physics: { default: 'arcade', arcade: { gravity: { y: 0 } } },
    //   scene: [WorldScene, UIScene]
    // };
    // window.PHASER_GAME = new Phaser.Game(config);
    
    // Fire game ready
    setTimeout(()=>{
      document.dispatchEvent(new CustomEvent('al:game:ready',{detail:{engine:'stub'}}));
      console.log('Game ready event fired (stub mode).');
    }, 100);
  });
  
  // Debug helper
  window.AL_DEBUG = {
    spawnEntity: (type, x, y) => {
      document.dispatchEvent(new CustomEvent('al:spawn',{
        detail:{type, x, y, id:'debug_'+Date.now()}
      }));
    },
    startCombat: () => {
      document.dispatchEvent(new CustomEvent('al:combat:engage',{
        detail:{
          A:{name:'Player',hp:120,dmg:20,armor:5},
          B:{name:'Enemy',hp:80,dmg:15,armor:3}
        }
      }));
    },
    showDialog: (text) => {
      document.dispatchEvent(new CustomEvent('al:dialog:show',{
        detail:{text, lines:[text]}
      }));
    },
    sendChat: (text) => {
      document.dispatchEvent(new CustomEvent('al:chat:send',{
        detail:{channel:'global', text}
      }));
    }
  };
  
  console.log('Debug helpers available: window.AL_DEBUG');
})();
