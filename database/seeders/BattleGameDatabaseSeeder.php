<?php

namespace Modules\BattleGame\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\BattleGame\Characters\CharacterRegistry;
use Modules\BattleGame\Models\BattleHero;
use Modules\BattleGame\Models\BattleEnemy;

class BattleGameDatabaseSeeder extends Seeder
{
  public function run() {
    $this->seedHeroes();
    $this->seedEnemies();
  }

  private function seedHeroes(): void
  {
    foreach (CharacterRegistry::getHeroes() as $hero) {
      BattleHero::firstOrCreate(
        ['name' => $hero->name],
        $hero->toArray()
      );
    }
  }

  private function seedEnemies(): void
  {
    foreach (CharacterRegistry::getEnemies() as $enemy) {
      BattleEnemy::firstOrCreate(
        ['name' => $enemy->name],
        $enemy->toArray()
      );
    }
  }
}