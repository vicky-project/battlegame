<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up() {
    Schema::create('user_currencies', function (Blueprint $table) {
      $table->id();
      $table->foreignId('telegram_user_id')
      ->constrained('telegram_users')
      ->cascadeOnDelete();
      $table->integer('gold')->default(0);
        $table->integer('diamond')->default(0);
        $table->timestamps();

        $table->unique('telegram_user_id');
      });
    }

    public function down() {
      Schema::dropIfExists('user_currencies');
    }
  };