<?php

namespace Modules\BattleGame\Services;

use Modules\BattleGame\Models\BattleHero;
use Modules\BattleGame\Models\BattleUserHero;
use Modules\BattleGame\Models\BattleUserProgress;
use Modules\BattleGame\Models\UserCurrency;
use Modules\Telegram\Models\TelegramUser;

class HeroService
{
  public function setSelectedHero(TelegramUser $user, int $userHeroId): void
  {
    $hero = BattleUserHero::where('telegram_user_id', $user->id)
    ->where('id', $userHeroId)
    ->firstOrFail();

    BattleUserHero::where('telegram_user_id', $user->id)
    ->update(['is_selected' => false]);

    $hero->is_selected = true;
    $hero->save();
  }

  public function unlockHero(TelegramUser $user, int $heroId): void
  {
    $hero = BattleHero::findOrFail($heroId);
    $progress = BattleUserProgress::where('telegram_user_id', $user->id)->firstOrFail();

    if (BattleUserHero::where('telegram_user_id', $user->id)->where('battle_hero_id', $heroId)->exists()) {
      throw new \Exception('Hero sudah dimiliki');
    }

    $requirements = $hero->unlock_requirements ?? [];
    $requiredLevel = $requirements['required_user_level'] ?? 1;
    $costGold = $requirements['unlock_cost_gold'] ?? 0;

    if ($progress->level < $requiredLevel) {
      throw new \Exception('Level user belum mencukupi');
    }

    $currency = UserCurrency::forUser($user);
    if (!$currency->deductGold($costGold)) {
      throw new \Exception('Gold tidak cukup');
    }

    BattleUserHero::create([
      'telegram_user_id' => $user->id,
      'battle_hero_id' => $hero->id,
      'level' => 1,
      'exp' => 0,
      'is_selected' => false,
    ]);
  }

  public function getStoreHeroes(TelegramUser $user): array
  {
    $progress = BattleUserProgress::firstOrCreate(['telegram_user_id' => $user->id]);
    $currency = UserCurrency::forUser($user);

    $allHeroes = BattleHero::where('is_active', true)->get();
    $ownedHeroIds = BattleUserHero::where('telegram_user_id', $user->id)
    ->pluck('battle_hero_id')->toArray();

    return $allHeroes->map(function ($hero) use ($progress, $currency, $ownedHeroIds) {
      $owned = in_array($hero->id, $ownedHeroIds);
      $requirements = $hero->unlock_requirements ?? [];
      $requiredLevel = $requirements['required_user_level'] ?? 1;
      $costGold = $requirements['unlock_cost_gold'] ?? 0;

      $canBuy = !$owned && $progress->level >= $requiredLevel && $currency->gold >= $costGold;

      return [
        'id' => $hero->id,
        'name' => $hero->name,
        'type' => $hero->type,
        'description' => $hero->description,
        'stats' => $hero->stats,
        'required_level' => $requiredLevel,
        'cost_gold' => $costGold,
        'owned' => $owned,
        'can_buy' => $canBuy,
      ];
    })->all();
  }
}