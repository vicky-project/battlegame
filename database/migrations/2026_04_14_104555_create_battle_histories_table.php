<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up() {
    Schema::create('battle_histories', function (Blueprint $table) {
      $table->id();
      $table->foreignId('telegram_user_id')
      ->constrained('telegram_users')
      ->cascadeOnDelete();
      $table->foreignId('battle_user_hero_id')
      ->nullable()
      ->constrained('battle_user_heroes')
      ->nullOnDelete();
      $table->foreignId('battle_enemy_id')
      ->nullable()
      ->constrained('battle_enemies')
      ->nullOnDelete();
      $table->enum('battle_type', ['vs_computer', 'vs_player'])
      ->default('vs_computer');
        $table->enum('result', ['win', 'lose', 'draw'])
        ->nullable();
        $table->json('battle_log')->nullable();
        $table->integer('player_hp_remaining')->nullable();
        $table->integer('enemy_hp_remaining')->nullable();
        $table->integer('exp_gained')->default(0);
        $table->float('duration')->nullable();
        $table->timestamps();
      });
    }

    public function down() {
      Schema::dropIfExists('battle_histories');
    }
  };