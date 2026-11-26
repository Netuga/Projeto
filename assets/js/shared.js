function appPath(path) {
  const base = window.APP_BASE || '';
  return /^https?:\/\//i.test(path) || path.startsWith('/') ? path : base + path;
}

const USER_STORAGE_KEY = 'fighters_defense_user';
const TEAM_STORAGE_KEY = 'fighters_defense_team';
const LEGACY_USER_STORAGE_KEY = 'atd_user';
const LEGACY_TEAM_STORAGE_KEY = 'atd_team';
let csrfTokenPromise;
let checkSessionPromise;

function clearAuthStorage() {
  [USER_STORAGE_KEY, LEGACY_USER_STORAGE_KEY].forEach(key => {
    localStorage.removeItem(key);
  });
}

function readStorageValue(primaryKey, legacyKey, fallback = '') {
  const primaryValue = localStorage.getItem(primaryKey);
  if (primaryValue !== null) return primaryValue;
  const legacyValue = localStorage.getItem(legacyKey);
  if (legacyValue !== null) {
    localStorage.setItem(primaryKey, legacyValue);
    return legacyValue;
  }
  return fallback;
}

function readBooleanStorage(primaryKey, legacyKey) {
  return readStorageValue(primaryKey, legacyKey, '0') === '1';
}

async function getCsrfToken(forceRefresh = false) {
  if (forceRefresh) csrfTokenPromise = null;
  if (!csrfTokenPromise) {
    csrfTokenPromise = (async () => {
      // Reuse the session bootstrap already in flight. Parallel first requests
      // can each create a different PHP session and overwrite the auth cookie.
      if (checkSessionPromise) {
        const session = await checkSessionPromise;
        if (session?.csrf_token) return String(session.csrf_token);
      }

      const res = await fetch(appPath('auth.php?action=csrf'), {
        credentials: 'same-origin',
        cache: 'no-store',
      });
      const data = await res.json();
      return String(data?.csrf_token || '');
    })().catch(() => '');
  }
  return csrfTokenPromise;
}

async function appendCsrfToken(formData) {
  const token = await getCsrfToken();
  if (token) {
    formData.set('csrf_token', token);
  }
  return formData;
}

window.getCsrfToken = getCsrfToken;
window.appendCsrfToken = appendCsrfToken;
window.clearAuthStorage = clearAuthStorage;

async function checkSession() {
  if (!checkSessionPromise) {
    checkSessionPromise = (async () => {
      // If CSRF bootstrap started first, let its Set-Cookie finish before
      // asking for the session so both requests use the same PHP session ID.
      if (csrfTokenPromise) await csrfTokenPromise;

      try {
        const res = await fetch(appPath('auth.php?action=session'), {
          credentials: 'same-origin',
          cache: 'no-store',
        });
        const data = await res.json();
        if (data?.csrf_token) {
          csrfTokenPromise = Promise.resolve(String(data.csrf_token));
        }
        if (!data.logged) {
          clearAuthStorage();
        }
        return data;
      } catch (error) {
        return { logged: false, error: true };
      }
    })();
  }

  try {
    return await checkSessionPromise;
  } finally {
    checkSessionPromise = null;
  }
}

async function logout() {
  clearAuthStorage();
  const fd = new FormData();
  fd.append('action', 'logout');
  await appendCsrfToken(fd);
  try {
    await fetch(appPath('auth.php'), {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      cache: 'no-store',
    });
  } catch (error) {}
  window.location.replace(appPath('pages/login.html'));
}

async function saveTeam(teamArray) {
  try {
    localStorage.setItem(TEAM_STORAGE_KEY, JSON.stringify(teamArray));
    return { success: true, message: 'Equipa guardada neste navegador.' };
  } catch (error) {
    return { success: false, message: 'Não foi possível guardar a equipa neste navegador.' };
  }
}

function loadTeamLocal() {
  try {
    return JSON.parse(readStorageValue(TEAM_STORAGE_KEY, LEGACY_TEAM_STORAGE_KEY, '[]') || '[]');
  } catch (error) {
    return [];
  }
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, char => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
  }[char]));
}

