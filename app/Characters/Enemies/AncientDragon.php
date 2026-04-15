<?php
namespace Modules\BattleGame\Characters\Enemies;

use Modules\BattleGame\Characters\Base\Enemy;

class AncientDragon extends Enemy
{
  public function __construct() {
    parent::__construct(
      id: 'ancient_dragon',
      name: 'Ancient Dragon',
      emoji: '🐉',
      minLevel: 10,
      maxLevel: null,
      rewards: ['exp' => 200, 'gold' => 100],
    );
  }

  public function baseHp(): int {
    return 400;
  }
  public function baseAtk(): int {
    return 45;
  }
  public function baseDef(): int {
    return 20;
  }
  public function baseAspd(): float {
    return 4.0;
  }
  public function baseBlockChance(): float {
    return 0.2;
  }
  public function baseBlockReduction(): float {
    return 0.5;
  }
  public function levelScalingFactor(): float {
    return 0.2;
  }
}