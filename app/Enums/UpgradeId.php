<?php

namespace Modules\BattleGame\Enums;

enum UpgradeId: string
{
  case ATTACK_BOOST = 'attack_boost';
  case DEFENSE_BOOST = 'defense_boost';
  case HP_BOOST = 'hp_boost';
  case CRITICAL_CHANCE = 'critical_chance';

    public function label(): string
    {
      return match($this) {
        self::ATTACK_BOOST => 'Peningkatan Serangan',
        self::DEFENSE_BOOST => 'Peningkatan Pertahanan',
        self::HP_BOOST => 'Peningkatan HP',
        self::CRITICAL_CHANCE => 'Critical Chance',
      };
    }

    public function description(): string
    {
      return match($this) {
        self::ATTACK_BOOST => 'Meningkatkan ATK sebesar 5 per level',
        self::DEFENSE_BOOST => 'Meningkatkan DEF sebesar 3 per level',
        self::HP_BOOST => 'Meningkatkan HP sebesar 20 per level',
        self::CRITICAL_CHANCE => 'Kesempatan critical hit +2% per level',
      };
    }

    public function effectPerLevel(): int
    {
      return match($this) {
        self::ATTACK_BOOST => 5,
        self::DEFENSE_BOOST => 3,
        self::HP_BOOST => 20,
        self::CRITICAL_CHANCE => 2,
      };
    }

    public function costType(): CurrencyType
    {
      return match($this) {
        self::ATTACK_BOOST,
        self::DEFENSE_BOOST => CurrencyType::GOLD,
        self::HP_BOOST,
        self::CRITICAL_CHANCE => CurrencyType::DIAMOND,
      };
    }

    public function baseCost(): int
    {
      return match($this) {
        self::ATTACK_BOOST => 200,
        self::DEFENSE_BOOST => 150,
        self::HP_BOOST => 50,
        self::CRITICAL_CHANCE => 30,
      };
    }

    public function costScaling(): float
    {
      return match($this) {
        self::ATTACK_BOOST,
        self::DEFENSE_BOOST,
        self::CRITICAL_CHANCE => 1.5,
        self::HP_BOOST => 1.3,
      };
    }

    public function maxLevel(): int
    {
      return match($this) {
        self::ATTACK_BOOST,
        self::DEFENSE_BOOST,
        self::CRITICAL_CHANCE => 10,
        self::HP_BOOST => 5,
      };
    }
}