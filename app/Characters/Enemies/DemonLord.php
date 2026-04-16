<?php
namespace Modules\BattleGame\Characters\Enemies;

use Modules\BattleGame\Characters\Base\Enemy;
use Modules\BattleGame\Enums\SkillType;

class DemonLord extends Enemy
{
  public function __construct() {
    parent::__construct(id: 'demon_lord', name: 'Demon Lord', emoji: '👿🔥', minLevel: 10, maxLevel: null, rewards: ['exp' => 250, 'gold' => 200]);
  }
  public function baseHp(): int {
    return 700;
  }
  public function baseAtk(): int {
    return 80;
  }
  public function baseDef(): int {
    return 30;
  }
  public function baseAspd(): float {
    return 4.0;
  }
  public function baseBlockChance(): float {
    return 0.25;
  }
  public function baseBlockReduction(): float {
    return 0.5;
  }
  public function levelScalingFactor(): float {
    return 0.25;
  }
  public function specialAbility(): array {
    return ['type' => SkillType::ENRAGE,
      'chance' => 1.0,
      'value' => 1.0];
  }
}