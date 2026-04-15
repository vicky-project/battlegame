<?php

namespace Modules\BattleGame\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Telegram\Models\TelegramUser;
use Modules\BattleGame\Characters\CharacterRegistry;

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

  // Relasi ke class hero (bukan model)
  public function getHeroAttribute() {
    return CharacterRegistry::getHero($this->hero_id);
  }

  // Hitung stat dengan memperhitungkan level dan upgrade user (sama seperti sebelumnya)
  public function getCalculatedStatsAttribute(): array
  {
    $hero = $this->hero;
    if (!$hero) {
      return [];
    }

    $progress = BattleUserProgress::where('telegram_user_id', $this->telegram_user_id)->first();
    $upgrades = $progress ? $progress->upgrades : [];

    $attackBonus = ($upgrades['attack_boost'] ?? 0) * 5;
    $defenseBonus = ($upgrades['defense_boost'] ?? 0) * 3;
    $hpBonus = ($upgrades['hp_boost'] ?? 0) * 20;

    $level = $this->level;

    return [
      'hp' => round($hero->baseHp() * (1 + 0.1 * ($level - 1))) + $hpBonus,
      'atk' => round($hero->baseAtk() * (1 + 0.1 * ($level - 1))) + $attackBonus,
      'def' => round($hero->baseDef() * (1 + 0.1 * ($level - 1))) + $defenseBonus,
      'aspd' => $hero->baseAspd(),
      'block_chance' => min($hero->baseBlockChance() + 0.02 * ($level - 1), 0.8),
      'block_reduction' => $hero->baseBlockReduction(),
    ];
  }
}