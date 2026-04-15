<?php
namespace Modules\BattleGame\Characters\Heroes;

use Modules\BattleGame\Characters\Base\Hero;
use Modules\BattleGame\Enums\HeroType;

class Warrior extends Hero
{
  public function __construct() {
    parent::__construct(
      id: 'warrior',
      name: 'Warrior',
      type: HeroType::WARRIOR,
      description: 'Petarung tangguh dengan pertahanan kuat.',
      emoji: '🛡️',
      unlockRequirements: ['required_user_level' => 1, 'unlock_cost_gold' => 0],
    );
  }

  public function baseHp(): int {
    return 150;
  }
  public function baseAtk(): int {
    return 20;
  }
  public function baseDef(): int {
    return 15;
  }
  public function baseAspd(): float {
    return 2.5;
  }
  public function baseBlockChance(): float {
    return 0.4;
  }
  public function baseBlockReduction(): float {
    return 0.5;
  }
}