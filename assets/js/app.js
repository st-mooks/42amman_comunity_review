// =====================================================================
// Shared front-end code: API calls, login guard, page shell, helpers.
// Every page loads this file first. No framework, no build step.
// =====================================================================

// ---------- icons (simple line icons, inline SVG) ----------
const ICON = {
  home: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 3 2 12h3v8h5v-5h4v5h5v-8h3z"/></svg>',
  star: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="m12 2.6 2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.5l-5.9 3.1 1.2-6.5L2.5 9.5l6.6-.9z"/></svg>',
  starLine: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"><path d="m12 2.6 2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.5l-5.9 3.1 1.2-6.5L2.5 9.5l6.6-.9z"/></svg>',
  users: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.6 3.3-5.5 6.5-5.5s5.9 1.9 6.5 5.5"/><circle cx="16.5" cy="7.5" r="3"/><path d="M15.5 13.6c3 .1 5.3 2 5.9 5.4"/></svg>',
  arrow: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M4 12h16M14 5l7 7-7 7"/></svg>',
  search: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 5 5" stroke-linecap="round"/></svg>',
  bars: '<svg viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="11" width="4.5" height="10"/><rect x="9.75" y="4" width="4.5" height="17"/><rect x="16.5" y="8" width="4.5" height="13"/></svg>',
  shield: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 2.5 4 6v6c0 4.6 3.3 8.3 8 9.5 4.7-1.2 8-4.9 8-9.5V6z"/><path d="m9 9 6 6m0-6-6 6"/></svg>',
  tag: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 12.6V4h8.6L21 13.4 13.4 21z"/><circle cx="7.5" cy="8.5" r="1.4"/></svg>',
  download: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5"/></svg>',
  calendar: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="17"/><path d="M3 9h18M9 9v12m6-12v12M3 15h18M8 2v4m8-4v4"/></svg>',
  check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>',
  checkCircle: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9.5"/><path d="m7.5 12.5 3 3 6-6.5"/></svg>',
  ban: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="m5.8 5.8 12.4 12.4"/></svg>',
  warn: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><path d="M12 3 2 20.5h20z"/><path d="M12 10v5m0 2.6v.1" stroke-linecap="round"/></svg>',
  clock: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="11" fill="currentColor"/><path d="M11 6v7h6" fill="none" stroke="#fff" stroke-width="1.8"/></svg>',
  diamond: '<svg viewBox="0 0 24 24"><path d="m12 1 8 8-8 8-8-8z" fill="#ddd9d2"/><path d="m12 3.5 8 8-8 8-8-8z" fill="#c4473e"/><path d="m12 6 8 8-8 8-8-8z" fill="#1f6f3c"/></svg>',
  logout: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10"/></svg>',
  // abuse types
  ring: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="5" r="2.5"/><circle cx="5" cy="17" r="2.5"/><circle cx="19" cy="17" r="2.5"/><path d="m10.6 7.2-4.3 7.6m7.1-7.6 4.3 7.6M7.5 17h9"/></svg>',
  pair: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M3 8h17m-4-4 4 4-4 4M21 16H4m4-4-4 4 4 4"/></svg>',
  group: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.6 3.3-5.5 6.5-5.5s5.9 1.9 6.5 5.5"/><circle cx="16.5" cy="7.5" r="3"/><path d="M15.5 13.6c3 .1 5.3 2 5.9 5.4"/></svg>',
  bulk: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 20V10m5 10V4m5 16v-7m5 7V7"/></svg>',
  repeat: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M20 12a8 8 0 0 1-14 5.3M4 12A8 8 0 0 1 18 6.7"/><path d="M18 2.5v4.2h-4.2M6 21.5v-4.2h4.2"/></svg>',
  bolt: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"><path d="M13.5 2 5 13.5h6L9.5 22 19 9.5h-6z"/></svg>',
  flag: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 21V3m0 1h12l-2.5 4.5L17 13H5"/></svg>',
};

// ---------- small helpers ----------
const $ = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];

