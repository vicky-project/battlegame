<?php
namespace Modules\BattleGame\Characters\Enemies;

use Modules\BattleGame\Characters\Base\Enemy;

class DarkKnight extends Enemy
{
  public function __construct() {
    parent::__construct(
      id: 'dark_knight',
      name: 'Dark Knight',
      emoji: '🦇',
      minLevel: 6,
      maxLevel: null,
      rewards: ['exp' => 100, 'gold' => 50],
    );
  }

  public function baseHp(): int {
    return 250;
  }
  public function baseAtk(): int {
    return 30;
  }
  public function baseDef(): int {
    return 15;
  }
  public function baseAspd(): float {
    return 3.0;
  }
  public function baseBlockChance(): float {
    return 0.25;
  }
  public function baseBlockReduction(): float {
    return 0.5;
  }
  public function levelScalingFactor(): float {
    return 0.15;
  }
}