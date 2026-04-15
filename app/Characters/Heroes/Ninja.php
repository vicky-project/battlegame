<?php
namespace Modules\BattleGame\Characters\Heroes;

use Modules\BattleGame\Characters\Base\Hero;
use Modules\BattleGame\Enums\HeroType;
use Modules\BattleGame\Enums\SkillType;

class Ninja extends Hero
{
  public function __construct() {
    parent::__construct(
      id: 'ninja',
      name: 'Ninja',
      type: HeroType::NINJA,
      description: 'Cepat dan mematikan, namun rapuh.',
      emoji: '🥷',
      unlockRequirements: ['required_user_level' => 3, 'unlock_cost_gold' => 500],
    );
  }

  public function baseHp(): int {
    return 90;
  }
  public function baseAtk(): int {
    return 35;
  }
  public function baseDef(): int {
    return 5;
  }
  public function baseAspd(): float {
    return 1.2;
  }
  public function baseBlockChance(): float {
    return 0.1;
  }
  public function baseBlockReduction(): float {
    return 0.5;
  }

  public function passiveSkill(): array
  {
    return [
      'type' => SkillType::EVASION,
      'value' => 0.15,
      // +15% miss chance musuh
    ];
  }
}