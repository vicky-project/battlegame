<?php

namespace Modules\BattleGame\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\BattleGame\Models\BattleHistory;
use Modules\BattleGame\Models\UserCurrency;
use Modules\BattleGame\Services\BattleService;
use Modules\BattleGame\Services\HeroService;
use Modules\BattleGame\Services\StoreService;
use Modules\BattleGame\Services\UserProgressService;
use Modules\Telegram\Models\TelegramUser;

class BattleController extends Controller
{
  public function __construct(
    protected UserProgressService $userService,
    protected BattleService $battleService,
    protected HeroService $heroService,
    protected StoreService $storeService
  ) {}

  public function getUserData(Request $request) {
    return response()->json([
      'success' => true,
      'data' => $this->userService->getUserData($request->user())
    ]);
  }

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

  public function setSelectedHero(Request $request) {
    try {
      $this->heroService->setSelectedHero($request->user(), $request->input('user_hero_id'));
      return response()->json(['success' => true, 'message' => 'Hero dipilih']);
    } catch (\Exception $e) {
      return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
    }
  }

  public function unlockHero(Request $request) {
    try {
      $this->heroService->unlockHero($request->user(), $request->input('hero_id'));
      return response()->json(['success' => true, 'message' => 'Hero berhasil di-unlock!']);
    } catch (\Exception $e) {
      return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
    }
  }

  public function getStoreData(Request $request) {
    return response()->json([
      'success' => true,
      'data' => $this->storeService->getStoreData($request->user())
    ]);
  }

  public function getStoreHeroes(Request $request) {
    return response()->json([
      'success' => true,
      'data' => $this->heroService->getStoreHeroes($request->user())
    ]);
  }

  public function buyHero(Request $request) {
    try {
      $this->heroService->unlockHero($request->user(), $request->input('hero_id'));
      return response()->json(['success' => true, 'message' => 'Hero berhasil dibeli!']);
    } catch (\Exception $e) {
      return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
    }
  }

  public function getDiamondPackages() {
    return response()->json([
      'success' => true,
      'data' => $this->storeService->getDiamondPackages()
    ]);
  }

  public function buyDiamond(Request $request) {
    try {
      $result = $this->storeService->buyDiamond($request->user(), $request->input('package_id'));
      return response()->json(['success' => true, 'data' => $result]);
    } catch (\Exception $e) {
      return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
    }
  }

  public function getUpgrades(Request $request) {
    return response()->json([
      'success' => true,
      'data' => $this->storeService->getUpgrades($request->user())
    ]);
  }

  public function buyUpgrade(Request $request) {
    try {
      $result = $this->storeService->buyUpgrade($request->user(), $request->input('upgrade_id'));
      return response()->json(['success' => true, 'data' => $result]);
    } catch (\Exception $e) {
      return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
    }
  }

  public function getBattleHistory(Request $request, $id) {
    $history = BattleHistory::where('telegram_user_id', $request->user()->id)->findOrFail($id);
    return response()->json(['success' => true, 'data' => $history]);
  }

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