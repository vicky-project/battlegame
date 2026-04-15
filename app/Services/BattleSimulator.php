<?php

namespace Modules\BattleGame\Services;

use Modules\BattleGame\Characters\Base\Hero;
use Modules\BattleGame\Characters\Base\Enemy;
use Modules\BattleGame\Enums\SkillType;
use Modules\BattleGame\Enums\StatusEffect;

class BattleSimulator
{
  protected array $log = [];
  protected string $winner = '';
  protected float $simulationTime = 0.0;
  protected array $playerStats;
  protected array $enemyStats;
  protected string $playerName;
  protected string $enemyName;
  protected string $playerEmoji;
  protected string $enemyEmoji;

  protected array $playerStatus = [];
  protected array $enemyStatus = [];
  protected array $playerSkillCooldown = [];
  protected array $enemySkillCooldown = [];

  protected const DAMAGE_VARIANCE = 0.15;
  protected const BASE_CRIT_CHANCE = 0.05;
  protected const BASE_CRIT_MULTIPLIER = 1.5;
  protected const BASE_MISS_CHANCE = 0.05;

  public function __construct(
    protected Hero $playerHero,
    protected Enemy $enemy,
    protected int $enemyLevel,
    protected array $playerUpgrades = []
  ) {
    $this->playerName = $playerHero->name;
    $this->enemyName = $enemy->name;
    $this->playerEmoji = $playerHero->emoji;
    $this->enemyEmoji = $enemy->emoji;
    $this->playerStats = $this->buildPlayerStats();
    $this->enemyStats = $this->buildEnemyStats();
    $this->applyPassiveSkills();
  }

  protected function buildPlayerStats(): array
  {
    $baseHp = $this->playerHero->baseHp();
    $baseAtk = $this->playerHero->baseAtk();
    $baseDef = $this->playerHero->baseDef();
    // Terapkan upgrade dari user
    if (!empty($this->playerUpgrades)) {
      $baseHp += ($this->playerUpgrades['hp_boost'] ?? 0) * 20;
      $baseAtk += ($this->playerUpgrades['attack_boost'] ?? 0) * 5;
      $baseDef += ($this->playerUpgrades['defense_boost'] ?? 0) * 3;
    }

    return [
      'hp' => $baseHp,
      'max_hp' => $baseHp,
      'atk' => $baseAtk,
      'def' => $baseDef,
      'aspd' => $this->playerHero->baseAspd(),
      'block_chance' => $this->playerHero->baseBlockChance(),
      'block_reduction' => $this->playerHero->baseBlockReduction(),
      'miss_chance_bonus' => 0,
      'crit_multiplier_bonus' => 0,
      'damage_reduction' => 0,
    ];
  }

  protected function buildEnemyStats(): array
  {
    $factor = 1 + $this->enemy->levelScalingFactor() * ($this->enemyLevel - 1);
    $baseHp = round($this->enemy->baseHp() * $factor);
    return [
      'hp' => $baseHp,
      'max_hp' => $baseHp,
      'atk' => round($this->enemy->baseAtk() * $factor),
      'def' => round($this->enemy->baseDef() * $factor),
      'aspd' => $this->enemy->baseAspd(),
      'block_chance' => min($this->enemy->baseBlockChance() + 0.01 * ($this->enemyLevel - 1), 0.7),
      'block_reduction' => $this->enemy->baseBlockReduction(),
      'enrage_active' => false,
    ];
  }

  protected function applyPassiveSkills(): void
  {
    $passive = $this->playerHero->passiveSkill();
    switch ($passive['type']) {
      case SkillType::EVASION:
        $this->playerStats['miss_chance_bonus'] = $passive['value'];
        break;
      case SkillType::CRITICAL_DAMAGE:
        $this->playerStats['crit_multiplier_bonus'] = $passive['value'];
        break;
      case SkillType::DAMAGE_REDUCTION:
        $this->playerStats['damage_reduction'] = $passive['value'];
        break;
      case SkillType::BERSERK:
        // Efek dihitung dinamis saat hitung damage
        break;
      case SkillType::HOLY_SHIELD:
        $this->playerSkillCooldown['holy_shield'] = 0;
        break;
      default: break;
    }
  }

