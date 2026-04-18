<?php

namespace Modules\BattleGame\Enums;

enum BattleResult: string
{
  case WIN = 'win';
  case LOSE = 'lose';
  case DRAW = 'draw';

    public function label(): string
    {
      return match($this) {
        self::WIN => 'Menang',
        self::LOSE => 'Kalah',
        self::DRAW => 'Seri',
      };
    }
}