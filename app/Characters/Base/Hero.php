<?php

namespace Modules\BattleGame\Characters\Base;

use Modules\BattleGame\Enums\HeroType;
use Modules\BattleGame\Enums\SkillType;

abstract class Hero
{
  public function __construct(
    public readonly string $id,
    public readonly string $name,
    public readonly HeroType $type,
    public readonly string $description,
    public readonly string $emoji,
    public readonly array $unlockRequirements,
  ) {}

  abstract public function baseHp(): int;
  abstract public function baseAtk(): int;
  abstract public function baseDef(): int;
  abstract public function baseAspd(): float;
  abstract public function baseBlockChance(): float;
  abstract public function baseBlockReduction(): float;

  /**
  * Skill pasif yang selalu aktif.
  * @return array{type: SkillType, value: float|int, condition?: string}
  */
  abstract public function passiveSkill(): array;

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
      'passive_skill' => [
        'type' => $this->passiveSkill()['type']->value,
        'value' => $this->passiveSkill()['value'],
        'condition' => $this->passiveSkill()['condition'] ?? null,
      ],
    ];
  }
}