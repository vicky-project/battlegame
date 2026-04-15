<?php
namespace Modules\BattleGame\Characters\Enemies;

use Modules\BattleGame\Characters\Base\Enemy;
use Modules\BattleGame\Enums\SkillType;

class AncientDragon extends Enemy
{
  public function __construct() {
    parent::__construct(
      id: 'ancient_dragon',
      name: 'Ancient Dragon',
      emoji: '🐉',
      minLevel: 10,
      maxLevel: null,
      rewards: ['exp' => 180, 'gold' => 140],
    );
  }

  public function baseHp(): int {
    return 600;
  }
  public function baseAtk(): int {
    return 60;
  }
  public function baseDef(): int {
    return 30;
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
    return 0.25;
  }

  public function specialAbility(): array
  {
    return [
      'type' => SkillType::FIRE_BREATH,
      'chance' => 0.3,
      'value' => 2.5,
      // 250% damage
    ];
  }
}