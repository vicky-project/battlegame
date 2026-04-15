<?php

namespace Modules\BattleGame\Characters;

use Modules\BattleGame\Characters\Base\Hero;
use Modules\BattleGame\Characters\Base\Enemy;
use Modules\BattleGame\Characters\Heroes\Warrior;
use Modules\BattleGame\Characters\Heroes\Ninja;
use Modules\BattleGame\Characters\Heroes\Mage;
use Modules\BattleGame\Characters\Heroes\Berserker;
use Modules\BattleGame\Characters\Heroes\Paladin;
use Modules\BattleGame\Characters\Enemies\Goblin;
use Modules\BattleGame\Characters\Enemies\OrcWarrior;
use Modules\BattleGame\Characters\Enemies\DarkKnight;
use Modules\BattleGame\Characters\Enemies\AncientDragon;

class CharacterRegistry
{
  protected static array $heroes = [];
  protected static array $enemies = [];

  public static function registerHeroes(): void
  {
    self::$heroes = [
      'warrior' => new Warrior(),
      'ninja' => new Ninja(),
      'mage' => new Mage(),
      'berserker' => new Berserker(),
      'paladin' => new Paladin(),
    ];
  }

  public static function registerEnemies(): void
  {
    self::$enemies = [
      'goblin' => new Goblin(),
      'orc_warrior' => new OrcWarrior(),
      'dark_knight' => new DarkKnight(),
      'ancient_dragon' => new AncientDragon(),
    ];
  }

  /**
  * @return array<string, Hero>
  */
  public static function getHeroes(): array
  {
    if (empty(self::$heroes)) self::registerHeroes();
    return self::$heroes;
  }

  /**
  * @return array<string, Enemy>
  */
  public static function getEnemies(): array
  {
    if (empty(self::$enemies)) self::registerEnemies();
    return self::$enemies;
  }

  public static function getHero(string $id): ?Hero
  {
    return self::getHeroes()[$id] ?? null;
  }

  public static function getEnemy(string $id): ?Enemy
  {
    return self::getEnemies()[$id] ?? null;
  }
}