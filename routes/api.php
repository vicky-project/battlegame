<?php

use Illuminate\Support\Facades\Route;
use Modules\BattleGame\Http\Controllers\Api\BattleController;

Route::middleware(['auth:sanctum'])
->prefix('battle')
->name('battle.')
->group(function () {
  Route::get('/user', [BattleController::class, 'getUserData']);
  Route::post('/vs-computer', [BattleController::class, 'startBattleVsComputer']);
  Route::get('/history/{id}', [BattleController::class, 'getBattleHistory']);
  Route::post('/select-hero', [BattleController::class, 'setSelectedHero']);

  Route::get('/store', [BattleController::class, 'getStoreData']);
  Route::get('/store/heroes', [BattleController::class, 'getStoreHeroes']);
  Route::post('/store/buy-hero', [BattleController::class, 'buyHero']);
  Route::get('/store/diamond-packages', [BattleController::class, 'getDiamondPackages']);
  Route::post('/store/buy-diamond', [BattleController::class, 'buyDiamond']);
  Route::get('/store/upgrades', [BattleController::class, 'getUpgrades']);
  Route::post('/store/buy-upgrade', [BattleController::class, 'buyUpgrade']);

  Route::get('/mining/status', [BattleController::class, 'getMiningStatus']);
  Route::post('/mining/claim', [BattleController::class, 'claimMining']);
});