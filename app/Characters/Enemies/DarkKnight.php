<?php
namespace Modules\BattleGame\Characters\Enemies;

use Modules\BattleGame\Characters\Base\Enemy;
use Modules\BattleGame\Enums\SkillType;

class DarkKnight extends Enemy
{
  public function __construct() {
    parent::__construct(
      id: 'dark_knight',
      name: 'Dark Knight',
      emoji: '🦇',
      minLevel: 6,
      maxLevel: null,
      rewards: ['exp' => 100, 'gold' => 70],
    );
  }

  public function baseHp(): int {
    return 350;
  }
  public function baseAtk(): int {
    return 40;
  }
  public function baseDef(): int {
    return 20;
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
    return 0.18;
  }

  public function specialAbility(): array
  {
    return [
      'type' => SkillType::LIFESTEAL,
      'chance' => 1.0,
      'value' => 0.3,
      // 30% lifesteal
    ];
  }
}