<?php

return [
  'name' => 'BattleGame',
  /*
    |--------------------------------------------------------------------------
    | Diamond Packages
    |--------------------------------------------------------------------------
    |
    | Daftar paket diamond yang tersedia di toko.
    | Setiap paket memiliki id, nama, gold_cost, dan diamond.
    |
    */
  'diamond_packages' => [
    [
      'id' => 'small',
      'name' => 'Paket Kecil',
      'gold_cost' => 500,
      'diamond' => 5,
    ],
    [
      'id' => 'medium',
      'name' => 'Paket Sedang',
      'gold_cost' => 1200,
      'diamond' => 15,
    ],
    [
      'id' => 'large',
      'name' => 'Paket Besar',
      'gold_cost' => 2500,
      'diamond' => 35,
    ],
    [
      'id' => 'xl',
      'name' => 'Paket Super',
      'gold_cost' => 5000,
      'diamond' => 80,
    ],
  ],

  /*
    |--------------------------------------------------------------------------
    | Battle Cost
    |--------------------------------------------------------------------------
    */
  'battle_cost' => env('BATTLE_COST', 10),

  /*
    |--------------------------------------------------------------------------
    | Mining Settings
    |--------------------------------------------------------------------------
    */
  'mining' => [
    'interval_seconds' => env('MINING_INTERVAL_SECONDS'), // 1 jam
    'gold_per_interval' => env('MINING_GOLD', 100),
  ],
];