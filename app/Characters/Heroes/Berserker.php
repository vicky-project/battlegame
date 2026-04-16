<?php
namespace Modules\BattleGame\Characters\Heroes;

use Modules\BattleGame\Characters\Base\Hero;
use Modules\BattleGame\Enums\HeroType;
use Modules\BattleGame\Enums\SkillType;

class Berserker extends Hero
{
  public function __construct() {
    parent::__construct(
      id: 'berserker',
      name: 'Berserker',
      type: HeroType::BERSERKER,
      description: 'Semakin terluka semakin kuat.',
      emoji: '⚔️',
      unlockRequirements: ['required_user_level' => 8, 'unlock_cost_gold' => 2000],
    );
  }

  public function baseHp(): int {
    return 200;
  }
  public function baseAtk(): int {
    return 28;
  }
  public function baseDef(): int {
    return 8;
  }
  public function baseAspd(): float {
    return 1.8;
  }
  public function baseBlockChance(): float {
    return 0.15;
  }
  public function baseBlockReduction(): float {
    return 0.4;
  }

  public function passiveSkill(): array
  {
    return [
      'type' => SkillType::BERSERK,
      'value' => 0.01,
    ];
  }
}