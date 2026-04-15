@extends('telegram::layouts.mini-app')

@section('title', 'Battle Arena')

@section('content')
<div id="battle-app" class="container py-0">
  {{-- Loading indicator --}}
  <div id="loading-screen" class="text-center py-5">
    <div class="spinner-border text-primary" role="status"></div>
    <p class="mt-2">
      Memuat data...
    </p>
  </div>

  {{-- Main content akan dirender oleh JS --}}
  <div id="app-content" style="display: none;"></div>
</div>
@endsection

@push('scripts')
<script>
  (function() {
  const tg = window.TelegramApp;
  const appEl = document.getElementById('app-content');
  const loadingEl = document.getElementById('loading-screen');

  // State
  let state = {
  user: null,
  userHeroes: [],
  selectedHeroId: null,
  currentScreen: 'home', // 'home', 'select-hero', 'battle', 'result', 'store', 'store-hero', 'store-diamond', 'store-upgrade'
  battleResult: null,
  gold: null,
  storeData: null,
  storeHeroes: [],
  diamondPackages: [],
  upgrades: []
  };

  // API base
  const API_BASE = '{{ config("app.url") }}/api/battle';

  // Helper fetch dengan token
  async function apiFetch(endpoint, options = {}) {
  try {
  return await tg.fetchWithAuth(API_BASE + endpoint, options);
  } catch (error) {
  tg.showToast('Gagal terhubung ke server: ' + error.message, 'danger');
  throw error;
  }
  }

  // Load data user
  async function loadUserData() {
  const response = await apiFetch('/user');
  if (response.success) {
  state.user = response.data;
  state.userHeroes = response.data.heroes;
  state.selectedHeroId = response.data.selected_hero_id;
  state.gold = response.data.gold;
  if (!state.storeData) state.storeData = {};
  state.storeData.gold = response.data.gold;
  state.storeData.diamond = response.data.diamond;
  renderHomeScreen();
  }
  }

  // Render fungsi
  function renderHomeScreen() {
  state.currentScreen = 'home';
  const html = `
  <div class="d-flex align-items-center mb-4">
  <i class="bi bi-controller me-2 fs-3"></i>
  <h1 class="h3 mb-0">Battle Arena</h1>
  </div>
  <div class="card shadow mb-3">
  <div class="card-body">
  <h5 class="card-title">Level ${state.user.user_level}</h5>
  <div class="progress mb-2" style="height: 20px;">
  <div class="progress-bar" role="progressbar"
  style="width: ${(state.user.user_exp / state.user.exp_to_next_level * 100)}%">
  ${state.user.user_exp}/${state.user.exp_to_next_level} EXP
  </div>
  </div>
  <p class="card-text">Total Battle: ${state.user.total_battles || 0} | Menang: ${state.user.total_wins || 0}</p>
  <p><i class="bi bi-coin"></i> Gold: ${state.gold}</p>
  </div>
  </div>
  <div class="d-grid gap-2">
  <button class="btn btn-primary btn-lg" id="btn-start-battle">
  <i class="bi bi-play-fill"></i> Mulai Bertarung
  </button>
  <button class="btn btn-outline-secondary" id="btn-select-hero">
  <i class="bi bi-person-lines-fill"></i> Pilih Hero
  </button>
  <button class="btn btn-outline-info" ud="btn-store"><i class="bi bi-shop"></i> Toko</button>
  </div>
  `;
  appEl.innerHTML = html;
  appEl.style.display = 'block';
  loadingEl.style.display = 'none';

  document.getElementById('btn-start-battle')?.addEventListener('click', () => {
  if (state.selectedHeroId) {
  startBattleVsComputer(state.selectedHeroId);
  } else {
  tg.showToast('Pilih hero terlebih dahulu', 'danger');
  renderSelectHeroScreen();
  }
  });
  document.getElementById('btn-select-hero')?.addEventListener('click', renderSelectHeroScreen);
  document.getElementById('btn-store')?.addEventListener('click', renderStoreHome);
  }

  async function renderSelectHeroScreen() {
  state.currentScreen = 'select-hero';
  await loadUserData();

  if (state.userHeroes.length === 0) {
  // Tidak punya hero sama sekali
  appEl.innerHTML = `
  <div class="text-center py-5">
  <i class="bi bi-emoji-frown fs-1"></i>
  <h4>Kamu belum memiliki hero</h4>
  <p>Kunjungi Toko untuk membeli hero pertama mu!</p>
  <button class="btn btn-primary" id="btn-goto-store">Ke Toko Hero</button>
  <button class="btn btn-link" id="btn-back-home">Kembali</button>
  </div>
  `;
  document.getElementById('btn-goto-store')?.addEventListener('click', renderStoreHero);
  document.getElementById('btn-back-home')?.addEventListener('click', renderHomeScreen);
  return;
  }

  let heroesHtml = '';
  state.userHeroes.forEach(hero => {
  const isSelected = hero.id === state.selectedHeroId;
  heroesHtml += `
  <div class="col-12 mb-3">
  <div class="card ${isSelected ? 'border-primary' : ''}">
  <div class="card-body">
  <div class="d-flex justify-content-between align-items-center">
  <h5 class="card-title">${hero.name} Lv.${hero.level}</h5>
  ${isSelected ? '<span class="badge bg-primary">Dipilih</span>' : ''}
  </div>
  <div class="row small">
  <div class="col-6">❤️ HP: ${hero.stats.hp}</div>
  <div class="col-6">⚔️ ATK: ${hero.stats.atk}</div>
  <div class="col-6">🛡️ DEF: ${hero.stats.def}</div>
  <div class="col-6">⏱️ Speed: ${hero.stats.aspd}s</div>
  </div>
  <button class="btn btn-sm ${isSelected ? 'btn-success' : 'btn-outline-primary'} w-100 mt-2 select-hero-btn"
  data-hero-id="${hero.id}">
  ${isSelected ? 'Terpilih' : 'Pilih Hero Ini'}
  </button>
  </div>
  </div>
  </div>
  `;
  });

  const html = `
  <div class="d-flex align-items-center mb-3">
  <button class="btn btn-link text-decoration-none p-0 me-2" id="btn-back-home">
  <i class="bi bi-arrow-left fs-5"></i>
  </button>
  <h2 class="h4 mb-0">Pilih Hero</h2>
  </div>
  <div class="row">
  ${heroesHtml}
  </div>
  <div class="d-grid mt-3">
  <button class="btn btn-primary" id="btn-done-select">Selesai</button>
  </div>
  `;
  // ...
  appEl.innerHTML = html;
  appEl.style.display = "block";
  loadingEl.style.display = "none";

  document.querySelectorAll('.select-hero-btn').forEach(btn => {
  btn.addEventListener('click', async (e) => {
  const heroId = e.target.dataset.heroId;
  tg.showLoading();
  try {
  const resp = await apiFetch("/select-hero", {
  method: "POST",
  body: JSON.stringify({ user_hero_id: heroId })
  });

  if(resp.success) {
  state.selectedHeroId = parseInt(heroId);
  tg.showToast(resp.message || 'Hero dipilih', 'success');
  await renderSelectHeroScreen(); // refresh
  } else {
  tg.showToast(resp.message, 'danger');
  }
  } catch(error) {
  tg.showToast("Gagal memilih hero", 'danger');
  } finally {
  tg.hideLoading();
  }
  });
  });

  document.getElementById('btn-back-home')?.addEventListener('click', renderHomeScreen);
  document.getElementById('btn-done-select')?.addEventListener('click', renderHomeScreen);
  }

  async function renderStoreHome() {
  state.currentScreen = 'store';
  tg.showLoading();
  try {
  const resp = await apiFetch('/store');
  if (resp.success) {
  state.storeData = resp.data;
  const data = resp.data;
  let html = `
  <div class="d-flex align-items-center mb-3">
  <button class="btn btn-link text-decoration-none p-0 me-2" id="btn-back-home">
  <i class="bi bi-arrow-left fs-5"></i>
  </button>
  <h2 class="h4 mb-0">Toko</h2>
  </div>
  <div class="card mb-3">
  <div class="card-body">
  <div class="d-flex justify-content-between">
  <span><i class="bi bi-coin"></i> Gold: ${data.gold}</span>
  <span><i class="bi bi-gem"></i> Diamond: ${data.diamond}</span>
  </div>
  </div>
  </div>
  <div class="row g-3">
  `;
  data.categories.forEach(cat => {
  html += `
  <div class="col-6">
  <div class="card h-100 store-category-card" data-category="${cat.id}">
  <div class="card-body text-center">
  <i class="bi bi-${cat.icon} fs-1"></i>
  <h5 class="card-title">${cat.name}</h5>
  <p class="card-text small">${cat.description}</p>
  </div>
  </div>
  </div>
  `;
  });
  html += `</div>`;
  appEl.innerHTML = html;
  }
  } finally {
  tg.hideLoading();
  appEl.style.display = 'block';
  loadingEl.style.display = 'none';
  }

  // Event listener untuk kartu kategori
  document.querySelectorAll('.store-category-card').forEach(card => {
  card.addEventListener('click', (e) => {
  const cat = card.dataset.category;
  if (cat === 'hero') renderStoreHero();
  else if (cat === 'diamond') renderStoreDiamond();
  else if (cat === 'upgrade') renderStoreUpgrade();
  });
  });
  document.getElementById('btn-back-home')?.addEventListener('click', renderHomeScreen);
  }

  async function renderStoreHero() {
  state.currentScreen = 'store-hero';
  tg.showLoading();
  try {
  const resp = await apiFetch('/store/heroes');
  if (resp.success) {
  state.storeHeroes = resp.data;
  let html = `
  <div class="d-flex align-items-center mb-3">
  <button class="btn btn-link text-decoration-none p-0 me-2" id="btn-back-store">
  <i class="bi bi-arrow-left fs-5"></i>
  </button>
  <h2 class="h4 mb-0">Beli Hero</h2>
  </div>
  <div class="row g-3">
  `;
  if (state.storeHeroes.length === 0) {
  html += `<div class="col-12"><p class="text-center">Tidak ada hero tersedia</p></div>`;
  } else {
  state.storeHeroes.forEach(hero => {
  let buttonHtml = '';
  if (hero.owned) {
  buttonHtml = `<button class="btn btn-secondary w-100" disabled>Dimiliki</button>`;
  } else if (!hero.can_buy) {
  let reason = '';
  if (state.user.user_level < hero.required_level) reason = `Butuh Lv.${hero.required_level}`;
  else if (state.storeData.gold < hero.cost_gold) reason = `Gold kurang`;
  else reason = 'Tidak memenuhi syarat';
  buttonHtml = `<button class="btn btn-secondary w-100" disabled>${reason}</button>`;
  } else {
  buttonHtml = `<button class="btn btn-primary w-100 buy-hero-btn" data-hero-id="${hero.id}">Beli (${hero.cost_gold} <i class="bi bi-coin"></i>)</button>`;
  }

  html += `
  <div class="col-12">
  <div class="card">
  <div class="card-body">
  <h5 class="card-title">${hero.name} (${hero.type})</h5>
  <p class="card-text">${hero.description}</p>
  <div class="row small mb-2">
  <div class="col-6">❤️ HP: ${hero.stats.hp}</div>
  <div class="col-6">⚔️ ATK: ${hero.stats.atk}</div>
  <div class="col-6">🛡️ DEF: ${hero.stats.def}</div>
  <div class="col-6">⏱️ Speed: ${hero.stats.aspd}s</div>
  </div>
  <div class="mb-2 small">
  <span>Syarat: Level ${hero.required_level}</span>
  </div>
  ${buttonHtml}
  </div>
  </div>
  </div>
  `;
  });
  }
  html += `</div>`;
  appEl.innerHTML = html;
  }
  } finally {
  tg.hideLoading();
  }

  document.querySelectorAll('.buy-hero-btn').forEach(btn => {
  btn.addEventListener('click', async (e) => {
  const heroId = btn.dataset.heroId;
  tg.showLoading();
  try {
  const resp = await apiFetch('/store/buy-hero', {
  method: 'POST',
  body: JSON.stringify({ hero_id: heroId })
  });
  if (resp.success) {
  tg.showToast(resp.message, 'success');
  await loadUserData(); // refresh
  renderStoreHero(); // refresh halaman
  } else {
  tg.showToast(resp.message, 'danger');
  }
  } finally {
  tg.hideLoading();
  }
  });
  });

  document.getElementById('btn-back-store')?.addEventListener('click', renderStoreHome);
  }

  async function renderStoreDiamond() {
  state.currentScreen = 'store-diamond';
  tg.showLoading();
  try {
  const resp = await apiFetch('/store/diamond-packages');
  if (resp.success) {
  state.diamondPackages = resp.data;
  let html = `
  <div class="d-flex align-items-center mb-3">
  <button class="btn btn-link text-decoration-none p-0 me-2" id="btn-back-store">
  <i class="bi bi-arrow-left fs-5"></i>
  </button>
  <h2 class="h4 mb-0">Beli Diamond</h2>
  </div>
  <div class="row g-3">
  `;
  state.diamondPackages.forEach(pkg => {
  const canBuy = state.storeData.gold >= pkg.gold_cost;
  html += `
  <div class="col-6">
  <div class="card">
  <div class="card-body text-center">
  <h5 class="card-title">${pkg.name}</h5>
  <p><i class="bi bi-gem"></i> ${pkg.diamond}</p>
  <p>${pkg.gold_cost} <i class="bi bi-coin"></i></p>
  <button class="btn ${canBuy ? 'btn-primary' : 'btn-secondary'} w-100 buy-diamond-btn"
  data-package-id="${pkg.id}" ${!canBuy ? 'disabled' : ''}>
  Beli
  </button>
  </div>
  </div>
  </div>
  `;
  });
  html += `</div>`;
  appEl.innerHTML = html;
  }
  } finally {
  tg.hideLoading();
  }

  document.querySelectorAll('.buy-diamond-btn').forEach(btn => {
  btn.addEventListener('click', async (e) => {
  const packageId = btn.dataset.packageId;
  tg.showLoading();
  try {
  const resp = await apiFetch('/store/buy-diamond', {
  method: 'POST',
  body: JSON.stringify({ package_id: packageId })
  });
  if (resp.success) {
  tg.showToast(resp.message, 'success');
  state.storeData.gold = resp.new_gold;
  state.storeData.diamond = resp.new_diamond;
  renderStoreDiamond(); // refresh tampilan
  } else {
  tg.showToast(resp.message, 'danger');
  }
  } finally {
  tg.hideLoading();
  }
  });
  });

  document.getElementById('btn-back-store')?.addEventListener('click', renderStoreHome);
  }

  async function renderStoreUpgrade() {
  state.currentScreen = 'store-upgrade';
  tg.showLoading();
  try {
  const resp = await apiFetch('/store/upgrades');
  if (resp.success) {
  state.upgrades = resp.data;
  let html = `
  <div class="d-flex align-items-center mb-3">
  <button class="btn btn-link text-decoration-none p-0 me-2" id="btn-back-store">
  <i class="bi bi-arrow-left fs-5"></i>
  </button>
  <h2 class="h4 mb-0">Upgrade</h2>
  </div>
  <div class="row g-3">
  `;
  state.upgrades.forEach(upg => {
  const nextCost = upg.next_cost;
  const canBuy = upg.can_upgrade && (
  (upg.cost_type === 'gold' && state.storeData.gold >= nextCost) ||
  (upg.cost_type === 'diamond' && state.storeData.diamond >= nextCost)
  );
  const costDisplay = nextCost ? `${nextCost} ${upg.cost_type === 'gold' ? '💰' : '💎'}` : 'Max';
  const buttonText = upg.current_level >= upg.max_level ? 'Maksimal' : `Level ${upg.current_level+1} (${costDisplay})`;

  html += `
  <div class="col-12">
  <div class="card">
  <div class="card-body">
  <h5 class="card-title">${upg.name}</h5>
  <p class="card-text">${upg.description}</p>
  <p>Level saat ini: ${upg.current_level} / ${upg.max_level}</p>
  <button class="btn ${canBuy ? 'btn-primary' : 'btn-secondary'} w-100 buy-upgrade-btn"
  data-upgrade-id="${upg.id}" ${!canBuy ? 'disabled' : ''}>
  ${buttonText}
  </button>
  </div>
  </div>
  </div>
  `;
  });
  html += `</div>`;
  appEl.innerHTML = html;
  }
  } finally {
  tg.hideLoading();
  }

  document.querySelectorAll('.buy-upgrade-btn').forEach(btn => {
  btn.addEventListener('click', async (e) => {
  const upgradeId = btn.dataset.upgradeId;
  tg.showLoading();
  try {
  const resp = await apiFetch('/store/buy-upgrade', {
  method: 'POST',
  body: JSON.stringify({ upgrade_id: upgradeId })
  });
  if (resp.success) {
  tg.showToast(resp.message, 'success');
  state.storeData.gold = resp.new_gold;
  state.storeData.diamond = resp.new_diamond;
  renderStoreUpgrade(); // refresh
  } else {
  tg.showToast(resp.message, 'danger');
  }
  } finally {
  tg.hideLoading();
  }
  });
  });

  document.getElementById('btn-back-store')?.addEventListener('click', renderStoreHome);
  }

  async function startBattleVsComputer(heroId) {
  tg.showLoading('Bertarung...');
  try {
  const response = await apiFetch('/vs-computer', {
  method: 'POST',
  body: JSON.stringify({ user_hero_id: heroId })
  });
  if (response.success) {
  state.battleResult = response.data.result;
  state.battleResult.exp_gained = response.data.exp_gained;
  // Update data user setelah battle
  await loadUserData(); // refresh
  renderBattleResultScreen();
  }
  } catch (error) {
  // error handled
  } finally {
  tg.hideLoading();
  }
  }

  function renderBattleResultScreen() {
  state.currentScreen = 'result';
  const result = state.battleResult;
  const isWin = result.winner !== 'Goblin'; // sesuaikan dengan nama musuh
  const html = `
  <div class="d-flex align-items-center mb-3">
  <button class="btn btn-link text-decoration-none p-0 me-2" id="btn-back-home-from-result">
  <i class="bi bi-house fs-5"></i>
  </button>
  <h2 class="h4 mb-0">Hasil Pertarungan</h2>
  </div>
  <div class="card mb-3">
  <div class="card-body text-center">
  ${isWin ? '<div class="display-1 text-success">🏆</div><h3 class="text-success">Kamu Menang!</h3>'
  : '<div class="display-1 text-danger">💀</div><h3 class="text-danger">Kamu Kalah</h3>'}
  <p>Durasi: ${result.duration} detik</p>
  <p>EXP diperoleh: +${state.battleResult.exp_gained}</p>
  </div>
  </div>
  <div class="row mb-3">
  <div class="col-6">
  <div class="card"><div class="card-body text-center">
  <h5>${result.player_name || 'Hero'}</h5>
  <div class="fs-3">❤️ ${result.player_hp_remaining}</div>
  </div></div>
  </div>
  <div class="col-6">
  <div class="card"><div class="card-body text-center">
  <h5>${result.enemy_name || 'Musuh'}</h5>
  <div class="fs-3">❤️ ${result.enemy_hp_remaining}</div>
  </div></div>
  </div>
  </div>
  <div class="card">
  <div class="card-header">Log Pertarungan</div>
  <div class="card-body" style="max-height: 300px; overflow-y: auto;">
  <ul class="list-unstyled mb-0 small">
  ${result.log.map(entry => `<li class="mb-1">${entry}</li>`).join('')}
  </ul>
  </div>
  </div>
  <div class="d-grid gap-2 mt-3">
  <button class="btn btn-primary" id="btn-battle-again">Bertarung Lagi</button>
  <button class="btn btn-outline-secondary" id="btn-back-home-from-result">Beranda</button>
  </div>
  `;
  appEl.innerHTML = html;

  document.getElementById('btn-battle-again')?.addEventListener('click', () => {
  if (state.selectedHeroId) {
  startBattleVsComputer(state.selectedHeroId);
  }
  });
  document.getElementById('btn-back-home-from-result')?.addEventListener('click', renderHomeScreen);
  }

  // Initialize
  loadUserData();
  })();
</script>
@endpush