<?php

namespace Modules\BattleGame\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Telegram\Models\TelegramUser;
use Carbon\Carbon;

class UserCurrency extends Model
{
  protected $fillable = ['telegram_user_id',
    'gold',
    'diamond',
    'last_mining_at'];

  protected $casts = [
    'last_mining_at' => 'datetime',
  ];

  public function telegramUser() {
    return $this->belongsTo(TelegramUser::class);
  }

  public static function forUser(TelegramUser $user): self
  {
    return self::firstOrCreate(
      ['telegram_user_id' => $user->id],
      ['gold' => 100, 'diamond' => 5, 'last_mining_at' => now()]
    );
  }

  public function addGold(int $amount): void
  {
    $this->gold += $amount;
    $this->save();
  }

  public function deductGold(int $amount): bool
  {
    if ($this->gold < $amount) {
      return false;
    }
    $this->gold -= $amount;
    $this->save();
    return true;
  }

  // Mining: berapa gold per jam? (default 60 gold per jam)
  public function getMiningRatePerSecond(): float
  {
    return 60 / 3600; // 60 gold per jam
  }

  public function claimMiningReward(): int
  {
    $now = now();
    $last = $this->last_mining_at ?? $now;
    $seconds = $last->diffInSeconds($now);
    if ($seconds <= 0) {
      return 0;
    }
    $earned = (int) floor($seconds * $this->getMiningRatePerSecond());
    if ($earned > 0) {
      $this->gold += $earned;
      $this->last_mining_at = $now;
      $this->save();
    }
    return $earned;
  }

  public function getMiningSecondsRemaining(): int
  {
    // Untuk UI countdown, kita bisa tentukan interval klaim manual (misal bisa klaim tiap 10 detik)
    // Atau pakai progress bar ke max capacity. Saya pilih: hitung waktu sejak last mining hingga max capacity tertentu (misal 100 gold)
    $maxGoldPerClaim = 100;
    $rate = $this->getMiningRatePerSecond();
    $secondsForMax = (int)($maxGoldPerClaim / $rate);
    $last = $this->last_mining_at ?? now();
    $elapsed = $last->diffInSeconds(now());
    $remaining = max(0, $secondsForMax - $elapsed);
    return $remaining;
  }
}