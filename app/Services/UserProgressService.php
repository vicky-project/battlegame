<?php

namespace Modules\BattleGame\Services;

use Modules\BattleGame\Models\BattleUserHero;
use Modules\BattleGame\Models\BattleUserProgress;
use Modules\BattleGame\Models\UserCurrency;
use Modules\Telegram\Models\TelegramUser;

class UserProgressService
{
  public function getUserData(TelegramUser $user): array
  {
    $progress = BattleUserProgress::firstOrCreate(
      ['telegram_user_id' => $user->id],
      ['level' => 1, 'exp' => 0, 'total_battles' => 0, 'total_wins' => 0, 'total_losses' => 0]
    );
    $currency = UserCurrency::forUser($user);

    $heroes = BattleUserHero::with('hero')
    ->where('telegram_user_id', $user->id)
    ->get()
    ->map(function ($userHero) {
      return [
        'id' => $userHero->id,
        'hero_id' => $userHero->hero->id,
        'name' => $userHero->hero->name,
        'level' => $userHero->level,
        'exp' => $userHero->exp,
        'stats' => $userHero->calculated_stats,
        'is_selected' => $userHero->is_selected,
      ];
    });

    return [
      'user_level' => $progress->level,
      'user_exp' => $progress->exp,
      'exp_to_next_level' => $progress->getExpForNextLevel(),
      'total_battles' => $progress->total_battles,
      'total_wins' => $progress->total_wins,
      'total_losses' => $progress->total_losses,
      'gold' => $currency->gold,
      'diamond' => $currency->diamond,
      'heroes' => $heroes,
      'selected_hero_id' => $heroes->firstWhere('is_selected', true)['id'] ?? null,
    ];
  }
}