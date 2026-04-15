<?php

namespace Modules\BattleGame\Services;

use Modules\BattleGame\Characters\Base\Enemy;
use Modules\BattleGame\Characters\CharacterRegistry;
use Modules\BattleGame\Enums\BattleType;
use Modules\BattleGame\Enums\BattleResult;;
use Modules\BattleGame\Models\BattleHistory;
use Modules\BattleGame\Models\BattleUserHero;
use Modules\BattleGame\Models\BattleUserProgress;
use Modules\BattleGame\Models\UserCurrency;
use Modules\Telegram\Models\TelegramUser;

class BattleService
{
  /**
  * Memulai pertarungan melawan komputer.
  *
  * @param TelegramUser $user
  * @param int $userHeroId
  * @param int|null $enemyLevel
  * @return array
  * @throws \Exception
  */
  public function startVsComputer(TelegramUser $user, int $userHeroId, ?int $enemyLevel = null): array
  {
    $currency = UserCurrency::forUser($user);
    $battleCost = 10;
    if (!$currency->deductGold($battleCost)) {
      throw new \Exception("Gold tidak cukup untuk bertarung");
    }
    // 1. Ambil hero milik user
    $userHero = BattleUserHero::where('telegram_user_id', $user->id)
    ->where('id', $userHeroId)
    ->firstOrFail();

    // 2. Ambil atau buat progress user
    $progress = BattleUserProgress::firstOrCreate(
      ['telegram_user_id' => $user->id],
      ['level' => 1, 'exp' => 0, 'total_battles' => 0, 'total_wins' => 0, 'total_losses' => 0]
    );

    // 3. Tentukan level musuh (default: level user)
    $targetLevel = $enemyLevel ?? $progress->level;

    // 4. Ambil class hero dari registry
    $heroClass = CharacterRegistry::getHero($userHero->hero_id ?? 'warrior');
    if (!$heroClass) {
      throw new \Exception('Hero tidak ditemukan dalam registry.');
    }

    // 5. Pilih musuh yang sesuai dengan level
    $enemyClass = $this->pickEnemyForLevel($targetLevel);
    if (!$enemyClass) {
      throw new \Exception('Tidak ada musuh yang tersedia untuk level ini.');
    }

    $playerUpgrades = $progress->upgrades ?? [];
    // 6. Jalankan simulasi pertarungan
    $simulator = new BattleSimulator($heroClass, $enemyClass, $targetLevel, $playerUpgrades);
    $result = $simulator->runSimulation();
    $isWin = $result['winner'] === $heroClass->name;

    // 8. Simpan riwayat pertarungan
    $history = BattleHistory::create([
      'telegram_user_id' => $user->id,
      'battle_user_hero_id' => $userHero->id,
      'enemy_id' => $enemyClass->id,
      'battle_type' => BattleType::VS_COMPUTER,
      'result' => $isWin ? BattleResult::WIN : BattleResult::LOSE,
      'battle_log' => $result['log'],
      'player_hp_remaining' => $result['player_hp_remaining'],
      'enemy_hp_remaining' => $result['enemy_hp_remaining'],
      'duration' => $result['duration'],
    ]);

    // 9. Update statistik progress user
    $progress->total_battles++;

    // 10. Ambil rewards dan currency user
    $rewards = $enemyClass->rewards;
    $currency = UserCurrency::forUser($user);

    $expGained = 0;
    $goldGained = 0;

    if ($isWin) {
      // User menang
      $progress->total_wins++;
      $expGained = $rewards['exp'];
      $goldGained = $rewards['gold'];
      $progress->addExp($expGained);
      $history->exp_gained = $expGained;
      $currency->addGold($goldGained);

      // Hero dapat exp dan naik level jika cukup
      $userHero->exp += $expGained;
      while ($userHero->exp >= $this->getHeroExpForNextLevel($userHero->level)) {
        $userHero->exp -= $this->getHeroExpForNextLevel($userHero->level);
        $userHero->level++;
      }
      $userHero->save();
    } else {
      // User kalah
      $progress->total_losses++;
      $expGained = max(5, (int)($rewards['exp'] * 0.2));
      $goldGained = max(1, (int)($rewards['gold'] * 0.2));
      $progress->addExp($expGained);
      $history->exp_gained = $expGained;
      $currency->addGold($goldGained);
    }

    $progress->save();
    $history->save();

    return [
      'result' => $result,
      'history_id' => $history->id,
      'exp_gained' => $expGained,
      'gold_gained' => $goldGained,
      'user_new_level' => $progress->level,
      'user_new_exp' => $progress->exp,
    ];
  }

  /**
  * Memilih musuh dari registry berdasarkan level user.
  *
  * @param int $level
  * @return Enemy|null
  */
  protected function pickEnemyForLevel(int $level): ?Enemy
  {
    $enemies = CharacterRegistry::getEnemies();
    $available = [];

    foreach ($enemies as $enemy) {
      if ($enemy->minLevel <= $level && ($enemy->maxLevel === null || $enemy->maxLevel >= $level)) {
        $available[] = $enemy;
      }
    }

    if (empty($available)) {
      // Fallback: kembalikan musuh pertama yang ada
      return $enemies[array_key_first($enemies)] ?? null;
    }

    // Pilih secara acak
    return $available[array_rand($available)];
  }

  /**
  * Hitung exp yang dibutuhkan hero untuk naik level.
  *
  * @param int $currentLevel
  * @return int
  */
  protected function getHeroExpForNextLevel(int $currentLevel): int
  {
    // Formula progresif untuk hero: 100 * level^2
    return 100 * $currentLevel * $currentLevel;
  }
}