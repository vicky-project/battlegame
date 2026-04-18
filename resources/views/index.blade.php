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
  <span class="badge bg-secondary"><i class="bi bi-star-fill"></i> Lv. ${state.user?.user_level ?? 1}</span>
  <div>
  <span class="badge bg-warning text-dark me-2" style="cursor:pointer;" id="btn-gold-bar"><i class="bi bi-coin"></i> ${state.storeData.gold}</span>
  <span class="badge bg-info text-dark me-2" style="cursor:pointer;" id="btn-diamond-bar"><i class="bi bi-gem"></i> ${state.storeData.diamond}</span>
  <span class="badge bg-primary me-2" style="cursor:pointer;" id="btn-store-bar"><i class="bi bi-shop"></i></span>
  <span class="badge bg-success me-2" style="cursor:pointer;" id="btn-history-bar"><i class="bi bi-clock-history"></i></span>
  <span class="badge bg-secondary" style="cursor:pointer;" id="btn-help-bar"><i class="bi bi-question-circle"></i></span>
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

  // ======================== HOME SCREEN ========================
  async function renderHomeScreen() {
  const user = state.user || {};
  const selectedHero = state.userHeroes.find(h => h.id === state.selectedHeroId);
  const expPercent = user.exp_to_next_level > 0 ? ((user.user_exp / user.exp_to_next_level) * 100) : 100;
  const expDisplay = `${user.user_exp || 0} / ${user.exp_to_next_level || '---'}`;
  const levelLabel = user.user_level > 50 ? `${user.user_level} (Paragon)` : user.user_level;

  setAppContent(`
  ${renderHeader(null)}
  <div class="d-flex align-items-center mb-3"><div style="font-size:48px;margin-right:12px;">😊</div><div><h2 class="h5 mb-0">Selamat datang, ${state.user?.first_name || 'Petarung'}!</h2><span class="badge bg-secondary">Lv. ${levelLabel}</span></div></div>
  ${selectedHero ? `<div class="card mb-3"><div class="card-body d-flex align-items-center"><span style="font-size:40px;margin-right:16px;">${renderLayeredEmoji(selectedHero.emoji||'👤', 40)}</span><div class="flex-grow-1 ms-3"><div class="d-flex justify-content-between align-items-center"><h5 class="card-title mb-0">${selectedHero.name}</h5><button class="btn btn-sm btn-outline-secondary" id="btn-change-hero"><i class="bi bi-arrow-repeat"></i></button></div><div class="row small mb-2"><div class="col-4">❤️ ${selectedHero.stats.hp}</div><div class="col-4">⚔️ ${selectedHero.stats.atk}</div><div class="col-4">🛡️ ${selectedHero.stats.def}</div></div><div class="d-flex justify-content-between small mb-1"><span>Level ${selectedHero.level} ${selectedHero.level > 30 ? '<small class="text-muted">(Paragon)</small>' : ''}</span><span>${selectedHero.exp || 0} / ${selectedHero.next_level_exp || 100}</span></div><div class="progress" style="height: 8px;"><div class="progress-bar bg-info" role="progressbar" style="width: ${(selectedHero.exp / selectedHero.next_level_exp) * 100 || 0}%"></div></div></div></div></div>`
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
  <div class="d-flex">
  <!-- Emoji -->
  <div class="me-3">
  ${renderLayeredEmoji(h.emoji || '👤', 48)}
  </div>
  <!-- Nama, Level, Stats Dasar -->
  <div class="flex-grow-1">
  <div class="d-flex justify-content-between align-items-start">
  <div>
  <h5 class="card-title mb-0">${h.name}<span class="badge bg-secondary">Lv.${h.level} ${h.level > 30 ? '<small class="text-muted">(Paragon)</small>' : ''}</span>
  </h5>
  </div>
  ${isSelected ? '<span class="badge bg-primary">✔️</span>' : ''}
  </div>
  <!-- Stats Dasar (2 kolom) -->
  <div class="row small mt-2">
  <div class="col-6">❤️ HP: ${stats.hp} ${bonusHp > 0 ? `<span class="text-success small"><i class="bi bi-arrow-up"></i>+${bonusHp}</span>` : ''}</div>
  <div class="col-6">⚔️ ATK: ${stats.atk} ${bonusAtk > 0 ? `<span class="text-success small"><i class="bi bi-arrow-up"></i>+${bonusAtk}</span>` : ''}</div>
  <div class="col-6">🛡️ DEF: ${stats.def} ${bonusDef > 0 ? `<span class="text-success small"><i class="bi bi-arrow-up"></i>+${bonusDef}</span>` : ''}</div>
  <div class="col-6">⏱️ ASPD: ${stats.aspd}s</div>
  </div>
  </div>
  </div>
  <!-- Baris 2: Skill Tambahan (2 kolom) -->
  <div class="row small text-muted mt-3">
  <div class="col-6">🎯 Akurasi: ${Math.round(stats.accuracy * 100)}% ${bonusAccuracy > 0 ? `<span class="text-success small"><i class="bi bi-arrow-up"></i>+${Math.round(bonusAccuracy * 100)}%</span>` : ''}</div>
  <div class="col-6">👟 Evasi: ${Math.round(stats.evasion * 100)}%</div>
  <div class="col-6">🛡️ Block: ${Math.round(stats.block_chance * 100)}%</div>
  <div class="col-6">⚡ Crit: +${Math.round((stats.crit_chance_bonus || 0) * 100)}% ${bonusCrit > 0 ? `<span class="text-success small"><i class="bi bi-arrow-up"></i>+${Math.round(bonusCrit * 100)}%</span>` : ''}</div>
  <div class="col-6">🔄 Counter: +${Math.round((stats.counter_chance_bonus || 0) * 100)}% ${bonusCounter > 0 ? `<span class="text-success small"><i class="bi bi-arrow-up"></i>+${Math.round(bonusCounter * 100)}%</span>` : ''}</div>
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
  <div class="row g-3">${resp.data.categories.map(c=>`<div class="col-6"><div class="card h-100 store-category-card" data-category="${c.id}"><div class="card-body text-center"><i class="bi bi-${c.icon} fs-1"></i><h5 class="card-title">${c.name}</h5><p class="card-text small">${c.description}</p></div></div></div>`).join('')}</div>
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
  <div class="row g-3">${state.allHeroes.map(h=>{const costGold=h.cost_gold||0,costDiamond=h.cost_diamond||0,stats=h.stats;let btn='';if(h.owned)btn='<button class="btn btn-secondary w-100" disabled>✅ Sudah Dimiliki</button>';else if(!h.can_buy){let r='';if(state.user.user_level<h.required_level)r=`🔒 Level ${h.required_level}`;else if(costGold>0&&state.storeData.gold<costGold)r='💰 Gold kurang';else if(costDiamond>0&&state.storeData.diamond<costDiamond)r='💎 Diamond kurang';else r='❌ Tidak memenuhi syarat';btn=`<button class="btn btn-secondary w-100" disabled>${r}</button>`;}else{let p=[];costGold&&p.push(`${costGold}<i class="bi bi-coin"></i>`);costDiamond&&p.push(`${costDiamond}<i class="bi bi-gem"></i>`);btn=`<button class="btn btn-primary w-100 buy-hero-btn" data-hero-id="${h.id}">🛒 Beli ${p.length?p.join(' + '):'Gratis'}</button>`;}let badge='';costGold&&(badge+=`<span class="badge bg-warning text-dark me-1"><i class="bi bi-coin"></i> ${costGold}</span>`);costDiamond&&(badge+=`<span class="badge bg-info text-dark"><i class="bi bi-gem"></i> ${costDiamond}</span>`);if(!badge)badge='<span class="badge bg-success">Gratis</span>';return`<div class="col-12"><div class="card"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><span style="font-size:48px;margin-right: 16px;">${renderLayeredEmoji(h.emoji)}</span><div class="flex-grow-1 float-end ms-auto"><h5>${h.name} (${h.type})</h5><p>${h.description}</p></div></div><div class="row small mb-1"><div class="col-6">❤️ ${stats.hp}</div><div class="col-6">⚔️ ${stats.atk}</div><div class="col-6">🛡️ ${stats.def}</div><div class="col-6">⏱️ ${stats.aspd}s</div></div><div class="row small text-muted mb-2"><div class="col-6">🎯 Akurasi: ${Math.round(stats.accuracy*100)}%</div><div class="col-6">👟 Evasi: ${Math.round(stats.evasion*100)}%</div><div class="col-6">🛡️ Block: ${Math.round(stats.block_chance*100)}%</div><div class="col-6">⚡ Crit: +${Math.round((stats.crit_chance_bonus||0)*100)}%</div><div class="col-6">🔄 Counter: +${Math.round((stats.counter_chance_bonus||0)*100)}%</div></div><div class="mb-2 small"><span class="badge bg-info me-2">Lv.${h.required_level}</span>${badge}</div>${btn}</div></div></div>`}).join('')}</div>
  `);
  document.querySelectorAll('.buy-hero-btn').forEach(b=>b.addEventListener('click',async(e)=>{const hid=b.dataset.heroId;tg.showLoading();try{const r=await apiFetch('/store/buy-hero',{method:'POST',body:JSON.stringify({hero_id:hid})});r.success?(tg.showToast(r.message,'success'),await loadUserData(),renderStoreHero()):tg.showToast(r.message,'danger');}finally{tg.hideLoading();}}));
  document.getElementById('btn-back-store')?.addEventListener('click', renderStoreHome);
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
  <div class="row g-3">${state.diamondPackages.map(p=>`<div class="col-6"><div class="card h-100"><div class="card-body text-center d-flex flex-column"><h5>${p.name}</h5><p class="display-6 my-2"><i class="bi bi-gem"></i> ${p.diamond}</p><button class="btn ${state.storeData.gold>=p.gold_cost?'btn-primary':'btn-secondary'} mt-auto w-100 buy-diamond-btn" data-package-id="${p.id}" ${state.storeData.gold<p.gold_cost?'disabled':''}>Beli <i class="bi bi-coin"></i>${p.gold_cost}</button></div></div></div>`).join('')}</div>
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
  <div class="card"><div class="card-body">
  <h5>🎮 Cara Bermain</h5><p>Battle Arena adalah game pertarungan otomatis di mana kamu mengendalikan hero melawan musuh komputer.</p><ol class="small"><li><strong>Pilih Hero</strong> - Pilih hero yang sudah kamu miliki dari menu "Pilih Hero".</li><li><strong>Mulai Bertarung</strong> - Klik "Mulai Bertarung" untuk melawan musuh secara otomatis. Biaya: ${BATTLE_COST} gold.</li><li><strong>Hasil Pertarungan</strong> - Dapatkan EXP dan gold jika menang. Jika kalah, tetap dapat sedikit hadiah.</li><li><strong>Tingkatkan Hero</strong> - EXP akan menaikkan level hero, meningkatkan HP, ATK, DEF.</li></ol>
  <h5>💰 Mata Uang</h5><ul class="small"><li><strong>Gold</strong> - Diperoleh dari battle, mining, atau kemenangan.</li><li><strong>Diamond</strong> - Mata uang premium, untuk upgrade spesial.</li></ul>
  <h5>⛏️ Mining Gold</h5><p class="small">Gold dapat ditambang otomatis setiap jam. Klik ikon <i class="bi bi-coin me-2"></i> di atas untuk klaim.</p>
  <h5>🛒 Toko</h5><ul class="small"><li><strong>Beli Hero</strong> - Hero baru memiliki skill pasif unik.</li><li><strong>Beli Diamond</strong> - Tukar gold dengan diamond.</li><li><strong>Upgrade</strong> - Tingkatkan ATK, DEF, HP, Critical Chance.</li></ul>
  <h5>⚔️ Pertarungan</h5><ul class="small"><li>Setiap serangan memiliki peluang critical, block, dan miss.</li><li>Hero & musuh memiliki skill pasif/spesial.</li></ul>
  <h5>📈 Level & EXP</h5><p class="small">User level & hero level meningkat seiring EXP.</p>
  <h5>❓ Tips</h5><ul class="small"><li>Kumpulkan gold dengan mining dan battle rutin.</li><li>Fokus upgrade ATK/DEF terlebih dahulu.</li><li>Jangan lupa klaim mining setiap jam!</li></ul>
  </div></div>
  <div class="d-grid mt-3"><button class="btn btn-primary" id="btn-back-home-help">Kembali ke Beranda</button></div>
  `);
  document.getElementById('btn-back-home')?.addEventListener('click', renderHomeScreen);
  document.getElementById('btn-back-home-help')?.addEventListener('click', renderHomeScreen);
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
  const btn = e.target.closest('.view-log-btn');
  if(!btn) return;
  const index = parseInt(btn.dataset.index);
  const logData = state.historyList[index]?.log || [];
  showLogViewer(logData);
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

  return `
  <div class="card mb-2">
  <div class="card-body p-3">
  <!-- Baris 1: Hero, VS + Badge, Musuh -->
  <div class="d-flex align-items-center justify-content-between">
  <!-- Hero Column -->
  <div class="text-center" style="min-width: 70px;">
  <div style="font-size: 32px;">${h.hero_emoji}</div>
  <div class="fw-bold">${h.hero_name}</div>
  <div class="small">❤️ ${h.player_hp_remaining}</div>
  </div>

  <!-- VS + Badge Column -->
  <div class="d-flex flex-column align-items-center mx-2" style="min-width: 60px;">
  <span style="font-size: 20px;">⚔️</span>
  <span class="badge ${isWin ? 'bg-success' : 'bg-danger'} mt-1">${isWin ? 'MENANG' : 'KALAH'}</span>
  </div>

  <!-- Enemy Column -->
  <div class="text-center" style="min-width: 70px;">
  <div style="font-size: 32px;">${h.enemy_emoji || '👾'}</div>
  <div class="fw-bold">${h.enemy_name}</div>
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
  📋
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
  }
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