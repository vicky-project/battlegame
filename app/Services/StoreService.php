<?php

namespace Modules\BattleGame\Services;

use Modules\BattleGame\Enums\CurrencyType;
use Modules\BattleGame\Enums\StoreCategory;
use Modules\BattleGame\Enums\UpgradeId;
use Modules\BattleGame\Models\BattleUserProgress;
use Modules\BattleGame\Models\UserCurrency;
use Modules\Telegram\Models\TelegramUser;

class StoreService
{
  /**
  * Data toko utama
  */
  public function getStoreData(TelegramUser $user): array
  {
    $progress = BattleUserProgress::firstOrCreate(['telegram_user_id' => $user->id]);
    $currency = UserCurrency::forUser($user);

    $categories = array_map(
      fn(StoreCategory $cat) => [
        'id' => $cat->value,
        'name' => $cat->label(),
        'icon' => $cat->icon(),
        'description' => $cat->description(),
        'color' => $car->color(),
      ],
      StoreCategory::cases()
    );

    return [
      'gold' => $currency->gold,
      'diamond' => $currency->diamond,
      'user_level' => $progress->level,
      'categories' => $categories,
    ];
  }

  /**
  * Paket diamond (harga mahal)
  */
  public function getDiamondPackages(): array
  {
    return config('battlegame.diamond_packages', []);
  }

  /**
  * Membeli diamond.
  */
  public function buyDiamond(TelegramUser $user, string $packageId): array
  {
    $packages = collect(config('battlegame.diamond_packages', []));
    $package = $packages->firstWhere('id', $packageId);

    if (!$package) {
      throw new \Exception('Paket tidak valid');
    }

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

  /**
  * Daftar upgrade (beberapa menggunakan diamond)
  */
  public function getUpgrades(TelegramUser $user): array
  {
    $progress = BattleUserProgress::firstOrCreate(['telegram_user_id' => $user->id]);

    $upgrades = [];
    foreach (UpgradeId::cases() as $upgradeId) {
      $currentLevel = $progress->getUpgradeLevel($upgradeId->value);
      if ($currentLevel < 1) {
        $currentLevel = 1;
        $progress->setUpgradeLevel($upgradeId->value, 1);
      }

      $nextLevel = $currentLevel + 1;
      $maxLevel = $upgradeId->maxLevel();

      $nextCost = null;
      $canUpgrade = false;

      if ($nextLevel <= $maxLevel) {
        $cost = $upgradeId->baseCost() * pow($upgradeId->costScaling(), $currentLevel - 1);
        $nextCost = (int) round($cost);
        $canUpgrade = true;
      }

      $upgrades[] = [
        'id' => $upgradeId->value,
        'name' => $upgradeId->label(),
        'description' => $upgradeId->description(),
        'cost_type' => $upgradeId->costType()->value,
        'base_cost' => $upgradeId->baseCost(),
        'cost_scaling' => $upgradeId->costScaling(),
        'current_level' => $currentLevel,
        'max_level' => $maxLevel,
        'effect_per_level' => $upgradeId->effectPerLevel(),
        'next_cost' => $nextCost,
        'can_upgrade' => $canUpgrade,
      ];
    }

    return $upgrades;
  }

  /**
  * Beli upgrade (menggunakan gold atau diamond)
  */
  public function buyUpgrade(TelegramUser $user, string $upgradeId): array
  {
    $upgrade = UpgradeId::tryFrom($upgradeId);
    if (!$upgrade) {
      throw new \Exception('Upgrade tidak valid');
    }

    $progress = BattleUserProgress::firstOrCreate(['telegram_user_id' => $user->id]);
    $currency = UserCurrency::forUser($user);
    $currentLevel = $progress->getUpgradeLevel($upgrade->value);

    if ($currentLevel >= $upgrade->maxLevel()) {
      throw new \Exception('Level maksimum tercapai');
    }

    $cost = (int) round($upgrade->baseCost() * pow($upgrade->costScaling(), $currentLevel - 1));

    if ($upgrade->costType() === CurrencyType::GOLD) {
      if (!$currency->deductGold($cost)) {
        throw new \Exception('Gold tidak cukup');
      }
    } else {
      if ($currency->diamond < $cost) {
        throw new \Exception('Diamond tidak cukup');
      }
      $currency->diamond -= $cost;
      $currency->save();
    }

    $progress->setUpgradeLevel($upgrade->value, $currentLevel + 1);

    return [
      'message' => 'Upgrade berhasil!',
      'new_level' => $currentLevel + 1,
      'new_gold' => $currency->gold,
      'new_diamond' => $currency->diamond,
    ];
  }
}