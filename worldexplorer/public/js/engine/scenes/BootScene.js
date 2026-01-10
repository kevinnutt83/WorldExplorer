class BootScene extends Phaser.Scene {
  constructor(){ super('BootScene'); }
  preload(){
    // Load local fallback assets with base path awareness
    const base = (window.AFTERLIGHT_CONFIG && window.AFTERLIGHT_CONFIG.baseUrl) || '';
    this.load.image('player', `${base}/public/assets/images/player.svg`);
    this.load.image('tile', `${base}/public/assets/images/tile.svg`);
    this.load.image('vehicle_default', `${base}/public/assets/images/vehicle_default.svg`);
    this.load.image('vehicle_car', `${base}/public/assets/images/vehicle_car.svg`);
    this.load.image('vehicle_bike', `${base}/public/assets/images/vehicle_bike.svg`);
    this.load.image('vehicle_truck', `${base}/public/assets/images/vehicle_truck.svg`);
    this.load.image('npc_default', `${base}/public/assets/images/npc_default.svg`);
    this.load.image('node_tree', `${base}/public/assets/images/node_tree.svg`);
    this.load.image('node_ore', `${base}/public/assets/images/node_ore.svg`);
    this.load.image('node_water', `${base}/public/assets/images/node_water.svg`);
    this.load.image('node_scrap', `${base}/public/assets/images/node_scrap.svg`);
    this.load.image('item_default', `${base}/public/assets/images/item_default.svg`);
  }
  create(){
    this.scene.start('WorldScene');
  }
}
