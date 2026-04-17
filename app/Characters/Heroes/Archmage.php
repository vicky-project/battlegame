<?php

namespace Modules\BattleGame\Characters\Heroes;

use Modules\BattleGame\Characters\Base\Hero;
use Modules\BattleGame\Enums\HeroType;
use Modules\BattleGame\Enums\SkillType;

class Archmage extends Hero
{
  public function __construct() {
    parent::__construct(
      id: 'archmage',
      name: 'Archmage',
      type: HeroType::MAGE, // Bisa pakai MAGE atau buat enum baru ARCHMAGE
      description: 'Penyihir legendaris dengan kekuatan sihir tak terbatas.',
      emoji: '🧙🔥',
      unlockRequirements: [
        'required_user_level' => 25,
        'unlock_cost_diamond' => 500,
      ],
    );
  }

  public function baseHp(): int {
    return 120;
  }
  public function baseAtk(): int {
    return 80;
  }
  public function baseDef(): int {
    return 10;
  }
  public function baseAspd(): float {
    return 2.8;
  }
  public function baseBlockChance(): float {
    return 0.05;
  }
  public function baseBlockReduction(): float {
    return 0.5;
  }
  public function baseAccuracy(): float {
    return 1.0;
  } // tidak pernah miss
  public function baseEvasion(): float {
    return 0.05;
  }

  public function passiveSkill(): array
  {
    return [
      'type' => SkillType::CRITICAL_DAMAGE,
      'value' => 1.2,
      // +120% critical damage
    ];
  }
}