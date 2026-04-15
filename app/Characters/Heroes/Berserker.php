<?php
namespace Modules\BattleGame\Characters\Heroes;

use Modules\BattleGame\Characters\Base\Hero;
use Modules\BattleGame\Enums\HeroType;

class Berserker extends Hero
{
  public function __construct() {
    parent::__construct(
      id: 'berserker',
      name: 'Berserker',
      type: HeroType::BERSERKER,
      description: 'Darah banyak, pukulan sakit.',
      emoji: '⚔️',
      unlockRequirements: ['required_user_level' => 8, 'unlock_cost_gold' => 2000],
    );
  }

  public function baseHp(): int {
    return 200;
  }
  public function baseAtk(): int {
    return 30;
  }
  public function baseDef(): int {
    return 8;
  }
  public function baseAspd(): float {
    return 1.8;
  }
  public function baseBlockChance(): float {
    return 0.15;
  }
  public function baseBlockReduction(): float {
    return 0.4;
  }
}