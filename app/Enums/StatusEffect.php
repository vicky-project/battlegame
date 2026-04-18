<?php

namespace Modules\BattleGame\Enums;

enum StatusEffect: string
{
  case POISON = 'poison';
  case STUN = 'stun';
  case REGEN = 'regen';
  case SHIELD = 'shield';
}