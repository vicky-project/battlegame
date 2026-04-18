<?php

namespace Modules\BattleGame\Services;

use Modules\BattleGame\Characters\Base\Enemy;
use Modules\BattleGame\Characters\CharacterRegistry;
use Modules\BattleGame\Enums\SkillType;
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

    // 4. Tentukan level musuh berdasarkan LEVEL HERO
    $targetLevel = $enemyLevel ?? $userHero->level;

    // 5. Hero class dari registry
    $heroClass = CharacterRegistry::getHero($userHero->hero_id);
    if (!$heroClass) {
      throw new \Exception('Hero tidak valid');
    }

    // 6. Statistik final hero (sudah termasuk level & upgrade)
    $playerStats = $userHero->calculated_stats;

    // 7. Pilih musuh adaptif berdasarkan LEVEL HERO
    $enemyClass = $this->pickEnemyForLevelWithHero($targetLevel, $heroClass);
    if (!$enemyClass) {
      throw new \Exception('Tidak ada musuh yang tersedia');
    }

    // 8. Simulasi pertarungan
    $simulator = new BattleSimulator($playerStats, $enemyClass, $targetLevel);
    $result = $simulator->runSimulation();
    $isWin = $result['winner'] === $playerStats['name'];

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

    // 10. Update progress user & reward
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

      // Hero dapat exp dan level up (tanpa batas)
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

    // Hitung skill improvement
    $skillImprovement = null;
    if ($heroLevelUp) {
      $oldLevel = $userHero->getOriginal('level');
      $newLevel = $heroLevelUp;
      $basePassive = $heroClass->passiveSkill();

      // Fungsi untuk menghitung scaled value
      $calcValue = function($lvl) use ($basePassive) {
        if ($lvl <= 30) {
          $mult = 1 + 0.05 * ($lvl - 1);
        } else {
          $mult = 1 + 0.05 * 29 + 0.01 * ($lvl - 30);
        }
        return $basePassive['value'] * $mult;
      };

      $oldValue = $calcValue($oldLevel);
      $newValue = $calcValue($newLevel);
      $label = $basePassive['type']->label();

      if (in_array($basePassive['type'], [SkillType::EVASION, SkillType::DAMAGE_REDUCTION])) {
        $oldStr = round($oldValue * 100) . '%';
        $newStr = round($newValue * 100) . '%';
      } elseif ($basePassive['type'] === SkillType::CRITICAL_DAMAGE) {
        $oldStr = '+' . round(($oldValue - 1) * 100) . '%';
        $newStr = '+' . round(($newValue - 1) * 100) . '%';
      } elseif ($basePassive['type'] === SkillType::HOLY_SHIELD) {
        $oldStr = round($oldValue) . ' HP';
        $newStr = round($newValue) . ' HP';
      } else {
        $oldStr = round($oldValue, 2);
        $newStr = round($newValue, 2);
      }
      $skillImprovement = "{$label}: {$oldStr} → {$newStr}";
    }

    return [
      'result' => $result,
      'history_id' => $history->id,
      'exp_gained' => $expGained,
      'gold_gained' => $goldGained,
      'user_new_level' => $progress->level,
      'user_new_exp' => $progress->exp,
      'user_level_up' => $userLevelUp,
      'hero_level_up' => $heroLevelUp,
      'player_level' => $userHero->level,
      'enemy_level' => $targetLevel,
      'skill_improvement' => $skillImprovement,
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

  public function getHeroExpForNextLevel(int $currentLevel): int
  {
    if ($currentLevel <= 30) {
      return (int) (150 * pow(1.5, $currentLevel - 1));
    }
    return (int) (150 * pow(1.5, 29) * pow(2.2, $currentLevel - 30));
  }
}