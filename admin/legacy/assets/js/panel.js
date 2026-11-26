/*
 * Panel admin UI-only
 * O CRUD vive em PHP (`admin/index.php`).
 * Este ficheiro trata apenas tabs, preview, formularios dinamicos e modais.
 */

const EXTRA_CONFIG = {
  item: { serverTab: 'items', listId: 'item-list', label: 'Item', includeType: true, includeSpeed: true }
};

const UNIT_CATEGORY_OPTIONS = [
  'Dark', 'Water', 'Fire', 'Fighter', 'Light', 'Psychic',
  'Bug', 'Flying', 'Poison', 'Ghost', 'Dragon', 'Grass'
];

const MODE_TYPES = {
  story: 'Story',
  infinite: 'Infinite',
  raid: 'Raid',
  event: 'Event'
};

const adminBasePath = window.APP_BASE || '../../';
const ELEMENT_ICON_ROOT = `${adminBasePath}assets/img/elements/`;
// O PHP injeta esta estrutura no HTML para o JS nao ter de fazer AJAX de leitura.
const adminData = window.__ADMIN_DATA__ || { activeTab: 'units', units: [], modes: [], extras: { item: [] } };

const selectedCategories = new Set();
const selectedUnitIds = new Map();
// Caches para procurar registos por ID quando o utilizador carrega em "Edit".
const unitCache = new Map((adminData.units || []).map(unit => [Number(unit.id), unit]));
const modeCache = new Map((adminData.modes || []).map(mode => [Number(mode.id), mode]));
const extraCache = {
  item: new Map((adminData.extras?.item || []).map(entry => [Number(entry.id), entry])),
};

let pendingDelete = null;
let editingUnitId = Number(document.getElementById('unit-id-hidden')?.value || 0);
let editingModeId = Number(document.getElementById('mode-id-hidden')?.value || 0);
const editingExtras = {
  item: Number(document.getElementById('item-id-hidden')?.value || 0),
};

function adminAssetPath(path) {
  const value = typeof safeAssetUrl === 'function'
    ? safeAssetUrl(path)
    : String(path || '').trim();
  if (!value) return '';
  return /^(https?:|data:|blob:|\/)/i.test(value) ? value : adminBasePath + value;
}

