<?php
namespace Modules\BattleGame\Characters\Enemies;

use Modules\BattleGame\Characters\Base\Enemy;
use Modules\BattleGame\Enums\SkillType;

class Necromancer extends Enemy
{
  public function __construct() {
    parent::__construct(
      id: 'necromancer',
      name: 'Necromancer',
      emoji: '🧙‍♂️💀',
      minLevel: 6,
      maxLevel: 9,
      rewards: ['exp' => 100, 'gold' => 70],
    );
  }

  public function baseHp(): int {
    return 280;
  }
  public function baseAtk(): int {
    return 45;
  }
  public function baseDef(): int {
    return 12;
  }
  public function baseAspd(): float {
    return 3.2;
  }
  public function baseBlockChance(): float {
    return 0.2;
  }
  public function baseBlockReduction(): float {
    return 0.5;
  }
  public function levelScalingFactor(): float {
    return 0.16;
  }

  public function specialAbility(): array
  {
    return [
      'type' => SkillType::LIFESTEAL,
      'chance' => 1.0,
      'value' => 0.15,
    ];
  }
}