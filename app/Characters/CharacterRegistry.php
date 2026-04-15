<?php

namespace Modules\BattleGame\Characters;

use Modules\BattleGame\Characters\Base\Hero;
use Modules\BattleGame\Characters\Base\Enemy;
use Modules\BattleGame\Characters\Heroes;
use Modules\BattleGame\Characters\Enemies;

class CharacterRegistry
{
  protected static array $heroes = [];
  protected static array $enemies = [];

  public static function registerHeroes(): void
  {
    self::$heroes = [
      'warrior' => new Heroes\Warrior(),
      'ninja' => new Heroes\Ninja(),
      'mage' => new Heroes\Mage(),
      'berserker' => new Heroes\Berserker(),
      'paladin' => new Heroes\Paladin(),
    ];
  }

  public static function registerEnemies(): void
  {
    self::$enemies = [
      'goblin' => new Enemies\Goblin(),
      'wolf' => new Enemies\Wolf(),
      'bandit' => new Enemies\Bandit(),
      'orc_warrior' => new Enemies\OrcWarrior(),
      'skeleton_mage' => new Enemies\SkeletonMage(),
      'dark_archer' => new Enemies\DarkArcher(),
      'dark_knight' => new Enemies\DarkKnight(),
      'necromancer' => new Enemies\Necromancer(),
      'shadow_assassin' => new Enemies\ShadowAssassin(),
      'ancient_dragon' => new Enemies\AncientDragon(),
      'lich_king' => new Enemies\LichKing(),
      'demon_lord' => new Enemies\DemonLord(),
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