<?php

namespace Modules\BattleGame\Services;

use Modules\BattleGame\Models\BattleUserProgress;
use Modules\BattleGame\Models\UserCurrency;
use Modules\Telegram\Models\TelegramUser;

class StoreService
{
  public function getStoreData(TelegramUser $user): array
  {
    $progress = BattleUserProgress::firstOrCreate(['telegram_user_id' => $user->id]);
    $currency = UserCurrency::forUser($user);

    $categories = [
      ['id' => 'hero',
        'name' => 'Hero',
        'icon' => 'person-plus',
        'description' => 'Buka hero baru'],
      ['id' => 'diamond',
        'name' => 'Diamond',
        'icon' => 'gem',
        'description' => 'Tukar gold dengan diamond'],
      ['id' => 'upgrade',
        'name' => 'Upgrade',
        'icon' => 'arrow-up-circle',
        'description' => 'Tingkatkan kemampuan'],
    ];

    return [
      'gold' => $currency->gold,
      'diamond' => $currency->diamond,
      'user_level' => $progress->level,
      'categories' => $categories,
    ];
  }

  public function getDiamondPackages(): array
  {
    return [
      ['id' => 'small',
        'name' => 'Paket Kecil',
        'gold_cost' => 100,
        'diamond' => 10],
      ['id' => 'medium',
        'name' => 'Paket Sedang',
        'gold_cost' => 250,
        'diamond' => 30],
      ['id' => 'large',
        'name' => 'Paket Besar',
        'gold_cost' => 500,
        'diamond' => 70],
      ['id' => 'xl',
        'name' => 'Paket Super',
        'gold_cost' => 1000,
        'diamond' => 150],
    ];
  }

  public function buyDiamond(TelegramUser $user, string $packageId): array
  {
    $packages = [
      'small' => ['gold_cost' => 100,
        'diamond' => 10],
      'medium' => ['gold_cost' => 250,
        'diamond' => 30],
      'large' => ['gold_cost' => 500,
        'diamond' => 70],
      'xl' => ['gold_cost' => 1000,
        'diamond' => 150],
    ];

    if (!isset($packages[$packageId])) {
      throw new \Exception('Paket tidak valid');
    }

    $package = $packages[$packageId];
    $currency = UserCurrency::forUser($user);

    if (!$currency->deductGold($package['gold_cost'])) {
      throw new \Exception('Gold tidak cukup');
    }

    $currency->diamond += $package['diamond'];
    $currency->save();

    return [
      'message' => "Berhasil membeli {$package['diamond']} diamond!",
      'new_gold' => $currency->gold,
      'new_diamond' => $currency->diamond,
    ];
  }

  public function getUpgrades(TelegramUser $user): array
  {
    $progress = BattleUserProgress::firstOrCreate(['telegram_user_id' => $user->id]);

    $upgrades = [
      [
        'id' => 'attack_boost',
        'name' => 'Peningkatan Serangan',
        'description' => 'Meningkatkan ATK sebesar 5 per level',
        'cost_type' => 'gold',
        'base_cost' => 200,
        'cost_scaling' => 1.5,
        'current_level' => $progress->getUpgradeLevel('attack_boost'),
        'max_level' => 10,
        'effect_per_level' => 5,
      ],
      [
        'id' => 'defense_boost',
        'name' => 'Peningkatan Pertahanan',
        'description' => 'Meningkatkan DEF sebesar 3 per level',
        'cost_type' => 'gold',
        'base_cost' => 150,
        'cost_scaling' => 1.5,
        'current_level' => $progress->getUpgradeLevel('defense_boost'),
        'max_level' => 10,
        'effect_per_level' => 3,
      ],
      [
        'id' => 'hp_boost',
        'name' => 'Peningkatan HP',
        'description' => 'Meningkatkan HP sebesar 20 per level',
        'cost_type' => 'diamond',
        'base_cost' => 50,
        'cost_scaling' => 1.3,
        'current_level' => $progress->getUpgradeLevel('hp_boost'),
        'max_level' => 5,
        'effect_per_level' => 20,
      ],
      [
        'id' => 'critical_chance',
        'name' => 'Critical Chance',
        'description' => 'Kesempatan critical hit +2% per level',
        'cost_type' => 'diamond',
        'base_cost' => 30,
        'cost_scaling' => 1.5,
        'current_level' => $progress->getUpgradeLevel('critical_chance'),
        'max_level' => 10,
        'effect_per_level' => 2,
      ],
    ];

    return array_map(function ($upgrade) {
      $nextLevel = $upgrade['current_level'] + 1;
      if ($nextLevel > $upgrade['max_level']) {
        $upgrade['next_cost'] = null;
        $upgrade['can_upgrade'] = false;
      } else {
        $cost = $upgrade['base_cost'] * pow($upgrade['cost_scaling'], $upgrade['current_level']);
        $upgrade['next_cost'] = (int) round($cost);
        $upgrade['can_upgrade'] = true;
      }
      return $upgrade;
    },
      $upgrades);
  }

  public function buyUpgrade(TelegramUser $user,
    string $upgradeId): array
  {
    $progress = BattleUserProgress::firstOrCreate(['telegram_user_id' => $user->id]);
    $currency = UserCurrency::forUser($user);

    $upgradesDef = [
      'attack_boost' => ['cost_type' => 'gold', 'base_cost' => 200, 'cost_scaling' => 1.5, 'max_level' => 10],
      'defense_boost' => ['cost_type' => 'gold', 'base_cost' => 150, 'cost_scaling' => 1.5, 'max_level' => 10],
      'hp_boost' => ['cost_type' => 'diamond', 'base_cost' => 50, 'cost_scaling' => 1.3, 'max_level' => 5],
      'critical_chance' => ['cost_type' => 'diamond', 'base_cost' => 30, 'cost_scaling' => 1.5, 'max_level' => 10],
    ];

    if (!isset($upgradesDef[$upgradeId])) {
      throw new \Exception('Upgrade tidak valid');
    }

    $def = $upgradesDef[$upgradeId];
    $currentLevel = $progress->getUpgradeLevel($upgradeId);

    if ($currentLevel >= $def['max_level']) {
      throw new \Exception('Level maksimum tercapai');
    }

    $cost = (int) round($def['base_cost'] * pow($def['cost_scaling'], $currentLevel));

    if ($def['cost_type'] === 'gold') {
      if ($currency->gold < $cost) {
        throw new \Exception('Gold tidak cukup');
      }
      $currency->deductGold($cost);
    } else {
      if ($currency->diamond < $cost) {
        throw new \Exception('Diamond tidak cukup');
      }
      $currency->diamond -= $cost;
      $currency->save();
    }

    $progress->setUpgradeLevel($upgradeId, $currentLevel + 1);

    return [
      'message' => 'Upgrade berhasil!',
      'new_level' => $currentLevel + 1,
      'new_gold' => $currency->gold,
      'new_diamond' => $currency->diamond,
    ];
  }
}