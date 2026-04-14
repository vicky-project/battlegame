<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up() {
    Schema::create('battle_user_heroes', function (Blueprint $table) {
      $table->id();
      $table->foreignId('telegram_user_id')->constrained('telegram_users')->cascadeOnDelete();
      $table->foreignId('battle_hero_id')->constrained('battle_heroes')->cascadeOnDelete();
      $table->integer('level')->default(1);
        $table->integer('exp')->default(0);
        $table->boolean('is_selected')->default(false);
        $table->timestamps();
      });
    }

    public function down() {
      Schema::dropIfExists('battle_user_heroes');
    }
  };