  public function runSimulation(): array
  {
    $pHp = $this->playerStats['hp'];
    $eHp = $this->enemyStats['hp'];
    $pTimer = 0.0;
    $eTimer = 0.0;
    $delta = 0.1;

    $this->log = [];
    $this->log[] = sprintf(
      "Pertarungan dimulai! %s %s (HP: %.1f) vs %s %s (Level %d, HP: %.1f)",
      $this->playerEmoji, $this->playerName, $pHp,
      $this->enemyEmoji, $this->enemyName, $this->enemyLevel, $eHp
    );
    $this->simulationTime = 0.0;

    while ($pHp > 0 && $eHp > 0) {
      $this->simulationTime += $delta;
      $pTimer += $delta;
      $eTimer += $delta;

      // Proses efek status setiap detik
      if (floor($this->simulationTime) > floor($this->simulationTime - $delta)) {
        $this->processStatusEffects($pHp, $eHp);
        $this->processSkillCooldowns();
      }

      // Player attack
      if ($pTimer >= $this->playerStats['aspd'] && !$this->isStunned('player')) {
        $pTimer = 0.0;
        $damage = $this->calculateDamage('player', $pHp, $eHp);
        $eHp -= $damage['value'];
        $this->log[] = sprintf(
          "[%.1fs] %s menyerang! %s HP %s tersisa %.1f",
          $this->simulationTime, $this->playerName, $damage['message'], $this->enemyName, max(0, $eHp)
        );
        if ($eHp <= 0) break;
      }

      // Enemy attack
      if ($eTimer >= $this->enemyStats['aspd'] && !$this->isStunned('enemy')) {
        $eTimer = 0.0;
        $damage = $this->calculateDamage('enemy', $pHp, $eHp);
        $pHp -= $damage['value'];
        $this->applyOnHitEffects('enemy', $damage, $eHp);
        $this->log[] = sprintf(
          "[%.1fs] %s menyerang! %s HP %s tersisa %.1f",
          $this->simulationTime, $this->enemyName, $damage['message'], $this->playerName, max(0, $pHp)
        );
        if ($pHp <= 0) break;
      }
    }

    $this->winner = $pHp > 0 ? $this->playerName : $this->enemyName;
    $this->log[] = sprintf("Pertarungan selesai! Pemenang: %s", $this->winner);

    return [
      'winner' => $this->winner,
      'player_name' => $this->playerName,
      'enemy_name' => $this->enemyName,
      'player_emoji' => $this->playerEmoji,
      'enemy_emoji' => $this->enemyEmoji,
      'log' => $this->log,
      'player_hp_remaining' => max(0, $pHp),
      'enemy_hp_remaining' => max(0, $eHp),
      'duration' => round($this->simulationTime, 1),
    ];
  }

  protected function calculateDamage(string $attackerSide, float &$attackerHp, float &$defenderHp): array
  {
    $attacker = $attackerSide === 'player' ? $this->playerStats : $this->enemyStats;
    $defender = $attackerSide === 'player' ? $this->enemyStats : $this->playerStats;

    // Miss chance
    $missChance = self::BASE_MISS_CHANCE;
    if ($attackerSide === 'player') {
      $missChance += $this->enemyStats['miss_chance_bonus'] ?? 0;
    } else {
      $missChance += $this->playerStats['miss_chance_bonus'] ?? 0;
    }
    if (mt_rand(1, 100) <= $missChance * 100) {
      return ['value' => 0,
        'critical' => false,
        'message' => 'Meleset! 0 damage'];
    }

    // Berserk effect
    $currentAtk = $attacker['atk'];
    if ($attackerSide === 'player' && $this->playerHero->passiveSkill()['type'] === SkillType::BERSERK) {
      $missingHpPercent = 1 - ($attackerHp / $this->playerStats['max_hp']);
      $currentAtk *= (1 + $missingHpPercent * $this->playerHero->passiveSkill()['value']);
    }
    // Enemy enrage
    if ($attackerSide === 'enemy' && $this->enemy->specialAbility()['type'] === SkillType::ENRAGE) {
      if ($attackerHp / $this->enemyStats['max_hp'] < 0.3) {
        $currentAtk *= (1 + $this->enemy->specialAbility()['value']);
        $this->enemyStats['enrage_active'] = true;
      }
    }

    $rawDamage = $currentAtk - $defender['def'];

    // Critical
    $critChance = self::BASE_CRIT_CHANCE;
    $critMultiplier = self::BASE_CRIT_MULTIPLIER;
    if ($attackerSide === 'player') {
      $critMultiplier += $this->playerStats['crit_multiplier_bonus'];
    }
    $isCritical = mt_rand(1, 100) <= $critChance * 100;
    if ($isCritical) {
      $rawDamage *= $critMultiplier;
    }

    // Fire breath (Ancient Dragon)
    if ($attackerSide === 'enemy' && $this->enemy->specialAbility()['type'] === SkillType::FIRE_BREATH) {
      if (mt_rand(1, 100) <= $this->enemy->specialAbility()['chance'] * 100) {
        $rawDamage *= $this->enemy->specialAbility()['value'];
        $isCritical = true; // visual saja
      }
    }

    // Variance
    $variance = 1 + (mt_rand(-100, 100) / 100) * self::DAMAGE_VARIANCE;
    $rawDamage *= $variance;

    // Block
    $blocked = false;
    if (mt_rand(1, 100) <= $defender['block_chance'] * 100) {
      $blocked = true;
      $rawDamage *= (1 - $defender['block_reduction']);
    }

    // Damage reduction pasif Warrior
    if ($attackerSide === 'enemy' && $this->playerHero->passiveSkill()['type'] === SkillType::DAMAGE_REDUCTION) {
      if ($defenderHp / $this->playerStats['max_hp'] < 0.5) {
        $rawDamage *= (1 - $this->playerHero->passiveSkill()['value']);
      }
    }

    $finalDamage = (int) max(1, round($rawDamage));

    $parts = [];
    if ($isCritical) $parts[] = '💥 Critical!';
    if ($blocked) $parts[] = '🛡️ Diblok!';
    if ($this->enemyStats['enrage_active'] ?? false) $parts[] = '😡 Mengamuk!';
    $parts[] = "Damage {$finalDamage}";

    return [
      'value' => $finalDamage,
      'critical' => $isCritical,
      'message' => implode(' ', $parts)
    ];
  }

