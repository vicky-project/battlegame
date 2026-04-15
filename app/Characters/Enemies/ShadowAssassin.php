<?php
namespace Modules\BattleGame\Characters\Enemies;

use Modules\BattleGame\Characters\Base\Enemy;
use Modules\BattleGame\Enums\SkillType;

class ShadowAssassin extends Enemy
{
  public function __construct() {
    parent::__construct(
      id: 'shadow_assassin',
      name: 'Shadow Assassin',
      emoji: '🥷🌑',
      minLevel: 6,
      maxLevel: 9,
      rewards: ['exp' => 110, 'gold' => 90],
    );
  }

  public function baseHp(): int {
    return 200;
  }
  public function baseAtk(): int {
    return 55;
  }
  public function baseDef(): int {
    return 8;
  }
  public function baseAspd(): float {
    return 1.3;
  }
  public function baseBlockChance(): float {
    return 0.1;
  }
  public function baseBlockReduction(): float {
    return 0.4;
  }
  public function levelScalingFactor(): float {
    return 0.18;
  }

  public function specialAbility(): array
  {
    return [
      'type' => SkillType::CRITICAL_DAMAGE,
      'chance' => 0.25,
      'value' => 0.8,
      // +80% crit damage
    ];
  }
}