function adminSafeBackground(value, fallback = 'linear-gradient(135deg,#2d171a,#5a1b22)') {
  return typeof safeCssBackground === 'function'
    ? safeCssBackground(value, fallback)
    : String(value || fallback).replace(/[<>"'`;{}]/g, '') || fallback;
}

function adminEscape(value) {
  if (typeof escapeHtml === 'function') return escapeHtml(value);
  return String(value ?? '').replace(/[&<>"']/g, char => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
  }[char]));
}

function escapeAttr(value) {
  return String(value || '')
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

function syncPreviewPanels(activePanelId) {
  document.querySelectorAll('.preview-panel').forEach(panel => {
    panel.hidden = panel.dataset.previewFor !== activePanelId;
  });
}

function activatePanel(panelId) {
  const buttons = document.querySelectorAll('.tab-btn');
  const panels = document.querySelectorAll('.tab-panel');

  buttons.forEach(button => button.classList.toggle('active', button.dataset.panel === panelId));
  panels.forEach(panel => panel.classList.toggle('active', panel.id === panelId));
  syncPreviewPanels(panelId);
}

function tabInit() {
  document.querySelectorAll('.tab-btn').forEach(button => {
    button.addEventListener('click', () => activatePanel(button.dataset.panel));
  });

  const initial = document.querySelector('.tab-btn.active') || document.querySelector('.tab-btn');
  if (initial) {
    activatePanel(initial.dataset.panel);
  }
}

function modeTypeLabel(value) {
  return MODE_TYPES[value] || 'Story';
}

function rarityValue(value) {
  return value === 'common' ? 'rare' : (value || 'rare');
}

function rarityLabel(value) {
  return {
    secreto: 'Secreta',
    myth: 'Mythic',
    legend: 'Legendary',
    epic: 'Epic',
    rare: 'Rare'
  }[rarityValue(value)] || rarityValue(value);
}

function elementSlug(value) {
  return String(value || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

function elementIconPath(value) {
  const slug = elementSlug(value);
  return slug ? `${ELEMENT_ICON_ROOT}${slug}.png` : '';
}

function splitCategories(value) {
  /*
   * Aceita:
   * - array JS;
   * - JSON em string (`["Dark","Water"]`);
   * - texto antigo (`Dark / Water`).
   */
  if (Array.isArray(value)) {
    return value.map(entry => String(entry || '').trim()).filter(Boolean);
  }

  const raw = String(value || '').trim();
  if (!raw) return [];

  if (raw.startsWith('[') && raw.endsWith(']')) {
    try {
      const parsed = JSON.parse(raw);
      if (Array.isArray(parsed)) {
        return parsed.map(entry => String(entry || '').trim()).filter(Boolean);
      }
    } catch (error) {}
  }

  return raw
    .split('/')
    .map(part => part.trim())
    .filter(Boolean);
}

function getSelectedElements() {
  return Array.from(selectedCategories).filter(Boolean);
}

function elementSummaryLabel(values) {
  const elements = splitCategories(values);
  if (!elements.length) return 'N/D';
  return elements.length === 1 ? elements[0] : `${elements[0]} +${elements.length - 1}`;
}

function elementBadgeMarkup(values) {
  const elements = splitCategories(values);
  const primary = elements[0];
  if (!primary) return '';

  const fallback = escapeAttr(primary.slice(0, 2).toUpperCase());
  const extraCount = elements.length > 1
    ? `<span class="unit-preview-element-count">+${elements.length - 1}</span>`
    : '';

  return `
    <div class="unit-preview-element">
      <img src="${escapeAttr(elementIconPath(primary))}" alt="${escapeAttr(primary)}" loading="lazy" onerror="this.hidden=true;this.nextElementSibling.hidden=false">
      <span class="unit-preview-element-fallback" hidden>${fallback}</span>
      ${extraCount}
    </div>
  `;
}

function categoryChipHtml(category) {
  const safe = escapeAttr(category);
  return `<button class="category-chip" type="button" data-category="${safe}">${safe}</button>`;
}

function bindCategoryButton(button) {
  button.addEventListener('click', () => {
    const category = button.dataset.category;
    if (selectedCategories.has(category)) {
      selectedCategories.delete(category);
    } else {
      selectedCategories.add(category);
    }

    button.classList.toggle('active', selectedCategories.has(category));
    updateUnitPreview();
  });
}

function buildCategoryPicker() {
  const picker = document.getElementById('unit-element-picker');
  if (!picker) return;
  picker.innerHTML = UNIT_CATEGORY_OPTIONS.map(categoryChipHtml).join('');
  picker.querySelectorAll('[data-category]').forEach(bindCategoryButton);
}

function ensureCategoryChip(category) {
  const value = String(category || '').trim();
  if (!value) return;
  const picker = document.getElementById('unit-element-picker');
  if (!picker) return;

  const existing = Array.from(picker.querySelectorAll('[data-category]')).find(button => button.dataset.category === value);
  if (existing) return;

  picker.insertAdjacentHTML('beforeend', categoryChipHtml(value));
  const nextButton = Array.from(picker.querySelectorAll('[data-category]')).find(button => button.dataset.category === value);
  if (nextButton) bindCategoryButton(nextButton);
}

function syncCategoryButtons() {
  document.querySelectorAll('.category-chip').forEach(button => {
    button.classList.toggle('active', selectedCategories.has(button.dataset.category));
  });
}

function setSelectedCategoriesFromValue(value) {
  selectedCategories.clear();
  splitCategories(value).forEach(category => {
    ensureCategoryChip(category);
    selectedCategories.add(category);
  });
  syncCategoryButtons();
}

function categoryBadges(value) {
  return splitCategories(value).map(category => `<span class="category-badge">${escapeAttr(category)}</span>`).join('');
}

function getUnitTypeValue() {
  return Array.from(new Set(getSelectedElements())).join(' / ');
}

function skinSlug(value) {
  const slug = String(value || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
  return slug || `skin-${Date.now()}`;
}

function previewUnitImagePath() {
  return document.getElementById('u-img-minimal').value.trim()
    || document.getElementById('u-img-card').value.trim()
    || document.getElementById('u-img').value.trim()
    || document.getElementById('u-img-ingame').value.trim();
}

function parseUltMultiplier(value) {
  const match = String(value || '').replace(',', '.').match(/(\d+(?:\.\d+)?)/);
  return match ? Number(match[1]) : null;
}

function computeUltDamage(atk, multiplierValue) {
  const multiplier = parseUltMultiplier(multiplierValue);
  if (!multiplier) return null;
  return Math.floor(Number(atk || 0) * multiplier);
}

function addSkinRow(skin = {}) {
  const list = document.getElementById('unit-skin-list');
  if (!list) return;

  const name = escapeAttr(skin.name || '');
  const cardImage = escapeAttr(skin.card_image || skin.img_card || skin.image || skin.img || '');
  const ingameImage = escapeAttr(skin.ingame_image || skin.img_ingame || skin.image || skin.img || '');
  const bg = escapeAttr(skin.bg || 'linear-gradient(135deg,#2d171a,#5a1b22)');
  const isShiny = skin.tone === 'shiny' || skin.id === 'shiny';

  list.insertAdjacentHTML('beforeend', `
    <div class="skin-row" data-skin-row>
      <div class="form-group"><label>Name</label><input class="form-input" data-skin-name type="text" value="${name}" placeholder="Skin / personagem"></div>
      <div class="form-group"><label>Card</label><input class="form-input" data-skin-card type="text" value="${cardImage}" placeholder="Personagens/skin-card.png"></div>
      <div class="form-group"><label>In-Game</label><input class="form-input" data-skin-ingame type="text" value="${ingameImage}" placeholder="Personagens/skin-ingame.png"></div>
      <div class="form-group"><label>Background CSS</label><input class="form-input" data-skin-bg type="text" value="${bg}"></div>
      <label class="check-row"><input type="checkbox" data-skin-shiny ${isShiny ? 'checked' : ''}><span>Shiny</span></label>
      <button class="btn btn-danger btn-sm" type="button" onclick="removeSkinRow(this)">Delete</button>
    </div>
  `);
}

function removeSkinRow(button) {
  button.closest('[data-skin-row]')?.remove();
}

function parseSkinRowsFromValue(value) {
  // Lê o valor escondido vindo do PHP e volta a materializa-lo em linhas editaveis.
  const raw = String(value || '').trim();
  if (!raw) return [];

  if (raw.startsWith('[') && raw.endsWith(']')) {
    try {
      const parsed = JSON.parse(raw);
      if (Array.isArray(parsed)) return parsed;
    } catch (error) {}
  }

  return raw.split(/\r\n|\r|\n/).map((line, index) => {
    const parts = line.split('|').map(entry => entry.trim());
    if (!parts.some(Boolean)) return null;

    return {
      id: `custom-${skinSlug(parts[0] || `skin-${index + 1}`)}`,
      name: parts[0] || `Skin ${index + 1}`,
      card_image: parts[1] || '',
      ingame_image: parts[2] || '',
      bg: parts[3] || 'linear-gradient(135deg,#2d171a,#5a1b22)',
      tone: parts[4] === '1' ? 'shiny' : 'custom'
    };
  }).filter(Boolean);
}

function setSkinRows(value) {
  const list = document.getElementById('unit-skin-list');
  if (!list) return;
  list.innerHTML = '';

  const skins = parseSkinRowsFromValue(value);
  skins.forEach(addSkinRow);
}

function collectSkins() {
  return Array.from(document.querySelectorAll('[data-skin-row]')).map((row, index) => {
    const name = row.querySelector('[data-skin-name]').value.trim();
    const cardImage = row.querySelector('[data-skin-card]').value.trim();
    const ingameImage = row.querySelector('[data-skin-ingame]').value.trim();
    const bg = row.querySelector('[data-skin-bg]').value.trim() || 'linear-gradient(135deg,#2d171a,#5a1b22)';
    const shiny = row.querySelector('[data-skin-shiny]').checked;
    if (!name && !cardImage && !ingameImage) return null;

    return {
      id: `custom-${skinSlug(name || `skin-${index + 1}`)}`,
      name: name || `Skin ${index + 1}`,
      card_image: cardImage,
      ingame_image: ingameImage,
      bg,
      tone: shiny ? 'shiny' : 'custom'
    };
  }).filter(Boolean);
}

function serializeSkins() {
  // Antes do submit, converte a grelha visual para o formato texto esperado pelo PHP.
  return collectSkins().map(skin => [
    skin.name || '',
    skin.card_image || '',
    skin.ingame_image || '',
    skin.bg || 'linear-gradient(135deg,#2d171a,#5a1b22)',
    skin.tone === 'shiny' ? '1' : '0'
  ].join('|')).join('\n');
}

function cssUrlValue(path) {
  const resolved = adminAssetPath(path);
  if (!resolved) return 'none';
  return `url("${encodeURI(resolved).replace(/[()'"]/g, match => ({
    '(': '%28',
    ')': '%29',
    "'": '%27',
    '"': '%22',
  }[match]))}")`;
}

function modeBackgroundStyle(gradient, imagePath) {
  const baseGradient = adminSafeBackground(gradient, 'linear-gradient(135deg,#5fa7ff,#153c78 58%,#0c1f3c)');
  return imagePath
    ? `linear-gradient(180deg, rgba(4,10,18,.22), rgba(4,10,18,.74)), ${cssUrlValue(imagePath)}, ${baseGradient}`
    : baseGradient;
}

function bindUnitPreview() {
  // O preview deve observar apenas o formulario, sem tocar na lista guardada.
  document.querySelectorAll('#unit-form input, #unit-form textarea, #unit-form select').forEach(input => {
    input.addEventListener('input', updateUnitPreview);
    input.addEventListener('change', updateUnitPreview);
  });
  updateUnitPreview();
}

function updateUnitPreview() {
  // Preview local: mostra ao utilizador como a unit vai aparecer sem tocar na BD.
  const name = document.getElementById('u-name').value.trim() || 'New Unit';
  const emoji = document.getElementById('u-emoji').value.trim() || 'FS';
  const img = previewUnitImagePath();
  const bg = document.getElementById('u-bg').value.trim() || 'linear-gradient(135deg,#2d171a,#5a1b22)';
  const rarity = rarityValue(document.getElementById('u-rarity').value);
  const tier = document.getElementById('u-tier').value || 'none';
  const description = document.getElementById('u-desc').value.trim();
  const obtain = document.getElementById('u-obtain').value.trim();
  const typeValue = getUnitTypeValue();
  const atkValue = Number(document.getElementById('u-atk').value || 0);
  const ultDamage = computeUltDamage(atkValue, document.getElementById('u-ult-mult').value.trim());

  const art = document.getElementById('preview-art');
  if (!art) return;

  art.style.background = adminSafeBackground(bg);
  art.innerHTML = img
    ? `<img src="${adminEscape(adminAssetPath(img))}" alt="${adminEscape(name)}" loading="lazy">${elementBadgeMarkup(typeValue)}`
    : `<span class="unit-preview-fallback">${emoji}</span>${elementBadgeMarkup(typeValue)}`;

  const rarityEl = document.getElementById('preview-rarity');
  rarityEl.className = `rarity-badge rb-${rarity}`;
  rarityEl.textContent = rarityLabel(rarity);

  const tierEl = document.getElementById('preview-tier');
  tierEl.textContent = tier === 'none' ? 'No Tier' : `Tier ${tier.toUpperCase()}`;

  document.getElementById('preview-name').textContent = name;
  document.getElementById('preview-copy').textContent = description || obtain || 'Fill in the fields to validate the result before saving.';
  document.getElementById('preview-atk').textContent = atkValue.toLocaleString('en-US');
  document.getElementById('preview-spd').textContent = `${Number(document.getElementById('u-spd').value || 0).toFixed(1)}s`;
  document.getElementById('preview-ult').textContent = ultDamage !== null ? ultDamage.toLocaleString('en-US') : 'N/D';
  document.getElementById('preview-elements').textContent = elementSummaryLabel(typeValue);
  document.getElementById('preview-element-list').innerHTML = categoryBadges(typeValue);
}

function bindModePreview() {
  // O mesmo principio aplica-se aos modos: preview isolado do formulario.
  document.querySelectorAll('#mode-form input, #mode-form textarea, #mode-form select').forEach(input => {
    input.addEventListener('input', updateModePreview);
    input.addEventListener('change', updateModePreview);
  });


  updateModePreview();
}

function updateModePreview() {
  // Preview local do mode com contagens e blocks derivados do formulario atual.
  const key = document.getElementById('m-mode-key')?.value || 'story';
  const label = document.getElementById('m-label')?.value.trim() || 'New mode';
  const badge = document.getElementById('m-badge')?.value.trim() || 'Badge';
  const previewTitle = document.getElementById('m-preview-title')?.value.trim() || label;
  const previewText = document.getElementById('m-preview-text')?.value.trim() || 'Fill in the fields to validate the browser before saving.';
  const gradient = document.getElementById('m-gradient')?.value.trim() || 'linear-gradient(135deg,#5fa7ff,#153c78 58%,#0c1f3c)';
  const bgImage = document.getElementById('m-bg-image')?.value.trim() || '';
  const meta = splitTextareaLines(document.getElementById('m-meta')?.value || '');
  const rewards = splitTextareaLines(document.getElementById('m-rewards')?.value || '');
  const notes = splitTextareaLines(document.getElementById('m-notes')?.value || '');

  const art = document.getElementById('mode-preview-art');
  if (art) {
    art.style.background = modeBackgroundStyle(gradient, bgImage);
  }

  document.getElementById('mode-preview-key').textContent = modeTypeLabel(key);
  document.getElementById('mode-preview-badge').textContent = badge;
  document.getElementById('mode-preview-title').textContent = previewTitle;
  document.getElementById('mode-preview-copy').textContent = previewText;
  document.getElementById('mode-preview-meta').textContent = meta.length;
  document.getElementById('mode-preview-rewards').textContent = rewards.length;
  document.getElementById('mode-preview-notes-count').textContent = notes.length;
  document.getElementById('mode-preview-meta-tags').innerHTML = meta.length
    ? meta.slice(0, 4).map(item => `<span class="mode-chip ${adminEscape(key)}">${adminEscape(item)}</span>`).join('')
    : `<span class="mode-chip ${adminEscape(key)}">${adminEscape(modeTypeLabel(key))}</span>`;
  document.getElementById('mode-preview-notes').innerHTML = notes.length
    ? notes.slice(0, 3).map(item => `<div class="mode-preview-note">${adminEscape(item)}</div>`).join('')
    : '<div class="mode-preview-note">Quick tips for this mode will appear here.</div>';
}

function splitTextareaLines(value) {
  return String(value || '')
    .split(/\r\n|\r|\n/)
    .map(entry => entry.trim())
    .filter(Boolean);
}

function clearUnitForm() {
  editingUnitId = 0;
  document.getElementById('unit-id-hidden').value = '';
  document.getElementById('unit-edit-banner').hidden = true;
  document.getElementById('unit-edit-copy').textContent = 'Editing unit';
  document.getElementById('unit-submit-btn').textContent = 'Create Unit';

  document.querySelectorAll('#unit-form input, #unit-form textarea, #unit-form select').forEach(input => {
    if (input.type === 'hidden') return;
    if (input.type === 'checkbox') {
      input.checked = false;
    } else {
      input.value = '';
    }
  });

  document.getElementById('u-bg').value = 'linear-gradient(135deg,#2d171a,#5a1b22)';
  document.getElementById('u-rarity').value = 'rare';
  document.getElementById('u-tier').value = 'c';
  document.getElementById('u-elements-payload').value = '[]';
  document.getElementById('u-skins-text').value = '';

  selectedCategories.clear();
  syncCategoryButtons();
  setSkinRows('');
  updateUnitPreview();
  activatePanel('panel-units');
}

function cancelUnitEdit() {
  clearUnitForm();
}

function editUnit(id) {
  const unit = unitCache.get(Number(id));
  if (!unit) return;

  // O form e reidratado com os dados da lista guardada para entrar em modo edicao.
  editingUnitId = Number(id);
  document.getElementById('unit-id-hidden').value = editingUnitId;
  document.getElementById('unit-edit-banner').hidden = false;
  document.getElementById('unit-edit-copy').textContent = `Editing: ${unit.name}`;
  document.getElementById('unit-submit-btn').textContent = 'Save Unit';

  document.getElementById('u-name').value = unit.name || '';
  document.getElementById('u-emoji').value = unit.emoji || '';
  document.getElementById('u-img').value = unit.img || '';
  document.getElementById('u-img-card').value = unit.img_card || '';
  document.getElementById('u-img-ingame').value = unit.img_ingame || '';
  document.getElementById('u-img-minimal').value = unit.img_minimal || '';
  document.getElementById('u-bg').value = unit.bg || 'linear-gradient(135deg,#2d171a,#5a1b22)';
  document.getElementById('u-rarity').value = rarityValue(unit.rarity);
  document.getElementById('u-tier').value = unit.tier || 'c';
  document.getElementById('u-atk').value = unit.atk || '';
  document.getElementById('u-spd').value = unit.spd || '';
  document.getElementById('u-ult-cd').value = unit.ult_cooldown || '';
  document.getElementById('u-ult-mult').value = unit.ult_multiplier || '';
  document.getElementById('u-video').value = unit.video_url || '';
  document.getElementById('u-wave').value = unit.perf_wave_clear || '';
  document.getElementById('u-boss').value = unit.perf_boss_dps || '';
  document.getElementById('u-utility').value = unit.perf_utility || '';
  document.getElementById('u-survival').value = unit.perf_survival || '';
  document.getElementById('u-obtain').value = unit.obtain_method || '';
  document.getElementById('u-desc').value = unit.description || '';
  document.getElementById('u-passive').value = unit.passive || '';
  document.getElementById('u-ability').value = unit.ability || '';
  document.getElementById('u-ability-desc').value = unit.ability_desc || '';
  document.getElementById('u-meta').checked = Number(unit.is_meta || 0) === 1;
  document.getElementById('u-new').checked = Number(unit.is_new || 0) === 1;

  setSelectedCategoriesFromValue(unit.elements_json || unit.type || '');
  document.getElementById('u-elements-payload').value = JSON.stringify(getSelectedElements());
  setSkinRows(unit.skins_json || '[]');
  document.getElementById('u-skins-text').value = serializeSkins();

  updateUnitPreview();
  activatePanel('panel-units');
  document.getElementById('panel-units').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function clearModeForm() {
  editingModeId = 0;
  document.getElementById('mode-id-hidden').value = '';
  document.getElementById('mode-edit-banner').hidden = true;
  document.getElementById('mode-edit-copy').textContent = 'Editing mode';
  document.getElementById('mode-submit-btn').textContent = 'Create Mode';

  document.querySelectorAll('#mode-form input, #mode-form textarea, #mode-form select').forEach(input => {
    if (input.type === 'hidden') return;
    input.value = '';
  });

  document.getElementById('m-mode-key').value = 'story';
  document.getElementById('m-sort-order').value = '0';
  document.getElementById('m-gradient').value = 'linear-gradient(135deg, #5fa7ff, #153c78 58%, #0c1f3c)';
  updateModePreview();
  activatePanel('panel-modes');
}

function cancelModeEdit() {
  clearModeForm();
}

function editMode(id) {
  const mode = modeCache.get(Number(id));
  if (!mode) return;

  // Tal como nas units, a edicao acontece no mesmo formulario de criacao.
  editingModeId = Number(id);
  document.getElementById('mode-id-hidden').value = editingModeId;
  document.getElementById('mode-edit-banner').hidden = false;
  document.getElementById('mode-edit-copy').textContent = `Editing: ${mode.label}`;
  document.getElementById('mode-submit-btn').textContent = 'Save Mode';
  document.getElementById('m-mode-key').value = mode.mode_key || 'story';
  document.getElementById('m-badge').value = mode.badge || '';
  document.getElementById('m-label').value = mode.label || '';
  document.getElementById('m-sort-order').value = mode.sort_order || 0;
  document.getElementById('m-preview-title').value = mode.preview_title || mode.label || '';
  document.getElementById('m-bg-image').value = mode.bg_image || '';
  document.getElementById('m-gradient').value = mode.gradient || 'linear-gradient(135deg, #5fa7ff, #153c78 58%, #0c1f3c)';
  document.getElementById('m-preview-text').value = mode.preview_text || '';
  document.getElementById('m-meta').value = Array.isArray(mode.meta) ? mode.meta.join('\n') : '';
  document.getElementById('m-rewards').value = Array.isArray(mode.rewards) ? mode.rewards.join('\n') : '';
  document.getElementById('m-notes').value = Array.isArray(mode.notes) ? mode.notes.join('\n') : '';



  updateModePreview();
  activatePanel('panel-modes');
  document.getElementById('panel-modes').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function clearExtraForm(type) {
  const cfg = EXTRA_CONFIG[type];
  if (!cfg) return;

  editingExtras[type] = 0;
  document.getElementById(`${type}-id-hidden`).value = '';
  document.getElementById(`${type}-edit-banner`).hidden = true;
  document.getElementById(`${type}-edit-copy`).textContent = `Editing ${cfg.label}`;
  document.getElementById(`${type}-submit-btn`).textContent = `Create ${cfg.label}`;

  ['name', 'emoji', 'img', 'type', 'obtain', 'dmg', 'spd', 'effect', 'desc'].forEach(field => {
    const input = document.getElementById(`${type}-${field}`);
    if (!input) return;
    if (input.tagName === 'TEXTAREA') {
      input.value = '';
    } else {
      input.value = '';
    }
  });

  document.getElementById(`${type}-bg`).value = 'linear-gradient(135deg,#2d171a,#5a1b22)';
  document.getElementById(`${type}-rarity`).value = 'rare';
  activatePanel(`panel-${cfg.serverTab}`);
}

function editExtra(type, id) {
  const cfg = EXTRA_CONFIG[type];
  const entry = extraCache[type]?.get(Number(id));
  if (!cfg || !entry) return;

  // A categoria selecionada decide que campos existem neste formulario.
  editingExtras[type] = Number(id);
  document.getElementById(`${type}-id-hidden`).value = editingExtras[type];
  document.getElementById(`${type}-edit-banner`).hidden = false;
  document.getElementById(`${type}-edit-copy`).textContent = `Editing: ${entry.name}`;
  document.getElementById(`${type}-submit-btn`).textContent = `Save ${cfg.label}`;

  document.getElementById(`${type}-name`).value = entry.name || '';
  document.getElementById(`${type}-emoji`).value = entry.emoji || '';
  document.getElementById(`${type}-img`).value = entry.img || '';
  document.getElementById(`${type}-bg`).value = entry.bg || 'linear-gradient(135deg,#2d171a,#5a1b22)';
  document.getElementById(`${type}-rarity`).value = rarityValue(entry.rarity);
  document.getElementById(`${type}-obtain`).value = entry.obtain_method || '';
  document.getElementById(`${type}-dmg`).value = entry.dmg_bonus || '';
  document.getElementById(`${type}-effect`).value = entry.effect || '';
  document.getElementById(`${type}-desc`).value = entry.description || '';
  if (cfg.includeType) {
    document.getElementById(`${type}-type`).value = entry.type || '';
  }
  if (cfg.includeSpeed) {
    document.getElementById(`${type}-spd`).value = entry.spd_bonus || '';
  }

  activatePanel(`panel-${cfg.serverTab}`);
  document.getElementById(`panel-${cfg.serverTab}`).scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function toggleUnitSelection(id, name, checked) {
  id = Number(id);
  if (!id) return;
  if (checked) {
    selectedUnitIds.set(id, name);
  } else {
    selectedUnitIds.delete(id);
  }
  updateBulkToolbar();
}

function updateBulkToolbar() {
  const toolbar = document.getElementById('bulk-toolbar');
  const count = selectedUnitIds.size;
  if (!toolbar) return;

  toolbar.hidden = count === 0;
  document.getElementById('bulk-count').textContent = `${count} selected`;
}

function clearUnitSelection() {
  selectedUnitIds.clear();
  document.querySelectorAll('[data-unit-select]').forEach(input => { input.checked = false; });
  updateBulkToolbar();
}

function openDeleteModal(ids, names) {
  // Guarda a intencao de delete em memoria ate o utilizador confirmar no modal.
  pendingDelete = { ids: ids.map(Number).filter(Boolean), names };
  if (!pendingDelete.ids.length) return;

  document.getElementById('delete-copy').textContent = pendingDelete.ids.length === 1
    ? 'You are about to permanently delete this unit.'
    : `You are about to delete ${pendingDelete.ids.length} units permanently.`;
  document.getElementById('delete-list').innerHTML = names.map(name => `<div>${adminEscape(String(name))}</div>`).join('');
  document.getElementById('delete-confirm-btn').disabled = false;
  document.getElementById('delete-msg').className = 'msg';
  document.getElementById('delete-msg').textContent = '';
  document.getElementById('delete-modal').classList.add('open');
}

function closeDeleteModal() {
  pendingDelete = null;
  document.getElementById('delete-modal').classList.remove('open');
}

function deleteUnit(id, name) {
  id = Number(id);
  if (!id) return;
  openDeleteModal([id], [name || `Unit #${id}`]);
}

function deleteSelectedUnits() {
  if (!selectedUnitIds.size) return;
  openDeleteModal(Array.from(selectedUnitIds.keys()), Array.from(selectedUnitIds.values()));
}

function runConfirmedDelete() {
  if (!pendingDelete || !pendingDelete.ids.length) return;
  document.getElementById('delete-unit-ids').value = JSON.stringify(pendingDelete.ids);
  document.getElementById('unit-delete-form').submit();
}

function submitModeDelete(id, name) {
  if (!confirm(`Delete "${name}"?`)) return;
  document.getElementById('delete-mode-id').value = Number(id) || 0;
  document.getElementById('mode-delete-form').submit();
}

function submitExtraDelete(type, id, name) {
  const cfg = EXTRA_CONFIG[type];
  if (!cfg) return;
  if (!confirm(`Delete "${name}"?`)) return;
  document.getElementById('delete-extra-tab').value = cfg.serverTab;
  document.getElementById('delete-extra-server-tab').value = cfg.serverTab;
  document.getElementById('delete-extra-id').value = Number(id) || 0;
  document.getElementById('extra-delete-form').submit();
}

function bindDeleteActions() {
  // Event delegation: um unico listener trata botoes criados or renderizados depois.
  document.addEventListener('change', event => {
    const select = event.target.closest('[data-unit-select]');
    if (!select) return;
    toggleUnitSelection(select.dataset.unitId, select.dataset.unitName, select.checked);
  });

  document.addEventListener('click', event => {
    const actionTarget = event.target.closest('[data-admin-action]');
    if (actionTarget) {
      const action = actionTarget.dataset.adminAction;
      if (action === 'clear-selection') return clearUnitSelection();
      if (action === 'delete-selected') return deleteSelectedUnits();
      if (action === 'close-delete') return closeDeleteModal();
      if (action === 'confirm-delete') return runConfirmedDelete();
      if (action === 'modal-backdrop' && event.target === actionTarget) return closeDeleteModal();
    }

    const editUnitButton = event.target.closest('[data-edit-unit-id]');
    if (editUnitButton) return editUnit(editUnitButton.dataset.editUnitId);

    const deleteUnitButton = event.target.closest('[data-delete-unit-id]');
    if (deleteUnitButton) return deleteUnit(deleteUnitButton.dataset.deleteUnitId, deleteUnitButton.dataset.deleteUnitName);

    const editModeButton = event.target.closest('[data-edit-mode-id]');
    if (editModeButton) return editMode(editModeButton.dataset.editModeId);

    const deleteModeButton = event.target.closest('[data-delete-mode-id]');
    if (deleteModeButton) return submitModeDelete(deleteModeButton.dataset.deleteModeId, deleteModeButton.dataset.deleteModeName);

    const editExtraButton = event.target.closest('[data-extra-edit]');
    if (editExtraButton) return editExtra(editExtraButton.dataset.extraType, editExtraButton.dataset.extraId);

    const deleteExtraButton = event.target.closest('[data-extra-delete]');
    if (deleteExtraButton) return submitExtraDelete(deleteExtraButton.dataset.extraType, deleteExtraButton.dataset.extraId, deleteExtraButton.dataset.extraName);
  });

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') closeDeleteModal();
  });
}

function bindFormSerialization() {
  document.getElementById('unit-form')?.addEventListener('submit', () => {
    // Estes hidden fields sao a "ponte" final entre a UI dinamica e o PHP.
    document.getElementById('u-elements-payload').value = JSON.stringify(getSelectedElements());
    document.getElementById('u-skins-text').value = serializeSkins();
  });

}

function hydrateInitialState() {
  // Reconstrucao do estado visual quando o PHP re-renderiza a pagina apos erro or edicao.
  buildCategoryPicker();
  setSelectedCategoriesFromValue(document.getElementById('u-elements-payload')?.value || '[]');
  setSkinRows(document.getElementById('u-skins-text')?.value || '');
}

function initAdminPage() {
  tabInit();
  bindDeleteActions();
  hydrateInitialState();
  bindFormSerialization();
  bindUnitPreview();
  bindModePreview();
  clearUnitSelection();
}

Object.assign(window, {
  addSkinRow,
  removeSkinRow,
  clearUnitForm,
  cancelUnitEdit,
  clearModeForm,
  cancelModeEdit,
  clearExtraForm,
});

initAdminPage();
