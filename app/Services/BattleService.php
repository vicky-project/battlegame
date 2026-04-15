<?php

namespace Modules\BattleGame\Services;

use Modules\BattleGame\Enums\BattleType;
use Modules\BattleGame\Enums\BattleResult;
use Modules\BattleGame\Models\BattleEnemy;
use Modules\BattleGame\Models\BattleHistory;
use Modules\BattleGame\Models\BattleUserHero;
use Modules\BattleGame\Models\BattleUserProgress;
use Modules\BattleGame\Models\UserCurrency;
use Modules\Telegram\Models\TelegramUser;

class BattleService
{
  public function startVsComputer(TelegramUser $user, int $userHeroId, ?int $enemyLevel = null): array
  {
    $userHero = BattleUserHero::where('telegram_user_id', $user->id)
    ->where('id', $userHeroId)
    ->with('hero')
    ->firstOrFail();

    $progress = BattleUserProgress::firstOrCreate(
      ['telegram_user_id' => $user->id],
      ['level' => 1, 'exp' => 0, 'total_battles' => 0, 'total_wins' => 0, 'total_losses' => 0]
    );

    $targetLevel = $enemyLevel ?? $progress->level;

    $enemy = $this->findEnemyForLevel($targetLevel);
    if (!$enemy) {
      throw new \Exception('Tidak ada musuh');
    }

    $playerStats = $userHero->calculated_stats;
    $enemyStats = $enemy->getStatsForLevel($targetLevel);

    $simulator = new BattleSimulator(
      array_merge(['name' => $userHero->hero->name], $playerStats),
      array_merge(['name' => $enemy->name], $enemyStats)
    );
    $result = $simulator->runSimulation();
    $isWin = $result['winner'] === $userHero->hero->name;

    $history = BattleHistory::create([
      'telegram_user_id' => $user->id,
      'battle_user_hero_id' => $userHero->id,
      'battle_enemy_id' => $enemy->id,
      'battle_type' => BattleType::VS_COMPUTER,
      'result' => $isWin ? BattleResult::WIN : BattleResult::LOSE,
      'battle_log' => $result['log'],
      'player_hp_remaining' => $result['player_hp_remaining'],
      'enemy_hp_remaining' => $result['enemy_hp_remaining'],
      'duration' => $result['duration'],
    ]);

    $progress->total_battles++;
    $rewards = $enemy->rewards ?? ['exp' => 20,
      'gold' => 5];
    $currency = UserCurrency::forUser($user);

    $expGained = 0;
    $goldGained = 0;

    if ($isWin) {
      $progress->total_wins++;
      $expGained = $rewards['exp'];
      $goldGained = $rewards['gold'];
      $progress->addExp($expGained);
      $history->exp_gained = $expGained;
      $currency->addGold($goldGained);

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

  protected function findEnemyForLevel(int $level): ?BattleEnemy
  {
    $enemy = BattleEnemy::where('min_level', '<=', $level)
    ->where(function ($q) use ($level) {
      $q->whereNull('max_level')->orWhere('max_level', '>=', $level);
    })
    ->where('is_active', true)
    ->inRandomOrder()
    ->first();

    return $enemy ?? BattleEnemy::where('is_active', true)->first();
  }

  protected function getHeroExpForNextLevel(int $currentLevel): int
  {
    return 100 * $currentLevel;
  }
}