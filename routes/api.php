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
});