function safeAssetUrl(value) {
  const raw = String(value ?? '').trim();
  if (!raw || /[\u0000-\u001F\u007F<>"'`]/.test(raw)) return '';
  if (/^(?:javascript:|vbscript:)/i.test(raw)) return '';
  if (/^data:/i.test(raw) && !/^data:image\//i.test(raw)) return '';
  return raw;
}

function safeCssBackground(value, fallback = 'var(--bg3)') {
  const raw = String(value ?? '').trim();
  if (!raw || /[<>"'`;{}]/.test(raw) || /(?:javascript:|vbscript:|expression\s*\()/i.test(raw)) {
    return fallback;
  }

  if (window.CSS?.supports && !CSS.supports('background', raw)) {
    return fallback;
  }

  return raw;
}

function safeClassToken(value, fallback = '') {
  const raw = String(value ?? '').trim().toLowerCase();
  return /^[a-z0-9_-]+$/.test(raw) ? raw : fallback;
}

window.safeAssetUrl = safeAssetUrl;
window.safeCssBackground = safeCssBackground;
window.safeClassToken = safeClassToken;

async function initUserMenu() {
  const nav = document.querySelector('.topnav');
  if (!nav || nav.querySelector('.user-menu-wrap')) return;

  const data = await checkSession();

  if (data.logged) {
    // A navegação pode já conter um botão de login estático. Não o deixes
    // bloquear a consulta da sessão nem aparecer ao lado do menu autenticado.
    nav.querySelectorAll('.btn-login-nav').forEach(button => button.remove());

    const user = data.user || {};
    const team = loadTeamLocal().map((unit, slot) => unit ? ({
      slot,
      unit_id: unit.id,
      unit_name: unit.name,
      unit_emoji: unit.emoji,
      unit_img: unit.img,
      unit_bg: unit.bg,
    }) : null).filter(Boolean);
    const username = escapeHtml(user.username || 'Player');
    const avatar = escapeHtml(user.avatar || 'U');
    const rank = escapeHtml(user.pvp_rank || 'Bronze');
    const points = Number(user.pvp_points || 0);
    const teamHtml = Array.from({ length: 5 }, (_, index) => {
      const slot = team.find(entry => parseInt(entry.slot, 10) === index);
      const image = escapeHtml(safeAssetUrl(slot?.unit_img || ''));
      const unitName = escapeHtml(slot?.unit_name || '');
      const unitBg = safeCssBackground(slot?.unit_bg || 'rgba(255,255,255,.03)', 'rgba(255,255,255,.03)');
      const fallback = escapeHtml(slot?.unit_emoji || 'FS');
      const content = image
        ? '<img src="' + image + '" alt="' + unitName + '" loading="lazy">'
        : fallback;
      return slot?.unit_id || slot?.unit_emoji
        ? `<div class="ud-team-unit" title="${unitName}" style="background:${unitBg}">${content}</div>`
        : '<div class="ud-team-unit empty" title="Vazio">.</div>';
    }).join('');

    const wrap = document.createElement('div');
    wrap.className = 'user-menu-wrap';
    wrap.innerHTML = `
      <button class="user-btn" type="button" onclick="toggleUserDropdown(event)">
        <div class="user-avatar">${avatar}</div>
        <span>${username}</span>
      </button>
      <div class="user-dropdown" id="userDropdown">
        <div class="ud-header">
          <div class="ud-username">${username}</div>
          <div class="ud-sub">Rank ${rank} - ${points} pts</div>
        </div>
        <div style="padding:10px 16px 4px;color:var(--muted);font-size:10px;letter-spacing:.14em;text-transform:uppercase">Team</div>
        <div class="ud-team">${teamHtml}</div>
        <div class="ud-divider"></div>
        <a href="${appPath('pages/profile.html')}" class="ud-item">Profile</a>
        <a href="${appPath('pages/guides.html')}" class="ud-item">Builder</a>
        <a href="${appPath('pages/units.html')}" class="ud-item">Units</a>
        <div class="ud-divider"></div>
        <button class="ud-item danger" type="button" onclick="logout()">Log out</button>
      </div>
    `;

    nav.appendChild(wrap);

    document.addEventListener('click', event => {
      if (!wrap.contains(event.target)) {
        document.getElementById('userDropdown')?.classList.remove('open');
      }
    });
    return;
  }

  const btn = document.createElement('a');
  btn.href = appPath('pages/login.html');
  btn.className = 'btn-login-nav';
  btn.textContent = 'Sign In';
  nav.appendChild(btn);
}

function toggleUserDropdown(event) {
  event.stopPropagation();
  document.getElementById('userDropdown')?.classList.toggle('open');
}

let adminStatusPromise;

async function checkAdminStatus() {
  if (adminStatusPromise) return adminStatusPromise;

  adminStatusPromise = (async () => {
    try {
      const session = await checkSession();
      if (session.logged) {
        return !!Number(session.user?.is_admin || 0);
      }
    } catch (error) {}
    return false;
  })();

  return adminStatusPromise;
}

async function initAdminVisibility() {
  const adminLinks = Array.from(document.querySelectorAll('[data-admin-only], a[href="admin.html"], a[href^="admin/index.php"], a[href="setup_tables.php"]'));
  if (!adminLinks.length && !document.body.classList.contains('admin-page')) return;

  const isAdmin = await checkAdminStatus();
  document.body.classList.toggle('is-admin', isAdmin);
  document.body.classList.toggle('not-admin', !isAdmin);

  const touchedFooterCols = new Set();
  adminLinks.forEach(link => {
    if (!isAdmin) {
      link.setAttribute('hidden', 'hidden');
    } else {
      link.removeAttribute('hidden');
    }

    const footerCol = link.closest('.footer-col');
    if (footerCol) touchedFooterCols.add(footerCol);
  });

  touchedFooterCols.forEach(col => {
    const visibleLinks = Array.from(col.querySelectorAll('a')).some(link => !link.hidden);
    col.hidden = !isAdmin && !visibleLinks;
  });

  document.dispatchEvent(new CustomEvent('admin-status', { detail: { isAdmin } }));
}

(function initReveal() {
  const markVisible = element => {
    element.classList.add('visible');
    element.dataset.revealBound = 'done';
  };

  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.querySelectorAll('.reveal').forEach(markVisible);
    window.refreshReveals = root => {
      const scope = root && root.querySelectorAll ? root : document;
      scope.querySelectorAll('.reveal').forEach(markVisible);
    };
    return;
  }

  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      entry.target.classList.add('visible');
      entry.target.dataset.revealBound = 'done';
      observer.unobserve(entry.target);
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });

  const bindReveal = element => {
    if (!(element instanceof HTMLElement)) return;
    if (element.dataset.revealBound) return;
    element.dataset.revealBound = '1';
    observer.observe(element);
  };

  const observeTree = root => {
    if (!root) return;
    if (root instanceof HTMLElement && root.classList.contains('reveal')) {
      bindReveal(root);
    }
    if (root.querySelectorAll) {
      root.querySelectorAll('.reveal').forEach(bindReveal);
    }
  };

  window.refreshReveals = observeTree;
  observeTree(document);

  if (!document.body) return;

  const mutationObserver = new MutationObserver(mutations => {
    mutations.forEach(mutation => {
      mutation.addedNodes.forEach(node => {
        if (node instanceof HTMLElement) {
          observeTree(node);
        }
      });
    });
  });

  mutationObserver.observe(document.body, { childList: true, subtree: true });
})();

(function initScrollProgress() {
  if (!document.body || document.getElementById('scroll-progress')) return;

  const bar = document.createElement('div');
  bar.id = 'scroll-progress';
  document.body.appendChild(bar);

  let ticking = false;

  const updateProgress = () => {
    ticking = false;
    const doc = document.documentElement;
    const maxScroll = Math.max(doc.scrollHeight - window.innerHeight, 0);
    const progress = maxScroll > 0 ? Math.min(window.scrollY / maxScroll, 1) : 0;
    doc.style.setProperty('--scroll-progress', progress.toFixed(4));
    bar.style.opacity = maxScroll > 0 ? '0.92' : '0';
  };

  const queueUpdate = () => {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(updateProgress);
  };

  updateProgress();
  window.addEventListener('scroll', queueUpdate, { passive: true });
  window.addEventListener('resize', queueUpdate, { passive: true });
})();

(function markActiveNavLink() {
  const path = window.location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.topnav nav a').forEach(link => {
    const linkPath = new URL(link.href, document.baseURI).pathname.split('/').pop();
    if (linkPath === path) {
      link.classList.add('active');
    }
  });
})();

