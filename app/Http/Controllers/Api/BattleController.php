<?php

namespace Modules\BattleGame\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
}