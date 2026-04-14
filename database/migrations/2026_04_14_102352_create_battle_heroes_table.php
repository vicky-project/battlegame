<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up() {
    Schema::create('battle_heroes', function (Blueprint $table) {
      $table->id();
      $table->string('name');
      $table->string('description')->nullable();
      $table->string('type')->default('warrior');
        $table->integer('base_hp');
        $table->integer('base_atk');
        $table->integer('base_def');
        $table->float('base_aspd')->default(2.0);
        $table->float('base_block_chance')->default(0.1);
        $table->float('base_block_reduction')->default(0.5);
        $table->json('unlock_requirements')->nullable(); // misal: {"level":5, "achievement":"..."}
        $table->boolean('is_active')->default(true);
        $table->timestamps();
      });
    }

    public function down() {
      Schema::dropIfExists('battle_heroes');
    }
  };