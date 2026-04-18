<?php

namespace Modules\BattleGame\Services;

use Modules\BattleGame\Characters\Base\Enemy;
use Modules\BattleGame\Enums\SkillType;
use Modules\BattleGame\Enums\StatusEffect;

class BattleSimulator
{
  protected array $log = [];
  protected string $winner = '';
  protected float $simulationTime = 0.0;
  protected array $enemyStats;
  protected string $playerName;
  protected string $enemyName;
  protected string $playerEmoji;
  protected string $enemyEmoji;

  protected array $playerStatus = [];
  protected array $enemyStatus = [];
  protected array $playerSkillCooldown = [];

  protected const DAMAGE_VARIANCE = 0.15;
  protected const BASE_CRIT_CHANCE = 0.05;
  protected const BASE_CRIT_MULTIPLIER = 1.5;
  protected const COUNTER_CHANCE = 0.3;
  protected const COUNTER_DAMAGE_RATIO = 0.5;

  protected const SIMULATION_SPEED_FACTOR = 0.05;

  public function __construct(
    protected array $playerStats,
    protected Enemy $enemy,
    protected int $enemyLevel
  ) {
    $this->playerName = $playerStats['name'];
    $this->enemyName = $enemy->name;
    $this->playerEmoji = $playerStats['emoji'];
    $this->enemyEmoji = $enemy->emoji;
    $this->enemyStats = $this->buildEnemyStats();
    $this->applyPassiveSkills();
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
      'accuracy' => $this->enemy->baseAccuracy(),
      'evasion' => $this->enemy->baseEvasion(),
      'enrage_active' => false,
    ];
  }

  protected function applyPassiveSkills(): void
  {
    $passive = $this->playerStats['passive_skill'] ?? null;
    if (!$passive) {
      return;
    }

    switch ($passive['type']) {
      case SkillType::HOLY_SHIELD->value:
        $this->playerSkillCooldown['holy_shield'] = 0;
        break;
      case SkillType::EVASION->value:
        $this->playerStats['evasion'] = ($this->playerStats['evasion'] ?? 0) + $passive['value'];
        break;
      case SkillType::CRITICAL_DAMAGE->value:
        $this->playerStats['crit_multiplier_bonus'] = ($this->playerStats['crit_multiplier_bonus'] ?? 0) + $passive['value'];
        break;
      case SkillType::BERSERK->value:
        break;
      case SkillType::DAMAGE_REDUCTION->value:
        break;
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
      $this->playerEmoji,
      $this->playerName,
      $pHp,
      $this->enemyEmoji,
      $this->enemyName,
      $this->enemyLevel,
      $eHp
    );
    $this->simulationTime = 0.0;

    while ($pHp > 0 && $eHp > 0) {
      $this->simulationTime += $delta;
      $pTimer += $delta;
      $eTimer += $delta;

      if (floor($this->simulationTime) > floor($this->simulationTime - $delta)) {
        $this->processStatusEffects($pHp, $eHp);
        $this->processSkillCooldowns($pHp);
      }

      // Giliran Player
      if ($pTimer >= $this->playerStats['aspd'] && !$this->isStunned('player')) {
        $pTimer = 0.0;
        $damage = $this->calculateDamage(
          'player',
          $this->playerStats,
          $this->enemyStats,
          $pHp,
          $eHp,
          'enemy'
        );
        $eHp -= $damage['value'];
        $this->log[] = sprintf(
          "[%.1fs] %s menyerang! %s HP %s tersisa %.1f",
          $this->simulationTime,
          $this->playerName,
          $damage['message'],
          $this->enemyName,
          max(0, $eHp)
        );
        if ($eHp <= 0) {
          break;
        }
      }

      // Giliran Enemy
      if ($eTimer >= $this->enemyStats['aspd'] && !$this->isStunned('enemy')) {
        $eTimer = 0.0;
        $damage = $this->calculateDamage(
          'enemy',
          $this->enemyStats,
          $this->playerStats,
          $eHp,
          $pHp,
          'player'
        );
        $pHp -= $damage['value'];
        $this->applyOnHitEffects('enemy', $damage, $eHp);
        $this->log[] = sprintf(
          "[%.1fs] %s menyerang! %s HP %s tersisa %.1f",
          $this->simulationTime,
          $this->enemyName,
          $damage['message'],
          $this->playerName,
          max(0, $pHp)
        );
        if ($pHp <= 0) {
          break;
        }
      }

      usleep((int) ($delta * 1000000 * self::SIMULATION_SPEED_FACTOR));
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
      'player_max_hp' => $this->playerStats['max_hp'],
      'enemy_max_hp' => $this->enemyStats['max_hp'],
      'duration' => round($this->simulationTime, 1),
    ];
  }

  protected function calculateDamage(
    string $attackerSide,
    array $attacker,
    array $defender,
    float &$attackerHp,
    float &$defenderHp,
    string $defenderSide
  ): array {
    // 1. Hit chance (accuracy - evasion)
    $hitChance = $attacker['accuracy'] - $defender['evasion'];
    $hitChance = max(0.1, min(1.0, $hitChance));
    if (mt_rand(1, 100) / 100 > $hitChance) {
      return [
        'value' => 0,
        'critical' => false,
        'blocked' => false,
        'message' => 'Meleset!'
      ];
    }

    if ($attackerSide === 'player') {
      $passive = $this->playerStats['passive_skill'] ?? null;
      if ($passive && $passive['type'] === SkillType::BERSERK->value) {
        $missingHpPercent = 1 - ($attackerHp / $this->playerStats['max_hp']);
        $attacker['atk'] *= (1 + $missingHpPercent * $passive['value']);
      }
    }

    if ($attackerSide === 'enemy') {
      $ability = $this->enemy->specialAbility();
      if ($ability['type'] === SkillType::ENRAGE) {
        $enemyHpPercent = $attackerHp / $this->enemyStats['max_hp'];
        if ($enemyHpPercent < 0.3) {
          $attacker['atk'] *= (1 + $ability['value']);
          $this->enemyStats['enrage_active'] = true;
        }
      }
    }

    // 2. Raw damage
    $rawDamage = $attacker['atk'] - $defender['def'];

    // 3. Critical hit
    $critChance = self::BASE_CRIT_CHANCE;
    if ($attackerSide === 'player') {
      $critChance += $attacker['crit_chance_bonus'] ?? 0;
    }
    $critMultiplier = self::BASE_CRIT_MULTIPLIER;
    if ($attackerSide === 'player' && isset($attacker['crit_multiplier_bonus'])) {
      $critMultiplier += $attacker['crit_multiplier_bonus'];
    }
    $isCritical = mt_rand(1, 100) <= $critChance * 100;
    if ($isCritical) {
      $rawDamage *= $critMultiplier;
    }

    // Fire breath (Ancient Dragon)
    if ($attackerSide === 'enemy' && $this->enemy->specialAbility()['type'] === SkillType::FIRE_BREATH) {
      if (mt_rand(1, 100) <= $this->enemy->specialAbility()['chance'] * 100) {
        $rawDamage *= $this->enemy->specialAbility()['value'];
      }
    }

    // 4. Variance
    $variance = 1 + (mt_rand(-100, 100) / 100) * self::DAMAGE_VARIANCE;
    $rawDamage *= $variance;

    // 5. Block
    $blocked = false;
    if (mt_rand(1, 100) <= $defender['block_chance'] * 100) {
      $blocked = true;
      $rawDamage *= (1 - $defender['block_reduction']);
    }

    // 6. Damage reduction pasif (Warrior)
    if ($attackerSide === 'enemy') {
      $passive = $this->playerStats['passive_skill'] ?? null;
      if ($passive && $passive['type'] === SkillType::DAMAGE_REDUCTION->value) {
        if ($defenderHp / $this->playerStats['max_hp'] < 0.5) {
          $rawDamage *= (1 - $passive['value']);
        }
      }
    }

    $finalDamage = (int) max(1, round($rawDamage));

    // 7. Counterattack
    $counterMessage = '';
    if ($blocked && $defenderHp > 0) {
      $counterChance = self::COUNTER_CHANCE;
      if ($defenderSide === 'player') {
        $counterChance += $defender['counter_chance_bonus'] ?? 0;
      }
      if (mt_rand(1, 100) <= $counterChance * 100) {
        $counterDamage = (int) max(1, round(
          ($defender['atk'] * self::COUNTER_DAMAGE_RATIO) - $attacker['def']
        ));
        $attackerHp -= $counterDamage;
        $counterMessage = sprintf(
          " %s membalas! Damage %d.",
          $defenderSide === 'player' ? $this->playerName : $this->enemyName,
          $counterDamage
        );
      }
    }

    // 8. Build message
    $parts = [];
    if ($isCritical) {
      $parts[] = '💥 Critical!';
    }
    if ($blocked) {
      $parts[] = '🛡️ Diblok!';
    }
    $parts[] = "Damage {$finalDamage}";
    $message = implode(' ', $parts) . $counterMessage;

    return [
      'value' => $finalDamage,
      'critical' => $isCritical,
      'blocked' => $blocked,
      'message' => $message,
    ];
  }

  protected function processStatusEffects(&$pHp, &$eHp): void
  {
    if (isset($this->playerStatus[StatusEffect::POISON->value])) {
      $dmg = $this->playerStatus[StatusEffect::POISON->value]['damage'];
      $pHp -= $dmg;
      $this->log[] = sprintf(
        "[%.1fs] %s terkena racun! -%d HP",
        $this->simulationTime,
        $this->playerName,
        $dmg
      );
      $this->playerStatus[StatusEffect::POISON->value]['duration']--;
      if ($this->playerStatus[StatusEffect::POISON->value]['duration'] <= 0) {
        unset($this->playerStatus[StatusEffect::POISON->value]);
      }
    }

    if (isset($this->playerStatus[StatusEffect::STUN->value])) {
      $this->playerStatus[StatusEffect::STUN->value]['duration']--;
      if ($this->playerStatus[StatusEffect::STUN->value]['duration'] <= 0) {
        unset($this->playerStatus[StatusEffect::STUN->value]);
      }
    }
  }

  protected function processSkillCooldowns(&$pHp): void
  {
    if (isset($this->playerSkillCooldown['holy_shield'])) {
      $this->playerSkillCooldown['holy_shield']--;
      if ($this->playerSkillCooldown['holy_shield'] <= 0) {
        $passive = $this->playerStats['passive_skill'] ?? null;
        if ($passive && $passive['type'] === SkillType::HOLY_SHIELD->value) {
          $shield = $passive['value'];
          $pHp += $shield;
          $this->log[] = sprintf(
            "[%.1fs] %s mendapatkan Perisai Suci +%d HP!",
            $this->simulationTime,
            $this->playerName,
            $shield
          );
        }
        $this->playerSkillCooldown['holy_shield'] = 7;
      }
    } else {
      $passive = $this->playerStats['passive_skill'] ?? null;
      if ($passive && $passive['type'] === SkillType::HOLY_SHIELD->value) {
        $this->playerSkillCooldown['holy_shield'] = 7;
      }
    }
  }

  protected function applyOnHitEffects(string $attackerSide, array $damageData, &$attackerHp): void
  {
    if ($attackerSide === 'enemy') {
      $ability = $this->enemy->specialAbility();

      if ($ability['type'] === SkillType::POISON) {
        if (mt_rand(1, 100) <= $ability['chance'] * 100) {
          $baseDamage = $ability['damage_per_tick'];
          $baseDuration = $ability['duration'];

          $resistance = $this->playerStats['poison_resistance'] ?? 0;
          $damage = (int) max(1, $baseDamage * (1 - $resistance));
          $duration = (int) max(1, $baseDuration * (1 - $resistance * 0.5)); // resistensi mengurangi durasi 50% dari nilai resistensi

          $this->playerStatus[StatusEffect::POISON->value] = [
            'damage' => $damage,
            'duration' => $duration
          ];
          $this->log[] = sprintf(
            "[%.1fs] %s terkena racun selama %d detik (damage %d)!%s",
            $this->simulationTime,
            $this->playerName,
            $duration,
            $damage,
            $resistance > 0 ? " (dikurangi " . round($resistance * 100) . "%)" : ""
          );
        }
      }

      if ($ability['type'] === SkillType::LIFESTEAL) {
        $baseHeal = (int)($damageData['value'] * $ability['value']);
        $break = $this->playerStats['lifesteal_break'] ?? 0;
        $heal = (int) max(1, $baseHeal * (1 - $break));

        // Batasi agar tidak melebihi max_hp
        $newHp = $attackerHp + $heal;
        $maxHp = $this->enemyStats['max_hp'];
        if ($newHp > $maxHp) {
          $heal = $maxHp - $attackerHp;
          $newHp = $maxHp;
        }
        $attackerHp = $newHp;

        $this->log[] = sprintf(
          "[%.1fs] %s mencuri nyawa +%d HP!%s",
          $this->simulationTime,
          $this->enemyName,
          $heal,
          $break > 0 ? " (dikurangi " . round($break * 100) . "%)" : ""
        );
      }

      if ($ability['type'] === SkillType::STUN) {
        if (mt_rand(1, 100) <= $ability['chance'] * 100) {
          $baseDuration = $ability['duration'];
          $resistance = $this->playerStats['stun_resistance'] ?? 0;
          $duration = (int) max(1, $baseDuration * (1 - $resistance));
          $this->playerStatus[StatusEffect::STUN->value] = ['duration' => $duration];
          $this->log[] = sprintf(
            "[%.1fs] %s terkena stun selama %d detik!%s",
            $this->simulationTime,
            $this->playerName,
            $duration,
            $resistance > 0 ? " (dikurangi " . round($resistance * 100) . "%)" : ""
          );
        }
      }
    }
  }

  protected function isStunned(string $side): bool
  {
    $statusArray = $side . 'Status';
    return isset($this->{$statusArray}[StatusEffect::STUN->value]);
  }

  public function getLog(): array
  {
    return $this->log;
  }

  public function getWinner(): string
  {
    return $this->winner;
  }
}