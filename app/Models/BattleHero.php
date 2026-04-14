<?php

namespace Modules\BattleGame\Models;

use Illuminate\Database\Eloquent\Model;

class BattleHero extends Model
{
  protected $fillable = [
    'name',
    'description',
    'type',
    'base_hp',
    'base_atk',
    'base_def',
    'base_aspd',
    'base_block_chance',
    'base_block_reduction',
    'unlock_requirements',
    'is_active'
  ];

  protected $casts = [
    'unlock_requirements' => 'array',
    'base_aspd' => 'float',
    'base_block_chance' => 'float',
    'base_block_reduction' => 'float',
  ];

  public function userHeroes() {
    return $this->hasMany(BattleUserHero::class);
  }

  public function getStatsAttribute(): array
  {
    return [
      'hp' => $this->base_hp,
      'atk' => $this->base_atk,
      'def' => $this->base_def,
      'aspd' => $this->base_aspd,
      'block_chance' => $this->base_block_chance,
      'block_reduction' => $this->base_block_reduction,
    ];
  }
}