// Escape text before putting it in HTML (prevents XSS from names/labels).
function esc(value) {
  return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function starsHtml(n) {
  return `<span class="stars-inline" title="${n} star${n == 1 ? '' : 's'}">${ICON.star} ${n}/3</span>`;
}

function labelChips(labels, catOf = {}) {
  return (labels || []).map((l) => `<span class="chip ${esc(catOf[l] || '')}">${esc(l)}</span>`).join(' ');
}

// "2026-11-08 14:05:00" -> "Sun 8 Nov, 14:05"
function fmtDate(s) {
  if (!s) return '—';
  const d = new Date(String(s).replace(' ', 'T'));
  if (isNaN(d)) return s;
  return d.toLocaleString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
}

function fmtCountdown(seconds) {
  if (seconds <= 0) return 'Closed';
  const d = Math.floor(seconds / 86400);
  const h = Math.floor((seconds % 86400) / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  if (d > 0) return `${d}d ${h}h`;
  if (h > 0) return `${h}h ${m}m`;
  return `${m}m ${seconds % 60}s`;
}

function initials(name) {
  return String(name || '?').split(/\s+/).map((p) => p[0]).slice(0, 2).join('').toUpperCase();
}

function toast(message, type = '') {
  let el = $('#toast');
  if (!el) {
    el = document.createElement('div');
    el.id = 'toast';
    el.setAttribute('role', 'status');
    document.body.appendChild(el);
  }
  el.className = `toast ${type}`;
  el.textContent = message;
  requestAnimationFrame(() => el.classList.add('show'));
  clearTimeout(el._t);
  el._t = setTimeout(() => el.classList.remove('show'), 3200);
}

// Simple confirm dialog that returns a Promise<boolean>.
function confirmDialog({ title, body, ok = 'Confirm', danger = false }) {
  return new Promise((resolve) => {
    const back = document.createElement('div');
    back.className = 'modal-back';
    back.innerHTML = `
      <div class="modal" role="dialog" aria-modal="true" aria-labelledby="dlg-t">
        <h3 id="dlg-t">${esc(title)}</h3>
        <div>${body}</div>
        <div class="actions">
          <button class="btn ghost" data-a="no">Cancel</button>
          <button class="btn ${danger ? 'danger' : ''}" data-a="yes">${esc(ok)}</button>
        </div>
      </div>`;
    const done = (v) => { back.remove(); document.removeEventListener('keydown', onKey); resolve(v); };
    const onKey = (e) => { if (e.key === 'Escape') done(false); };
    back.addEventListener('click', (e) => {
      if (e.target === back || e.target.dataset.a === 'no') done(false);
      if (e.target.dataset.a === 'yes') done(true);
    });
    document.addEventListener('keydown', onKey);
    document.body.appendChild(back);
    $('[data-a="yes"]', back).focus();
  });
}

// ---------- API ----------
// All requests go to the same domain (/api/...), so the session cookie is sent
// automatically and no CORS setup is needed.
class ApiError extends Error {
  constructor(message, status, data) { super(message); this.status = status; this.data = data; }
}

async function api(path, { method = 'GET', body } = {}) {
  const opts = { method, credentials: 'same-origin', headers: { Accept: 'application/json' } };
  if (body !== undefined) {
    opts.headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify(body);
  }
  let res;
  try {
    res = await fetch(path, opts);
  } catch {
    throw new ApiError('Cannot reach the server. Check your connection and try again.', 0);
  }
  let data = null;
  try { data = await res.json(); } catch { /* not JSON */ }
  if (res.status === 401 && !path.includes('/auth/login') && location.pathname !== '/login.html') {
    location.href = '/login.html?next=' + encodeURIComponent(location.pathname + location.search);
    throw new ApiError('Please log in.', 401, data);
  }
  if (!res.ok || !data || data.success === false) {
    throw new ApiError((data && (data.message || (data.errors || []).join(' '))) || `Request failed (${res.status}).`, res.status, data);
  }
  return data;
}

async function logout() {
  try { await api('/api/auth/logout.php', { method: 'POST' }); } catch { /* already out */ }
  location.href = '/login.html';
}

// Check the session and the role before showing a page.
// Students never see staff pages and vice versa (the API checks again).
async function guard(role) {
  try {
    const { user } = await api('/api/auth/me.php');
    if (role && user.role !== role) {
      location.href = user.role === 'staff' ? '/staff/' : '/student/';
      return new Promise(() => {});
    }
    return user;
  } catch (e) {
    if (e.status !== 401) location.href = '/login.html';
    return new Promise(() => {});
  }
}

// ---------- page shell (sidebar + top bar) ----------
const NAV = {
  student: [
    { href: '/student/', key: 'home', icon: 'home', text: 'Home' },
    { href: '/student/rate.html', key: 'rate', icon: 'starLine', text: 'Rate a peer' },
  ],
  staff: [
    { href: '/staff/', key: 'dashboard', icon: 'home', text: 'Dashboard' },
    { href: '/staff/students.html', key: 'students', icon: 'users', text: 'Students' },
    { href: '/staff/ratings.html', key: 'ratings', icon: 'bars', text: 'Ratings' },
    { href: '/staff/abuse.html', key: 'abuse', icon: 'shield', text: 'Abuse Review', count: true },
    { href: '/staff/labels.html', key: 'labels', icon: 'tag', text: 'Labels' },
    { href: '/staff/settings.html', key: 'settings', icon: 'calendar', text: 'Voting window' },
    { href: '/api/staff/export_csv.php', key: 'csv', icon: 'download', text: 'Export (CSV)', csv: true },
  ],
};

function shell(user, active, title = '') {
  const role = user.role;
  document.body.classList.add(role);
  const links = NAV[role].map((n) => `
      <a href="${n.href}" ${n.csv ? 'data-csv' : ''} class="${n.key === active ? 'active' : ''}" ${n.key === active ? 'aria-current="page"' : ''}>
        ${ICON[n.icon]}<span>${n.text}</span>${n.count ? '<span class="count" data-abuse-count hidden></span>' : ''}
      </a>`).join('');

  // Move the page's own elements (with their event listeners) into the layout.
  const main = $('#main');
  const page = document.createDocumentFragment();
  while (main.firstChild) page.appendChild(main.firstChild);
  main.remove();
  const frame = document.createElement('div');
  frame.innerHTML = `
    <div class="shell">
      <aside class="sidebar" aria-label="Main navigation">
        <div>
          <p class="brand ${role}">Peer Rating Platform</p>
          ${role === 'staff' ? '<span class="staff-tag">STAFF</span>' : ''}
        </div>
        <nav class="nav">${links}</nav>
        <img class="logo" src="/assets/img/logo-42amman.png" alt="42 Amman">
        ${role === 'student' ? '<img class="wave" src="/assets/img/wave.png" alt="">' : ''}
      </aside>
      <main class="main" id="main">
        ${role === 'student' ? `
        <div class="m-head">
          <h1>Hi there,<br><span>${esc(user.full_name.split(' ')[0])}</span>!</h1>
          <div><img src="/assets/img/logo-42amman.png" alt="42 Amman"><button class="m-out" data-logout>Log out</button></div>
        </div>` : `<nav class="m-nav" aria-label="Staff navigation">${NAV.staff.map((n) => `<a href="${n.href}" ${n.csv ? 'data-csv' : ''} class="${n.key === active ? 'active' : ''}">${n.text}</a>`).join('')}</nav>`}
        <div class="topbar">
          ${title ? `<h1>${esc(title)}</h1>` : '<span></span>'}
          <div class="row" id="topbar-extra"></div>
          <div class="user-menu">
            <button class="user-btn" aria-haspopup="true" aria-expanded="false">
              <span class="avatar">${esc(initials(user.full_name))}</span>
              <span class="uname">${esc(user.username)}</span>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m5 9 7 7 7-7"/></svg>
            </button>
            <div class="user-pop" hidden>
              <div class="who"><strong>${esc(user.full_name)}</strong>${esc(user.username)} · ${esc(role)}</div>
              <button data-logout>${'Log out'}</button>
            </div>
          </div>
        </div>
        <div id="page-slot"></div>
      </main>
      ${role === 'student' ? `
      <nav class="bottom-nav" aria-label="Student navigation">
        <a href="/student/" class="${active === 'home' ? 'active' : ''}" aria-label="Home">${ICON.home}</a>
        <a href="/student/rate.html" class="${active === 'rate' ? 'active' : ''}" aria-label="Rate a peer">${ICON.search}</a>
      </nav>` : ''}
    </div>`;
  document.body.prepend(frame.firstElementChild);
  $('#page-slot').replaceWith(page);

  fillIcons();
  const btn = $('.user-btn');
  const pop = $('.user-pop');
  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    pop.hidden = !pop.hidden;
    btn.setAttribute('aria-expanded', String(!pop.hidden));
  });
  document.addEventListener('click', () => { pop.hidden = true; btn.setAttribute('aria-expanded', 'false'); });
  $$('[data-logout]').forEach((b) => b.addEventListener('click', logout));
  $$('[data-csv]').forEach((a) => a.addEventListener('click', (e) => {
    e.preventDefault();
    const w = new URLSearchParams(location.search).get('window_id') || localStorage.getItem('windowId') || '';
    location.href = '/api/staff/export_csv.php' + (w ? `?window_id=${encodeURIComponent(w)}` : '');
  }));
}

