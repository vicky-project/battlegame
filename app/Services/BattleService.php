<?php

namespace Modules\BattleGame\Services;

use Modules\BattleGame\Characters\Base\Enemy;
use Modules\BattleGame\Characters\CharacterRegistry;
use Modules\BattleGame\Models\BattleHistory;
use Modules\BattleGame\Models\BattleUserHero;
use Modules\BattleGame\Models\BattleUserProgress;
use Modules\BattleGame\Models\UserCurrency;
use Modules\Telegram\Models\TelegramUser;

class BattleService
{
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

    // 8. Simpan riwayat (gunakan enemy_id string, BUKAN model)
    $history = BattleHistory::create([
      'telegram_user_id' => $user->id,
      'battle_user_hero_id' => $userHero->id,
      'enemy_id' => $enemyClass->id, // ⬅️ string ID, bukan foreign key
      'battle_type' => 'vs_computer',
      'result' => $isWin ? 'win' : 'lose',
      'battle_log' => $result['log'],
      'player_hp_remaining' => $result['player_hp_remaining'],
      'enemy_hp_remaining' => $result['enemy_hp_remaining'],
      'duration' => $result['duration'],
    ]);

    // 9. Update progress & reward
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

  protected function pickEnemyForLevelWithHero(int $level, $heroClass): ?Enemy
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

    if (count($available) === 1) {
      return $available[0];
    }

    // Hitung skor keseimbangan (seperti yang sudah dijelaskan sebelumnya)
    $heroAtk = $heroClass->baseAtk();
    $heroDef = $heroClass->baseDef();

    $scoredEnemies = [];
    foreach ($available as $enemy) {
      $enemyStats = $enemy->getStatsForLevel($level);
      $atkDiff = abs($heroDef - $enemyStats['atk']);
      $defDiff = abs($heroAtk - $enemyStats['def']);
      $score = $atkDiff + $defDiff;
      $scoredEnemies[] = ['enemy' => $enemy,
        'score' => $score];
    }

    usort($scoredEnemies, fn($a, $b) => $a['score'] <=> $b['score']);
    $top = array_slice($scoredEnemies, 0, min(3, count($scoredEnemies)));
    $selected = $top[array_rand($top)];

    return $selected['enemy'];
  }

  protected function getHeroExpForNextLevel(int $currentLevel): int
  {
    return 100 * $currentLevel * $currentLevel;
  }
}