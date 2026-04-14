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
  currentScreen: 'home', // 'home', 'select-hero', 'battle', 'result'
  battleResult: null
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
  <div class="card mb-3">
  <div class="card-body">
  <h5 class="card-title">Level ${state.user.user_level}</h5>
  <div class="progress mb-2" style="height: 20px;">
  <div class="progress-bar" role="progressbar"
  style="width: ${(state.user.user_exp / state.user.exp_to_next_level * 100)}%">
  ${state.user.user_exp}/${state.user.exp_to_next_level} EXP
  </div>
  </div>
  <p class="card-text">Total Battle: ${state.user.total_battles || 0} | Menang: ${state.user.total_wins || 0}</p>
  </div>
  </div>
  <div class="d-grid gap-2">
  <button class="btn btn-primary btn-lg" id="btn-start-battle">
  <i class="bi bi-play-fill"></i> Mulai Bertarung
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
  if (state.selectedHeroId) {
  startBattleVsComputer(state.selectedHeroId);
  } else {
  tg.showToast('Pilih hero terlebih dahulu', 'danger');
  renderSelectHeroScreen();
  }
  });
  document.getElementById('btn-select-hero')?.addEventListener('click', renderSelectHeroScreen);
  }

  function renderSelectHeroScreen() {
  state.currentScreen = 'select-hero';
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
  appEl.innerHTML = html;

  document.querySelectorAll('.select-hero-btn').forEach(btn => {
  btn.addEventListener('click', async (e) => {
  const heroId = e.target.dataset.heroId;
  // Panggil API untuk set selected hero (akan kita buat endpointnya nanti)
  // Sementara update state saja
  state.selectedHeroId = parseInt(heroId);
  renderSelectHeroScreen(); // refresh
  tg.showToast('Hero dipilih', 'success');
  });
  });

  document.getElementById('btn-back-home')?.addEventListener('click', renderHomeScreen);
  document.getElementById('btn-done-select')?.addEventListener('click', renderHomeScreen);
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