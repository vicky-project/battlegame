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
    // Progress user (level, exp, total battle, dll.)
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

    // Mata uang user
    $currency = UserCurrency::forUser($user);

    // Daftar hero yang dimiliki user
    $userHeroes = BattleUserHero::with('telegramUser')
    ->where('telegram_user_id', $user->id)
    ->get()
    ->map(function (BattleUserHero $userHero) use ($progress) {
      // Ambil data dasar hero dari CharacterRegistry
      $heroClass = CharacterRegistry::getHero($userHero->hero_id);
      if (!$heroClass) {
        return null; // Skip jika hero tidak ditemukan (seharusnya tidak terjadi)
      }

      // Statistik setelah memperhitungkan level hero dan upgrade user
      $stats = $this->calculateHeroStats($userHero, $heroClass, $progress);

      return [
        'id' => $userHero->id,
        'hero_id' => $userHero->hero_id,
        'name' => $heroClass->name,
        'type' => $heroClass->type->value,
        'emoji' => $heroClass->emoji,
        'level' => $userHero->level,
        'exp' => $userHero->exp,
        'stats' => $stats,
        'is_selected' => $userHero->is_selected,
      ];
    })
    ->filter() // Hapus null
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

  /**
  * Menghitung statistik hero berdasarkan level hero dan upgrade user.
  */
  protected function calculateHeroStats(
    BattleUserHero $userHero,
    $heroClass,
    BattleUserProgress $progress
  ): array {
    $level = $userHero->level;
    $upgrades = $progress->upgrades ?? [];

    // Bonus dari upgrade
    $attackBonus = ($upgrades['attack_boost'] ?? 0) * 5;
    $defenseBonus = ($upgrades['defense_boost'] ?? 0) * 3;
    $hpBonus = ($upgrades['hp_boost'] ?? 0) * 20;

    // Scaling per level: +10% per level di atas level 1
    $levelMultiplier = 1 + 0.1 * ($level - 1);

    return [
      'hp' => round($heroClass->baseHp() * $levelMultiplier) + $hpBonus,
      'atk' => round($heroClass->baseAtk() * $levelMultiplier) + $attackBonus,
      'def' => round($heroClass->baseDef() * $levelMultiplier) + $defenseBonus,
      'aspd' => $heroClass->baseAspd(),
      'block_chance' => min($heroClass->baseBlockChance() + 0.02 * ($level - 1),
        0.8),
      'block_reduction' => $heroClass->baseBlockReduction(),
    ];
  }
}