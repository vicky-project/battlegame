<?php

namespace Modules\BattleGame\Characters\Base;

use Modules\BattleGame\Enums\SkillType;

abstract class Enemy
{
  public function __construct(
    public readonly string $id,
    public readonly string $name,
    public readonly string $emoji,
    public readonly int $minLevel,
    public readonly ?int $maxLevel,
    public readonly array $rewards,
  ) {}

  abstract public function baseHp(): int;
  abstract public function baseAtk(): int;
  abstract public function baseDef(): int;
  abstract public function baseAspd(): float;
  abstract public function baseBlockChance(): float;
  abstract public function baseBlockReduction(): float;
  abstract public function levelScalingFactor(): float;

  // Akurasi dan Evasi (default)
  public function baseAccuracy(): float {
    return 0.95;
  }
  public function baseEvasion(): float {
    return 0.05;
  }

  abstract public function specialAbility(): array;

  public function getStatsForLevel(int $level): array
  {
    $factor = 1 + $this->levelScalingFactor() * ($level - 1);
    return [
      'hp' => round($this->baseHp() * $factor),
      'atk' => round($this->baseAtk() * $factor),
      'def' => round($this->baseDef() * $factor),
      'aspd' => $this->baseAspd(),
      'block_chance' => min($this->baseBlockChance() + 0.01 * ($level - 1), 0.7),
      'block_reduction' => $this->baseBlockReduction(),
      'accuracy' => $this->baseAccuracy(),
      'evasion' => $this->baseEvasion(),
    ];
  }

  /**
  * Mendapatkan reward yang sudah diskalakan berdasarkan level.
  */
  public function getRewardsForLevel(int $level): array
  {
    $baseRewards = $this->rewards;
    $factor = 1 + 0.1 * ($level - 1); // +10% per level di atas 1
    return [
      'exp' => (int) round($baseRewards['exp'] * $factor),
      'gold' => (int) round($baseRewards['gold'] * $factor),
    ];
  }

  public function toArray(): array
  {
    return [
      'id' => $this->id,
      'name' => $this->name,
      'emoji' => $this->emoji,
      'min_level' => $this->minLevel,
      'max_level' => $this->maxLevel,
      'rewards' => $this->rewards,
      'base_hp' => $this->baseHp(),
      'base_atk' => $this->baseAtk(),
      'base_def' => $this->baseDef(),
      'base_aspd' => $this->baseAspd(),
      'base_block_chance' => $this->baseBlockChance(),
      'base_block_reduction' => $this->baseBlockReduction(),
      'level_scaling_factor' => $this->levelScalingFactor(),
      'base_accuracy' => $this->baseAccuracy(),
      'base_evasion' => $this->baseEvasion(),
      'special_ability' => [
        'type' => $this->specialAbility()['type']->value,
        'chance' => $this->specialAbility()['chance'],
        'value' => $this->specialAbility()['value'] ?? null,
        'duration' => $this->specialAbility()['duration'] ?? null,
        'damage_per_tick' => $this->specialAbility()['damage_per_tick'] ?? null,
      ],
    ];
  }
}