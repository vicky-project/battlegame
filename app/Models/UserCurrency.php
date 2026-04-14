<?php

namespace Modules\BattleGame\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Telegram\Models\TelegramUser;

class UserCurrency extends Model
{
  protected $fillable = ['telegram_user_id',
    'gold',
    'diamond'];

  public function telegramUser() {
    return $this->belongsTo(TelegramUser::class);
  }

  /**
  * Ambil atau buat currency user
  */
  public static function forUser(TelegramUser $user): self
  {
    return self::firstOrCreate(
      ['telegram_user_id' => $user->id],
      ['gold' => 100, 'diamond' => 5] // starter bonus
    );
  }

  /**
  * Tambah gold
  */
  public function addGold(int $amount): void
  {
    $this->gold += $amount;
    $this->save();
  }

  /**
  * Kurangi gold, return false jika tidak cukup
  */
  public function deductGold(int $amount): bool
  {
    if ($this->gold < $amount) {
      return false;
    }
    $this->gold -= $amount;
    $this->save();
    return true;
  }

  // Mirip untuk diamond
}