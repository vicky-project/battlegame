<?php

namespace Modules\BattleGame\Enums;

enum StoreCategory: string
{
  case HERO = 'hero';
  case DIAMOND = 'diamond';
  case UPGRADE = 'upgrade';

    public function label(): string
    {
      return match($this) {
        self::HERO => 'Hero',
        self::DIAMOND => 'Diamond',
        self::UPGRADE => 'Upgrade',
      };
    }

    public function icon(): string
    {
      return match($this) {
        self::HERO => 'person-plus',
        self::DIAMOND => 'gem',
        self::UPGRADE => 'arrow-up-circle',
      };
    }

    public function description(): string
    {
      return match($this) {
        self::HERO => 'Buka hero baru',
        self::DIAMOND => 'Tukar gold dengan diamond',
        self::UPGRADE => 'Tingkatkan kemampuan',
      };
    }

    public function color(): string
    {
      return match($this) {
        self::HERO => 'text-warning',
        self::DIAMOND => 'text-info',
        self::UPGRADE => 'text-danger'
      };
    }
}