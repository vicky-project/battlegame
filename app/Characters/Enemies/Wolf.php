<?php
namespace Modules\BattleGame\Characters\Enemies;

use Modules\BattleGame\Characters\Base\Enemy;
use Modules\BattleGame\Enums\SkillType;

class Wolf extends Enemy
{
  public function __construct() {
    parent::__construct(id: 'wolf', name: 'Serigala Buas', emoji: '🐺', minLevel: 1, maxLevel: 2, rewards: ['exp' => 25, 'gold' => 15]);
  }
  public function baseHp(): int {
    return 120;
  }
  public function baseAtk(): int {
    return 18;
  }
  public function baseDef(): int {
    return 3;
  }
  public function baseAspd(): float {
    return 1.8;
  }
  public function baseBlockChance(): float {
    return 0.05;
  }
  public function baseBlockReduction(): float {
    return 0.5;
  }
  public function levelScalingFactor(): float {
    return 0.12;
  }
  public function baseEvasion(): float {
    return 0.15;
  }
  public function specialAbility(): array {
    return ['type' => SkillType::EVASION,
      'chance' => 1.0,
      'value' => 0.1];
  }
}