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

@push('styles')
<style>
  .custom-tooltip .tooltip-inner {
    background-color: #212529 !important;
    color: #ffffff !important;
    box-shadow: 0 4px 8px rgba(0,0,0,0.3);
    border-radius: 8px;
    padding: 6px 12px;
    font-size: 13px;
    max-width: 200px;
  }

  .custom-tooltip.bs-tooltip-top .tooltip-arrow::before {
    border-top-color: #212529 !important;
  }

  .custom-tooltip.bs-tooltip-bottom .tooltip-arrow::before {
    border-bottom-color: #212529 !important;
  }

  .custom-tooltip.bs-tooltip-start .tooltip-arrow::before {
    border-left-color: #212529 !important;
  }

  .custom-tooltip.bs-tooltip-end .tooltip-arrow::before {
    border-right-color: #212529 !important;
  }
</style>
@endpush

@push('scripts')
<script>
  (function() {
  const tg = window.TelegramApp;
  const appEl = document.getElementById('app-content');
  const loadingEl = document.getElementById('loading-screen');
  const API_BASE = '{{ config("app.url") }}/api/battle';
  const BATTLE_COST = {{ config("battlegame.battle_cost", 10) }};

  // ======================== STATE & TIMERS ========================
  let state = {
  user: null, userHeroes: [], selectedHeroId: null, allHeroes: [],
  storeData: { gold: 0, diamond: 0 }, diamondPackages: [], upgrades: [],
  currentScreen: 'home', battleResult: null,
  miningData: { gold_per_interval: 60, next_claim_seconds: 0, can_claim: false },
  historyList: [],
  historyPage: 1,
  historyHasMore: true,
  historyLoading: false,
  viewingLog: null,
  logViewerInterval: null
  };
  let timers = { mining: null, preview: null, overlay: null };
  let battleLoadingOverlay = null;

  // ======================== UTILITIES ========================
  const formatTime = sec => `${Math.floor(sec/60)}:${(sec%60).toString().padStart(2,'0')}`;
  const stopMiningPreviewTimer = () => timers.preview && (clearInterval(timers.preview), timers.preview = null);
  const stopMiningTimer = () => timers.mining && (clearInterval(timers.mining), timers.mining = null);
  const removeOverlays = () => {
  ['mining-claim-overlay', 'battle-result-overlay'].forEach(id => document.getElementById(id)?.remove());
  timers.overlay && (clearTimeout(timers.overlay), timers.overlay = null);
  };
  const setAppContent = (html) => { appEl.innerHTML = html; appEl.style.display = 'block'; loadingEl.style.display = 'none'; };

  async function apiFetch(endpoint, options = {}) {
  try { return await tg.fetchWithAuth(API_BASE + endpoint, options); }
  catch (error) { tg.showToast(error.message || 'Gagal terhubung', 'danger'); throw error; }
  }

  async function loadUserData() {
  const resp = await apiFetch('/user');
  if (resp.success) {
  state.user = resp.data; state.userHeroes = resp.data.heroes; state.selectedHeroId = resp.data.selected_hero_id;
  state.storeData.gold = resp.data.gold; state.storeData.diamond = resp.data.diamond;
  }
  }

  // ======================== UI COMPONENTS ========================
  const renderCurrencyBar = () => `
  <div class="currency-bar d-flex justify-content-between align-items-center mb-2">
  <div>
  <span class="badge bg-secondary"><i class="bi bi-star-fill"></i> Lv. ${state.user?.user_level ?? 1}</span>
  <span class="badge bg-secondary" style="cursor:pointer;" id="btn-help-bar"><i class="bi bi-question-circle"></i></span>
  </div>
  <div>
  <span class="badge bg-warning text-dark me-1" style="cursor:pointer;" id="btn-gold-bar"><i class="bi bi-coin"></i> ${state.storeData.gold}</span>
  <span class="badge bg-info text-dark me-1" style="cursor:pointer;" id="btn-diamond-bar"><i class="bi bi-gem"></i> ${state.storeData.diamond}</span>
  <span class="badge bg-secondary me-1" style="cursor:pointer;" id="btn-store-bar">🛒</span>
  <span class="badge bg-success" style="cursor:pointer;" id="btn-history-bar"><i class="bi bi-clock-history"></i></span>
  </div>
  </div>
  `;

  const showOverlay = (id, content, autoClose = 2000, onClose) => {
  removeOverlays(); // bersihkan overlay dan timeout sebelumnya
  const overlay = document.createElement('div');
  overlay.id = id;
  overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:9999;display:flex;align-items:center;justify-content:center;color:white;font-size:24px;flex-direction:column;';
  overlay.innerHTML = content;

  // Event listener untuk tap
  overlay.addEventListener('click', () => {
  // Batalkan timeout jika ada
  if (timers.overlay) {
  clearTimeout(timers.overlay);
  timers.overlay = null;
  }
  overlay.remove();
  if (onClose) onClose();
  });

  document.body.appendChild(overlay);

  if (autoClose) {
  timers.overlay = setTimeout(() => {
  overlay.remove();
  timers.overlay = null;
  if (onClose) onClose();
  }, autoClose);
  }

  return overlay;
  };

  // ======================== BATTLE LOADING ========================
  const showBattleLoading = (pName, pEmoji, eName, eEmoji) => {
  battleLoadingOverlay?.remove();
  battleLoadingOverlay = document.createElement('div');
  battleLoadingOverlay.id = 'battle-loading-overlay';
  battleLoadingOverlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:9999;display:flex;align-items:center;justify-content:center;color:white;font-size:18px;flex-direction:column;';
  battleLoadingOverlay.innerHTML = `
  <div style="display:flex;align-items:center;gap:40px;margin-bottom:30px;">
  <div style="text-align:center;"><div style="font-size:64px;animation:attackLeft 1s infinite alternate;">${renderLayeredEmoji(pEmoji)}</div><div>${pName}</div></div>
  <div style="font-size:48px;animation:clash 0.5s infinite;">⚡</div>
  <div style="text-align:center;"><div style="font-size:64px;animation:attackRight 1s infinite alternate;">${eEmoji}</div><div>${eName}</div></div>
  </div>
  <div style="margin-top:20px;font-size:24px;animation:pulse 1.5s infinite;">⚔️ Bertarung... ⚔️</div>
  <style>@keyframes attackLeft{0%{transform:translateX(0)}100%{transform:translateX(20px)}}@keyframes attackRight{0%{transform:translateX(0)}100%{transform:translateX(-20px)}}@keyframes clash{0%,100%{opacity:.3;transform:scale(.8)}50%{opacity:1;transform:scale(1.2)}}@keyframes pulse{0%,100%{opacity:.6}50%{opacity:1;text-shadow:0 0 10px gold}}</style>
  `;
  document.body.appendChild(battleLoadingOverlay);
  };
  const hideBattleLoading = () => battleLoadingOverlay?.remove() || (battleLoadingOverlay = null);

  /**
  * Memisahkan emoji dari string. Jika terdapat lebih dari satu emoji,
  * emoji pertama menjadi main, sisanya menjadi layer.
  * @param {string} emojiString - String emoji dari backend (contoh: "🧙‍♂️🔥")
  * @param {number} size - Ukuran font dalam px
  * @returns {string} HTML string
  */
  function renderLayeredEmoji(emojiString, size = 60) {
  // Regex untuk mencocokkan emoji (termasuk variasi dan ZWJ)
  const emojiRegex = /\p{Extended_Pictographic}|\p{Emoji_Component}/gu;
  const emojis = emojiString.match(emojiRegex) || [emojiString];

  if (emojis.length === 1) {
  return `<span style="font-size:${size}px;">${emojis[0]}</span>`;
  }

  const mainEmoji = emojis[0];
  const layerEmoji = emojis.slice(1).join('');

  return `
  <span style="display:inline-block; position:relative; width:${size}px; height:${size}px; vertical-align:middle;">
  <span style="position:absolute; top:0; left:0; font-size:${size}px; opacity:0.7;">${layerEmoji}</span>
  <span style="position:absolute; top:0; left:0; font-size:${size-6}px; transform:translate(3px, 3px);">${mainEmoji}</span>
  </span>
  `;
  }

  function renderTooltip(icon, value, label, bonus = 0) {
  const bonusText = bonus > 0 ? ` (+${bonus} bonus)` : '';
  const displayBonus = bonus > 0 ? `<span class="text-success small"><i class="bi bi-arrow-up"></i>+${bonus}</span>` : '';
  return `
  <span class="d-inline-block me-3 stat-item"
  style="cursor: help;"
  data-bs-toggle="tooltip"
  data-bs-placement="top"
  title="${label}: ${value}${bonusText}">
  ${icon} ${value} ${displayBonus}
  </span>
  `;
  }

  function initTooltips(selector = '[data-bs-toggle="tooltip"]') {
  // Hapus tooltip yang sudah ada untuk mencegah duplikasi
  document.querySelectorAll(selector).forEach(el => {
  const tooltips = bootstrap.Tooltip.getInstance(el);
  if (tooltips) tooltips.dispose();
  });

  // Inisialisasi ulang semua elemen dengan class .stat-item
  const tooltipTriggerList = document.querySelectorAll(selector);
  [...tooltipTriggerList].map(el => new bootstrap.Tooltip(el, {
  customClass: 'custom-tooltip'
  }));
  }

  // ======================== HOME SCREEN ========================
  async function renderHomeScreen() {
  const user = state.user || {};
  const selectedHero = state.userHeroes.find(h => h.id === state.selectedHeroId);
  const expPercent = user.exp_to_next_level > 0 ? ((user.user_exp / user.exp_to_next_level) * 100) : 100;
  const expDisplay = `${user.user_exp || 0} / ${user.exp_to_next_level || '---'}`;
  const levelLabel = user.user_level > 50 ? `${user.user_level} (Paragon)` : user.user_level;

  setAppContent(`
  ${renderHeader(null)}
  <div class="d-flex align-items-center mb-3"><div style="font-size:48px;margin-right:12px;">😊</div><div><h2 class="h5 mb-0">Selamat datang, ${user.user?.first_name || 'Petarung'}!</h2><span class="badge bg-secondary">Lv. ${levelLabel}</span></div></div>
  ${selectedHero ? `<div class="card mb-3"><div class="card-body d-flex align-items-center">${renderLayeredEmoji(selectedHero.emoji||'👤', 48)}<div class="flex-grow-1 ms-3"><div class="d-flex justify-content-between align-items-center mb-2"><h5 class="card-title mb-0">${selectedHero.name}</h5><button class="btn btn-sm btn-outline-secondary" id="btn-change-hero"><i class="bi bi-arrow-repeat"></i></button></div><div class="row small mb-2"><div class="col-4">${renderTooltip('❤️', selectedHero.stats.hp, 'Health Point', 0)}</div><div class="col-4">${renderTooltip('⚔️', selectedHero.stats.atk, 'Attack', 0)}</div><div class="col-4">${renderTooltip('🛡', selectedHero.stats.def, 'Defense', 0)}</div></div><div class="d-flex justify-content-between small mb-1"><span>Level ${selectedHero.level} ${selectedHero.level > 30 ? '<small class="text-muted">(Paragon)</small>' : ''}</span><span>${selectedHero.exp || 0} / ${selectedHero.next_level_exp || 100}</span></div><div class="progress" style="height: 8px;"><div class="progress-bar bg-info" role="progressbar" style="width: ${(selectedHero.exp / selectedHero.next_level_exp) * 100 || 0}%"></div></div></div></div></div>`
  : `<div class="card mb-3"><div class="card-body text-center py-3"><p class="mb-2">Belum ada hero dipilih</p><button class="btn btn-primary btn-sm" id="btn-select-hero-empty">Pilih Hero</button></div></div>`}
  <div class="card mb-3"><div class="card-body"><div class="d-flex justify-content-between mb-1"><span>Level ${levelLabel}</span><span>${expDisplay}</span></div><div class="progress" style="height:20px;"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width:${expPercent}%">${Math.floor(expPercent)}%</div></div></div></div>
  <div class="row g-2 mb-3">
  ${[['⚔️','Total',user.total_battles],['🏆','Menang',user.total_wins],['💀','Kalah',user.total_losses]].map(([icon,label,val])=>`<div class="col-4"><div class="card text-center"><div class="card-body py-2"><div class="fs-4">${icon}</div><div class="fw-bold ${label==='Menang'?'text-success':label==='Kalah'?'text-danger':''}">${val||0}</div><small>${label}</small></div></div></div>`).join('')}
  </div>
  <div class="card mb-3"><div class="card-body d-flex justify-content-between align-items-center"><div><i class="bi bi-minecart-loaded"></i> Tambang Gold <span class="badge bg-warning text-dark ms-2" id="mining-preview-timer">--:--</span></div><button class="btn btn-sm btn-outline-warning" id="btn-mining-preview">Klaim <i class="bi bi-arrow-right"></i></button></div></div>
  <div class="d-grid gap-2"><button class="btn btn-primary btn-lg" id="btn-start-battle"><i class="bi bi-play-fill"></i> Mulai Bertarung (<i class="bi bi-coin"></i>${BATTLE_COST})</button></div>
  `);
  startMiningPreviewTimer();
  document.getElementById('btn-start-battle')?.addEventListener('click', ()=> state.selectedHeroId ? startBattleVsComputer(state.selectedHeroId) : (tg.showToast('Pilih hero', 'warning'), renderSelectHeroScreen()));
  document.getElementById('btn-change-hero')?.addEventListener('click', renderSelectHeroScreen);
  document.getElementById('btn-select-hero-empty')?.addEventListener('click', renderSelectHeroScreen);
  document.getElementById('btn-mining-preview')?.addEventListener('click', renderMiningScreen);
  initTooltips();
  }

  async function startMiningPreviewTimer() {
  stopMiningPreviewTimer();
  const update = async () => {
  const el = document.getElementById('mining-preview-timer'); if (!el) return;
  try {
  const resp = await apiFetch('/mining/status');
  if (resp.success) {
  const d = resp.data;
  el.textContent = d.can_claim ? 'Siap!' : formatTime(d.next_claim_seconds);
  el.classList.toggle('bg-success', d.can_claim); el.classList.toggle('text-white', d.can_claim);
  el.classList.toggle('bg-warning', !d.can_claim); el.classList.toggle('text-dark', !d.can_claim);
  }
  } catch { el.textContent = '--:--'; }
  };
  await update(); timers.preview = setInterval(update, 1000);
  }

  // ======================== SELECT HERO ========================
  async function renderSelectHeroScreen() {
  stopMiningPreviewTimer(); removeOverlays();
  await loadUserData();

  if (!state.userHeroes.length) {
  setAppContent(`${renderCurrencyBar()}<div class="text-center py-5"><span style="font-size:64px;">😢</span><h4>Kamu belum memiliki hero</h4><p>Kunjungi Toko untuk membeli hero pertama mu!</p><button class="btn btn-primary" id="btn-goto-store">Ke Toko Hero</button><button class="btn btn-link" id="btn-back-home">Kembali</button></div>`);
  document.getElementById('btn-goto-store')?.addEventListener('click', renderStoreHero);
  document.getElementById('btn-back-home')?.addEventListener('click', renderHomeScreen);
  return;
  }

  setAppContent(`
  ${renderHeader('btn-back-home', 'Pilih Hero')}
  <div class="row">${state.userHeroes.map(h => {
  const isSelected = h.id === state.selectedHeroId;
  const stats = h.stats;
  const bonusHp = stats.bonus_hp || 0;
  const bonusAtk = stats.bonus_atk || 0;
  const bonusDef = stats.bonus_def || 0;
  const bonusAccuracy = stats.bonus_accuracy || 0;
  const bonusCrit = stats.bonus_crit_chance || 0;
  const bonusCounter = stats.bonus_counter_chance || 0;

  return `
  <div class="col-12 mb-3">
  <div class="card ${isSelected ? 'border-primary' : ''}">
  <div class="card-body">
  <!-- Baris 1: Emoji (kiri) + Nama/Level/Stats (kanan) -->
  <div class="d-flex mb-3">
  <!-- Emoji -->
  <div class="text-center me-3">
  ${renderLayeredEmoji(h.emoji || '👤', 80)}
  </div>
  <!-- Nama, Level, Stats Dasar -->
  <div class="flex-grow-1 ms-auto">
  <div class="d-flex justify-content-between align-items-start">
  <div>
  <h5 class="card-title mb-0">${h.name}<span class="small ms-2">Lv.${h.level} ${h.level > 30 ? '<small class="text-muted">(Paragon)</small>' : ''}</span>
  </h5>
  </div>
  ${isSelected ? '<span class="badge bg-primary">✔️</span>' : ''}
  </div>
  <div class="d-flex align-items-start mt-2">
  <p>${h.description}</p>
  </div>
  </div>
  </div>
  <hr>
  <div class="row small mt-3">
  <!-- Stats Dasar (2 kolom) -->
  <div class="col-6">
  ${renderTooltip('❤️', stats.hp, 'Health Point', bonusHp)}
  </div>
  <div class="col-6">
  ${renderTooltip('⚔️', stats.atk, 'Attack', bonusAtk)}
  </div>
  <div class="col-6">
  ${renderTooltip('🛡️', stats.def, 'Defense', bonusDef)}
  </div>
  <div class="col-6">
  ${renderTooltip('⏱️', stats.aspd+'s', 'Attack Speed', 0)}
  </div>
  <!-- Baris 2: Skill Tambahan (2 kolom) -->
  <div class="col-6">
  ${renderTooltip('🎯', Math.round(stats.accuracy * 100) + '%', 'Accuracy', bonusAccuracy)}
  </div>
  <div class="col-6">
  ${renderTooltip('👟', Math.round(stats.evasion * 100) + '%', 'Evasion', 0)}
  </div>
  <div class="col-6">
  ${renderTooltip('🛡', Math.round(stats.block_chance * 100) + '%', 'Block Chance', 0)}
  </div>
  <div class="col-6">
  ${renderTooltip('⚡', '+' + Math.round((stats.crit_chance_bonus || 0) * 100) + '%', 'Critical Chance Bonus', bonusCrit)}
  </div>
  <div class="col-6">
  ${renderTooltip('🔄', '+' + Math.round((stats.counter_chance_bonus || 0) * 100) + '%', 'Counter Chance Bonus', bonusCounter)}
  </div>
  </div>
  <!-- Tombol -->
  <button class="btn btn-sm ${isSelected ? 'btn-success' : 'btn-outline-primary'} w-100 mt-3 select-hero-btn" data-hero-id="${h.id}" ${isSelected ? 'disabled' : ''}>
  ${isSelected ? 'Terpilih' : 'Pilih Hero Ini'}
  </button>
  </div>
  </div>
  </div>
  `;
  }).join('')}</div>
  <div class="d-grid mt-3"><button class="btn btn-primary" id="btn-done-select">Selesai</button></div>
  `);

  document.querySelectorAll('.select-hero-btn').forEach(b => b.addEventListener('click', async (e) => {
  const hid = b.dataset.heroId; tg.showLoading();
  try {
  const r = await apiFetch('/select-hero', { method: 'POST', body: JSON.stringify({ user_hero_id: hid }) });
  r.success ? (state.selectedHeroId = parseInt(hid), await loadUserData(), tg.showToast(r.message, 'success'), renderSelectHeroScreen()) : tg.showToast(r.message, 'danger');
  } finally { tg.hideLoading(); }
  }));
  document.getElementById('btn-back-home')?.addEventListener('click', renderHomeScreen);
  document.getElementById('btn-done-select')?.addEventListener('click', renderHomeScreen);

  initTooltips();
  }

  // ======================== BATTLE ========================
  async function startBattleVsComputer(heroId) {
  const hero = state.userHeroes.find(h=>h.id===heroId);
  if(!hero) return tg.showToast('Hero tidak valid','danger');
  showBattleLoading(hero.name, hero.emoji||'👤', 'Musuh', '👾');
  try {
  const resp = await apiFetch('/vs-computer', {method:'POST', body:JSON.stringify({user_hero_id:heroId})});
  if(resp.success) {
  state.battleResult = {...resp.data.result, exp_gained: resp.data.exp_gained, gold_gained: resp.data.gold_gained, user_level_up: resp.data.user_level_up, hero_level_up: resp.data.hero_level_up, player_level: resp.data.player_level, enemy_level: resp.data.enemy_level, skill_improvement: resp.data.skill_improvement };
  await loadUserData();
  showBattleResultOverlay();
  } else tg.showToast(resp.message||'Gagal bertarung','danger');
  } catch(e) { hideBattleLoading(); tg.showToast(e.message||'Gagal','danger');
  } finally {
  hideBattleLoading();
  }
  }

  function showBattleResultOverlay() {
  const r = state.battleResult, isWin = r.winner === r.player_name;
  let levelUpMessage = '';
  if (r.user_level_up) {
  levelUpMessage += `<p style="color:#FFD700;">⭐ User naik level ke ${r.user_level_up}!</p>`;
  }
  if (r.hero_level_up) {
  levelUpMessage += `<p style="color:#FFD700;">🆙 Hero naik level ke ${r.hero_level_up}!</p>`;
  if (r.skill_improvement) {
  levelUpMessage += `<p style="color:#a0d6ff; font-size:16px;">✨ ${r.skill_improvement}</p>`;
  }
  }

  showOverlay('battle-result-overlay', `
  <span style="font-size:80px;">${isWin ? '🏆' : '💀'}</span>
  <h2 style="color:${isWin ? '#4CAF50' : '#f44336'};">${isWin ? 'KAMU MENANG!' : 'KAMU KALAH'}</h2>
  <div style="margin:20px 0;">
  <p>⚔️ EXP diperoleh: +${r.exp_gained}</p>
  <p><i class="bi bi-coin"></i> Gold diperoleh: +${r.gold_gained}</p>
  ${levelUpMessage}
  </div>
  <p style="font-size:14px;color:#aaa;">Tap layar untuk melanjutkan...</p>
  `, 4000, renderBattleResultScreen);
  }

  function renderBattleResultScreen() {
  stopMiningPreviewTimer();
  const r=state.battleResult, isWin=r.winner===r.player_name, playerMaxHp = r.player_max_hp || r.player_hp_remaining, enemyMaxHp = r.enemy_max_hp || r.enemy_hp_remaining;
  setAppContent(`
  ${renderHeader('btn-back-home', 'Hasil Pertarungan')}
  <div class="card mb-3">
  <div class="card-body text-center">
  <span style="font-size:64px;">${isWin ? '🏆' : '💀'}</span>
  <h3 class="${isWin ? 'text-success' : 'text-danger'}">${isWin ? 'Kamu Menang!' : 'Kamu Kalah'}</h3>
  <p>Durasi: ${r.duration} detik</p>
  <p>⚔️ EXP: +${r.exp_gained}</p>
  <p><i class="bi bi-coin"></i> Gold: +${r.gold_gained}</p>
  </div>
  </div>
  <div class="row mb-3">
  <div class="col-6">
  <div class="card">
  <div class="card-body text-center">
  ${renderLayeredEmoji(r.player_emoji, 48)}
  <h5 class="mt-2">${r.player_name}</h5>
  <div class="d-flex justify-content-center align-items-center gap-2 small">
  <span class="badge bg-secondary">Lv.${state.battleResult.player_level || '?'}</span>
  <span><span style="font-size: 14px;">❤️ </span>${r.player_hp_remaining}/${playerMaxHp}</span>
  </div>
  </div>
  </div>
  </div>
  <div class="col-6">
  <div class="card">
  <div class="card-body text-center">
  ${renderLayeredEmoji(r.enemy_emoji, 48)}
  <h5 class="mt-2">${r.enemy_name}</h5>
  <div class="d-flex justify-content-center align-items-center gap-2 small">
  <span class="badge bg-secondary">Lv.${state.battleResult.enemy_level || '?'}</span>
  <span><span style="font-size: 14px;">❤️ </span>${r.enemy_hp_remaining}/${enemyMaxHp}</span>
  </div>
  </div>
  </div>
  </div>
  </div>
  <div class="d-grid gap-2 mb-3">
  <button class="btn btn-primary" id="btn-battle-again">Bertarung Lagi (<i class="bi bi-coin"></i>${BATTLE_COST})</button>
  <button class="btn btn-outline-secondary" id="btn-back-home-from-result">Beranda</button>
  </div>
  <div class="card">
  <div class="card-header"><i class="bi bi-list-ul"></i> Log Pertarungan</div>
  <div class="card-body" style="max-height:200px;overflow-y:auto;">
  <ul class="list-unstyled small">${r.log.map(e => `<li class="mb-1">${e}</li>`).join('')}</ul>
  </div>
  </div>
  `);
  document.getElementById('btn-battle-again')?.addEventListener('click', ()=> state.selectedHeroId ? startBattleVsComputer(state.selectedHeroId) : (tg.showToast('Pilih hero','warning'), renderSelectHeroScreen()));
  document.getElementById('btn-back-home-from-result')?.addEventListener('click', renderHomeScreen);
  document.getElementById('btn-back-home')?.addEventListener('click', renderHomeScreen);
  }

  // ======================== MINING SCREEN ========================
  async function renderMiningScreen() {
  stopMiningPreviewTimer(); stopMiningTimer(); removeOverlays(); tg.showLoading();
  try {
  const resp = await apiFetch('/mining/status');
  if (!resp.success) return;
  const d = resp.data;
  state.miningData = { gold_per_interval: d.gold_per_interval, next_claim_seconds: d.next_claim_seconds, can_claim: d.can_claim, gold: d.gold };
  state.storeData.gold = d.gold;
  if (d.earned > 0) showOverlay('mining-claim-overlay', `<span style="font-size:80px;">⛏️💰</span><h2 style="color:#FFD700;">+${d.earned} Gold</h2><p>Berhasil diklaim!</p>`, 2000);
  setAppContent(`
  ${renderHeader('btn-back-home', 'Tambang Gold')}
  <div class="card text-center"><div class="card-body"><span style="font-size:64px;">⛏️💰</span><h4>Gold kamu: <i class="bi bi-coin"></i>${d.gold}</h4><p>Gold per jam: <i class="bi bi-coin"></i>${d.gold_per_interval}</p><p>Waktu ke klaim berikutnya: <span id="mining-timer">${formatTime(d.next_claim_seconds)}</span></p><p class="text-muted small mt-3">Gold akan otomatis diklaim saat waktu habis.</p></div></div>
  `);
  startMiningTimer(d.next_claim_seconds, d.can_claim);
  } finally { tg.hideLoading(); }
  document.getElementById('btn-back-home')?.addEventListener('click', renderHomeScreen);
  }

  function startMiningTimer(initialSeconds, canClaimNow) {
  stopMiningTimer();
  let seconds = initialSeconds;
  const el = document.getElementById('mining-timer');
  if (!el) return;
  const update = () => { el.textContent = formatTime(seconds); };
  update();
  if (canClaimNow && seconds === 0) return performAutoClaim();
  timers.mining = setInterval(() => {
  if (seconds > 0) { seconds--; update(); }
  if (seconds <= 0) { clearInterval(timers.mining); el.textContent = 'Siap diklaim!'; performAutoClaim(); }
  }, 1000);
  }

  async function performAutoClaim() {
  try {
  const resp = await apiFetch('/mining/claim', {method:'POST'});
  if (resp.success) {
  state.storeData.gold = resp.data.gold;
  showOverlay('mining-claim-overlay', `<span style="font-size:80px;">⛏️💰</span><h2 style="color:#FFD700;">+${resp.data.earned} Gold</h2><p>Berhasil diklaim!</p>`, 2000);
  const newResp = await apiFetch('/mining/status');
  if (newResp.success) { state.miningData = { ...newResp.data }; startMiningTimer(newResp.data.next_claim_seconds, false); }
  }
  } catch(e) { console.error('Auto claim failed', e); }
  }

  // ======================== STORE SCREENS ========================
  async function renderStoreHome() {
  stopMiningPreviewTimer(); removeOverlays(); tg.showLoading();
  try {
  const resp = await apiFetch('/store');
  if (!resp.success) return;
  state.storeData = resp.data;
  setAppContent(`
  ${renderHeader('btn-back-home', 'Toko')}
  <div class="row g-3">${resp.data.categories.map(c=>`<div class="col-6"><div class="card h-100 store-category-card" data-category="${c.id}"><div class="card-body text-center"><i class="bi bi-${c.icon} fs-1 ${c.color}"></i><h5 class="card-title">${c.name}</h5><p class="card-text small">${c.description}</p></div></div></div>`).join('')}</div>
  `);
  document.querySelectorAll('.store-category-card').forEach(c=>c.addEventListener('click', ()=> ({hero:renderStoreHero, diamond:renderStoreDiamond, upgrade:renderStoreUpgrade})[c.dataset.category]()));
  document.getElementById('btn-back-home')?.addEventListener('click', renderHomeScreen);
  } finally { tg.hideLoading(); }
  }

  async function renderStoreHero() {
  stopMiningPreviewTimer(); tg.showLoading();
  try {
  const resp = await apiFetch('/store/heroes');
  if (!resp.success) return;
  state.allHeroes = resp.data;
  setAppContent(`
  ${renderHeader('btn-back-store', 'Beli Hero')}
  <div class="row g-3">${state.allHeroes.map(h=>{const costGold=h.cost_gold||0,costDiamond=h.cost_diamond||0,stats=h.stats;let btn='';if(h.owned)btn='<button class="btn btn-secondary w-100" disabled>✅ Sudah Dimiliki</button>';else if(!h.can_buy){let r='';if(state.user.user_level<h.required_level)r=`🔒 Level ${h.required_level}`;else if(costGold>0&&state.storeData.gold<costGold)r='💰 Gold kurang';else if(costDiamond>0&&state.storeData.diamond<costDiamond)r='💎 Diamond kurang';else r='❌ Tidak memenuhi syarat';btn=`<button class="btn btn-secondary w-100" disabled>${r}</button>`;}else{let p=[];costGold&&p.push(`${costGold}<i class="bi bi-coin"></i>`);costDiamond&&p.push(`${costDiamond}<i class="bi bi-gem"></i>`);btn=`<button class="btn btn-primary w-100 buy-hero-btn" data-hero-id="${h.id}">🛒 Beli ${p.length?p.join(' + '):'Gratis'}</button>`;}let badge='';costGold&&(badge+=`<span class="badge bg-warning text-dark me-1"><i class="bi bi-coin"></i> ${costGold}</span>`);costDiamond&&(badge+=`<span class="badge bg-info text-dark"><i class="bi bi-gem"></i> ${costDiamond}</span>`);if(!badge)badge='<span class="badge bg-success">Gratis</span>';return`<div class="col-12"><div class="card"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3">${renderLayeredEmoji(h.emoji, 80)}<div class="flex-grow-1 float-end ms-auto"><h5>${h.name} (${h.type})</h5><p>${h.description}</p></div></div><hr><div class="row small text-muted mb-2"><div class="col-6">${renderTooltip('❤️', stats.hp, 'Health Point', 0)}</div><div class="col-6">${renderTooltip('⚔️', stats.atk, 'Attack', 0)}</div><div class="col-6">${renderTooltip('🛡', stats.def, 'Defense', 0)}</div><div class="col-6">${renderTooltip('⏱️', stats.aspd+'s', 'Attack Speed', 0)}</div><div class="col-6">${renderTooltip('🎯', Math.round(stats.accuracy*100) + '%', 'Accuracy', 0)}</div><div class="col-6">${renderTooltip('👟', Math.round(stats.evasion*100) + '%', 'Evasion', 0)}</div><div class="col-6">${renderTooltip('🛡', Math.round(stats.block_chance*100) + '%', 'Block', 0)}</div><div class="col-6">${renderTooltip('⚡', Math.round((stats.crit_chance_bonus||0)*100) + '%', 'Critical Chance', 0)}</div><div class="col-6">${renderTooltip('🔄', Math.round((stats.counter_chance_bonus||0)*100) + '%', 'Counter Chance', 0)}</div></div><div class="mb-2 small"><span class="badge bg-info me-2">Lv.${h.required_level}</span>${badge}</div>${btn}</div></div></div>`}).join('')}</div>
  `);
  document.querySelectorAll('.buy-hero-btn').forEach(b=>b.addEventListener('click',async(e)=>{const hid=b.dataset.heroId;tg.showLoading();try{const r=await apiFetch('/store/buy-hero',{method:'POST',body:JSON.stringify({hero_id:hid})});r.success?(tg.showToast(r.message,'success'),await loadUserData(),renderStoreHero()):tg.showToast(r.message,'danger');}finally{tg.hideLoading();}}));
  document.getElementById('btn-back-store')?.addEventListener('click', renderStoreHome);
  initTooltips();
  } finally { tg.hideLoading(); }
  }

  async function renderStoreDiamond() {
  stopMiningPreviewTimer(); tg.showLoading();
  try {
  const resp = await apiFetch('/store/diamond-packages');
  if (!resp.success) return;
  state.diamondPackages = resp.data;
  setAppContent(`
  ${renderHeader('btn-back-store', 'Beli Diamond')}
  <div class="row g-3">${state.diamondPackages.map(p=>`<div class="col-6"><div class="card h-100"><div class="card-body text-center d-flex flex-column"><h5>${p.name}</h5><p class="display-6 my-2"><i class="bi bi-gem text-info"></i> ${p.diamond}</p><button class="btn ${state.storeData.gold>=p.gold_cost?'btn-primary':'btn-secondary'} mt-auto w-100 buy-diamond-btn" data-package-id="${p.id}" ${state.storeData.gold<p.gold_cost?'disabled':''}>Beli <i class="bi bi-coin"></i>${p.gold_cost}</button></div></div></div>`).join('')}</div>
  `);
  document.querySelectorAll('.buy-diamond-btn').forEach(b=>b.addEventListener('click',async(e)=>{const pid=b.dataset.packageId;tg.showLoading();try{const r=await apiFetch('/store/buy-diamond',{method:'POST',body:JSON.stringify({package_id:pid})});r.success?(tg.showToast(r.data.message,'success'),state.storeData.gold=r.data.new_gold,state.storeData.diamond=r.data.new_diamond,renderStoreDiamond()):tg.showToast(r.message,'danger');}finally{tg.hideLoading();}}));
  document.getElementById('btn-back-store')?.addEventListener('click', renderStoreHome);
  } finally { tg.hideLoading(); }
  }

  async function renderStoreUpgrade() {
  stopMiningPreviewTimer(); tg.showLoading();
  try {
  const resp = await apiFetch('/store/upgrades');
  if (!resp.success) return;
  state.upgrades = resp.data;
  setAppContent(`
  ${renderHeader('btn-back-store', 'Upgrade')}
  <div class="row g-3">${state.upgrades.map(u=>{const cost=u.next_cost,canBuy=u.can_upgrade&&((u.cost_type==='gold'&&state.storeData.gold>=cost)||(u.cost_type==='diamond'&&state.storeData.diamond>=cost));return`<div class="col-12"><div class="card"><div class="card-body"><h5>${u.name}</h5><p>${u.description}</p><p>Level: ${u.current_level}/${u.max_level}</p><button class="btn ${canBuy?'btn-primary':'btn-secondary'} w-100 buy-upgrade-btn" data-upgrade-id="${u.id}" ${!canBuy?'disabled':''}>${u.current_level>=u.max_level?'Maksimal':`Level ${u.current_level+1} (${u.cost_type==='gold'?'<i class="bi bi-coin"></i>':'💎'}${cost})`}</button></div></div></div>`}).join('')}</div>
  `);
  document.querySelectorAll('.buy-upgrade-btn').forEach(b=>b.addEventListener('click',async(e)=>{const uid=b.dataset.upgradeId;tg.showLoading();try{const r=await apiFetch('/store/buy-upgrade',{method:'POST',body:JSON.stringify({upgrade_id:uid})});r.success?(tg.showToast(r.data.message,'success'),state.storeData.gold=r.data.new_gold,state.storeData.diamond=r.data.new_diamond,renderStoreUpgrade()):tg.showToast(r.message,'danger');}finally{tg.hideLoading();}}));
  document.getElementById('btn-back-store')?.addEventListener('click', renderStoreHome);
  } finally { tg.hideLoading(); }
  }

  // ======================== HELP SCREEN ========================
  function renderHelpScreen() {
  stopMiningPreviewTimer();
  setAppContent(`
  ${renderHeader('btn-back-home', 'Pusat Bantuan')}
  <div class="card">
  <div class="card-body">
  <h5>🎮 Selamat Datang di Battle Arena</h5>
  <p class="small">Game pertarungan otomatis di mana kamu mengendalikan hero melawan musuh komputer. Kumpulkan Gold & Diamond, tingkatkan hero dan skill, lalu taklukkan musuh yang semakin kuat!</p>

  <h5 class="mt-4">🕹️ Cara Bermain Dasar</h5>
  <ol class="small">
  <li><strong>Pilih Hero</strong> – Buka menu <i class="bi bi-person-lines-fill"></i> Pilih Hero, lalu pilih salah satu hero milikmu.</li>
  <li><strong>Mulai Bertarung</strong> – Klik tombol <strong>Mulai Bertarung</strong> (biaya ${BATTLE_COST}💰). Pertarungan berlangsung otomatis.</li>
  <li><strong>Hasil Pertarungan</strong> – Jika menang dapat EXP, Gold, dan kesempatan level up. Kalah tetap dapat hadiah kecil.</li>
  <li><strong>Tingkatkan Hero & User</strong> – EXP menaikkan level user (membuka hero baru) dan level hero (meningkatkan statistik).</li>
  </ol>

  <h5 class="mt-4">💰 Mata Uang</h5>
  <ul class="small">
  <li><i class="bi bi-coin text-warning"></i> <strong>Gold</strong> – Diperoleh dari battle, mining, atau kemenangan. Digunakan untuk unlock hero, upgrade, dan beli diamond.</li>
  <li><i class="bi bi-gem text-info"></i> <strong>Diamond</strong> – Mata uang premium. Dibeli dengan gold atau event. Untuk upgrade spesial.</li>
  </ul>

  <h5 class="mt-4">⛏️ Mining Gold</h5>
  <p class="small">Gold ditambang otomatis setiap jam. Klik ikon <i class="bi bi-coin"></i> di menubar atas untuk klaim. Waktu tersisa ditampilkan di beranda.</p>

  <h5 class="mt-4">🛒 Toko & Upgrade</h5>
  <ul class="small">
  <li><strong>Beli Hero</strong> – Hero baru punya skill pasif unik. Syarat level & gold/diamond.</li>
  <li><strong>Beli Diamond</strong> – Tukar gold dengan diamond.</li>
  <li><strong>Upgrade</strong> – Tingkatkan ATK, DEF, HP, Critical Chance, Akurasi, Counter, dan Resistensi (Poison, Stun, Lifesteal).</li>
  </ul>

  <h5 class="mt-4">📊 Statistik & Ikon</h5>
  <p class="small mb-2">Arahkan kursor ke ikon untuk melihat detail. Berikut legendanya:</p>
  <div class="row small">
  <div class="col-6"><i class="bi bi-heart-fill text-danger"></i> HP (Health Point)</div>
  <div class="col-6"><i class="bi bi-lightning-fill text-warning"></i> ATK (Attack)</div>
  <div class="col-6"><i class="bi bi-shield-fill"></i> DEF (Defense)</div>
  <div class="col-6"><i class="bi bi-clock"></i> ASPD (Attack Speed)</div>
  <div class="col-6"><i class="bi bi-bullseye"></i> Akurasi (peluang hit)</div>
  <div class="col-6"><i class="bi bi-person-walking"></i> Evasi (peluang menghindar)</div>
  <div class="col-6"><i class="bi bi-shield-shaded"></i> Block Chance</div>
  <div class="col-6"><i class="bi bi-arrow-up-short text-success"></i> Bonus upgrade</div>
  <div class="col-6"><i class="bi bi-star-fill text-warning"></i> Critical Chance</div>
  <div class="col-6"><i class="bi bi-arrow-return-left"></i> Counter Chance</div>
  </div>

  <h5 class="mt-4">⚔️ Mekanik Pertarungan</h5>
  <ul class="small">
  <li><strong>Akurasi vs Evasi</strong> – Peluang serangan mengenai = akurasi penyerang - evasi defender.</li>
  <li><strong>Critical Hit</strong> – Peluang 5% + bonus, damage 1.5x + bonus.</li>
  <li><strong>Block</strong> – Mengurangi damage sesuai block reduction.</li>
  <li><strong>Counterattack</strong> – Setelah block, 30% + bonus chance membalas 50% damage.</li>
  <li><strong>Poison</strong> – Damage per detik, bisa dikurangi resistensi.</li>
  <li><strong>Stun</strong> – Menghentikan serangan selama beberapa detik.</li>
  <li><strong>Lifesteal</strong> – Musuh memulihkan HP berdasarkan damage.</li>
  </ul>

  <h5 class="mt-4">📈 Level & Paragon</h5>
  <p class="small">User & hero tidak memiliki batas level maksimal. Setelah level 50 (user) atau 30 (hero), kenaikan exp melambat drastis – disebut <strong>Paragon</strong>. Bonus stat per level juga berkurang, tetapi progres tetap berjalan.</p>

  <h5 class="mt-4">💡 Tips Pro</h5>
  <ul class="small">
  <li>💰 Kumpulkan gold dengan mining rutin dan battle.</li>
  <li>⚔️ Fokus upgrade ATK/DEF terlebih dahulu.</li>
  <li>🛡️ Jika sering kena poison/stun, beli upgrade resistensi.</li>
  <li>🔄 Counterattack sangat berguna untuk hero dengan block tinggi.</li>
  <li>⏳ Jangan lupa klaim mining – gratis gold setiap jam!</li>
  </ul>
  </div>
  </div>
  <div class="d-grid mt-3">
  <button class="btn btn-primary" id="btn-back-home-help">Kembali ke Beranda</button>
  </div>
  `);
  document.getElementById('btn-back-home')?.addEventListener('click', renderHomeScreen);
  document.getElementById('btn-back-home-help')?.addEventListener('click', renderHomeScreen);
  initTooltips(); // jika ada ikon dengan tooltip di sini
  }

  async function renderHistoryScreen() {
  stopMiningPreviewTimer();
  state.currentScreen = 'history';
  state.historyPage = 1;
  state.historyList = [];
  state.historyHasMore = true;

  tg.showLoading();
  try {
  await loadHistoryData();
  setAppContent(`
  ${renderHeader('btn-back-home', 'Riwayat Pertarungan')}
  <div id="history-list-container">
  ${renderHistoryList()}
  </div>
  ${state.historyHasMore ? `
  <div class="text-center mt-3" id="load-more-container">
  <button class="btn btn-outline-primary btn-sm" id="btn-load-more">Muat Lebih Banyak</button>
  </div>
  ` : ''}
  `);
  } finally { tg.hideLoading(); }

  document.getElementById('btn-back-home')?.addEventListener('click', renderHomeScreen);
  document.getElementById('btn-load-more')?.addEventListener('click', loadMoreHistory);
  document.getElementById('history-list-container')?.addEventListener('click', e => {
  const logBtn = e.target.closest('.view-log-btn');
  const simBtn = e.target.closest('.replay-btn');
  if(logBtn) {
  const index = parseInt(logBtn.dataset.index);
  const logData = state.historyList[index]?.log || [];
  showLogViewer(logData);
  } else if(simBtn) {
  const index = parseInt(simBtn.dataset.index);
  const historyItem = state.historyList[index] || [];
  if(historyItem && historyItem.log) {
  animateBattleReplay(historyItem);
  } else {
  tg.showToast('Log tidak tersedia', 'warning');
  }
  }
  });
  }

  function renderHistoryList() {
  if (state.historyList.length === 0) {
  return `<div class="text-center py-5 text-muted">Belum ada riwayat pertarungan.</div>`;
  }

  return state.historyList.map((h, index) => {
  const date = new Date(h.created_at);
  const timeStr = date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
  const dateStr = date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
  const isWin = h.result === 'win';
  const duration = h.duration ? `${h.duration}s` : '?';

  return `
  <div class="card mb-2">
  <div class="card-body p-3">
  <!-- Baris 1: Hero, VS + Badge, Musuh -->
  <div class="d-flex align-items-center justify-content-between">
  <!-- Hero Column -->
  <div class="text-center" style="min-width: 70px;">
  <div>${renderLayeredEmoji(h.hero_emoji, 32)}</div>
  <div class="fw-bold mt-3">${h.hero_name}</div>
  <div class="small">❤️ ${h.player_hp_remaining}</div>
  </div>

  <!-- VS + Badge Column -->
  <div class="d-flex flex-column align-items-center mx-2" style="min-width: 60px;">
  <span style="font-size: 20px;">⚔️</span>
  <span class="badge ${isWin ? 'bg-success' : 'bg-danger'} mt-1">${isWin ? 'MENANG' : 'KALAH'}</span>
  <span class="small text-muted mt-1">⏱️ ${duration}</span>
  </div>

  <!-- Enemy Column -->
  <div class="text-center" style="min-width: 70px;">
  <div>${renderLayeredEmoji(h.enemy_emoji || '👾', 32)}</div>
  <div class="fw-bold mt-3">${h.enemy_name}</div>
  <div class="small">❤️ ${h.enemy_hp_remaining}</div>
  </div>
  </div>

  <!-- Baris 2: Waktu, EXP, Gold -->
  <div class="d-flex justify-content-between align-items-center mt-3">
  <div class="small text-muted">${dateStr}, ${timeStr}</div>
  <div class="small">
  <span class="me-3">⚔️ +${h.exp_gained}</span>
  <span><i class="bi bi-coin"></i> +${h.gold_gained}</span>
  </div>
  <div class="small">
  <button class="btn btn-sm btn-outline-info view-log-btn" data-index='${index}'>
  <i class="bi bi-file-check"></i>
  </button>
  <button class="btn btn-sm btn-outline-warning replay-btn" data-index='${index}'>
  <i class="bi bi-play-fill"></i>
  </button>
  </div>
  </div>
  </div>
  </div>
  `}).join('');
  }

  async function loadHistoryData(page = 1) {
  if (state.historyLoading) return;
  state.historyLoading = true;

  try {
  const resp = await apiFetch(`/history?page=${page}&per_page=10`);
  if (resp.success) {
  if (page === 1) {
  state.historyList = resp.data;
  } else {
  state.historyList = [...state.historyList, ...resp.data];
  }
  state.historyPage = resp.meta.current_page;
  state.historyHasMore = resp.meta.current_page < resp.meta.last_page;
  }
  } catch (e) {
  tg.showToast('Gagal memuat history', 'danger');
  } finally {
  state.historyLoading = false;
  }
  }

  function showLogViewer(logArray) {
  if (!Array.isArray(logArray) || logArray.length === 0) {
  tg.showToast('Tidak ada log pertarungan', 'warning');
  return;
  }

  // Hapus viewer lama jika ada
  const existing = document.getElementById('log-viewer-overlay');
  if (existing) existing.remove();
  if (state.logViewerInterval) {
  clearInterval(state.logViewerInterval);
  state.logViewerInterval = null;
  }

  // Buat overlay
  const overlay = document.createElement('div');
  overlay.id = 'log-viewer-overlay';
  overlay.style.cssText = `
  position: fixed; top: 0; left: 0; width: 100%; height: 100%;
  background: rgba(0,0,0,0.9); z-index: 10000;
  display: flex; flex-direction: column; padding: 16px;
  `;

  overlay.innerHTML = `
  <div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="text-white mb-0"><i class="bi bi-list-ul"></i> Log Pertarungan</h5>
  <button class="btn btn-sm btn-outline-light" id="close-log-viewer">
  <i class="bi bi-x-lg"></i>
  </button>
  </div>
  <div style="flex:1; overflow-y:auto; font-family: monospace; font-size: 13px; color: #aaa;" id="log-viewer-content">
  <!-- Log akan muncul di sini -->
  </div>
  `;
  document.body.appendChild(overlay);

  const contentEl = document.getElementById('log-viewer-content');
  let lineIndex = 0;
  let charIndex = 0;
  let currentLine = '';

  const typeNext = () => {
  if (lineIndex >= logArray.length) {
  clearInterval(state.logViewerInterval);
  state.logViewerInterval = null;
  return;
  }

  const fullLine = logArray[lineIndex];
  if (charIndex < fullLine.length) {
  currentLine += fullLine[charIndex];
  // Render semua baris yang sudah selesai + baris yang sedang diketik
  const linesHtml = logArray.slice(0, lineIndex)
  .map(l => `<div style="color:#ccc;">${escapeHtml(l)}</div>`)
  .join('');
  contentEl.innerHTML = linesHtml + `<div style="color:#fff;">${escapeHtml(currentLine)}</div>`;
  charIndex++;
  } else {
  // Pindah ke baris berikutnya
  lineIndex++;
  charIndex = 0;
  currentLine = '';

  if(lineIndex < logArray.length) {
  typeNext();
  return;
  } else {
  clearInterval(state.logViewerInterval);
  state.logViewerInterval = null;
  return;
  }
  }

  contentEl.scrollTop = contentEl.scrollHeight;
  };

  state.logViewerInterval = setInterval(typeNext, 20); // kecepatan ketik

  // Tombol close
  document.getElementById('close-log-viewer')?.addEventListener('click', () => {
  clearInterval(state.logViewerInterval);
  state.logViewerInterval = null;
  overlay.remove();
  });
  }

  // Helper escape HTML sederhana
  function escapeHtml(text) {
  return text.replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  async function loadMoreHistory() {
  const btn = document.getElementById('btn-load-more');
  if (btn) {
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Memuat...';
  }

  await loadHistoryData(state.historyPage + 1);

  // Update tampilan list
  const container = document.getElementById('history-list-container');
  if (container) {
  container.innerHTML = renderHistoryList();

  container.querySelectorAll('.view-log-btn').forEach(btn => {
  btn.addEventListener('click', (e) => {
  e.stopPropagation();
  const index = parseInt(btn.dataset.index);
  const logData = state.historyList[index]?.log || [];
  showLogViewer(logData);
  });
  });
  container.querySelectorAll('.replay-btn').forEach(btn => {
  btn.addEventListener('click', (e) => {
  e.stopPropagation();
  const index = parseInt(btn.dataset.index);
  const simData = state.historyList[index] || [];
  animateBattleReplay(simData);
  });
  });
  }

  // Update tombol load more
  const loadMoreContainer = document.getElementById('load-more-container');
  if (loadMoreContainer) {
  if (state.historyHasMore) {
  loadMoreContainer.innerHTML = `<button class="btn btn-outline-primary btn-sm" id="btn-load-more">Muat Lebih Banyak</button>`;
  document.getElementById('btn-load-more')?.addEventListener('click', loadMoreHistory);
  } else {
  loadMoreContainer.innerHTML = '';
  }
  }
  }

  const renderHeader = (backId, title = '') => `
  <div class="sticky-top" style="background-color: var(--tg-theme-bg-color); z-index: 100; padding-top: 8px; padding-bottom: 6px;">
  ${renderCurrencyBar()}
  ${backId ? `
  <div class="d-flex align-items-center mb-2">
  <button class="btn btn-link text-decoration-none p-0 me-2" id="${backId}">
  <i class="bi bi-arrow-left fs-5"></i>
  </button>
  ${title ? `<h2 class="h4 mb-0">${title}</h2>` : ''}
  </div>
  ` : ''}
  </div>
  `;

  function animateBattleReplay(historyItem) {
  return new Promise((resolve) => {
  removeOverlays();

  const log = historyItem.log || [];
  if (log.length === 0) {
  tg.showToast('Log kosong', 'warning');
  resolve();
  return;
  }

  // === 1. Ekstrak data dari baris pertama ===
  const firstLine = log[0];
  const vsIndex = firstLine.indexOf(' vs ');
  if (vsIndex === -1) {
  tg.showToast('Format log tidak valid', 'danger');
  resolve();
  return;
  }

  const leftPart = firstLine.substring(0, vsIndex).replace(/^Pertarungan dimulai!\s*/, '');
  const rightPart = firstLine.substring(vsIndex + 4);

  // Fungsi helper untuk ekstrak nama dan HP dari bagian string
  function extractCharacterInfo(part, isEnemy = false) {
  // Pola: (emoji) Nama (HP: XXX)   atau untuk enemy: (emoji) Nama (Level X, HP: XXX)
  const regex = isEnemy
  ? /^(.+?)\s+([^()]+?)\s*\(Level\s*\d+,\s*HP:\s*([\d.]+)\)/
  : /^(.+?)\s+([^()]+?)\s*\(HP:\s*([\d.]+)\)/;
  const match = part.match(regex);
  if (!match) return null;
  return {
  emoji: match[1].trim(),
  name: match[2].trim(),
  maxHp: parseFloat(match[3])
  };
  }

  const playerInfo = extractCharacterInfo(leftPart, false);
  const enemyInfo = extractCharacterInfo(rightPart, true);

  if (!playerInfo || !enemyInfo) {
  tg.showToast('Gagal parsing data karakter', 'danger');
  resolve();
  return;
  }

  let pHp = playerInfo.maxHp;
  let eHp = enemyInfo.maxHp;
  let logIndex = 0;

  // === 2. Overlay ===
  const overlay = document.createElement('div');
  overlay.id = 'battle-replay-overlay';
  overlay.style.cssText = `
  position: fixed; top: 0; left: 0; width: 100%; height: 100%;
  background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
  z-index: 10001; display: flex; flex-direction: column; padding: 16px;
  `;

  overlay.innerHTML = `
  <div class="d-flex justify-content-between align-items-center mb-2">
  <h5 class="text-white mb-0"><i class="bi bi-play-circle"></i> Replay Pertarungan</h5>
  <button class="btn btn-sm btn-outline-light" id="close-replay"><i class="bi bi-x-lg"></i></button>
  </div>

  <div class="d-flex justify-content-around align-items-center flex-grow-1">
  <div class="text-center" style="width: 40%;">
  <div class="position-relative d-inline-block">
  <div id="replay-player-emoji" style="font-size: 80px; transition: all 0.2s;">${renderLayeredEmoji(playerInfo.emoji, 80)}</div>
  <div id="player-damage-popup" class="damage-popup"></div>
  </div>
  <div class="text-white fw-bold mt-2">${playerInfo.name}</div>
  <div class="hp-bar-container mt-2">
  <div class="hp-bar-fill" id="player-hp-fill" style="width: 100%;"></div>
  <span class="hp-text" id="replay-player-hp">❤️ ${pHp}/${playerInfo.maxHp}</span>
  </div>
  </div>

  <div style="font-size: 40px; color: gold; position: relative;">
  ⚔️
  <div id="splash-message" class="splash-message"></div>
  </div>

  <div class="text-center" style="width: 40%;">
  <div class="position-relative d-inline-block">
  <div id="replay-enemy-emoji" style="font-size: 80px; transition: all 0.2s;">${renderLayeredEmoji(enemyInfo.emoji, 80)}</div>
  <div id="enemy-damage-popup" class="damage-popup"></div>
  </div>
  <div class="text-white fw-bold mt-2">${enemyInfo.name}</div>
  <div class="hp-bar-container mt-2">
  <div class="hp-bar-fill" id="enemy-hp-fill" style="width: 100%;"></div>
  <span class="hp-text" id="replay-enemy-hp">❤️ ${eHp}/${enemyInfo.maxHp}</span>
  </div>
  </div>
  </div>

  <div style="max-height: 60px; overflow-y: auto; font-size: 12px; color: #aaa;" id="replay-log-mini"></div>
  `;
  document.body.appendChild(overlay);

  // === 3. CSS ===
  const style = document.createElement('style');
  style.textContent = `
  .hp-bar-container { width: 100%; height: 16px; background: #333; border-radius: 20px; position: relative; overflow: hidden; margin: 0 auto; }
  .hp-bar-fill { height: 100%; background: linear-gradient(90deg, #e74c3c, #c0392b); border-radius: 20px; transition: width 0.3s ease; }
  .hp-text { position: absolute; left: 0; right: 0; top: -2px; color: white; font-size: 12px; font-weight: bold; text-shadow: 1px 1px 2px black; }
  .damage-popup { position: absolute; top: -30px; left: 50%; transform: translateX(-50%); font-size: 24px; font-weight: bold; color: #ff6b6b; opacity: 0; white-space: nowrap; pointer-events: none; text-shadow: 2px 2px 4px black; z-index: 10; }
  .splash-message { position: absolute; top: -60px; left: 50%; transform: translateX(-50%); font-size: 20px; font-weight: bold; color: #f1c40f; opacity: 0; white-space: nowrap; background: rgba(0,0,0,0.7); padding: 8px 16px; border-radius: 30px; pointer-events: none; z-index: 20; border: 1px solid #f1c40f; }
  @keyframes popDamage { 0% { opacity: 0; transform: translateX(-50%) translateY(10px); } 20% { opacity: 1; transform: translateX(-50%) translateY(0); } 80% { opacity: 1; } 100% { opacity: 0; transform: translateX(-50%) translateY(-30px); } }
  @keyframes popSplash { 0% { opacity: 0; transform: translateX(-50%) scale(0.5); } 20% { opacity: 1; transform: translateX(-50%) scale(1); } 80% { opacity: 1; } 100% { opacity: 0; transform: translateX(-50%) scale(1.2); } }
  @keyframes shake { 0%,100% { transform: translateX(0); } 25% { transform: translateX(-8px); } 75% { transform: translateX(8px); } }
  @keyframes attackPlayer { 0%,100% { transform: scale(1) translateX(0); } 50% { transform: scale(1.2) translateX(20px); } }
  @keyframes attackEnemy { 0%,100% { transform: scale(1) translateX(0); } 50% { transform: scale(1.2) translateX(-20px); } }
  `;
  document.head.appendChild(style);

  // === 4. Elemen UI ===
  const playerEmojiEl = document.getElementById('replay-player-emoji');
  const enemyEmojiEl = document.getElementById('replay-enemy-emoji');
  const playerHpFill = document.getElementById('player-hp-fill');
  const enemyHpFill = document.getElementById('enemy-hp-fill');
  const playerHpText = document.getElementById('replay-player-hp');
  const enemyHpText = document.getElementById('replay-enemy-hp');
  const playerDmgPopup = document.getElementById('player-damage-popup');
  const enemyDmgPopup = document.getElementById('enemy-damage-popup');
  const splashMsg = document.getElementById('splash-message');
  const logMini = document.getElementById('replay-log-mini');

  function showDamage(target, amount, isCrit = false) {
  const popup = target === 'player' ? playerDmgPopup : enemyDmgPopup;
  popup.textContent = `-${Math.floor(amount)}`;
  popup.style.color = isCrit ? '#ffaa00' : '#ff6b6b';
  popup.style.animation = 'none';
  popup.offsetHeight;
  popup.style.animation = 'popDamage 1s ease-out forwards';
  }

  function showSplash(text, color = '#f1c40f') {
  splashMsg.textContent = text;
  splashMsg.style.color = color;
  splashMsg.style.animation = 'none';
  splashMsg.offsetHeight;
  splashMsg.style.animation = 'popSplash 1.5s ease-out forwards';
  }

  function updateHpBars() {
  const playerPercent = Math.max(0, (pHp / playerInfo.maxHp) * 100);
  const enemyPercent = Math.max(0, (eHp / enemyInfo.maxHp) * 100);
  playerHpFill.style.width = `${playerPercent}%`;
  enemyHpFill.style.width = `${enemyPercent}%`;
  playerHpText.textContent = `❤️ ${Math.floor(pHp)}/${playerInfo.maxHp}`;
  enemyHpText.textContent = `❤️ ${Math.floor(eHp)}/${enemyInfo.maxHp}`;
  }

  function processNextLog() {
  if (logIndex >= log.length) {
  const winner = pHp > 0 ? playerInfo.name : enemyInfo.name;
  showSplash(`🏆 ${winner} Menang!`, '#2ecc71');
  setTimeout(() => {
  overlay.remove();
  style.remove();
  resolve();
  }, 2500);
  return;
  }

  const line = log[logIndex];
  logMini.innerHTML = `<div style="color:#ccc;">${escapeHtml(line)}</div>`;

  // Deteksi aksi berdasarkan substring
  const hasPlayerAttack = line.includes(playerInfo.name) && line.includes('menyerang');
  const hasEnemyAttack = line.includes(enemyInfo.name) && line.includes('menyerang');
  const hasCounter = line.includes('membalas');
  const isCritical = line.includes('Critical');
  const isBlocked = line.includes('Diblok');
  const isMiss = line.includes('Meleset');
  const isPoison = line.includes('racun');
  const isStun = line.includes('stun');
  const isLifesteal = line.includes('mencuri nyawa');
  const isShield = line.includes('Perisai Suci');

  // Splash spesial
  if (isPoison) showSplash('☠️ Racun!', '#9b59b6');
  else if (isStun) showSplash('💫 Stun!', '#3498db');
  else if (isLifesteal) showSplash('🩸 Lifesteal!', '#e74c3c');
  else if (isShield) showSplash('🛡️ Perisai Suci!', '#2ecc71');
  else if (isMiss) showSplash('💨 Meleset!', '#95a5a6');

  // Animasi emoji
  playerEmojiEl.style.animation = '';
  enemyEmojiEl.style.animation = '';

  if (hasPlayerAttack) {
  playerEmojiEl.style.animation = 'attackPlayer 0.3s ease-out';
  enemyEmojiEl.style.animation = 'shake 0.2s ease-out';
  } else if (hasEnemyAttack) {
  enemyEmojiEl.style.animation = 'attackEnemy 0.3s ease-out';
  playerEmojiEl.style.animation = 'shake 0.2s ease-out';
  } else if (hasCounter) {
  if (line.includes(playerInfo.name)) {
  playerEmojiEl.style.animation = 'attackPlayer 0.25s ease-out';
  enemyEmojiEl.style.animation = 'shake 0.2s ease-out';
  } else {
  enemyEmojiEl.style.animation = 'attackEnemy 0.25s ease-out';
  playerEmojiEl.style.animation = 'shake 0.2s ease-out';
  }
  }

  // Heal/Shield (langsung tambah HP)
  const healMatch = line.match(/(?:mendapatkan|mencuri nyawa)\s.*?\+(\d+)\s*HP/i);
  if (healMatch) {
  const healAmount = parseInt(healMatch[1]);
  if (line.includes(playerInfo.name)) {
  pHp = Math.min(playerInfo.maxHp, pHp + healAmount);
  showSplash(`+${healAmount} HP`, '#2ecc71');
  } else if (line.includes(enemyInfo.name)) {
  eHp = Math.min(enemyInfo.maxHp, eHp + healAmount);
  showSplash(`+${healAmount} HP`, '#2ecc71');
  }
  updateHpBars();
  }

  // Cari klausa "tersisa" untuk update HP
  const playerRemaining = line.match(new RegExp(`${playerInfo.name}.*?tersisa\\s+([\\d.]+)`));
  const enemyRemaining = line.match(new RegExp(`${enemyInfo.name}.*?tersisa\\s+([\\d.]+)`));

  if (playerRemaining) {
  const newHp = parseFloat(playerRemaining[1]);
  if (!isNaN(newHp)) {
  if (newHp < pHp) {
  showDamage('player', pHp - newHp, isCritical);
  }
  pHp = newHp;
  }
  }

  if (enemyRemaining) {
  const newHp = parseFloat(enemyRemaining[1]);
  if (!isNaN(newHp)) {
  if (newHp < eHp) {
  showDamage('enemy', eHp - newHp, isCritical);
  }
  eHp = newHp;
  }
  }

  updateHpBars();
  logIndex++;

  let delay = 700;
  if (hasPlayerAttack || hasEnemyAttack) delay = 500;
  if (hasCounter) delay = 400;
  if (line.includes('Pertarungan selesai')) delay = 2000;
  setTimeout(processNextLog, delay);
  }

  setTimeout(processNextLog, 500);
  updateHpBars();

  document.getElementById('close-replay')?.addEventListener('click', () => {
  overlay.remove();
  style.remove();
  resolve();
  });
  });
  }

  // ======================== INIT ========================
  async function init() {
  tg.showLoading('Memuat...');
  try {
  await loadUserData();
  appEl.addEventListener('click', e => {
  const map = {
  'store-bar': renderStoreHome,
  'gold-bar': renderMiningScreen,
  'diamond-bar': renderStoreDiamond,
  'help-bar': renderHelpScreen,
  'history-bar': renderHistoryScreen  // tambahkan ini
  };
  for (const [id, fn] of Object.entries(map)) if (e.target.closest(`#btn-${id}`)) return fn();
  });
  renderHomeScreen();
  } catch { tg.showToast('Gagal memuat data', 'danger'); } finally { tg.hideLoading(); }
  }
  init();
  })();
  </script>
  @endpush