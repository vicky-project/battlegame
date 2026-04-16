<?php
namespace Modules\BattleGame\Characters\Enemies;

use Modules\BattleGame\Characters\Base\Enemy;
use Modules\BattleGame\Enums\SkillType;

class DarkArcher extends Enemy
{
  public function __construct() {
    parent::__construct(id: 'dark_archer', name: 'Dark Archer', emoji: '🏹', minLevel: 3, maxLevel: 5, rewards: ['exp' => 40, 'gold' => 35]);
  }
  public function baseHp(): int {
    return 160;
  }
  public function baseAtk(): int {
    return 28;
  }
  public function baseDef(): int {
    return 5;
  }
  public function baseAspd(): float {
    return 1.5;
  }
  public function baseBlockChance(): float {
    return 0.15;
  }
  public function baseBlockReduction(): float {
    return 0.5;
  }
  public function levelScalingFactor(): float {
    return 0.13;
  }
  public function baseAccuracy(): float {
    return 0.98;
  }
  public function specialAbility(): array {
    return ['type' => SkillType::STUN,
      'chance' => 0.15,
      'duration' => 2];
  }
}