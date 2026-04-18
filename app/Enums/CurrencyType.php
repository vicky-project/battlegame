<?php

namespace Modules\BattleGame\Enums;

enum CurrencyType: string
{
  case GOLD = 'gold';
  case DIAMOND = 'diamond';

    public function label(): string
    {
      return match($this) {
        self::GOLD => 'Gold',
        self::DIAMOND => 'Diamond',
      };
    }

    public function icon(): string
    {
      return match($this) {
        self::GOLD => 'coin',
        self::DIAMOND => 'gem',
      };
    }
}