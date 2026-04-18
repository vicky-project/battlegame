<?php
namespace Modules\BattleGame\Characters\Enemies;

use Modules\BattleGame\Characters\Base\Enemy;
use Modules\BattleGame\Enums\SkillType;

class Bandit extends Enemy
{
  public function __construct() {
    parent::__construct(
      id: 'bandit',
      name: 'Bandit',
      emoji: '💰',
      minLevel: 1,
      maxLevel: 2,
      rewards: ['exp' => 45, 'gold' => 45],
    );
  }

  public function baseHp(): int {
    return 90;
  }
  public function baseAtk(): int {
    return 22;
  }
  public function baseDef(): int {
    return 4;
  }
  public function baseAspd(): float {
    return 2.2;
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

  public function specialAbility(): array
  {
    return [
      'type' => SkillType::CRITICAL_DAMAGE,
      'chance' => 0.15,
      'value' => 0.3,
      // +30% critical damage
    ];
  }
}