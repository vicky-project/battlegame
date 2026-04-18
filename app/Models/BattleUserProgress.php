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
  * Tambah exp dan naikkan level jika cukup (tanpa batas maksimal).
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
  * - Level 1-50  : 100 * 1.5^(level-1)
  * - Level 51+   : 100 * 1.5^49 * 2.0^(level-50)
  */
  public function getExpForNextLevel(): int
  {
    if ($this->level <= 50) {
      return (int) (100 * pow(1.5, $this->level - 1));
    }
    return (int) (100 * pow(1.5, 49) * pow(2.0, $this->level - 50));
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