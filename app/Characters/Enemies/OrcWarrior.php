<?php
namespace Modules\BattleGame\Characters\Enemies;

use Modules\BattleGame\Characters\Base\Enemy;
use Modules\BattleGame\Enums\SkillType;

class OrcWarrior extends Enemy
{
  public function __construct() {
    parent::__construct(
      id: 'orc_warrior',
      name: 'Orc Warrior',
      emoji: '👹',
      minLevel: 3,
      maxLevel: 10,
      rewards: ['exp' => 60, 'gold' => 35],
    );
  }

  public function baseHp(): int {
    return 180;
  }
  public function baseAtk(): int {
    return 22;
  }
  public function baseDef(): int {
    return 8;
  }
  public function baseAspd(): float {
    return 2.5;
  }
  public function baseBlockChance(): float {
    return 0.15;
  }
  public function baseBlockReduction(): float {
    return 0.5;
  }
  public function levelScalingFactor(): float {
    return 0.12;
  }

  public function specialAbility(): array
  {
    return [
      'type' => SkillType::ENRAGE,
      'chance' => 1.0,
      // pasti aktif saat HP < 30%
      'value' => 0.5,
      // +50% damage
      'condition' => 'hp_below_30_percent',
    ];
  }
}