function animCount(el, end, duration = 1800) {
  if (!el) return;
  let start = 0;
  const step = end / (duration / 16);
  const timer = setInterval(() => {
    start += step;
    if (start >= end) {
      start = end;
      clearInterval(timer);
    }
    el.textContent = Math.floor(start).toLocaleString('en-US');
  }, 16);
}

function initParticles(containerId, count = 24) {
  const container = document.getElementById(containerId);
  if (!container) return;

  const colors = ['#f1f1f1', '#d91f2f', '#ff6b73', '#d8d8d8'];
  for (let i = 0; i < count; i += 1) {
    const particle = document.createElement('div');
    particle.style.cssText = `
      position:absolute;
      border-radius:50%;
      pointer-events:none;
      width:${Math.random() * 4 + 2}px;
      height:${Math.random() * 4 + 2}px;
      left:${Math.random() * 100}%;
      bottom:-10px;
      background:${colors[Math.floor(Math.random() * colors.length)]};
      opacity:0;
      animation:floatUp ${Math.random() * 8 + 8}s linear ${Math.random() * 8}s infinite;
    `;
    container.appendChild(particle);
  }

  if (!document.getElementById('particle-style')) {
    const style = document.createElement('style');
    style.id = 'particle-style';
    style.textContent = `
      @keyframes floatUp {
        0% { transform: translateY(0) scale(0.4); opacity: 0; }
        10% { opacity: 0.75; }
        100% { transform: translateY(-95vh) scale(1); opacity: 0; }
      }
    `;
    document.head.appendChild(style);
  }
}

initUserMenu();
initAdminVisibility();
