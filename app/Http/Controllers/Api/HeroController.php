<?php

namespace Modules\BattleGame\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\BattleGame\Services\HeroService;

class HeroController extends Controller
{
  public function __construct(
    protected HeroService $heroService
  ) {}

  public function unlockHero(Request $request) {
    try {
      $this->heroService->unlockHero($request->user(), $request->input('hero_id'));
      return response()->json(['success' => true, 'message' => 'Hero berhasil di-unlock!']);
    } catch (\Exception $e) {
      return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
    }
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
}