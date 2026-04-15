<?php
namespace Modules\BattleGame\Characters\Enemies;

use Modules\BattleGame\Characters\Base\Enemy;

class Goblin extends Enemy
{
  public function __construct() {
    parent::__construct(
      id: 'goblin',
      name: 'Goblin',
      emoji: '👺',
      minLevel: 1,
      maxLevel: 5,
      rewards: ['exp' => 30, 'gold' => 10],
    );
  }

  public function baseHp(): int {
    return 100;
  }
  public function baseAtk(): int {
    return 15;
  }
  public function baseDef(): int {
    return 5;
  }
  public function baseAspd(): float {
    return 2.0;
  }
  public function baseBlockChance(): float {
    return 0.1;
  }
  public function baseBlockReduction(): float {
    return 0.5;
  }
  public function levelScalingFactor(): float {
    return 0.1;
  }
}