<?php

namespace Modules\BattleGame\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\BattleGame\Models\BattleEnemy;
use Modules\BattleGame\Models\BattleHistory;
use Modules\BattleGame\Models\BattleUserHero;
use Modules\BattleGame\Models\BattleUserProgress;
use Modules\BattleGame\Models\UserCurrency;
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

    return response()->json([
      'success' => true,
      'data' => [
        'user_level' => $progress->level,
        'user_exp' => $progress->exp,
        'exp_to_next_level' => $progress->getExpForNextLevel(),
        'gold' => $currency->gold,
        'diamond' => $currency->diamond,
        'heroes' => $heroes,
        'selected_hero_id' => $heroes->firstWhere('is_selected', true)['id'] ?? null,
      ]
    ]);
  }

  public function startBattleVsComputer(Request $request) {
    $user = $request->user();
    $heroId = $request->input('user_hero_id');
    $enemyLevel = $request->input('enemy_level'); // opsional

    $userHero = BattleUserHero::where('telegram_user_id', $user->id)
    ->where('id', $heroId)
    ->with('hero')
    ->firstOrFail();

    $progress = BattleUserProgress::firstOrCreate(['telegram_user_id' => $user->id]);
    $targetLevel = $enemyLevel ?? $progress->level;

    // Cari musuh yang sesuai level
    $enemy = BattleEnemy::where('min_level', '<=', $targetLevel)
    ->where(function($q) use ($targetLevel) {
      $q->whereNull('max_level')->orWhere('max_level', '>=', $targetLevel);
    })
    ->where('is_active', true)
    ->inRandomOrder()
    ->first();

    if (!$enemy) {
      $enemy = BattleEnemy::where('is_active', true)->first();
    }
    if (!$enemy) {
      return response()->json(['success' => false, 'message' => 'Tidak ada musuh'], 500);
    }

    $playerStats = $userHero->calculated_stats;
    $enemyStats = $enemy->getStatsForLevel($targetLevel);

    $simulator = new BattleSimulator(
      array_merge(['name' => $userHero->hero->name], $playerStats),
      array_merge(['name' => $enemy->name], $enemyStats)
    );
    $result = $simulator->runSimulation();

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

    // Update progress dan rewards
    $progress->total_battles++;
    $rewards = $enemy->rewards ?? ['exp' => 20,
      'gold' => 5];
    $currency = UserCurrency::forUser($user);

    if ($history->result === 'win') {
      $progress->total_wins++;
      $expGained = $rewards['exp'];
      $goldGained = $rewards['gold'];
      $progress->addExp($expGained);
      $history->exp_gained = $expGained;
      $currency->addGold($goldGained);

      // Hero level up
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

    return response()->json([
      'success' => true,
      'data' => [
        'result' => $result,
        'history_id' => $history->id,
        'exp_gained' => $history->exp_gained,
        'gold_gained' => $goldGained ?? 0,
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

  public function unlockHero(Request $request) {
    $user = $request->user();
    $heroId = $request->input('hero_id');

    $hero = BattleHero::findOrFail($heroId);
    $progress = BattleUserProgress::where('telegram_user_id', $user->id)->first();
    if (!$progress) {
      return response()->json(['success' => false, 'message' => 'Progress tidak ditemukan'], 400);
    }

    // Cek apakah sudah punya
    $alreadyOwned = BattleUserHero::where('telegram_user_id', $user->id)
    ->where('battle_hero_id', $heroId)->exists();
    if ($alreadyOwned) {
      return response()->json(['success' => false, 'message' => 'Hero sudah dimiliki'], 400);
    }

    // Cek syarat level
    if ($progress->level < $hero->required_user_level) {
      return response()->json(['success' => false, 'message' => 'Level user belum mencukupi'], 400);
    }

    // Cek gold
    $currency = UserCurrency::forUser($user);
    if (!$currency->deductGold($hero->unlock_cost_gold)) {
      return response()->json(['success' => false, 'message' => 'Gold tidak cukup'], 400);
    }

    // Buat user hero
    BattleUserHero::create([
      'telegram_user_id' => $user->id,
      'battle_hero_id' => $hero->id,
      'level' => 1,
      'exp' => 0,
      'is_selected' => false,
    ]);

    return response()->json(['success' => true, 'message' => 'Hero berhasil di-unlock!']);
  }
}