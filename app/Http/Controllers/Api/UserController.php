<?php

namespace Modules\BattleGame\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\BattleGame\Services\HeroService;
use Modules\BattleGame\Services\UserProgressService;

class UserController extends Controller
{
  public function __construct(
    protected UserProgressService $userService,
    protected HeroService $heroService
  ) {}

  public function getUserData(Request $request) {
    return response()->json([
      'success' => true,
      'data' => $this->userService->getUserData($request->user())
    ]);
  }

  public function setSelectedHero(Request $request) {
    try {
      $this->heroService->setSelectedHero($request->user(), $request->input('user_hero_id'));
      return response()->json(['success' => true, 'message' => 'Hero dipilih']);
    } catch (\Exception $e) {
      return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
    }
  }
}