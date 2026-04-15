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

  /**
  * Kemampuan spesial musuh.
  * @return array{type: SkillType, chance: float, value?: float, duration?: int, damage_per_tick?: int}
  */
  abstract public function specialAbility(): array;

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