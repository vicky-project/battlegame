<?php

namespace Modules\BattleGame\Characters\Enemies;

use Modules\BattleGame\Characters\Base\Enemy;
use Modules\BattleGame\Enums\SkillType;

class VoidLord extends Enemy
{
  public function __construct() {
    parent::__construct(
      id: 'void_lord',
      name: 'Void Lord',
      emoji: '🌑👑',
      minLevel: 30,
      maxLevel: null,
      rewards: ['exp' => 700, 'gold' => 600],
    );
  }

  public function baseHp(): int {
    return 1200;
  }
  public function baseAtk(): int {
    return 120;
  }
  public function baseDef(): int {
    return 50;
  }
  public function baseAspd(): float {
    return 3.5;
  }
  public function baseBlockChance(): float {
    return 0.4;
  }
  public function baseBlockReduction(): float {
    return 0.6;
  }
  public function baseAccuracy(): float {
    return 0.98;
  }
  public function baseEvasion(): float {
    return 0.10;
  }
  public function levelScalingFactor(): float {
    return 0.3;
  }

  public function specialAbility(): array
  {
    // Void Lord memiliki kombinasi skill mematikan
    return [
      'type' => SkillType::LIFESTEAL,
      'chance' => 1.0,
      'value' => 0.5,
      // 50% lifesteal
    ];
  }
}