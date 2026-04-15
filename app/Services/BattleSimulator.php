<?php

namespace Modules\BattleGame\Services;

class BattleSimulator
{
  protected array $player;
  protected array $enemy;
  protected array $log = [];
  protected string $winner = '';
  protected float $simulationTime = 0.0;

  /**
  * @param array $player  Statistik pemain, harus memiliki keys:
  *                       name, hp, atk, def, aspd, block_chance, block_reduction
  * @param array $enemy   Statistik musuh (keys sama)
  */
  public function __construct(array $player, array $enemy) {
    $this->player = $player;
    $this->enemy = $enemy;
  }

  /**
  * Jalankan simulasi pertarungan.
  *
  * @return array Hasil pertarungan berisi winner, log, hp sisa, durasi, dll.
  */
  public function runSimulation(): array
  {
    // Salin HP agar tidak mengubah data asli
    $pHp = (float) $this->player['hp'];
    $eHp = (float) $this->enemy['hp'];

    $pTimer = 0.0;
    $eTimer = 0.0;
    $delta = 0.1; // interval update 0.1 detik

    $this->log = [];
    $this->log[] = sprintf(
      "Pertarungan dimulai! %s (HP: %.1f) vs %s (HP: %.1f)",
      $this->player['name'],
      $pHp,
      $this->enemy['name'],
      $eHp
    );
    $this->simulationTime = 0.0;

    while ($pHp > 0 && $eHp > 0) {
      $this->simulationTime += $delta;
      $pTimer += $delta;
      $eTimer += $delta;

      // Giliran Player menyerang
      if ($pTimer >= $this->player['aspd']) {
        $pTimer = 0.0;
        $damageData = $this->calculateDamage($this->player, $this->enemy);
        $eHp -= $damageData['value'];
        $this->log[] = sprintf(
          "[%.1fs] %s menyerang! %s HP %s tersisa %.1f",
          $this->simulationTime,
          $this->player['name'],
          $damageData['message'],
          $this->enemy['name'],
          max(0, $eHp)
        );
        if ($eHp <= 0) {
          break;
        }
      }

      // Giliran Enemy menyerang
      if ($eTimer >= $this->enemy['aspd']) {
        $eTimer = 0.0;
        $damageData = $this->calculateDamage($this->enemy, $this->player);
        $pHp -= $damageData['value'];
        $this->log[] = sprintf(
          "[%.1fs] %s menyerang! %s HP %s tersisa %.1f",
          $this->simulationTime,
          $this->enemy['name'],
          $damageData['message'],
          $this->player['name'],
          max(0, $pHp)
        );
        if ($pHp <= 0) {
          break;
        }
      }
    }

    $this->winner = $pHp > 0 ? $this->player['name'] : $this->enemy['name'];
    $this->log[] = sprintf(
      "Pertarungan selesai! Pemenang: %s",
      $this->winner
    );

    return [
      'winner' => $this->winner,
      'player_name' => $this->player['name'],
      'enemy_name' => $this->enemy['name'],
      'log' => $this->log,
      'player_hp_remaining' => max(0, $pHp),
      'enemy_hp_remaining' => max(0, $eHp),
      'duration' => round($this->simulationTime, 1),
    ];
  }

  /**
  * Hitung damage yang diberikan penyerang ke defender.
  *
  * @param array $attacker
  * @param array $defender
  * @return array ['value' => int, 'blocked' => bool, 'message' => string]
  */
  protected function calculateDamage(array $attacker, array $defender): array
  {
    $rawDamage = $attacker['atk'] - $defender['def'];
    $blocked = false;
    $blockMessage = '';

    // Cek block chance (nilai antara 0 - 1)
    $blockChance = $defender['block_chance'] ?? 0.0;
    if (mt_rand(1, 100) <= $blockChance * 100) {
      $blocked = true;
      $blockReduction = $defender['block_reduction'] ?? 0.5;
      $rawDamage = $rawDamage * (1 - $blockReduction);
      $blockMessage = 'Berhasil diblok!';
    }

    // Minimal damage adalah 1
    $finalDamage = (int) max(1, round($rawDamage));

    $message = $blocked
    ? "{$blockMessage} Damage dikurangi menjadi {$finalDamage}"
    : "Damage {$finalDamage}";

    return [
      'value' => $finalDamage,
      'blocked' => $blocked,
      'message' => $message,
    ];
  }

  /**
  * Dapatkan log pertarungan.
  */
  public function getLog(): array
  {
    return $this->log;
  }

  /**
  * Dapatkan nama pemenang.
  */
  public function getWinner(): string
  {
    return $this->winner;
  }
}