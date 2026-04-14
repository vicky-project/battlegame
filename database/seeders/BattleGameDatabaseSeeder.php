<?php

namespace Modules\BattleGame\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\BattleGame\Models\BattleHero;
use Modules\BattleGame\Models\BattleEnemy;

class BattleGameDatabaseSeeder extends Seeder
{
  public function run() {
    // ==================== HEROES ====================
    $heroes = [
      [
        'name' => 'Warrior',
        'type' => 'warrior',
        'description' => 'Petarung tangguh dengan pertahanan kuat.',
        'base_hp' => 150,
        'base_atk' => 20,
        'base_def' => 15,
        'base_aspd' => 2.5,
        'base_block_chance' => 0.4,
        'base_block_reduction' => 0.5,
        'unlock_requirements' => [
          'required_user_level' => 1,
          'unlock_cost_gold' => 0,
        ],
        'is_active' => true,
      ],
      [
        'name' => 'Ninja',
        'type' => 'ninja',
        'description' => 'Cepat dan mematikan, namun rapuh.',
        'base_hp' => 90,
        'base_atk' => 35,
        'base_def' => 5,
        'base_aspd' => 1.2,
        'base_block_chance' => 0.1,
        'base_block_reduction' => 0.5,
        'unlock_requirements' => [
          'required_user_level' => 3,
          'unlock_cost_gold' => 500,
        ],
        'is_active' => true,
      ],
      [
        'name' => 'Mage',
        'type' => 'mage',
        'description' => 'Serangan sihir dahsyat namun lambat.',
        'base_hp' => 80,
        'base_atk' => 45,
        'base_def' => 3,
        'base_aspd' => 3.0,
        'base_block_chance' => 0.05,
        'base_block_reduction' => 0.5,
        'unlock_requirements' => [
          'required_user_level' => 5,
          'unlock_cost_gold' => 1000,
        ],
        'is_active' => true,
      ],
      [
        'name' => 'Berserker',
        'type' => 'berserker',
        'description' => 'Darah banyak, pukulan sakit.',
        'base_hp' => 200,
        'base_atk' => 30,
        'base_def' => 8,
        'base_aspd' => 1.8,
        'base_block_chance' => 0.15,
        'base_block_reduction' => 0.4,
        'unlock_requirements' => [
          'required_user_level' => 8,
          'unlock_cost_gold' => 2000,
        ],
        'is_active' => true,
      ],
      [
        'name' => 'Paladin',
        'type' => 'paladin',
        'description' => 'Pertahanan suci, sulit ditembus.',
        'base_hp' => 180,
        'base_atk' => 22,
        'base_def' => 25,
        'base_aspd' => 2.2,
        'base_block_chance' => 0.5,
        'base_block_reduction' => 0.6,
        'unlock_requirements' => [
          'required_user_level' => 10,
          'unlock_cost_gold' => 3000,
        ],
        'is_active' => true,
      ],
    ];

    foreach ($heroes as $heroData) {
      // Konversi unlock_requirements ke JSON string jika diperlukan
      // Model sudah memiliki cast 'array', jadi bisa langsung array
      BattleHero::firstOrCreate(
        ['name' => $heroData['name']],
        $heroData
      );
    }

    // ==================== ENEMIES ====================
    $enemies = [
      [
        'name' => 'Goblin',
        'min_level' => 1,
        'max_level' => 5,
        'base_hp' => 100,
        'base_atk' => 15,
        'base_def' => 5,
        'base_aspd' => 2.0,
        'base_block_chance' => 0.1,
        'base_block_reduction' => 0.5,
        'level_scaling_factor' => 0.1,
        'rewards' => ['exp' => 30,
          'gold' => 10],
        'is_active' => true,
      ],
      [
        'name' => 'Orc Warrior',
        'min_level' => 3,
        'max_level' => 10,
        'base_hp' => 180,
        'base_atk' => 22,
        'base_def' => 8,
        'base_aspd' => 2.5,
        'base_block_chance' => 0.15,
        'base_block_reduction' => 0.5,
        'level_scaling_factor' => 0.12,
        'rewards' => ['exp' => 60,
          'gold' => 25],
        'is_active' => true,
      ],
      [
        'name' => 'Dark Knight',
        'min_level' => 6,
        'max_level' => null,
        'base_hp' => 250,
        'base_atk' => 30,
        'base_def' => 15,
        'base_aspd' => 3.0,
        'base_block_chance' => 0.25,
        'base_block_reduction' => 0.5,
        'level_scaling_factor' => 0.15,
        'rewards' => ['exp' => 100,
          'gold' => 50],
        'is_active' => true,
      ],
      [
        'name' => 'Ancient Dragon',
        'min_level' => 10,
        'max_level' => null,
        'base_hp' => 400,
        'base_atk' => 45,
        'base_def' => 20,
        'base_aspd' => 4.0,
        'base_block_chance' => 0.2,
        'base_block_reduction' => 0.5,
        'level_scaling_factor' => 0.2,
        'rewards' => ['exp' => 200,
          'gold' => 100],
        'is_active' => true,
      ],
    ];

    foreach ($enemies as $enemyData) {
      BattleEnemy::firstOrCreate(
        ['name' => $enemyData['name']],
        $enemyData
      );
    }
  }
}