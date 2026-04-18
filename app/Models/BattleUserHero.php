<?php

namespace Modules\BattleGame\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\BattleGame\Characters\CharacterRegistry;
use Modules\BattleGame\Services\BattleService;
use Modules\Telegram\Models\TelegramUser;

class BattleUserHero extends Model
{
  protected $fillable = [
    'telegram_user_id',
    'hero_id',
    'level',
    'exp',
    'is_selected',
  ];

  public function telegramUser() {
    return $this->belongsTo(TelegramUser::class);
  }

  public function getHeroAttribute() {
    return CharacterRegistry::getHero($this->hero_id);
  }

  public function getNextLevelExpAttribute(): int
  {
    $battleService = app(BattleService::class);
    return $battleService->getHeroExpForNextLevel($this->level);
  }

  public function getCalculatedStatsAttribute(): array
  {
    $hero = $this->hero;
    if (!$hero) {
      return [];
    }

    $level = $this->level;
    $progress = BattleUserProgress::where('telegram_user_id', $this->telegram_user_id)->first();
    $upgrades = $progress ? $progress->upgrades : [];

    // Bonus dari upgrade
    $hpBonus = ($upgrades['hp_boost'] ?? 0) * 20;
    $atkBonus = ($upgrades['attack_boost'] ?? 0) * 5;
    $defBonus = ($upgrades['defense_boost'] ?? 0) * 3;
    $accuracyBonus = ($upgrades['accuracy_boost'] ?? 0) * 0.02;
    $counterBonus = ($upgrades['counter_attack_boost'] ?? 0) * 0.02;
    $critChanceBonus = ($upgrades['critical_chance'] ?? 0) * 0.02;
    $poisonResistance = ($upgrades['poison_resistance'] ?? 0) * 0.05;
    $stunResistance = ($upgrades['stun_resistance'] ?? 0) * 0.10;
    $lifestealBreak = ($upgrades['lifesteal_break'] ?? 0) * 0.06;

    // Scaling level
    if ($level <= 30) {
      $levelMultiplier = 1 + 0.1 * ($level - 1);
    } else {
      $levelMultiplier = 1 + 0.1 * 29 + 0.02 * ($level - 30);
    }

    $finalHp = round($hero->baseHp() * $levelMultiplier) + $hpBonus;

    $basePassive = $hero->passiveSkill();
    $passiveType = $basePassive['type']->value;
    $passiveValue = $basePassive['value'];

    if ($level <= 30) {
      $skillLevelMultiplier = 1 + 0.05 * ($level - 1);
    } else {
      $skillLevelMultiplier = 1 + 0.05 * 29 + 0.01 * ($level - 30);
    }
    $scaledPassiveValue = $passiveValue * $skillLevelMultiplier;

    switch ($passiveType) {
      case 'evasion':
        $scaledPassiveValue = min(0.6, $scaledPassiveValue);
        break;
      case 'damage_reduction':
        $scaledPassiveValue = min(0.6, $scaledPassiveValue);
        break;
    }

    return [
      'name' => $hero->name,
      'emoji' => $hero->emoji,
      'hp' => $finalHp,
      'max_hp' => $finalHp,
      'atk' => round($hero->baseAtk() * $levelMultiplier) + $atkBonus,
      'def' => round($hero->baseDef() * $levelMultiplier) + $defBonus,
      'aspd' => $hero->baseAspd(),
      'block_chance' => min($hero->baseBlockChance() + 0.02 * ($level - 1), 0.8),
      'block_reduction' => $hero->baseBlockReduction(),
      'accuracy' => min(1.0, $hero->baseAccuracy() + $accuracyBonus),
      'evasion' => $hero->baseEvasion(),
      'crit_chance_bonus' => $critChanceBonus,
      'counter_chance_bonus' => $counterBonus,
      'poison_resistance' => min(0.5, $poisonResistance),
      'stun_resistance' => min(0.5, $stunResistance),
      'lifesteal_break' => min(0.3, $lifestealBreak),
      'passive_skill' => [
        'type' => $passiveType,
        'value' => $scaledPassiveValue,
      ],
    ];
  }
}