  protected function processStatusEffects(&$pHp, &$eHp): void
  {
    // Player poison
    if (isset($this->playerStatus[StatusEffect::POISON->value])) {
      $dmg = $this->playerStatus[StatusEffect::POISON->value]['damage'];
      $pHp -= $dmg;
      $this->log[] = sprintf("[%.1fs] %s terkena racun! -%d HP", $this->simulationTime, $this->playerName, $dmg);
      $this->playerStatus[StatusEffect::POISON->value]['duration']--;
      if ($this->playerStatus[StatusEffect::POISON->value]['duration'] <= 0) {
        unset($this->playerStatus[StatusEffect::POISON->value]);
      }
    }
    // Enemy poison (bisa ditambahkan nanti)
  }

  protected function processSkillCooldowns(): void
  {
    // Holy Shield
    if (isset($this->playerSkillCooldown['holy_shield'])) {
      $this->playerSkillCooldown['holy_shield']--;
      if ($this->playerSkillCooldown['holy_shield'] <= 0) {
        $shield = $this->playerHero->passiveSkill()['value'];
        $this->playerStats['hp'] += $shield;
        $this->log[] = sprintf("[%.1fs] %s mendapatkan Perisai Suci +%d HP!", $this->simulationTime, $this->playerName, $shield);
        $this->playerSkillCooldown['holy_shield'] = 10; // reset 10 detik
      }
    } else {
      if ($this->playerHero->passiveSkill()['type'] === SkillType::HOLY_SHIELD) {
        $this->playerSkillCooldown['holy_shield'] = 10;
      }
    }
  }

  protected function applyOnHitEffects(string $attackerSide, array $damageData, &$attackerHp): void
  {
    if ($attackerSide === 'enemy') {
      $ability = $this->enemy->specialAbility();
      if ($ability['type'] === SkillType::POISON) {
        if (mt_rand(1, 100) <= $ability['chance'] * 100) {
          $this->playerStatus[StatusEffect::POISON->value] = [
            'damage' => $ability['damage_per_tick'],
            'duration' => $ability['duration']
          ];
          $this->log[] = sprintf("[%.1fs] %s terkena racun!", $this->simulationTime, $this->playerName);
        }
      }
      if ($ability['type'] === SkillType::LIFESTEAL) {
        $heal = (int)($damageData['value'] * $ability['value']);
        $attackerHp += $heal;
        $this->log[] = sprintf("[%.1fs] %s mencuri nyawa +%d HP!", $this->simulationTime, $this->enemyName, $heal);
      }
    }
  }

  protected function isStunned(string $side): bool
  {
    return isset($this-> {
      $side . 'Status'
    }[StatusEffect::STUN->value]);
  }

  public function getLog(): array {
    return $this->log;
  }
  public function getWinner(): string {
    return $this->winner;
  }
}