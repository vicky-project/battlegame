<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up() {
    Schema::create('battle_user_progress', function (Blueprint $table) {
      $table->id();
      $table->foreignId('telegram_user_id')->constrained('telegram_users')->cascadeOnDelete();
      $table->integer('level')->default(1);
        $table->integer('exp')->default(0);
        $table->integer('total_battles')->default(0);
        $table->integer('total_wins')->default(0);
        $table->integer('total_losses')->default(0);
        $table->timestamps();
      });
    }

    public function down() {
      Schema::dropIfExists('battle_user_progress');
    }
  };