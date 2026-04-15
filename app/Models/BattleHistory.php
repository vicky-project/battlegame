<?php

namespace Modules\BattleGame\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Telegram\Models\TelegramUser;
use Modules\BattleGame\Enums\BattleType;
use Modules\BattleGame\Enums\BattleResult;

class BattleHistory extends Model
{
  protected $fillable = [
    'telegram_user_id',
    'battle_user_hero_id',
    'battle_enemy_id',
    'battle_type',
    'result',
    'battle_log',
    'player_hp_remaining',
    'enemy_hp_remaining',
    'exp_gained',
    'duration',
  ];

  protected $casts = [
    'battle_log' => 'array',
    'battle_type' => BattleType::class,
    'result' => BattleResult::class,
    'duration' => 'float',
  ];

  // Relasi ke user Telegram
  public function telegramUser() {
    return $this->belongsTo(TelegramUser::class);
  }

  // Relasi ke hero yang digunakan user
  public function userHero() {
    return $this->belongsTo(BattleUserHero::class, 'battle_user_hero_id');
  }

  // Relasi ke musuh (jika vs computer)
  public function enemy() {
    return $this->belongsTo(BattleEnemy::class, 'battle_enemy_id');
  }

  // Scope untuk user tertentu
  public function scopeForUser($query, $telegramUserId) {
    return $query->where('telegram_user_id', $telegramUserId);
  }

  // Scope untuk tipe pertarungan
  public function scopeVsComputer($query) {
    return $query->where('battle_type', 'vs_computer');
  }

  // Scope menang
  public function scopeWon($query) {
    return $query->where('result', 'win');
  }

  // Scope kalah
  public function scopeLost($query) {
    return $query->where('result', 'lose');
  }

  // Mendapatkan ringkasan log dalam teks
  public function getSummaryAttribute(): string
  {
    $log = $this->battle_log ?? [];
    $lastEntries = array_slice($log, -3);
    return implode(' | ', $lastEntries);
  }

  // Format durasi dalam detik
  public function getFormattedDurationAttribute(): string
  {
    return number_format($this->duration, 1) . ' detik';
  }
}