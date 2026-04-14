<?php

namespace Modules\BattleGame\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\BattleGame\Models\BattleEnemy;
use Modules\BattleGame\Models\BattleHistory;
use Modules\BattleGame\Models\BattleUserHero;
use Modules\BattleGame\Models\BattleUserProgress;
use Modules\BattleGame\Models\UserCurrency;
use Modules\BattleGame\Services\BattleSimulator;
use Modules\Telegram\Models\TelegramUser;

class BattleController extends Controller
{
  public function getUserData(Request $request) {
    /** @var TelegramUser $user */
    $user = $request->user();
    $progress = BattleUserProgress::firstOrCreate(
      ['telegram_user_id' => $user->id],
      ['level' => 1, 'exp' => 0]
    );
    $currency = UserCurrency::forUser($user);

    $heroes = BattleUserHero::with('hero')
    ->where('telegram_user_id', $user->id)
    ->get()
    ->map(function ($userHero) {
      return [
        'id' => $userHero->id,
        'hero_id' => $userHero->hero->id,
        'name' => $userHero->hero->name,
        'level' => $userHero->level,
        'exp' => $userHero->exp,
        'stats' => $userHero->calculated_stats,
        'is_selected' => $userHero->is_selected,
      ];
    });

    return response()->json([
      'success' => true,
      'data' => [
        'user_level' => $progress->level,
        'user_exp' => $progress->exp,
        'exp_to_next_level' => $progress->getExpForNextLevel(),
        'gold' => $currency->gold,
        'diamond' => $currency->diamond,
        'heroes' => $heroes,
        'selected_hero_id' => $heroes->firstWhere('is_selected', true)['id'] ?? null,
      ]
    ]);
  }

  public function startBattleVsComputer(Request $request) {
    $user = $request->user();
    $heroId = $request->input('user_hero_id');
    $enemyLevel = $request->input('enemy_level'); // opsional

    $userHero = BattleUserHero::where('telegram_user_id', $user->id)
    ->where('id', $heroId)
    ->with('hero')
    ->firstOrFail();

    $progress = BattleUserProgress::firstOrCreate(['telegram_user_id' => $user->id]);
    $targetLevel = $enemyLevel ?? $progress->level;

    // Cari musuh yang sesuai level
    $enemy = BattleEnemy::where('min_level', '<=', $targetLevel)
    ->where(function($q) use ($targetLevel) {
      $q->whereNull('max_level')->orWhere('max_level', '>=', $targetLevel);
    })
    ->where('is_active', true)
    ->inRandomOrder()
    ->first();

    if (!$enemy) {
      $enemy = BattleEnemy::where('is_active', true)->first();
    }
    if (!$enemy) {
      return response()->json(['success' => false, 'message' => 'Tidak ada musuh'], 500);
    }

    $playerStats = $userHero->calculated_stats;
    $enemyStats = $enemy->getStatsForLevel($targetLevel);

    $simulator = new BattleSimulator(
      array_merge(['name' => $userHero->hero->name], $playerStats),
      array_merge(['name' => $enemy->name], $enemyStats)
    );
    $result = $simulator->runSimulation();

    $history = BattleHistory::create([
      'telegram_user_id' => $user->id,
      'battle_user_hero_id' => $userHero->id,
      'battle_enemy_id' => $enemy->id,
      'battle_type' => 'vs_computer',
      'result' => $result['winner'] === $userHero->hero->name ? 'win' : 'lose',
      'battle_log' => $result['log'],
      'player_hp_remaining' => $result['player_hp_remaining'],
      'enemy_hp_remaining' => $result['enemy_hp_remaining'],
      'duration' => $result['duration'],
    ]);

    // Update progress dan rewards
    $progress->total_battles++;
    $rewards = $enemy->rewards ?? ['exp' => 20,
      'gold' => 5];
    $currency = UserCurrency::forUser($user);

    if ($history->result === 'win') {
      $progress->total_wins++;
      $expGained = $rewards['exp'];
      $goldGained = $rewards['gold'];
      $progress->addExp($expGained);
      $history->exp_gained = $expGained;
      $currency->addGold($goldGained);

      // Hero level up
      $userHero->exp += $expGained;
      while ($userHero->exp >= $this->getHeroExpForNextLevel($userHero->level)) {
        $userHero->exp -= $this->getHeroExpForNextLevel($userHero->level);
        $userHero->level++;
      }
      $userHero->save();
    } else {
      $progress->total_losses++;
      $expGained = max(5, (int)($rewards['exp'] * 0.2));
      $goldGained = max(1, (int)($rewards['gold'] * 0.2));
      $progress->addExp($expGained);
      $history->exp_gained = $expGained;
      $currency->addGold($goldGained);
    }

    $progress->save();
    $history->save();

    return response()->json([
      'success' => true,
      'data' => [
        'result' => $result,
        'history_id' => $history->id,
        'exp_gained' => $history->exp_gained,
        'gold_gained' => $goldGained ?? 0,
        'user_new_level' => $progress->level,
        'user_new_exp' => $progress->exp,
      ]
    ]);
  }

  protected function getHeroExpForNextLevel($currentLevel) {
    return 100 * $currentLevel;
  }

