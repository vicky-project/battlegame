<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up() {
    Schema::create('battle_enemies', function (Blueprint $table) {
      $table->id();
      $table->string('name');
      $table->string('emoji')->nullable();
      $table->integer('min_level')->default(1);
        $table->integer('max_level')->nullable();
        $table->integer('base_hp');
        $table->integer('base_atk');
        $table->integer('base_def');
        $table->float('base_aspd')->default(2.0);
        $table->float('base_block_chance')->default(0.1);
        $table->float('base_block_reduction')->default(0.5);
        $table->float('level_scaling_factor')->default(0.1); // peningkatan per level
        $table->json('rewards')->nullable(); // exp, gold, dll
        $table->boolean('is_active')->default(true);
        $table->timestamps();
      });
    }

    public function down() {
      Schema::dropIfExists('battle_enemies');
    }
  };