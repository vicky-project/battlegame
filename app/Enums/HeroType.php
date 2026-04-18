<?php

namespace Modules\BattleGame\Enums;

enum HeroType: string
{
  case WARRIOR = 'warrior';
  case NINJA = 'ninja';
  case MAGE = 'mage';
  case BERSERKER = 'berserker';
  case PALADIN = 'paladin';

    public function label(): string
    {
      return match($this) {
        self::WARRIOR => 'Warrior',
        self::NINJA => 'Ninja',
        self::MAGE => 'Mage',
        self::BERSERKER => 'Berserker',
        self::PALADIN => 'Paladin',
      };
    }
}