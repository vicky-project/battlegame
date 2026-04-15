<?php

namespace Modules\BattleGame\Enums;

enum BattleType: string
{
  case VS_COMPUTER = 'vs_computer';
  case VS_PLAYER = 'vs_player';

    public function label(): string
    {
      return match($this) {
        self::VS_COMPUTER => 'vs Computer',
        self::VS_PLAYER => 'vs Player',
      };
    }
}