<?php
namespace Modules\BattleGame\Characters\Enemies;

use Modules\BattleGame\Characters\Base\Enemy;
use Modules\BattleGame\Enums\SkillType;

class SkeletonMage extends Enemy
{
  public function __construct() {
    parent::__construct(id: 'skeleton_mage', name: 'Skeleton Mage', emoji: '💀🔮', minLevel: 3, maxLevel: 5, rewards: ['exp' => 45, 'gold' => 30]);
  }
  public function baseHp(): int {
    return 140;
  }
  public function baseAtk(): int {
    return 35;
  }
  public function baseDef(): int {
    return 6;
  }
  public function baseAspd(): float {
    return 2.8;
  }
  public function baseBlockChance(): float {
    return 0.1;
  }
  public function baseBlockReduction(): float {
    return 0.5;
  }
  public function levelScalingFactor(): float {
    return 0.12;
  }
  public function baseAccuracy(): float {
    return 0.90;
  }
  public function specialAbility(): array {
    return ['type' => SkillType::POISON,
      'chance' => 0.2,
      'damage_per_tick' => 4,
      'duration' => 3];
  }
}