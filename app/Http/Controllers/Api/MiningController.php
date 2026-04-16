<?php

namespace Modules\BattleGame\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\BattleGame\Models\UserCurrency;

class MiningController extends Controller
{
  public function getMiningStatus(Request $request) {
    $user = $request->user();
    $currency = UserCurrency::forUser($user);
    $earned = $currency->claimMiningReward();

    return response()->json([
      'success' => true,
      'data' => [
        'gold' => $currency->gold,
        'earned' => $earned,
        'next_claim_seconds' => $currency->getMiningSecondsRemaining(),
        'can_claim' => $currency->canClaimMining(),
        'gold_per_interval' => $currency->getGoldPerInterval(),
      ]
    ]);
  }

  public function claimMining(Request $request) {
    $user = $request->user();
    $currency = UserCurrency::forUser($user);
    $earned = $currency->claimMiningReward();

    if ($earned === 0) {
      return response()->json([
        'success' => false,
        'message' => 'Belum waktunya klaim',
      ], 400);
    }

    return response()->json([
      'success' => true,
      'data' => [
        'gold' => $currency->gold,
        'earned' => $earned,
        'next_claim_seconds' => $currency->getMiningSecondsRemaining(),
        'can_claim' => false,
      ]
    ]);
  }
}