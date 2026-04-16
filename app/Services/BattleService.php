<?php

namespace Modules\BattleGame\Services;

use Modules\BattleGame\Characters\Base\Enemy;
use Modules\BattleGame\Characters\Base\Hero;
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
    // 1. Biaya battle
    $currency = UserCurrency::forUser($user);
    $battleCost = config('battlegame.battle_cost', 10);
    if (!$currency->deductGold($battleCost)) {
      throw new \Exception('Gold tidak cukup untuk bertarung (butuh ' . $battleCost . ')');
    }

    // 2. Ambil hero user
    $userHero = BattleUserHero::where('telegram_user_id', $user->id)
    ->where('id', $userHeroId)
    ->firstOrFail();

    // 3. Progress user
    $progress = BattleUserProgress::firstOrCreate(
      ['telegram_user_id' => $user->id],
      ['level' => 1, 'exp' => 0, 'total_battles' => 0, 'total_wins' => 0, 'total_losses' => 0]
    );
    $targetLevel = $enemyLevel ?? $progress->level;

    // 4. Hero class dari registry
    $heroClass = CharacterRegistry::getHero($userHero->hero_id);
    if (!$heroClass) {
      throw new \Exception('Hero tidak valid');
    }

    // 5. Statistik final hero (sudah termasuk level & upgrade)
    $playerStats = $userHero->calculated_stats;

    // 6. Pilih musuh adaptif
    $enemyClass = $this->pickEnemyForLevelWithHero($targetLevel, $heroClass);
    if (!$enemyClass) {
      throw new \Exception('Tidak ada musuh yang tersedia');
    }

    // 7. Simulasi pertarungan
    $simulator = new BattleSimulator($playerStats, $enemyClass, $targetLevel);
    $result = $simulator->runSimulation();
    $isWin = $result['winner'] === $playerStats['name'];

    // 8. Simpan musuh ke database (untuk relasi history)
    $enemyDb = BattleEnemy::firstOrCreate(
      ['name' => $enemyClass->name],
      $enemyClass->toArray()
    );

    // 9. Simpan riwayat
    $history = BattleHistory::create([
      'telegram_user_id' => $user->id,
      'battle_user_hero_id' => $userHero->id,
      'enemy_id' => $enemyClass->id,
      'battle_type' => 'vs_computer',
      'result' => $isWin ? 'win' : 'lose',
      'battle_log' => $result['log'],
      'player_hp_remaining' => $result['player_hp_remaining'],
      'enemy_hp_remaining' => $result['enemy_hp_remaining'],
      'duration' => $result['duration'],
    ]);

    // 10. Update progress & reward
    $progress->total_battles++;
    $rewards = $enemyClass->rewards;
    $expGained = 0;
    $goldGained = 0;

    if ($isWin) {
      $progress->total_wins++;
      $expGained = $rewards['exp'];
      $goldGained = $rewards['gold'];
      $progress->addExp($expGained);
      $history->exp_gained = $expGained;
      $history->gold_gained = $goldGained;
      $currency->addGold($goldGained);

      // Hero dapat exp dan level up
      $userHero->exp += $expGained;
      while ($userHero->exp >= $this->getHeroExpForNextLevel($userHero->level)) {
        $userHero->exp -= $this->getHeroExpForNextLevel($userHero->level);
        $userHero->level++;
      }
      $userHero->save();
    } else {
      $progress->total_losses++;
      $expGained = max(5, (int)($rewards['exp'] * 0.2));
      $goldGained = max(1, (int)($rewards['gold'] * 0.2));
      $progress->addExp($expGained);
      $history->exp_gained = $expGained;
      $history->gold_gained = $goldGained;
      $currency->addGold($goldGained);
    }

    $progress->save();
    $history->save();

    // Cek level up user dan hero
    $userLevelUp = $progress->wasChanged('level') ? $progress->level : null;
    $heroLevelUp = $userHero->wasChanged('level') ? $userHero->level : null;

    return [
      'result' => $result,
      'history_id' => $history->id,
      'exp_gained' => $expGained,
      'gold_gained' => $goldGained,
      'user_new_level' => $progress->level,
      'user_new_exp' => $progress->exp,
      'user_level_up' => $userLevelUp,
      'hero_level_up' => $heroLevelUp,
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

  protected function pickEnemyForLevelWithHero(int $level, Hero $hero): ?Enemy
  {
    $allEnemies = CharacterRegistry::getEnemies();
    $available = [];

    foreach ($allEnemies as $enemy) {
      if ($enemy->minLevel <= $level && ($enemy->maxLevel === null || $enemy->maxLevel >= $level)) {
        $available[] = $enemy;
      }
    }

    if (empty($available)) {
      return $allEnemies[array_key_first($allEnemies)] ?? null;
    }

    // Jika hanya satu, langsung kembalikan
    if (count($available) === 1) {
      return $available[0];
    }

    // Hitung stat hero (bisa ditambah upgrade jika perlu, tapi kita ambil base dulu)
    $heroAtk = $hero->baseAtk();
    $heroDef = $hero->baseDef();

    // Hitung skor keseimbangan untuk setiap musuh
    $scoredEnemies = [];
    foreach ($available as $enemy) {
      $enemyStats = $enemy->getStatsForLevel($level); // perlu method getStatsForLevel di Enemy class
      $enemyAtk = $enemyStats['atk'];
      $enemyDef = $enemyStats['def'];

      // Skor: semakin kecil selisih (ATK hero vs DEF musuh) dan (DEF hero vs ATK musuh) semakin baik
      // Kita ingin musuh yang ATK-nya sebanding dengan DEF hero, dan DEF-nya sebanding dengan ATK hero
      $atkDiff = abs($heroDef - $enemyAtk);
      $defDiff = abs($heroAtk - $enemyDef);
      $score = $atkDiff + $defDiff;

      $scoredEnemies[] = [
        'enemy' => $enemy,
        'score' => $score,
      ];
    }

    // Urutkan berdasarkan skor terendah (paling seimbang)
    usort($scoredEnemies, fn($a, $b) => $a['score'] <=> $b['score']);

    // Ambil 3 teratas, lalu pilih acak di antaranya agar tidak selalu sama
    $top = array_slice($scoredEnemies, 0, min(3, count($scoredEnemies)));
    $selected = $top[array_rand($top)];

    return $selected['enemy'];
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