// Put the SVG for <span data-icon="star"></span> elements.
function fillIcons(root = document) {
  $$('[data-icon]', root).forEach((el) => { el.innerHTML = ICON[el.dataset.icon] || ''; });
}

// Staff: badge with the number of pending abuse cases in the sidebar.
function setAbuseCount(n) {
  $$('[data-abuse-count]').forEach((el) => { el.hidden = !n; el.textContent = n; });
}

// Staff: which voting window am I looking at? (?window_id=… or the last one chosen)
function windowParam() {
  const q = new URLSearchParams(location.search).get('window_id');
  if (q) { try { localStorage.setItem('windowId', q); } catch { /* private mode */ } return q; }
  try { return localStorage.getItem('windowId') || ''; } catch { return ''; }
}
function withWindow(path) {
  const w = windowParam();
  return w ? path + (path.includes('?') ? '&' : '?') + 'window_id=' + encodeURIComponent(w) : path;
}

// Staff: dropdown to switch voting window (shown in the top bar).
function windowPicker(windows, current, onChange) {
  const box = $('#topbar-extra');
  if (!box || !windows || !windows.length) return;
  box.innerHTML = `<label class="window-pick">Window
      <select>${windows.map((w) => `<option value="${w.id}" ${current && w.id == current.id ? 'selected' : ''}>${esc(w.title)}</option>`).join('')}</select>
    </label>`;
  $('select', box).addEventListener('change', (e) => {
    try { localStorage.setItem('windowId', e.target.value); } catch { /* ignore */ }
    const url = new URL(location.href);
    url.searchParams.set('window_id', e.target.value);
    history.replaceState(null, '', url);
    onChange();
  });
}

function showError(target, err) {
  target.innerHTML = `<div class="alert error">${esc(err.message || err)}</div>`;
}
