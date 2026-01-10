// Phaser game bootstrap
(function(){
  const w = window;
  const config = {
    type: Phaser.AUTO,
    parent: 'phaser-container',
    backgroundColor: '#0b0d12',
    scale: { mode: Phaser.Scale.RESIZE, width: '100%', height: '100%' },
    physics: { default: 'arcade', arcade: { debug: false } },
    scene: [BootScene, WorldScene, UIScene]
  };
  w.ALGame = new Phaser.Game(config);
})();
