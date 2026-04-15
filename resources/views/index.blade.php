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
  user: null,
  userHeroes: [],
  selectedHeroId: null,
  allHeroes: [],
  storeData: { gold: 0, diamond: 0 },
  diamondPackages: [],
  upgrades: [],
  currentScreen: 'home',
  battleResult: null,
  miningData: { gold_per_interval: 60, next_claim_seconds: 0, can_claim: false }
  };

  const API_BASE = '{{ config("app.url") }}/api/battle';
  let miningInterval = null;
  let overlayTimeout = null;

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
  state.storeData.gold = resp.data.gold;
  state.storeData.diamond = resp.data.diamond;
  }
  }

  // ======================== CURRENCY BAR (INTERAKTIF) ========================
  function renderCurrencyBar() {
  const gold = state.storeData?.gold ?? 0;
  const diamond = state.storeData?.diamond ?? 0;
  const userLevel = state.user?.user_level ?? 1;
  return `
  <div class="currency-bar d-flex justify-content-between align-items-center mb-2">
  <span class="badge bg-secondary">
  <i class="bi bi-star-fill"></i> Lv. ${userLevel}
  </span>
  <div>
  <span class="badge bg-warning text-dark me-2" style="cursor:pointer;" id="btn-gold-bar">
  <i class="bi bi-coin"></i> ${gold}
  </span>
  <span class="badge bg-info text-dark me-2" style="cursor:pointer;" id="btn-diamond-bar">
  <i class="bi bi-gem"></i> ${diamond}
  </span>
  <span class="badge bg-primary" style="cursor:pointer;" id="btn-store-bar">
  <i class="bi bi-shop"></i>
  </span>
  </div>
  </div>
  `;
  }

  function setupGlobalDelegation() {
  appEl.addEventListener('click', (e) => {
  if (e.target.closest('#btn-store-bar')) {
  renderStoreHome();
  } else if (e.target.closest('#btn-gold-bar')) {
  renderMiningScreen();
  } else if (e.target.closest('#btn-diamond-bar')) {
  renderStoreDiamond();
  }
  });
  }

  // ======================== OVERLAY NOTIFIKASI ========================
  function showMiningClaimOverlay(amount) {
  // Hapus overlay lama jika ada
  const existing = document.getElementById('mining-claim-overlay');
  if (existing) existing.remove();
  if (overlayTimeout) clearTimeout(overlayTimeout);

  const overlay = document.createElement('div');
  overlay.id = 'mining-claim-overlay';
  overlay.style.cssText = `
  position: fixed; top: 0; left: 0; width: 100%; height: 100%;
  background: rgba(0,0,0,0.85); z-index: 9999;
  display: flex; align-items: center; justify-content: center;
  color: white; font-size: 24px; flex-direction: column;
  `;
  overlay.innerHTML = `
  <span style="font-size: 80px;">⛏️💰</span>
  <h2 style="color: #FFD700;">+${amount} Gold</h2>
  <p>Berhasil diklaim!</p>
  `;
  document.body.appendChild(overlay);

  overlayTimeout = setTimeout(() => {
  overlay.remove();
  overlayTimeout = null;
  }, 2000);
  }

  function showBattleResultOverlay(resultData) {
  const existing = document.getElementById('battle-result-overlay');
  if (existing) existing.remove();
  if (overlayTimeout) clearTimeout(overlayTimeout);

  const isWin = resultData.result.winner === resultData.result.player_name;
  const overlay = document.createElement('div');
  overlay.id = 'battle-result-overlay';
  overlay.style.cssText = `
  position: fixed; top: 0; left: 0; width: 100%; height: 100%;
  background: rgba(0,0,0,0.9); z-index: 9999;
  display: flex; align-items: center; justify-content: center;
  color: white; font-size: 18px; flex-direction: column;
  padding: 20px; text-align: center;
  `;

  let levelUpMessage = '';
  if (resultData.user_level_up) {
  levelUpMessage += `<p style="color: #FFD700;">⭐ User naik level ke ${resultData.user_level_up}!</p>`;
  }
  if (resultData.hero_level_up) {
  levelUpMessage += `<p style="color: #FFD700;">🆙 Hero naik level ke ${resultData.hero_level_up}!</p>`;
  }

  overlay.innerHTML = `
  <span style="font-size: 80px;">${isWin ? '🏆' : '💀'}</span>
  <h2 style="color: ${isWin ? '#4CAF50' : '#f44336'};">${isWin ? 'KAMU MENANG!' : 'KAMU KALAH'}</h2>
  <div style="margin: 20px 0;">
  <p>⚔️ EXP diperoleh: +${resultData.exp_gained}</p>
  <p>💰 Gold diperoleh: +${resultData.gold_gained}</p>
  ${levelUpMessage}
  </div>
  <p style="font-size: 14px; color: #aaa;">Tap layar untuk melanjutkan...</p>
  `;
  document.body.appendChild(overlay);

  // Tap overlay untuk close
  overlay.addEventListener('click', () => {
  overlay.remove();
  renderBattleResultScreen(); // Lanjut ke layar detail
  });

  // Auto close setelah 4 detik
  overlayTimeout = setTimeout(() => {
  overlay.remove();
  renderBattleResultScreen();
  overlayTimeout = null;
  }, 4000);
  }

  // ======================== RENDER HOME ========================
  function renderHomeScreen() {
  removeOverlays();
  state.currentScreen = 'home';
  const user = state.user || {};
  const html = `
  ${renderCurrencyBar()}
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
  <p class="card-text">Total Battle: ${user.total_battles || 0} | Menang: ${user.total_wins || 0}</p>
  </div>
  </div>
  <div class="d-grid gap-2">
  <button class="btn btn-primary btn-lg" id="btn-start-battle">
  <i class="bi bi-play-fill"></i> Mulai Bertarung (10💰)
  </button>
  <button class="btn btn-outline-secondary" id="btn-select-hero">
  <i class="bi bi-person-lines-fill"></i> Pilih Hero
  </button>
  </div>
  `;
  appEl.innerHTML = html;
  appEl.style.display = 'block';
  loadingEl.style.display = 'none';

  document.getElementById('btn-start-battle')?.addEventListener('click', () => {
  const heroId = state.selectedHeroId || state.user?.selected_hero_id;
  if (heroId) {
  startBattleVsComputer(heroId);
  } else {
  tg.showToast('Pilih hero terlebih dahulu', 'warning');
  renderSelectHeroScreen();
  }
  });
  document.getElementById('btn-select-hero')?.addEventListener('click', renderSelectHeroScreen);
  }

  // ======================== PILIH HERO ========================
  async function renderSelectHeroScreen() {
  removeOverlays();
  state.currentScreen = 'select-hero';
  await loadUserData();

  if (state.userHeroes.length === 0) {
  appEl.innerHTML = `
  ${renderCurrencyBar()}
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
  ${renderCurrencyBar()}
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
  await loadUserData();
  tg.showToast(resp.message, 'success');
  await renderSelectHeroScreen();
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
  if(!heroId) {
  tg.showToast("Hero tidak valid", 'danger');
  renderSelectHeroScreen();
  return;
  }
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
  state.battleResult.user_level_up = resp.data.user_level_up;
  state.battleResult.hero_level_up = resp.data.hero_level_up;
  await loadUserData();
  showBattleResultOverlay(state.battleResult);
  } else {
  tg.showToast(resp.message || 'Gagal bertarung', 'danger');
  }
  } catch (error) {
  tg.showToast('Gagal memulai pertarungan', 'danger');
  } finally {
  tg.hideLoading();
  }
  }

  function removeOverlays() {
  const overlays = ['mining-claim-overlay', 'battle-result-overlay'];
  overlays.forEach(id => {
  const el = document.getElementById(id);
  if (el) el.remove();
  });
  if (overlayTimeout) {
  clearTimeout(overlayTimeout);
  overlayTimeout = null;
  }
  }

  function renderBattleResultScreen() {
  state.currentScreen = 'result';
  const result = state.battleResult;
  const isWin = result.winner === result.player_name;

  const html = `
  ${renderCurrencyBar()}
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

  // ======================== MINING SCREEN (AUTO-CLAIM) ========================
  async function renderMiningScreen() {
  state.currentScreen = 'mining';
  tg.showLoading();
  try {
  const resp = await apiFetch('/mining/status');
  if (resp.success) {
  const data = resp.data;
  state.miningData = {
  gold_per_interval: data.gold_per_interval || 60,
  next_claim_seconds: data.next_claim_seconds,
  can_claim: data.can_claim,
  gold: data.gold
  };
  state.storeData.gold = data.gold;

  // Jika ada gold otomatis terklaim saat load (interval terlewati)
  if (data.earned > 0) {
  showMiningClaimOverlay(data.earned);
  }

  const html = `
  ${renderCurrencyBar()}
  <div class="d-flex align-items-center mb-3">
  <button class="btn btn-link text-decoration-none p-0 me-2" id="btn-back-home">
  <i class="bi bi-arrow-left fs-5"></i>
  </button>
  <h2 class="h4 mb-0">Tambang Gold</h2>
  </div>
  <div class="card text-center">
  <div class="card-body">
  <span style="font-size: 64px;">⛏️💰</span>
  <h4>Gold kamu: ${data.gold}</h4>
  <p>Gold per jam: ${state.miningData.gold_per_interval}</p>
  <p>Waktu ke klaim berikutnya: <span id="mining-timer">${formatTime(data.next_claim_seconds)}</span></p>
  <p class="text-muted small mt-3">Gold akan otomatis diklaim saat waktu habis.</p>
  </div>
  </div>
  `;
  appEl.innerHTML = html;
  startMiningTimer(data.next_claim_seconds, data.can_claim);
  }
  } finally {
  tg.hideLoading();
  appEl.style.display = 'block';
  loadingEl.style.display = 'none';
  }

  document.getElementById('btn-back-home')?.addEventListener('click', renderHomeScreen);
  }

  async function performAutoClaim() {
  try {
  const resp = await apiFetch('/mining/claim', { method: 'POST' });
  if (resp.success) {
  const earned = resp.data.earned;
  state.storeData.gold = resp.data.gold;
  showMiningClaimOverlay(earned);
  // Update data mining untuk restart timer
  const newResp = await apiFetch('/mining/status');
  if (newResp.success) {
  state.miningData = {
  gold_per_interval: newResp.data.gold_per_interval || 60,
  next_claim_seconds: newResp.data.next_claim_seconds,
  can_claim: false,
  gold: newResp.data.gold
  };
  // Update tampilan timer
  const timerEl = document.getElementById('mining-timer');
  if (timerEl) {
  startMiningTimer(newResp.data.next_claim_seconds, false);
  }
  }
  } else {
  // Jika claim gagal (misal belum waktunya), refresh status
  const statusResp = await apiFetch('/mining/status');
  if (statusResp.success) {
  state.miningData = {
  gold_per_interval: statusResp.data.gold_per_interval || 60,
  next_claim_seconds: statusResp.data.next_claim_seconds,
  can_claim: statusResp.data.can_claim,
  gold: statusResp.data.gold
  };
  startMiningTimer(statusResp.data.next_claim_seconds, statusResp.data.can_claim);
  }
  }
  } catch (error) {
  console.error('Auto claim failed', error);
  }
  }

  function startMiningTimer(initialSeconds, canClaimNow = false) {
  if (miningInterval) clearInterval(miningInterval);
  let seconds = initialSeconds;
  const timerEl = document.getElementById('mining-timer');
  if (!timerEl) return;

  const updateTimerDisplay = () => {
  timerEl.textContent = formatTime(seconds);
  };
  updateTimerDisplay();

  // Jika saat inisialisasi sudah bisa claim, langsung auto-claim
  if (canClaimNow && seconds === 0) {
  performAutoClaim();
  return;
  }

  miningInterval = setInterval(() => {
  if (seconds > 0) {
  seconds--;
  updateTimerDisplay();
  }
  if (seconds <= 0) {
  clearInterval(miningInterval);
  timerEl.textContent = 'Siap diklaim!';
  // Auto claim
  performAutoClaim();
  }
  }, 1000);
  }

  function formatTime(sec) {
  const m = Math.floor(sec / 60);
  const s = sec % 60;
  return `${m}:${s.toString().padStart(2, '0')}`;
  }

  // ======================== TOKO UTAMA ========================
  async function renderStoreHome() {
  removeOverlays();
  state.currentScreen = 'store';
  tg.showLoading();
  try {
  const resp = await apiFetch('/store');
  if (resp.success) {
  state.storeData = resp.data;
  const data = resp.data;
  let html = `
  ${renderCurrencyBar()}
  <div class="d-flex align-items-center mb-3">
  <button class="btn btn-link text-decoration-none p-0 me-2" id="btn-back-home-store">
  <i class="bi bi-arrow-left fs-5"></i>
  </button>
  <h2 class="h4 mb-0">Toko</h2>
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
  ${renderCurrencyBar()}
  <div class="d-flex align-items-center mb-3">
  <button class="btn btn-link text-decoration-none p-0 me-2" id="btn-back-store">
  <i class="bi bi-arrow-left fs-5"></i>
  </button>
  <h2 class="h4 mb-0">Beli Hero</h2>
  </div>
  <div class="row g-3">
  `;
  state.allHeroes.forEach(hero => {
  const cost = hero.cost_gold ?? 0;
  let btnHtml = '';
  if (hero.owned) {
  btnHtml = `<button class="btn btn-secondary w-100" disabled>✅ Sudah Dimiliki</button>`;
  } else if (!hero.can_buy) {
  let reason = '';
  if (state.user.user_level < hero.required_level) reason = `🔒 Level ${hero.required_level} diperlukan`;
  else if (state.storeData.gold < cost) reason = `💰 Gold kurang`;
  else reason = '❌ Tidak memenuhi syarat';
  btnHtml = `<button class="btn btn-secondary w-100" disabled>${reason}</button>`;
  } else {
  btnHtml = `<button class="btn btn-primary w-100 buy-hero-btn" data-hero-id="${hero.id}">
  🛒 Beli ${cost > 0 ? `${cost} <i class="bi bi-coin"></i>` : 'Gratis'}
  </button>`;
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
  <div class="mb-2 small">
  <span class="badge bg-info me-1">Lv. ${hero.required_level}</span>
  <span class="badge bg-warning text-dark">💰 ${cost} Gold</span>
  </div>
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
  renderStoreHero();
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

  // ======================== TOKO DIAMOND ========================
  async function renderStoreDiamond() {
  state.currentScreen = 'store-diamond';
  tg.showLoading();
  try {
  const resp = await apiFetch('/store/diamond-packages');
  if (resp.success) {
  state.diamondPackages = resp.data;
  let html = `
  ${renderCurrencyBar()}
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
  renderStoreDiamond(); // Refresh
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

  // ======================== TOKO UPGRADE ========================
  async function renderStoreUpgrade() {
  state.currentScreen = 'store-upgrade';
  tg.showLoading();
  try {
  const resp = await apiFetch('/store/upgrades');
  if (resp.success) {
  state.upgrades = resp.data;
  let html = `
  ${renderCurrencyBar()}
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
  setupGlobalDelegation();
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