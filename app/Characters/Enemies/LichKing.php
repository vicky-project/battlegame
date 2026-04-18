<?php
namespace Modules\BattleGame\Characters\Enemies;

use Modules\BattleGame\Characters\Base\Enemy;
use Modules\BattleGame\Enums\SkillType;

class LichKing extends Enemy
{
  public function __construct() {
    parent::__construct(id: 'lich_king', name: 'Lich King', emoji: '👑❄️', minLevel: 10, maxLevel: null, rewards: ['exp' => 450, 'gold' => 400]);
  }
  public function baseHp(): int {
    return 550;
  }
  public function baseAtk(): int {
    return 70;
  }
  public function baseDef(): int {
    return 25;
  }
  public function baseAspd(): float {
    return 3.5;
  }
  public function baseBlockChance(): float {
    return 0.3;
  }
  public function baseBlockReduction(): float {
    return 0.6;
  }
  public function levelScalingFactor(): float {
    return 0.22;
  }
  public function baseAccuracy(): float {
    return 1.0;
  }
  public function specialAbility(): array {
    return ['type' => SkillType::STUN,
      'chance' => 0.2,
      'duration' => 2];
  }
}