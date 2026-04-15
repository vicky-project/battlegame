<?php

namespace Modules\BattleGame\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Telegram\Models\TelegramUser;

class BattleUserProgress extends Model
{
  protected $fillable = [
    'telegram_user_id',
    'level',
    'exp',
    'total_battles',
    'total_wins',
    'total_losses',
    'upgrades',
  ];

  protected $casts = [
    'upgrades' => 'array',
  ];

  public function user() {
    return $this->belongsTo(TelegramUser::class, 'telegram_user_id');
  }

  /**
  * Tambah exp dan naikkan level jika cukup.
  */
  public function addExp(int $exp): void
  {
    $this->exp += $exp;
    $nextLevelExp = $this->getExpForNextLevel();
    while ($this->exp >= $nextLevelExp) {
      $this->exp -= $nextLevelExp;
      $this->level++;
      $nextLevelExp = $this->getExpForNextLevel();
    }
    $this->save();
  }

  /**
  * Exp yang dibutuhkan untuk naik ke level berikutnya.
  * Formula: 100 * level^2 (semakin tinggi level semakin besar)
  */
  public function getExpForNextLevel(): int
  {
    return 100 * $this->level * $this->level;
  }

  public function getUpgradeLevel(string $key): int
  {
    $level = $this->upgrades[$key] ?? 1;
    return max(1, $level);
  }

  public function setUpgradeLevel(string $key, int $level): void
  {
    $upgrades = $this->upgrades ?? [];
    $upgrades[$key] = $level;
    $this->upgrades = $upgrades;
    $this->save();
  }
}