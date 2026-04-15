<?php

namespace Modules\BattleGame\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Telegram\Models\TelegramUser;
use Carbon\Carbon;

class UserCurrency extends Model
{
  protected $fillable = [
    'telegram_user_id',
    'gold',
    'diamond',
    'last_mining_at'
  ];

  protected $casts = [
    'last_mining_at' => 'datetime',
  ];

  // Interval mining dalam detik (1 jam)
  public const MINING_INTERVAL_SECONDS = 3600;

  // Gold yang dihasilkan per interval
  public const GOLD_PER_INTERVAL = 60;

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

  /**
  * Klaim gold mining jika interval sudah terlewati.
  * Mengembalikan jumlah gold yang didapat (0 jika belum waktunya).
  */
  public function claimMiningReward(): int
  {
    $now = now();
    $last = $this->last_mining_at ?? $now;
    $elapsed = $last->diffInSeconds($now);
    $intervalSeconds = self::getMiningInterval();
    $goldPerInterval = self::getGoldPerInterval();

    if ($elapsed < $intervalSeconds) {
      return 0; // belum waktunya klaim
    }

    // Hitung berapa kali interval penuh terlewati (maksimal 1x untuk mencegah akumulasi berlebihan)
    $intervals = floor($elapsed / $intervalSeconds);
    $intervals = min($intervals, 1); // hanya ambil 1 interval meskipun lama tidak klaim (opsional)
    $earned = (int)($intervals * $goldPerInterval);

    if ($earned > 0) {
      $this->gold += $earned;
      // Set last_mining_at ke waktu terakhir yang sesuai dengan interval
      $this->last_mining_at = $last->addSeconds($intervals * $intervalSeconds);
      $this->save();
    }

    return $earned;
  }

  /**
  * Mendapatkan sisa waktu (detik) hingga klaim berikutnya bisa dilakukan.
  */
  public function getMiningSecondsRemaining(): int
  {
    $now = now();
    $last = $this->last_mining_at ?? $now;
    $elapsed = $last->diffInSeconds($now);
    $remaining = max(0, $this->getMiningInterval() - $elapsed);
    return (int) $remaining;
  }

  /**
  * Cek apakah mining reward bisa diklaim sekarang.
  */
  public function canClaimMining(): bool
  {
    return $this->getMiningSecondsRemaining() === 0;
  }

  public static function getMiningInterval(): int
  {
    return config('battlegame.mining.interval_seconds', self::MINING_INTERVAL_SECONDS);
  }

  public static function getGoldPerInterval(): int
  {
    return config('battlegame.mining.gold_per_interval', self::GOLD_PER_INTERVAL);
  }
}