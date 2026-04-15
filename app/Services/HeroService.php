<?php

namespace Modules\BattleGame\Services;

use Modules\BattleGame\Characters\CharacterRegistry;
use Modules\BattleGame\Models\BattleUserHero;
use Modules\BattleGame\Models\BattleUserProgress;
use Modules\BattleGame\Models\UserCurrency;
use Modules\Telegram\Models\TelegramUser;

class HeroService
{
  /**
  * Mengatur hero yang dipilih oleh user.
  */
  public function setSelectedHero(TelegramUser $user, int $userHeroId): void
  {
    $userHero = BattleUserHero::where('telegram_user_id', $user->id)
    ->where('id', $userHeroId)
    ->firstOrFail();

    BattleUserHero::where('telegram_user_id', $user->id)
    ->update(['is_selected' => false]);

    $userHero->is_selected = true;
    $userHero->save();
  }

  /**
  * Membuka (membeli) hero baru untuk user.
  */
  public function unlockHero(TelegramUser $user, string $heroId): void
  {
    $hero = CharacterRegistry::getHero($heroId);
    if (!$hero) {
      throw new \Exception('Hero tidak ditemukan');
    }

    $progress = BattleUserProgress::where('telegram_user_id', $user->id)->firstOrFail();

    // Cek apakah user sudah memiliki hero ini
    $exists = BattleUserHero::where('telegram_user_id', $user->id)
    ->where('hero_id', $heroId)
    ->exists();
    if ($exists) {
      throw new \Exception('Hero sudah dimiliki');
    }

    // Cek persyaratan level dan gold
    $req = $hero->unlockRequirements;
    $requiredLevel = $req['required_user_level'] ?? 1;
    $costGold = $req['unlock_cost_gold'] ?? 0;

    if ($progress->level < $requiredLevel) {
      throw new \Exception('Level user belum mencukupi');
    }

    $currency = UserCurrency::forUser($user);
    if (!$currency->deductGold($costGold)) {
      throw new \Exception('Gold tidak cukup');
    }

    // Buat record kepemilikan hero
    BattleUserHero::create([
      'telegram_user_id' => $user->id,
      'hero_id' => $heroId,
      'level' => 1,
      'exp' => 0,
      'is_selected' => false,
    ]);
  }

  /**
  * Mendapatkan daftar semua hero untuk ditampilkan di toko.
  */
  public function getStoreHeroes(TelegramUser $user): array
  {
    $progress = BattleUserProgress::firstOrCreate(['telegram_user_id' => $user->id]);
    $currency = UserCurrency::forUser($user);

    // Ambil semua ID hero yang sudah dimiliki user
    $ownedHeroIds = BattleUserHero::where('telegram_user_id', $user->id)
    ->pluck('hero_id')
    ->toArray();

    $result = [];
    foreach (CharacterRegistry::getHeroes() as $hero) {
      $owned = in_array($hero->id, $ownedHeroIds);
      $req = $hero->unlockRequirements;
      $requiredLevel = $req['required_user_level'] ?? 1;
      $costGold = $req['unlock_cost_gold'] ?? 0;

      $canBuy = !$owned && $progress->level >= $requiredLevel && $currency->gold >= $costGold;

      $result[] = [
        'id' => $hero->id,
        'name' => $hero->name,
        'type' => $hero->type->value,
        'description' => $hero->description,
        'emoji' => $hero->emoji,
        'stats' => [
          'hp' => $hero->baseHp(),
          'atk' => $hero->baseAtk(),
          'def' => $hero->baseDef(),
          'aspd' => $hero->baseAspd(),
          'block_chance' => $hero->baseBlockChance(),
          'block_reduction' => $hero->baseBlockReduction(),
        ],
        'required_level' => $requiredLevel,
        'cost_gold' => $costGold,
        'owned' => $owned,
        'can_buy' => $canBuy,
      ];
    }

    return $result;
  }
}