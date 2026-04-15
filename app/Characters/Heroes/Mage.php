<?php
namespace Modules\BattleGame\Characters\Heroes;

use Modules\BattleGame\Characters\Base\Hero;
use Modules\BattleGame\Enums\HeroType;

class Mage extends Hero
{
  public function __construct() {
    parent::__construct(
      id: 'mage',
      name: 'Mage',
      type: HeroType::MAGE,
      description: 'Serangan sihir dahsyat namun lambat.',
      emoji: '🔮',
      unlockRequirements: ['required_user_level' => 5, 'unlock_cost_gold' => 1000],
    );
  }

  public function baseHp(): int {
    return 80;
  }
  public function baseAtk(): int {
    return 45;
  }
  public function baseDef(): int {
    return 3;
  }
  public function baseAspd(): float {
    return 3.0;
  }
  public function baseBlockChance(): float {
    return 0.05;
  }
  public function baseBlockReduction(): float {
    return 0.5;
  }
}