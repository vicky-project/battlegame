<?php

namespace Modules\BattleGame\Enums;

enum UpgradeId: string
{
  case ATTACK_BOOST = 'attack_boost';
  case DEFENSE_BOOST = 'defense_boost';
  case HP_BOOST = 'hp_boost';
  case CRITICAL_CHANCE = 'critical_chance';
  case ACCURACY_BOOST = 'accuracy_boost';
  case COUNTER_ATTACK_BOOST = 'counter_attack_boost';

    public function label(): string
    {
      return match($this) {
        self::ATTACK_BOOST => 'Peningkatan Serangan',
        self::DEFENSE_BOOST => 'Peningkatan Pertahanan',
        self::HP_BOOST => 'Peningkatan HP',
        self::CRITICAL_CHANCE => 'Critical Chance',
        self::ACCURACY_BOOST => 'Akurasi',
        self::COUNTER_ATTACK_BOOST => 'Counter Attack',
      };
    }

    public function description(): string
    {
      return match($this) {
        self::ATTACK_BOOST => 'Meningkatkan ATK sebesar 5 per level',
        self::DEFENSE_BOOST => 'Meningkatkan DEF sebesar 3 per level',
        self::HP_BOOST => 'Meningkatkan HP sebesar 20 per level',
        self::CRITICAL_CHANCE => 'Kesempatan critical hit +2% per level',
        self::ACCURACY_BOOST => 'Meningkatkan akurasi sebesar 2% per level',
        self::COUNTER_ATTACK_BOOST => 'Meningkatkan peluang counterattack sebesar 2% per level',
      };
    }

    public function effectPerLevel(): float|int
    {
      return match($this) {
        self::ATTACK_BOOST => 5,
        self::DEFENSE_BOOST => 3,
        self::HP_BOOST => 20,
        self::CRITICAL_CHANCE => 0.02,
        self::ACCURACY_BOOST => 0.02,
        self::COUNTER_ATTACK_BOOST => 0.02,
      };
    }

    public function costType(): CurrencyType
    {
      return match($this) {
        self::ATTACK_BOOST,
        self::DEFENSE_BOOST,
        self::ACCURACY_BOOST,
        self::COUNTER_ATTACK_BOOST => CurrencyType::GOLD,
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
        self::ACCURACY_BOOST => 150,
        self::COUNTER_ATTACK_BOOST => 150,
      };
    }

    public function costScaling(): float
    {
      return match($this) {
        self::HP_BOOST => 1.3,
      default => 1.5,
      };
    }

    public function maxLevel(): int
    {
      return match($this) {
        self::ATTACK_BOOST,
        self::DEFENSE_BOOST,
        self::CRITICAL_CHANCE,
        self::ACCURACY_BOOST,
        self::COUNTER_ATTACK_BOOST => 10,
        self::HP_BOOST => 5,
      };
    }
}