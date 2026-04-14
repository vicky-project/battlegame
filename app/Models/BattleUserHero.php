<?php

namespace Modules\BattleGame\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Telegram\Models\TelegramUser;

class BattleUserHero extends Model
{
  protected $fillable = [
    'telegram_user_id',
    'battle_hero_id',
    'level',
    'exp',
    'is_selected'
  ];

  public function telegramUser() {
    return $this->belongsTo(TelegramUser::class);
  }

  public function hero() {
    return $this->belongsTo(BattleHero::class, 'battle_hero_id');
  }

  public function getCalculatedStatsAttribute(): array
  {
    $base = $this->hero->stats;
    $level = $this->level;
    // Formula scaling sederhana: +10% per level

    $progress = BattleUserProgress::where('telegram_user_id', $this->telegram_user_id)->first();
    $upgrades = $progress ? $progress->upgrades : [];

    $attackBonus = ($upgrades['attack_boost'] ?? 0) * 5;
    $defenseBonus = ($upgrades['defense_boost'] ?? 0) * 3;
    $hpBonus = ($upgrades['hp_boost'] ?? 0) * 20;
    $critChanceBonus = ($upgrades['critical_chance'] ?? 0) * 0.02;

    return [
      'hp' => round($base['hp'] * (1 + 0.1 * ($level - 1))) + $hpBonus,
      'atk' => round($base['atk'] * (1 + 0.1 * ($level - 1))) + $attackBonus,
      'def' => round($base['def'] * (1 + 0.1 * ($level - 1))) + $defenseBonus,
      'aspd' => $base['aspd'],
      // tidak scaling
      'block_chance' => min($base['block_chance'] + 0.02 * ($level - 1), 0.8),
      'block_reduction' => $base['block_reduction'],
      'critical_chance' => $critChanceBonus,
      // tambahkan di BattleSimulator jika mau
    ];
  }
}