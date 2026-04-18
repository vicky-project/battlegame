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
  case POISON_RESISTANCE = 'poison_resistance';
  case STUN_RESISTANCE = 'stun_resistance';
  case LIFESTEAL_BREAK = 'lifesteal_break';

    public function label(): string
    {
      return match($this) {
        self::ATTACK_BOOST => 'Peningkatan Serangan',
        self::DEFENSE_BOOST => 'Peningkatan Pertahanan',
        self::HP_BOOST => 'Peningkatan HP',
        self::CRITICAL_CHANCE => 'Critical Chance',
        self::ACCURACY_BOOST => 'Akurasi',
        self::COUNTER_ATTACK_BOOST => 'Counter Attack',
        self::POISON_RESISTANCE => 'Ketahanan Racun',
        self::STUN_RESISTANCE => 'Ketahanan Stun',
        self::LIFESTEAL_BREAK => 'Pemutus Lifesteal',
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
        self::POISON_RESISTANCE => 'Mengurangi damage & durasi racun sebesar 5% per level (maks 50%)',
        self::STUN_RESISTANCE => 'Mengurangi durasi stun sebesar 10% per level (maks 50%)',
        self::LIFESTEAL_BREAK => 'Mengurangi lifesteal musuh sebesar 6% per level (maks 30%)',
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
        self::POISON_RESISTANCE => 0.05,
        self::STUN_RESISTANCE => 0.10,
        self::LIFESTEAL_BREAK => 0.06,
      };
    }

    public function costType(): CurrencyType
    {
      return match($this) {
        self::ATTACK_BOOST,
        self::DEFENSE_BOOST,
        self::ACCURACY_BOOST,
        self::COUNTER_ATTACK_BOOST,
        self::POISON_RESISTANCE => CurrencyType::GOLD,
        self::HP_BOOST,
        self::CRITICAL_CHANCE,
        self::STUN_RESISTANCE,
        self::LIFESTEAL_BREAK => CurrencyType::DIAMOND,
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
        self::POISON_RESISTANCE => 120,
        self::STUN_RESISTANCE => 40,
        self::LIFESTEAL_BREAK => 50,
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
        self::POISON_RESISTANCE => 10,
        self::STUN_RESISTANCE => 5,
        self::LIFESTEAL_BREAK => 5,
      };
    }
}