  // Endpoint untuk mendapatkan history detail (opsional)
  public function getBattleHistory(Request $request, $id) {
    $history = BattleHistory::where('telegram_user_id', $request->user()->id)
    ->findOrFail($id);
    return response()->json(['success' => true, 'data' => $history]);
  }

  public function setSelectedHero(Request $request) {
    $user = $request->user();
    $heroId = $request->input('user_hero_id');

    BattleUserHero::where('telegram_user_id', $user->id)
    ->update(['is_selected' => false]);

    BattleUserHero::where('telegram_user_id', $user->id)
    ->where('id', $heroId)
    ->update(['is_selected' => true]);

    return response()->json(['success' => true]);
  }

  public function unlockHero(Request $request) {
    $user = $request->user();
    $heroId = $request->input('hero_id');

    $hero = BattleHero::findOrFail($heroId);
    $progress = BattleUserProgress::where('telegram_user_id', $user->id)->first();
    if (!$progress) {
      return response()->json(['success' => false, 'message' => 'Progress tidak ditemukan'], 400);
    }

    // Cek apakah sudah punya
    $alreadyOwned = BattleUserHero::where('telegram_user_id', $user->id)
    ->where('battle_hero_id', $heroId)->exists();
    if ($alreadyOwned) {
      return response()->json(['success' => false, 'message' => 'Hero sudah dimiliki'], 400);
    }

    // Cek syarat level
    if ($progress->level < $hero->required_user_level) {
      return response()->json(['success' => false, 'message' => 'Level user belum mencukupi'], 400);
    }

    // Cek gold
    $currency = UserCurrency::forUser($user);
    if (!$currency->deductGold($hero->unlock_cost_gold)) {
      return response()->json(['success' => false, 'message' => 'Gold tidak cukup'], 400);
    }

    // Buat user hero
    BattleUserHero::create([
      'telegram_user_id' => $user->id,
      'battle_hero_id' => $hero->id,
      'level' => 1,
      'exp' => 0,
      'is_selected' => false,
    ]);

    return response()->json(['success' => true, 'message' => 'Hero berhasil di-unlock!']);
  }

  /**
  * Mendapatkan data untuk halaman store utama.
  */
  public function getStoreData(Request $request) {
    $user = $request->user();
    $progress = BattleUserProgress::firstOrCreate(['telegram_user_id' => $user->id]);
    $currency = UserCurrency::forUser($user);

    // Kategori yang tersedia
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

    return response()->json([
      'success' => true,
      'data' => [
        'gold' => $currency->gold,
        'diamond' => $currency->diamond,
        'user_level' => $progress->level,
        'categories' => $categories,
      ]
    ]);
  }

  /**
  * Mendapatkan daftar hero untuk store (semua hero).
  */
  public function getStoreHeroes(Request $request) {
    $user = $request->user();
    $progress = BattleUserProgress::firstOrCreate(['telegram_user_id' => $user->id]);
    $currency = UserCurrency::forUser($user);

    $allHeroes = BattleHero::where('is_active', true)->get();
    $ownedHeroIds = BattleUserHero::where('telegram_user_id', $user->id)
    ->pluck('battle_hero_id')->toArray();

    $heroes = $allHeroes->map(function($hero) use ($progress, $currency, $ownedHeroIds) {
      $owned = in_array($hero->id, $ownedHeroIds);
      $requirements = $hero->unlock_requirements ?? [];
      $requiredLevel = $requirements['required_user_level'] ?? 1;
      $costGold = $requirements['unlock_cost_gold'] ?? 0;

      $canBuy = !$owned && $progress->level >= $requiredLevel && $currency->gold >= $costGold;

      return [
        'id' => $hero->id,
        'name' => $hero->name,
        'type' => $hero->type,
        'description' => $hero->description,
        'stats' => $hero->stats,
        'required_level' => $requiredLevel,
        'cost_gold' => $costGold,
        'owned' => $owned,
        'can_buy' => $canBuy,
      ];
    });

    return response()->json(['success' => true, 'data' => $heroes]);
  }

  /**
  * Membeli hero.
  */
  public function buyHero(Request $request) {
    $user = $request->user();
    $heroId = $request->input('hero_id');

    $hero = BattleHero::findOrFail($heroId);
    $progress = BattleUserProgress::where('telegram_user_id', $user->id)->first();
    if (!$progress) {
      return response()->json(['success' => false, 'message' => 'Progress tidak ditemukan'], 400);
    }

    // Cek sudah punya?
    if (BattleUserHero::where('telegram_user_id', $user->id)->where('battle_hero_id', $heroId)->exists()) {
      return response()->json(['success' => false, 'message' => 'Hero sudah dimiliki'], 400);
    }

    $requirements = $hero->unlock_requirements ?? [];
    $requiredLevel = $requirements['required_user_level'] ?? 1;
    $costGold = $requirements['unlock_cost_gold'] ?? 0;

    if ($progress->level < $requiredLevel) {
      return response()->json(['success' => false, 'message' => 'Level tidak mencukupi'], 400);
    }

    $currency = UserCurrency::forUser($user);
    if (!$currency->deductGold($costGold)) {
      return response()->json(['success' => false, 'message' => 'Gold tidak cukup'], 400);
    }

    BattleUserHero::create([
      'telegram_user_id' => $user->id,
      'battle_hero_id' => $hero->id,
      'level' => 1,
      'exp' => 0,
      'is_selected' => false,
    ]);

    return response()->json(['success' => true, 'message' => 'Hero berhasil dibeli!']);
  }

