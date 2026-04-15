<?php

namespace Modules\BattleGame\Services;

use Modules\BattleGame\Characters\Base\Hero;
use Modules\BattleGame\Characters\Base\Enemy;

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

  // Konstanta untuk randomness
  protected const DAMAGE_VARIANCE = 0.15; // ±15% variasi damage
  protected const CRIT_CHANCE = 0.10; // 10% critical chance
  protected const CRIT_MULTIPLIER = 1.8; // 180% damage saat critical
  protected const MISS_CHANCE = 0.05; // 5% kemungkinan meleset

  public function __construct(
    protected Hero $playerHero,
    protected Enemy $enemy,
    protected int $enemyLevel
  ) {
    $this->playerName = $playerHero->name;
    $this->enemyName = $enemy->name;
    $this->playerEmoji = $playerHero->emoji;
    $this->enemyEmoji = $enemy->emoji;
    $this->playerStats = $this->buildPlayerStats();
    $this->enemyStats = $this->buildEnemyStats();
  }

  protected function buildPlayerStats(): array
  {
    return [
      'hp' => $this->playerHero->baseHp(),
      'atk' => $this->playerHero->baseAtk(),
      'def' => $this->playerHero->baseDef(),
      'aspd' => $this->playerHero->baseAspd(),
      'block_chance' => $this->playerHero->baseBlockChance(),
      'block_reduction' => $this->playerHero->baseBlockReduction(),
    ];
  }

  protected function buildEnemyStats(): array
  {
    $factor = 1 + $this->enemy->levelScalingFactor() * ($this->enemyLevel - 1);
    return [
      'hp' => round($this->enemy->baseHp() * $factor),
      'atk' => round($this->enemy->baseAtk() * $factor),
      'def' => round($this->enemy->baseDef() * $factor),
      'aspd' => $this->enemy->baseAspd(),
      'block_chance' => min($this->enemy->baseBlockChance() + 0.01 * ($this->enemyLevel - 1), 0.7),
      'block_reduction' => $this->enemy->baseBlockReduction(),
    ];
  }

  public function runSimulation(): array
  {
    $pHp = (float) $this->playerStats['hp'];
    $eHp = (float) $this->enemyStats['hp'];

    $pTimer = 0.0;
    $eTimer = 0.0;
    $delta = 0.1;

    $this->log = [];
    $this->log[] = sprintf(
      "Pertarungan dimulai! %s %s (HP: %.1f) vs %s %s (HP: %.1f)",
      $this->playerEmoji,
      $this->playerName,
      $pHp,
      $this->enemyEmoji,
      $this->enemyName,
      $eHp
    );
    $this->simulationTime = 0.0;

    while ($pHp > 0 && $eHp > 0) {
      $this->simulationTime += $delta;
      $pTimer += $delta;
      $eTimer += $delta;

      if ($pTimer >= $this->playerStats['aspd']) {
        $pTimer = 0.0;
        $damage = $this->calculateDamage($this->playerStats, $this->enemyStats, true);
        $eHp -= $damage['value'];
        $this->log[] = sprintf(
          "[%.1fs] %s menyerang! %s HP %s tersisa %.1f",
          $this->simulationTime,
          $this->playerName,
          $damage['message'],
          $this->enemyName,
          max(0, $eHp)
        );
        if ($eHp <= 0) break;
      }

      if ($eTimer >= $this->enemyStats['aspd']) {
        $eTimer = 0.0;
        $damage = $this->calculateDamage($this->enemyStats, $this->playerStats, false);
        $pHp -= $damage['value'];
        $this->log[] = sprintf(
          "[%.1fs] %s menyerang! %s HP %s tersisa %.1f",
          $this->simulationTime,
          $this->enemyName,
          $damage['message'],
          $this->playerName,
          max(0, $pHp)
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

  protected function calculateDamage(array $attacker, array $defender, bool $isPlayerAttacking): array
  {
    // 1. Cek miss (hanya untuk serangan biasa, tidak bisa di-miss jika critical nanti)
    if (mt_rand(1, 100) <= self::MISS_CHANCE * 100) {
      return [
        'value' => 0,
        'blocked' => false,
        'critical' => false,
        'miss' => true,
        'message' => 'Meleset! 0 damage',
      ];
    }

    // 2. Hitung raw damage (ATK - DEF)
    $rawDamage = $attacker['atk'] - $defender['def'];

    // 3. Cek critical hit (sebelum block)
    $isCritical = false;
    if (mt_rand(1, 100) <= self::CRIT_CHANCE * 100) {
      $isCritical = true;
      $rawDamage = $rawDamage * self::CRIT_MULTIPLIER;
    }

    // 4. Variasi damage ±15% (setelah critical)
    $variance = 1 + (mt_rand(-100, 100) / 100) * self::DAMAGE_VARIANCE;
    $rawDamage = $rawDamage * $variance;

    // 5. Cek block
    $blocked = false;
    $blockMessage = '';
    $blockChance = $defender['block_chance'] ?? 0.0;
    if (mt_rand(1, 100) <= $blockChance * 100) {
      $blocked = true;
      $blockReduction = $defender['block_reduction'] ?? 0.5;
      $rawDamage = $rawDamage * (1 - $blockReduction);
      $blockMessage = 'Berhasil diblok!';
    }

    // 6. Final damage (minimal 1 jika tidak miss)
    $finalDamage = (int) max(1, round($rawDamage));

    // 7. Buat pesan
    $messageParts = [];
    if ($isCritical) $messageParts[] = '💥 Critical!';
    if ($blocked) $messageParts[] = $blockMessage;
    $messageParts[] = "Damage {$finalDamage}";

    $message = implode(' ', $messageParts);

    return [
      'value' => $finalDamage,
      'blocked' => $blocked,
      'critical' => $isCritical,
      'miss' => false,
      'message' => $message,
    ];
  }

  public function getLog(): array {
    return $this->log;
  }
  public function getWinner(): string {
    return $this->winner;
  }
}