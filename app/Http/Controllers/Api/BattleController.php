<?php

namespace Modules\BattleGame\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\BattleGame\Models\BattleEnemy;
use Modules\BattleGame\Models\BattleHistory;
use Modules\BattleGame\Models\BattleUserHero;
use Modules\BattleGame\Models\BattleUserProgress;
use Modules\BattleGame\Services\BattleSimulator;
use Modules\Telegram\Models\TelegramUser;

class BattleController extends Controller
{
  public function getUserData(Request $request) {
    /** @var TelegramUser $user */
    $user = $request->user();
    $progress = BattleUserProgress::firstOrCreate(
      ['telegram_user_id' => $user->id],
      ['level' => 1, 'exp' => 0]
    );

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

    return response()->json([
      'success' => true,
      'data' => [
        'user_level' => $progress->level,
        'user_exp' => $progress->exp,
        'exp_to_next_level' => $progress->getExpForNextLevel(),
        'heroes' => $heroes,
        'selected_hero_id' => $heroes->firstWhere('is_selected', true)['id'] ?? null,
      ]
    ]);
  }

  public function startBattleVsComputer(Request $request) {
    $user = $request->user();
    $heroId = $request->input('user_hero_id');

    $userHero = BattleUserHero::where('telegram_user_id', $user->id)
    ->where('id', $heroId)
    ->with('hero')
    ->firstOrFail();

    $progress = BattleUserProgress::firstOrCreate(['telegram_user_id' => $user->id]);

    // Pilih musuh berdasarkan level user
    $enemy = BattleEnemy::where('min_level', '<=', $progress->level)
    ->where(function ($q) use ($progress) {
      $q->whereNull('max_level')->orWhere('max_level', '>=', $progress->level);
    })
    ->where('is_active', true)
    ->inRandomOrder()
    ->first();

    if (!$enemy) {
      // fallback
      $enemy = BattleEnemy::where('is_active', true)->first();
    }

    $playerStats = $userHero->calculated_stats;
    $enemyStats = $enemy->getStatsForLevel($progress->level);

    $simulator = new BattleSimulator(
      array_merge(['name' => $userHero->hero->name], $playerStats),
      array_merge(['name' => $enemy->name], $enemyStats)
    );
    $result = $simulator->runSimulation();

    // Simpan history
    $history = BattleHistory::create([
      'telegram_user_id' => $user->id,
      'battle_user_hero_id' => $userHero->id,
      'battle_enemy_id' => $enemy->id,
      'battle_type' => 'vs_computer',
      'result' => $result['winner'] === $userHero->hero->name ? 'win' : 'lose',
      'battle_log' => $result['log'],
      'player_hp_remaining' => $result['player_hp_remaining'],
      'enemy_hp_remaining' => $result['enemy_hp_remaining'],
      'duration' => $result['duration'],
    ]);

    // Update progress
    $progress->total_battles++;
    if ($history->result === 'win') {
      $progress->total_wins++;
      $expGained = $enemy->rewards['exp'] ?? 50;
      $progress->addExp($expGained);
      $history->exp_gained = $expGained;

      // Hero dapat exp juga
      $userHero->exp += $expGained;
      // level up hero jika perlu
      while ($userHero->exp >= $this->getHeroExpForNextLevel($userHero->level)) {
        $userHero->exp -= $this->getHeroExpForNextLevel($userHero->level);
        $userHero->level++;
      }
      $userHero->save();
    } else {
      $progress->total_losses++;
      $expGained = 10; // consolation
      $progress->addExp($expGained);
      $history->exp_gained = $expGained;
    }
    $progress->save();
    $history->save();

    return response()->json([
      'success' => true,
      'data' => [
        'result' => $result,
        'history_id' => $history->id,
        'exp_gained' => $history->exp_gained,
        'user_new_level' => $progress->level,
        'user_new_exp' => $progress->exp,
      ]
    ]);
  }

  protected function getHeroExpForNextLevel($currentLevel) {
    return 100 * $currentLevel;
  }

  // Endpoint untuk mendapatkan history detail (opsional)
  public function getBattleHistory(Request $request, $id) {
    $history = BattleHistory::where('telegram_user_id', $request->user()->id)
    ->findOrFail($id);
    return response()->json(['success' => true, 'data' => $history]);
  }

  public function setSelectedHero(Request $request) {
    $user = $request->user();
    $heroId = $request->input('user_hero_id');

    BattleUserHero::where('telegram_user_id', $user->id)
    ->update(['is_selected' => false]);

    BattleUserHero::where('telegram_user_id', $user->id)
    ->where('id', $heroId)
    ->update(['is_selected' => true]);

    return response()->json(['success' => true]);
  }
}