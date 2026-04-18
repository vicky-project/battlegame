<?php

namespace Modules\BattleGame\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\BattleGame\Services\StoreService;

class StoreController extends Controller
{
  public function __construct(
    protected StoreService $storeService
  ) {}

  public function getStoreData(Request $request) {
    return response()->json([
      'success' => true,
      'data' => $this->storeService->getStoreData($request->user())
    ]);
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
}