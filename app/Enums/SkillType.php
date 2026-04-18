<?php

namespace Modules\BattleGame\Enums;

enum SkillType: string
{
  // Pasif Hero
  case DAMAGE_REDUCTION = 'damage_reduction';
  case EVASION = 'evasion';
  case CRITICAL_DAMAGE = 'critical_damage';
  case BERSERK = 'berserk';
  case HOLY_SHIELD = 'holy_shield';

    // Spesial Enemy
  case POISON = 'poison';
  case ENRAGE = 'enrage';
  case LIFESTEAL = 'lifesteal';
  case FIRE_BREATH = 'fire_breath';
  case STUN = 'stun';

    public function label(): string
    {
      return match($this) {
        self::DAMAGE_REDUCTION => 'Pengurangan Damage',
        self::EVASION => 'Penghindaran',
        self::CRITICAL_DAMAGE => 'Critical Damage',
        self::BERSERK => 'Berserk',
        self::HOLY_SHIELD => 'Perisai Suci',
        self::POISON => 'Racun',
        self::ENRAGE => 'Mengamuk',
        self::LIFESTEAL => 'Mencuri Nyawa',
        self::FIRE_BREATH => 'Napas Api',
        self::STUN => 'Stun',
      };
    }
}