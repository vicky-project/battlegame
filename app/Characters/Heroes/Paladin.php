<?php
namespace Modules\BattleGame\Characters\Heroes;

use Modules\BattleGame\Characters\Base\Hero;
use Modules\BattleGame\Enums\HeroType;
use Modules\BattleGame\Enums\SkillType;

class Paladin extends Hero
{
  public function __construct() {
    parent::__construct(
      id: 'paladin',
      name: 'Paladin',
      type: HeroType::PALADIN,
      description: 'Pertahanan suci, sulit ditembus.',
      emoji: '🛡️✨',
      unlockRequirements: ['required_user_level' => 10, 'unlock_cost_gold' => 3000],
    );
  }

  public function baseHp(): int {
    return 180;
  }
  public function baseAtk(): int {
    return 22;
  }
  public function baseDef(): int {
    return 25;
  }
  public function baseAspd(): float {
    return 2.2;
  }
  public function baseBlockChance(): float {
    return 0.5;
  }
  public function baseBlockReduction(): float {
    return 0.6;
  }

  public function passiveSkill(): array
  {
    return [
      'type' => SkillType::HOLY_SHIELD,
      'value' => 30,
      // Shield 30 HP setiap 10 detik
    ];
  }
}