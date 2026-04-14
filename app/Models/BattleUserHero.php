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
    return [
      'hp' => round($base['hp'] * (1 + 0.1 * ($level - 1))),
      'atk' => round($base['atk'] * (1 + 0.1 * ($level - 1))),
      'def' => round($base['def'] * (1 + 0.1 * ($level - 1))),
      'aspd' => $base['aspd'],
      // tidak scaling
      'block_chance' => min($base['block_chance'] + 0.02 * ($level - 1), 0.8),
      'block_reduction' => $base['block_reduction'],
    ];
  }
}