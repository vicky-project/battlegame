<?php

namespace Modules\BattleGame\Models;

use Illuminate\Database\Eloquent\Model;

class BattleEnemy extends Model
{
  protected $fillable = [
    'name',
    'min_level',
    'max_level',
    'base_hp',
    'base_atk',
    'base_def',
    'base_aspd',
    'base_block_chance',
    'base_block_reduction',
    'level_scaling_factor',
    'rewards',
    'is_active'
  ];

  protected $casts = [
    'rewards' => 'array',
    'base_aspd' => 'float',
    'base_block_chance' => 'float',
    'base_block_reduction' => 'float',
    'level_scaling_factor' => 'float',
  ];

  public function getStatsForLevel(int $level): array
  {
    $factor = 1 + $this->level_scaling_factor * ($level - 1);
    return [
      'hp' => round($this->base_hp * $factor),
      'atk' => round($this->base_atk * $factor),
      'def' => round($this->base_def * $factor),
      'aspd' => $this->base_aspd,
      'block_chance' => min($this->base_block_chance + 0.01 * ($level - 1), 0.7),
      'block_reduction' => $this->base_block_reduction,
    ];
  }
}