  /**
  * Mendapatkan daftar paket diamond.
  */
  public function getDiamondPackages() {
    // Bisa hardcode atau dari config
    $packages = [
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
    return response()->json(['success' => true, 'data' => $packages]);
  }

  /**
  * Membeli diamond.
  */
  public function buyDiamond(Request $request) {
    $user = $request->user();
    $packageId = $request->input('package_id');

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
      return response()->json(['success' => false, 'message' => 'Paket tidak valid'], 400);
    }

    $package = $packages[$packageId];
    $currency = UserCurrency::forUser($user);

    if (!$currency->deductGold($package['gold_cost'])) {
      return response()->json(['success' => false, 'message' => 'Gold tidak cukup'], 400);
    }

    $currency->diamond += $package['diamond'];
    $currency->save();

    return response()->json([
      'success' => true,
      'message' => "Berhasil membeli {$package['diamond']} diamond!",
      'new_gold' => $currency->gold,
      'new_diamond' => $currency->diamond,
    ]);
  }

  /**
  * Mendapatkan daftar upgrade yang tersedia.
  */
  public function getUpgrades(Request $request) {
    $user = $request->user();
    $progress = BattleUserProgress::firstOrCreate(['telegram_user_id' => $user->id]);

    $upgrades = [
      [
        'id' => 'attack_boost',
        'name' => 'Peningkatan Serangan',
        'description' => 'Meningkatkan ATK sebesar 5 per level',
        'cost_type' => 'gold',
        'base_cost' => 200,
        'cost_scaling' => 1.5,
        // biaya level berikutnya *1.5
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

    // Hitung biaya next level
    $upgrades = array_map(function($upgrade) {
      $nextLevel = $upgrade['current_level'] + 1;
      if ($nextLevel > $upgrade['max_level']) {
        $upgrade['next_cost'] = null;
        $upgrade['can_upgrade'] = false;
      } else {
        $cost = $upgrade['base_cost'] * pow($upgrade['cost_scaling'], $upgrade['current_level']);
        $upgrade['next_cost'] = (int) round($cost);
        $upgrade['can_upgrade'] = true; // akan dicek di frontend berdasarkan currency
      }
      return $upgrade;
    },
      $upgrades);

    return response()->json(['success' => true,
      'data' => $upgrades]);
  }

  /**
  * Membeli upgrade.
  */
  public function buyUpgrade(Request $request) {
    $user = $request->user();
    $upgradeId = $request->input('upgrade_id');

    $progress = BattleUserProgress::firstOrCreate(['telegram_user_id' => $user->id]);
    $currency = UserCurrency::forUser($user);

    // Definisikan upgrade yang tersedia (sama seperti di getUpgrades)
    $upgradesDef = [
      'attack_boost' => ['cost_type' => 'gold', 'base_cost' => 200, 'cost_scaling' => 1.5, 'max_level' => 10],
      'defense_boost' => ['cost_type' => 'gold', 'base_cost' => 150, 'cost_scaling' => 1.5, 'max_level' => 10],
      'hp_boost' => ['cost_type' => 'diamond', 'base_cost' => 50, 'cost_scaling' => 1.3, 'max_level' => 5],
      'critical_chance' => ['cost_type' => 'diamond', 'base_cost' => 30, 'cost_scaling' => 1.5, 'max_level' => 10],
    ];

    if (!isset($upgradesDef[$upgradeId])) {
      return response()->json(['success' => false, 'message' => 'Upgrade tidak valid'], 400);
    }

    $def = $upgradesDef[$upgradeId];
    $currentLevel = $progress->getUpgradeLevel($upgradeId);

    if ($currentLevel >= $def['max_level']) {
      return response()->json(['success' => false, 'message' => 'Level maksimum tercapai'], 400);
    }

    $cost = (int) round($def['base_cost'] * pow($def['cost_scaling'], $currentLevel));

    // Cek kecukupan currency
    if ($def['cost_type'] === 'gold') {
      if ($currency->gold < $cost) {
        return response()->json(['success' => false, 'message' => 'Gold tidak cukup'], 400);
      }
      $currency->deductGold($cost);
    } else {
      // diamond
      if ($currency->diamond < $cost) {
        return response()->json(['success' => false, 'message' => 'Diamond tidak cukup'], 400);
      }
      $currency->diamond -= $cost;
      $currency->save();
    }

    // Naikkan level upgrade
    $progress->setUpgradeLevel($upgradeId, $currentLevel + 1);

    return response()->json([
      'success' => true,
      'message' => 'Upgrade berhasil!',
      'new_level' => $currentLevel + 1,
      'new_gold' => $currency->gold,
      'new_diamond' => $currency->diamond,
    ]);
  }
}