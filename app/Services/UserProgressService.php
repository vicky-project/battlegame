<?php

namespace Modules\BattleGame\Services;

use Modules\BattleGame\Characters\CharacterRegistry;
use Modules\BattleGame\Models\BattleUserHero;
use Modules\BattleGame\Models\BattleUserProgress;
use Modules\BattleGame\Models\UserCurrency;
use Modules\Telegram\Models\TelegramUser;

class UserProgressService
{
  /**
  * Mendapatkan data lengkap user untuk ditampilkan di frontend.
  */
  public function getUserData(TelegramUser $user): array
  {
    $progress = BattleUserProgress::firstOrCreate(
      ['telegram_user_id' => $user->id],
      [
        'level' => 1,
        'exp' => 0,
        'total_battles' => 0,
        'total_wins' => 0,
        'total_losses' => 0,
      ]
    );

    $currency = UserCurrency::forUser($user);

    $userHeroes = BattleUserHero::with('telegramUser')
    ->where('telegram_user_id', $user->id)
    ->get()
    ->map(function (BattleUserHero $userHero) use ($progress) {
      $stats = $userHero->calculated_stats;
      if (empty($stats)) {
        return null;
      }

      $heroClass = CharacterRegistry::getHero($userHero->hero_id);

      return [
        'id' => $userHero->id,
        'hero_id' => $userHero->hero_id,
        'name' => $stats['name'],
        'type' => $heroClass?->type->value ?? '',
        'description' => $heroClass->description,
        'emoji' => $stats['emoji'],
        'level' => $userHero->level,
        'exp' => $userHero->exp,
        'next_level_exp' => $userHero->next_level_exp,
        'stats' => $stats,
        'is_selected' => $userHero->is_selected,
        'user' => $userHero->telegramUser
      ];
    })
    ->filter()
    ->values()
    ->toArray();

    return [
      'user_level' => $progress->level,
      'user_exp' => $progress->exp,
      'exp_to_next_level' => $progress->getExpForNextLevel(),
      'total_battles' => $progress->total_battles,
      'total_wins' => $progress->total_wins,
      'total_losses' => $progress->total_losses,
      'gold' => $currency->gold,
      'diamond' => $currency->diamond,
      'heroes' => $userHeroes,
      'selected_hero_id' => collect($userHeroes)->firstWhere('is_selected',
        true)['id'] ?? null,
    ];
  }
}