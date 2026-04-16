<?php

namespace Modules\BattleGame\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\BattleGame\Characters\CharacterRegistry;
use Modules\BattleGame\Models\BattleHistory;
use Modules\BattleGame\Services\BattleService;

class BattleController extends Controller
{
  public function __construct(
    protected BattleService $battleService
  ) {}

  public function startBattleVsComputer(Request $request) {
    try {
      $data = $this->battleService->startVsComputer(
        $request->user(),
        $request->input('user_hero_id'),
        $request->input('enemy_level')
      );
      return response()->json(['success' => true, 'data' => $data]);
    } catch (\Exception $e) {
      \Log::error("Gagal menyerang", [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);
      return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
    }
  }

  public function getBattleHistory(Request $request, $id) {
    $history = BattleHistory::where('telegram_user_id', $request->user()->id)->findOrFail($id);
    return response()->json(['success' => true, 'data' => $history]);
  }

  public function getBattleHistoryList(Request $request) {
    $user = $request->user();
    $perPage = $request->input('per_page', 10);

    $histories = BattleHistory::where('telegram_user_id', $user->id)
    ->with(['userHero'])
    ->orderBy('created_at', 'desc')
    ->paginate($perPage);

    // Transform data untuk frontend
    $data = $histories->through(function ($history) {
      $enemyClass = CharacterRegistry::getEnemy($history->enemy_id);
      $heroClass = $history->userHero
      ? CharacterRegistry::getHero($history->userHero->hero_id)
      : null;

      return [
        'id' => $history->id,
        'hero_name' => $heroClass?->name ?? 'Unknown',
        'hero_emoji' => $heroClass?->emoji ?? '👤',
        'enemy_name' => $enemyClass?->name ?? $history->enemy_id,
        'enemy_emoji' => $enemyClass?->emoji ?? '👾',
        'result' => $history->result->value,
        'exp_gained' => $history->exp_gained,
        'gold_gained' => $history->gold_gained ?? 0, // pastikan kolom ada atau dari battle_log
        'player_hp_remaining' => $history->player_hp_remaining,
        'enemy_hp_remaining' => $history->enemy_hp_remaining,
        'created_at' => $history->created_at->toISOString(),
        'duration' => $history->duration,
      ];
    });

    return response()->json([
      'success' => true,
      'data' => $data->items(),
      'meta' => [
        'current_page' => $histories->currentPage(),
        'last_page' => $histories->lastPage(),
        'per_page' => $histories->perPage(),
        'total' => $histories->total(),
      ]
    ]);
  }
}