<?php

namespace Modules\BattleGame\Characters\Base;

use Modules\BattleGame\Enums\HeroType;

abstract class Hero
{
  public function __construct(
    public readonly string $id,
    public readonly string $name,
    public readonly HeroType $type,
    public readonly string $description,
    public readonly string $emoji,
    public readonly array $unlockRequirements, // ['required_user_level' => int, 'unlock_cost_gold' => int]
  ) {}

  // Stat dasar (akan diimplementasi oleh subclass)
  abstract public function baseHp(): int;
  abstract public function baseAtk(): int;
  abstract public function baseDef(): int;
  abstract public function baseAspd(): float;
  abstract public function baseBlockChance(): float;
  abstract public function baseBlockReduction(): float;

  // Skill pasif (opsional, bisa di-override)
  public function passiveSkill(): ?string
  {
    return null;
  }

  // Method untuk mendapatkan data lengkap
  public function toArray(): array
  {
    return [
      'id' => $this->id,
      'name' => $this->name,
      'type' => $this->type->value,
      'description' => $this->description,
      'emoji' => $this->emoji,
      'unlock_requirements' => $this->unlockRequirements,
      'base_hp' => $this->baseHp(),
      'base_atk' => $this->baseAtk(),
      'base_def' => $this->baseDef(),
      'base_aspd' => $this->baseAspd(),
      'base_block_chance' => $this->baseBlockChance(),
      'base_block_reduction' => $this->baseBlockReduction(),
      'passive_skill' => $this->passiveSkill(),
    ];
  }
}