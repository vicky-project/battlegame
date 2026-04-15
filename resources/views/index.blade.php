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

  // ======================== STATE ========================
  let state = {
  user: null,               // data user dari /api/battle/user
  userHeroes: [],           // hero yang dimiliki user
  selectedHeroId: null,     // ID hero yang sedang dipilih
  allHeroes: [],            // semua hero dari toko
  storeData: null,          // data gold, diamond, kategori
  diamondPackages: [],      // paket diamond
  upgrades: [],             // data upgrade
  currentScreen: 'home',    // 'home', 'select-hero', 'battle', 'result', 'store', 'store-hero', 'store-diamond', 'store-upgrade'
  battleResult: null        // hasil pertarungan terakhir
  };

  const API_BASE = '{{ config("app.url") }}/api/battle';

  // ======================== HELPER API ========================
  async function apiFetch(endpoint, options = {}) {
  try {
  return await tg.fetchWithAuth(API_BASE + endpoint, options);
  } catch (error) {
  tg.showToast('Gagal terhubung ke server', 'danger');
  throw error;
  }
  }

  async function loadUserData() {
  const resp = await apiFetch('/user');
  if (resp.success) {
  state.user = resp.data;
  state.userHeroes = resp.data.heroes;
  state.selectedHeroId = resp.data.selected_hero_id;
  // Simpan gold/diamond
  state.storeData = state.storeData || {};
  state.storeData.gold = resp.data.gold;
  state.storeData.diamond = resp.data.diamond;
  }
  }

  // ======================== RENDER UTAMA ========================
  function renderHomeScreen() {
  state.currentScreen = 'home';
  const user = state.user || {};
  const html = `
  <div class="d-flex align-items-center mb-4">
  <i class="bi bi-controller me-2 fs-3"></i>
  <h1 class="h3 mb-0">Battle Arena</h1>
  </div>
  <div class="card mb-3">
  <div class="card-body">
  <h5 class="card-title">Level ${user.user_level || 1}</h5>
  <div class="progress mb-2" style="height: 20px;">
  <div class="progress-bar" role="progressbar"
  style="width: ${(user.user_exp / user.exp_to_next_level * 100) || 0}%">
  ${user.user_exp || 0}/${user.exp_to_next_level || 100} EXP
  </div>
  </div>
  <p class="card-text">
  <i class="bi bi-coin"></i> ${state.storeData?.gold || 0}
  <i class="bi bi-gem ms-2"></i> ${state.storeData?.diamond || 0}
  </p>
  <p class="card-text">Total Battle: ${user.total_battles || 0} | Menang: ${user.total_wins || 0}</p>
  </div>
  </div>
  <div class="d-grid gap-2">
  <button class="btn btn-primary btn-lg" id="btn-start-battle">
  <i class="bi bi-play-fill"></i> Mulai Bertarung
  </button>
  <button class="btn btn-outline-secondary" id="btn-select-hero">
  <i class="bi bi-person-lines-fill"></i> Pilih Hero
  </button>
  <button class="btn btn-outline-info" id="btn-store">
  <i class="bi bi-shop"></i> Toko
  </button>
  </div>
  `;
  appEl.innerHTML = html;
  appEl.style.display = 'block';
  loadingEl.style.display = 'none';

  document.getElementById('btn-start-battle')?.addEventListener('click', () => {
  if (state.selectedHeroId) {
  startBattleVsComputer(state.selectedHeroId);
  } else {
  tg.showToast('Pilih hero terlebih dahulu', 'warning');
  renderSelectHeroScreen();
  }
  });
  document.getElementById('btn-select-hero')?.addEventListener('click', renderSelectHeroScreen);
  document.getElementById('btn-store')?.addEventListener('click', renderStoreHome);
  }

  // ======================== PILIH HERO ========================
  async function renderSelectHeroScreen() {
  state.currentScreen = 'select-hero';
  await loadUserData(); // refresh

  if (state.userHeroes.length === 0) {
  appEl.innerHTML = `
  <div class="text-center py-5">
  <span style="font-size: 64px;">😢</span>
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
  <div class="card-body d-flex align-items-center">
  <div style="font-size: 48px; margin-right: 16px;">${hero.emoji || '👤'}</div>
  <div class="flex-grow-1">
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
  appEl.innerHTML = html;

  document.querySelectorAll('.select-hero-btn').forEach(btn => {
  btn.addEventListener('click', async (e) => {
  const heroId = btn.dataset.heroId;
  tg.showLoading();
  try {
  const resp = await apiFetch('/select-hero', {
  method: 'POST',
  body: JSON.stringify({ user_hero_id: heroId })
  });
  if (resp.success) {
  state.selectedHeroId = parseInt(heroId);
  tg.showToast(resp.message, 'success');
  await renderSelectHeroScreen(); // refresh
  } else {
  tg.showToast(resp.message, 'danger');
  }
  } finally {
  tg.hideLoading();
  }
  });
  });

  document.getElementById('btn-back-home')?.addEventListener('click', renderHomeScreen);
  document.getElementById('btn-done-select')?.addEventListener('click', renderHomeScreen);
  }

  // ======================== BATTLE ========================
  async function startBattleVsComputer(heroId) {
  tg.showLoading('Bertarung...');
  try {
  const resp = await apiFetch('/vs-computer', {
  method: 'POST',
  body: JSON.stringify({ user_hero_id: heroId })
  });
  if (resp.success) {
  state.battleResult = resp.data.result;
  state.battleResult.exp_gained = resp.data.exp_gained;
  state.battleResult.gold_gained = resp.data.gold_gained;
  await loadUserData(); // perbarui progress & currency
  renderBattleResultScreen();
  } else {
  tg.showToast(resp.message || 'Gagal bertarung', 'danger');
  }
  } catch (error) {
  tg.showToast('Gagal memulai pertarungan', 'danger');
  } finally {
  tg.hideLoading();
  }
  }

  function renderBattleResultScreen() {
  state.currentScreen = 'result';
  const result = state.battleResult;
  const isWin = result.winner === result.player_name;

  const html = `
  <div class="d-flex align-items-center mb-3">
  <button class="btn btn-link text-decoration-none p-0 me-2" id="btn-back-home-result">
  <i class="bi bi-arrow-left fs-5"></i>
  </button>
  <h2 class="h4 mb-0">Hasil Pertarungan</h2>
  </div>
  <div class="card mb-3">
  <div class="card-body text-center">
  ${isWin ?
  '<span style="font-size: 64px;">🏆</span><h3 class="text-success">Kamu Menang!</h3>' :
  '<span style="font-size: 64px;">💀</span><h3 class="text-danger">Kamu Kalah</h3>'
  }
  <p>Durasi: ${result.duration} detik</p>
  <p>EXP diperoleh: +${state.battleResult.exp_gained || 0}</p>
  <p>Gold diperoleh: +${state.battleResult.gold_gained || 0}</p>
  </div>
  </div>
  <div class="row mb-3">
  <div class="col-6">
  <div class="card">
  <div class="card-body text-center">
  <span style="font-size: 48px;">${result.player_emoji || '👤'}</span>
  <h5>${result.player_name}</h5>
  <div class="fs-3">❤️ ${result.player_hp_remaining}</div>
  </div>
  </div>
  </div>
  <div class="col-6">
  <div class="card">
  <div class="card-body text-center">
  <span style="font-size: 48px;">${result.enemy_emoji || '👾'}</span>
  <h5>${result.enemy_name}</h5>
  <div class="fs-3">❤️ ${result.enemy_hp_remaining}</div>
  </div>
  </div>
  </div>
  </div>
  <div class="card">
  <div class="card-header"><i class="bi bi-list-ul"></i> Log Pertarungan</div>
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
  } else {
  tg.showToast('Tidak ada hero yang dipilih', 'warning');
  renderSelectHeroScreen();
  }
  });
  const backHome = () => renderHomeScreen();
  document.getElementById('btn-back-home-from-result')?.addEventListener('click', backHome);
  document.getElementById('btn-back-home-result')?.addEventListener('click', backHome);
  }

  // ======================== TOKO UTAMA ========================
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
  <button class="btn btn-link text-decoration-none p-0 me-2" id="btn-back-home-store">
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

  document.querySelectorAll('.store-category-card').forEach(card => {
  card.addEventListener('click', () => {
  const cat = card.dataset.category;
  if (cat === 'hero') renderStoreHero();
  else if (cat === 'diamond') renderStoreDiamond();
  else if (cat === 'upgrade') renderStoreUpgrade();
  });
  });
  document.getElementById('btn-back-home-store')?.addEventListener('click', renderHomeScreen);
  }

  // ======================== TOKO HERO ========================
  async function renderStoreHero() {
  state.currentScreen = 'store-hero';
  tg.showLoading();
  try {
  const resp = await apiFetch('/store/heroes');
  if (resp.success) {
  state.allHeroes = resp.data;
  let html = `
  <div class="d-flex align-items-center mb-3">
  <button class="btn btn-link text-decoration-none p-0 me-2" id="btn-back-store">
  <i class="bi bi-arrow-left fs-5"></i>
  </button>
  <h2 class="h4 mb-0">Beli Hero</h2>
  </div>
  <div class="row g-3">
  `;
  state.allHeroes.forEach(hero => {
  let btnHtml = '';
  if (hero.owned) {
  btnHtml = `<button class="btn btn-secondary w-100" disabled>Sudah Dimiliki</button>`;
  } else if (!hero.can_buy) {
  let reason = '';
  if (state.user.user_level < hero.required_level) reason = `Butuh Lv.${hero.required_level}`;
  else if (state.storeData.gold < hero.cost_gold) reason = `Gold kurang`;
  else reason = 'Tidak memenuhi syarat';
  btnHtml = `<button class="btn btn-secondary w-100" disabled>${reason}</button>`;
  } else {
  btnHtml = `<button class="btn btn-primary w-100 buy-hero-btn" data-hero-id="${hero.id}">Beli (${hero.cost_gold} <i class="bi bi-coin"></i>)</button>`;
  }

  html += `
  <div class="col-12">
  <div class="card">
  <div class="card-body d-flex align-items-center">
  <span style="font-size: 48px; margin-right: 16px;">${hero.emoji || '👤'}</span>
  <div class="flex-grow-1">
  <h5 class="card-title">${hero.name} (${hero.type})</h5>
  <p class="card-text">${hero.description}</p>
  <div class="row small mb-2">
  <div class="col-6">❤️ HP: ${hero.stats.hp}</div>
  <div class="col-6">⚔️ ATK: ${hero.stats.atk}</div>
  <div class="col-6">🛡️ DEF: ${hero.stats.def}</div>
  <div class="col-6">⏱️ Speed: ${hero.stats.aspd}s</div>
  </div>
  <div class="mb-2 small">Syarat: Level ${hero.required_level}</div>
  ${btnHtml}
  </div>
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
  await loadUserData();
  renderStoreHero(); // refresh
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

  // ======================== TOKO DIAMOND & UPGRADE (ringkas) ========================
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
  tg.showToast(resp.data.message, 'success');
  state.storeData.gold = resp.data.new_gold;
  state.storeData.diamond = resp.data.new_diamond;
  renderStoreDiamond();
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
  const btnText = upg.current_level >= upg.max_level ? 'Maksimal' : `Level ${upg.current_level+1} (${costDisplay})`;

  html += `
  <div class="col-12">
  <div class="card">
  <div class="card-body">
  <h5 class="card-title">${upg.name}</h5>
  <p class="card-text">${upg.description}</p>
  <p>Level saat ini: ${upg.current_level} / ${upg.max_level}</p>
  <button class="btn ${canBuy ? 'btn-primary' : 'btn-secondary'} w-100 buy-upgrade-btn"
  data-upgrade-id="${upg.id}" ${!canBuy ? 'disabled' : ''}>
  ${btnText}
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
  tg.showToast(resp.data.message, 'success');
  state.storeData.gold = resp.data.new_gold;
  state.storeData.diamond = resp.data.new_diamond;
  renderStoreUpgrade();
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

  // ======================== INISIALISASI ========================
  async function init() {
  tg.showLoading('Memuat data...');
  try {
  await loadUserData();
  renderHomeScreen();
  } catch (error) {
  tg.showToast('Gagal memuat data awal', 'danger');
  console.error(error);
  } finally {
  tg.hideLoading();
  }
  }

  init();
  })();
</script>
@endpush