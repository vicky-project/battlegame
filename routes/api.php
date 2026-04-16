<?php

use Illuminate\Support\Facades\Route;
use Modules\BattleGame\Http\Controllers\Api\BattleController;
use Modules\BattleGame\Http\Controllers\Api\HeroController;
use Modules\BattleGame\Http\Controllers\Api\MiningController;
use Modules\BattleGame\Http\Controllers\Api\StoreController;
use Modules\BattleGame\Http\Controllers\Api\UserController;

Route::middleware(['auth:sanctum'])
->prefix('battle')
->group(function () {
  // User & Hero Selection
  Route::get('/user', [UserController::class, 'getUserData']);
  Route::post('/select-hero', [UserController::class, 'setSelectedHero']);

  // Battle
  Route::post('/vs-computer', [BattleController::class, 'startBattleVsComputer']);
  Route::get('/history', [BattleController::class, 'getBattleHistoryList']);
  Route::get('/history/{id}', [BattleController::class, 'getBattleHistory']);

  Route::prefix('store')
  ->group(function() {

    // Store Main & Diamond
    Route::get('/', [StoreController::class, 'getStoreData']);
    Route::get('/diamond-packages', [StoreController::class, 'getDiamondPackages']);
    Route::post('/buy-diamond', [StoreController::class, 'buyDiamond']);

    // Upgrades
    Route::get('/upgrades', [StoreController::class, 'getUpgrades']);
    Route::post('/buy-upgrade', [StoreController::class, 'buyUpgrade']);

    // Heroes Store
    Route::get('/heroes', [HeroController::class, 'getStoreHeroes']);
    Route::post('/buy-hero', [HeroController::class, 'buyHero']);
  });

  Route::post('/unlock-hero', [HeroController::class, 'unlockHero']); // alias

  // Mining
  Route::get('/mining/status', [MiningController::class, 'getMiningStatus']);
  Route::post('/mining/claim', [MiningController::class, 'claimMining']);
});