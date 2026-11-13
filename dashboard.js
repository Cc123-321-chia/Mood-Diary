/* ============================================================
   dashboard.js — v20261128
   Loads data, renders calendar/cards/profile/theme,
   handles settings + logout, exposes shared state via
   window.MoodDiary for diary-crud.js.
   ============================================================ */
(function () {
  'use strict';

  const themes = [
    { id: 'mint-choco', name: 'Mint Choco', colors: ['#d9e6e1', '#6b4f3c'] },
    { id: 'vintage',    name: 'Vintage',    colors: ['#efe6db', '#b38b6d'] },
    { id: 'macaron',    name: 'Macaron',    colors: ['#fde2e4', '#d88b9c'] },
    { id: 'monochrome', name: 'Monochrome', colors: ['#e8e8e8', '#5a5a5a'] },
    { id: 'ocean',      name: 'Ocean',      colors: ['#c9e4f0', '#2d7a92'] },
    { id: 'forest',     name: 'Forest',     colors: ['#d0e0d0', '#3e704a'] },
    { id: 'sunset',     name: 'Sunset',     colors: ['#fde2c8', '#c9773a'] },
    { id: 'lavender',   name: 'Lavender',   colors: ['#e6d9f0', '#9b72b8'] },
    { id: 'peach',      name: 'Peach',      colors: ['#fde0d9', '#e08163'] },
    { id: 'midnight',   name: 'Midnight',   colors: ['#2c3e50', '#6b8fa8'] }
  ];

  const DEFAULT_AVATAR = '/mood_diary/assets/default-avatar.png';

  const API = {
    read:    'api/get_diaries.php',
    profile: 'api/update_profile.php',
    logout:  'api/logout.php',
  };

  const state = {
    currentUser: { name: '', email: '', avatar: DEFAULT_AVATAR },
    activeTheme: 'mint-choco',
    draftUser:   { name: '', avatar: DEFAULT_AVATAR },
    draftTheme:  'mint-choco',
    diaryEntries: [],
    currentYear:  new Date().getFullYear(),
    currentMonth: new Date().getMonth(),
    csrfToken: '',
    moodsFromDb: [],
    weathersFromDb: [],
  };

  const diaryGrid = document.getElementById('diaryGrid');
  const calendarGrid = document.getElementById('calendarGrid');
  const calendarMonthYear = document.getElementById('calendarMonthYear');
  const prevMonthBtn = document.getElementById('prevMonthBtn');
  const nextMonthBtn = document.getElementById('nextMonthBtn');
  const settingsToggle = document.getElementById('settingsToggle');
  const settingsPanel = document.getElementById('settingsPanel');
  const displayNameSpan = document.getElementById('displayName');
  const displayEmailSpan = document.getElementById('displayEmail');
  const avatarContainer = document.getElementById('avatarContainer');
  const nameInput = document.getElementById('nameInput');
  const emailDisplay = document.getElementById('emailDisplay');
  const saveSettingsBtn = document.getElementById('saveSettingsBtn');
  const cancelSettingsBtn = document.getElementById('cancelSettingsBtn');
  const logoutBtn = document.getElementById('logoutBtn');
  const themeGrid = document.getElementById('themeGrid');
  const settingsAvatarPreview = document.getElementById('settingsAvatarPreview');
  const settingsAvatarUploadBtn = document.getElementById('settingsAvatarUploadBtn');
  const settingsAvatarInput = document.getElementById('settingsAvatarInput');

  const $  = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  function el(tag, className, html) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (html !== undefined) node.innerHTML = html;
    return node;
  }
  function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
  }
  function safeUrl(url) {
    if (typeof url !== 'string' || !url) return '';
    const t = url.trim();
    if (t.startsWith('/')) return t;
    if (/^https?:\/\//i.test(t)) return t;
    return '';
  }
  function isImageValue(v) {
    return typeof v === 'string' &&
      (v.startsWith('http') || v.startsWith('data:image') || v.startsWith('/'));
  }
  function setAvatarImage(container, src) {
    const img = document.createElement('img');
    img.alt = 'avatar';
    img.src = isImageValue(src) ? src : DEFAULT_AVATAR;
    container.replaceChildren(img);
  }
  function formatDateReadable(dateStr) {
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
  }
  function todayISO() {
    const d = new Date();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${d.getFullYear()}-${m}-${day}`;
  }
  function nowHHMM() {
    const d = new Date();
    return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
  }
  function toast(kind, message) {
    let node = $('.diary-demo-notice');
    if (!node) { node = el('div', 'diary-demo-notice'); document.body.appendChild(node); }
    node.dataset.kind = kind;
    node.textContent = message;
    node.classList.add('visible');
    clearTimeout(node._t);
    node._t = setTimeout(() => node.classList.remove('visible'), 3200);
  }
  function postJson(url, body) {
    return fetch(url, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': state.csrfToken },
      body: JSON.stringify(body),
    }).then(async res => {
      let data = null;
      try { data = await res.json(); } catch (_) {}
      return { status: res.status, data };
    });
  }

  function getDiaryByDate(dateStr) { return state.diaryEntries.find(e => e.date === dateStr); }
  function getDiaryById(id) { return state.diaryEntries.find(e => e.id === id); }

  function resetDraftsFromSaved() {
    state.draftUser  = { name: state.currentUser.name, avatar: state.currentUser.avatar };
    state.draftTheme = state.activeTheme;
  }

  async function loadFromDatabase() {
    try {
      const res = await fetch(API.read, { credentials: 'same-origin', cache: 'no-store' });
      if (res.status === 401) { window.location.replace('login.html'); return; }
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Unknown error');

      state.currentUser = {
        name:   data.user.name  || 'User',
        email:  data.user.email || '',
        avatar: data.user.avatar || DEFAULT_AVATAR,
      };
      state.activeTheme = data.theme || 'mint-choco';
      state.csrfToken   = data.csrf || '';
      state.moodsFromDb    = data.moods    || [];
      state.weathersFromDb = data.weathers || [];

      resetDraftsFromSaved();

      state.diaryEntries = (data.diaries || []).map(d => ({
        id:          d.id,
        mood:        d.mood,
        moodCode:    d.moodCode,
        moodLabel:   d.moodLabel,
        weather:     d.weather,
        weatherCode: d.weatherCode,
        weatherLabel: d.weatherLabel,
        content:     d.content,
        date:        (d.date || '').slice(0, 10),
        time:        d.time,
        media:       d.media || [],
      }));

      if (state.diaryEntries.length > 0) {
        const latest = state.diaryEntries.reduce((a, b) => (a.date > b.date ? a : b));
        const parts = latest.date.split('-');
        if (parts.length === 3) {
          state.currentYear  = parseInt(parts[0], 10);
          state.currentMonth = parseInt(parts[1], 10) - 1;
        }
      }

      applyThemeToBody(state.activeTheme);
      renderThemeOptions();
      renderProfile();
      renderDiaryCards();
      renderCalendar(state.currentYear, state.currentMonth);
    } catch (err) {
      console.error('Failed to load diaries:', err);
      diaryGrid.innerHTML = `<div style="padding:2rem;color:var(--text-secondary);">⚠️ Could not load diary data. ${escapeHtml(err.message)}</div>`;
      calendarGrid.innerHTML = '';
    }
  }

  function applyThemeToBody(themeId) {
    document.body.setAttribute('data-theme', themeId);
  }
  function renderThemeOptions() {
    themeGrid.innerHTML = '';
    themes.forEach(theme => {
      const swatch = document.createElement('div');
      swatch.className = `theme-option ${state.draftTheme === theme.id ? 'active' : ''}`;
      swatch.style.background = `linear-gradient(135deg, ${theme.colors[0]} 0%, ${theme.colors[1]} 100%)`;
      swatch.title = theme.name;
      swatch.dataset.themeId = theme.id;
      swatch.addEventListener('click', () => {
        state.draftTheme = theme.id;
        applyThemeToBody(state.draftTheme);
        $$('.theme-option').forEach(sw => sw.classList.toggle('active', sw.dataset.themeId === state.draftTheme));
      });
      themeGrid.appendChild(swatch);
    });
  }

  function renderProfile() {
    displayNameSpan.textContent = state.currentUser.name || '';
    displayEmailSpan.textContent = state.currentUser.email || '';
    setAvatarImage(avatarContainer, state.currentUser.avatar || DEFAULT_AVATAR);
    nameInput.value = state.draftUser.name || '';
    emailDisplay.value = state.currentUser.email || '';
    setAvatarImage(settingsAvatarPreview, state.draftUser.avatar || DEFAULT_AVATAR);
  }
  settingsAvatarUploadBtn.addEventListener('click', () => settingsAvatarInput.click());
  settingsAvatarInput.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (!file) return;
    if (!file.type.startsWith('image/') || file.size > 2 * 1024 * 1024) {
      alert('Please choose an image up to 2 MB.');
      e.target.value = ''; return;
    }
    const reader = new FileReader();
    reader.onload = (ev) => { state.draftUser.avatar = ev.target.result; renderProfile(); };
    reader.readAsDataURL(file);
    e.target.value = '';
  });
  nameInput.addEventListener('input', () => { state.draftUser.name = nameInput.value; });

  function openSettings() {
    resetDraftsFromSaved();
    applyThemeToBody(state.draftTheme);
    renderThemeOptions();
    renderProfile();
    settingsPanel.classList.add('show');
  }
  function closeSettings() { settingsPanel.classList.remove('show'); }
  function toggleSettings() {
    if (settingsPanel.classList.contains('show')) cancelSettings();
    else openSettings();
  }
  async function saveSettings() {
    const trimmedName = (state.draftUser.name || '').trim();
    const payload = { name: trimmedName || state.currentUser.name, theme: state.draftTheme };
    if (state.draftUser.avatar && isImageValue(state.draftUser.avatar)) payload.avatar = state.draftUser.avatar;

    saveSettingsBtn.disabled = true;
    const originalHtml = saveSettingsBtn.innerHTML;
    saveSettingsBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
    try {
      const res = await fetch(API.profile, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': state.csrfToken },
        credentials: 'same-origin', body: JSON.stringify(payload),
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Save failed');
      state.currentUser.name = payload.name;
      if (payload.avatar) state.currentUser.avatar = payload.avatar;
      state.activeTheme = state.draftTheme;
      resetDraftsFromSaved();
      applyThemeToBody(state.activeTheme);
      renderThemeOptions();
      renderProfile();
      closeSettings();
    } catch (err) {
      console.error('Save settings failed:', err);
      alert('❌ Failed to save: ' + err.message);
    } finally {
      saveSettingsBtn.disabled = false;
      saveSettingsBtn.innerHTML = originalHtml;
    }
  }
  function cancelSettings() {
    resetDraftsFromSaved();
    applyThemeToBody(state.activeTheme);
    renderThemeOptions();
    renderProfile();
    closeSettings();
  }
  saveSettingsBtn.addEventListener('click', saveSettings);
  cancelSettingsBtn.addEventListener('click', cancelSettings);
  settingsToggle.addEventListener('click', toggleSettings);

    // ========== Delete Account ==========
  const deleteAccountBtn = document.getElementById('deleteAccountBtn');
  if (deleteAccountBtn) {
    deleteAccountBtn.addEventListener('click', async () => {
      const password = prompt('Enter your password to confirm account deletion:');
      if (!password) return;
      if (!confirm('Your account will be deleted. Continue?')) return;

      deleteAccountBtn.disabled = true;
      const originalText = deleteAccountBtn.innerHTML;
      deleteAccountBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting…';

      try {
        const res = await fetch('api/delete_account.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': state.csrfToken },
          body: JSON.stringify({ password }),
        });
        const data = await res.json();
        if (!res.ok || !data.ok) throw new Error((data && data.error) || 'Delete failed.');

        alert('Account deleted. Redirecting to login page.');
        window.location.replace('login.html');
      } catch (err) {
        alert('❌ ' + (err.message || 'Could not delete account.'));
        deleteAccountBtn.disabled = false;
        deleteAccountBtn.innerHTML = originalText;
      }
    });
  }

  logoutBtn.addEventListener('click', async () => {
    logoutBtn.disabled = true;
    try {
      const response = await fetch(API.logout, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' }, body: '{}',
      });
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.error || 'Logout failed. Please try again.');
      window.location.replace('login.html');
    } catch (err) {
      alert(err.message); logoutBtn.disabled = false;
    }
  });

  function renderCalendar(year, month) {
    const monthNames = ['January','February','March','April','May','June',
      'July','August','September','October','November','December'];
    calendarMonthYear.textContent = `${monthNames[month]} ${year}`;

    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const daysInPrevMonth = new Date(year, month, 0).getDate();

    const today = new Date();
    const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

    let html = '';
    for (let i = firstDay - 1; i >= 0; i--) {
      const day = daysInPrevMonth - i;
      html += `<div class="calendar-day other-month"><span class="day-number">${day}</span></div>`;
    }
    for (let day = 1; day <= daysInMonth; day++) {
      const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
      const entry = getDiaryByDate(dateStr);
      const isToday = dateStr === todayStr;
      const classes = ['calendar-day'];
      if (isToday) classes.push('today');
      if (entry) classes.push('has-entry');
      let inner = `<span class="day-number">${day}</span>`;
      if (entry) {
        inner += `<span class="day-mood">${escapeHtml(entry.mood)}</span>`;
        inner += `<span class="day-weather">${escapeHtml(entry.weather)}</span>`;
        inner += `<span class="day-badge"></span>`;
      }
      html += `<div class="${classes.join(' ')}" data-date="${dateStr}">${inner}</div>`;
    }
    const totalCells = firstDay + daysInMonth;
    const remaining = (7 - (totalCells % 7)) % 7;
    for (let i = 1; i <= remaining; i++) {
      html += `<div class="calendar-day other-month"><span class="day-number">${i}</span></div>`;
    }
    calendarGrid.innerHTML = html;

    calendarGrid.querySelectorAll('.calendar-day.has-entry').forEach(cell => {
      cell.addEventListener('click', () => {
        const entry = getDiaryByDate(cell.dataset.date);
        if (entry && window.MoodDiary && window.MoodDiary.openFullpage) {
          window.MoodDiary.openFullpage(entry);
        }
      });
    });
  }

  function renderDiaryCards() {
    const sorted = [...state.diaryEntries].sort((a, b) => b.date.localeCompare(a.date));
    const titleEl = document.querySelector('.diary-section-title');
    const hintEl  = document.querySelector('.hint');

    if (sorted.length === 0) {
      if (titleEl) titleEl.style.display = 'none';
      if (hintEl)  hintEl.style.display  = 'none';
      diaryGrid.innerHTML = `
        <div class="empty-state">
          <i class="fas fa-feather-alt"></i>
          <p>No diary entries yet.</p>
          <p class="empty-hint">Click “Write Diary” to add your first entry.</p>
        </div>`;
      return;
    }
    if (titleEl) titleEl.style.display = '';
    if (hintEl)  hintEl.style.display  = '';

    diaryGrid.innerHTML = sorted.map(entry => {
      const firstImage = (entry.media || []).find(m => m.type === 'image' && m.url);
      const imageHtml = firstImage
        ? `<img src="${escapeHtml(safeUrl(firstImage.url))}" alt="diary cover">`
        : `<div class="no-image"><i class="fas fa-feather-alt"></i></div>`;

      const excerpt = entry.content.length > 80
        ? entry.content.substring(0, 80) + '…'
        : entry.content;

      return `
        <div class="diary-card" data-id="${escapeHtml(entry.id)}">
          <div class="card-image-area">
            ${imageHtml}
            <div class="card-image-overlay"></div>
            <span class="card-date-badge">${formatDateReadable(entry.date)}</span>
            <span class="card-weather-float">${escapeHtml(entry.weather)}</span>
            <span class="card-mood-center">${escapeHtml(entry.mood)}</span>
          </div>
          <div class="card-info">
            <div class="card-title">${escapeHtml(excerpt)}</div>
            <div class="card-meta">
              <span><i class="far fa-clock"></i> ${escapeHtml(entry.time || '')}</span>
              <span><i class="fas fa-images"></i> ${(entry.media || []).length} media</span>
            </div>
          </div>
        </div>
      `;
    }).join('');

    diaryGrid.querySelectorAll('.diary-card').forEach(card => {
      const id = parseInt(card.dataset.id, 10);
      const entry = getDiaryById(id);
      if (!entry) return;
      card.addEventListener('click', () => {
        if (window.MoodDiary && window.MoodDiary.openFullpage) {
          window.MoodDiary.openFullpage(entry);
        }
      });
    });
  }

  prevMonthBtn.addEventListener('click', () => {
    state.currentMonth--;
    if (state.currentMonth < 0) { state.currentMonth = 11; state.currentYear--; }
    renderCalendar(state.currentYear, state.currentMonth);
  });
  nextMonthBtn.addEventListener('click', () => {
    state.currentMonth++;
    if (state.currentMonth > 11) { state.currentMonth = 0; state.currentYear++; }
    renderCalendar(state.currentYear, state.currentMonth);
  });

  window.MoodDiary = {
    get csrfToken()      { return state.csrfToken; },
    get diaryEntries()   { return state.diaryEntries; },
    get moodsFromDb()    { return state.moodsFromDb; },
    get weathersFromDb() { return state.weathersFromDb; },
    loadFromDatabase,
    getDiaryById,
    getDiaryByDate,
    $, $$, el, escapeHtml, safeUrl, toast, postJson, todayISO, nowHHMM, formatDateReadable,
    openFullpage: null,
    openEditor: null,
  };

  window.addEventListener('pageshow', (event) => {
    if (event.persisted) loadFromDatabase();
  });
  function init() {
    renderThemeOptions();
    loadFromDatabase();
    const createDiaryBtn = document.getElementById('createDiaryBtn');
    if (createDiaryBtn) {
      createDiaryBtn.addEventListener('click', () => {
        if (window.MoodDiary && window.MoodDiary.openEditor) {
          window.MoodDiary.openEditor(null);
        }
      });
    }
  }
  document.addEventListener('DOMContentLoaded', () => {
    setTimeout(init, 0);
  });
})();