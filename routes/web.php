<?php

use Illuminate\Support\Facades\Route;
use Modules\BattleGame\Http\Controllers\BattleGameController;

Route::prefix('apps')->group(function () {
  Route::view('battlegames', 'battlegame::index');
});