(function () {
  const browserData = {
    story: {
      hashPrefix: 'stage',
      listTitle: 'Story - Stages',
      centerTitle: 'Chapters',
      previewKicker: 'Mode Story',
      items: [
        {
          id: 1,
          label: 'Chapter 1',
          badge: 'Easy',
          gradient: 'linear-gradient(135deg, #5fa7ff, #153c78 58%, #0c1f3c)',
          previewTitle: 'Chapter 1 - Origin Celeste',
          previewText: 'Opens the main game progression and stabilizes early economy with simple bosses and short waves.',
          meta: ['Solo or Co-op', '2 chapters', 'Boss: Prism Sentinel'],
          rewards: ['Gems', 'EXP', 'Common ticket'],
          notes: [
            'Bring a cheap unit to hold the first waves.',
            'Save burst for the final boss and clear the chapter faster.'
          ],
          chapters: [
            { title: 'Chapter A', description: 'Map entry focused on positioning and quick economy.' },
            { title: 'Chapter B', description: 'Introductory boss with simple mechanics and consistent drops.' }
          ]
        },
        {
          id: 2,
          label: 'Chapter 2',
          badge: 'Easy',
          gradient: 'linear-gradient(135deg, #59d1ff, #2568d2 58%, #143565)',
          previewTitle: 'Chapter 2 - Iron Frontier',
          previewText: 'Raises wave tempo and asks for a balanced trio of area damage, economy, and group control.',
          meta: ['Solo or Co-op', '2 chapters', 'Boss: General Mecha'],
          rewards: ['Gems', 'Soul shard', 'Rare ticket'],
          notes: [
            'Coloca slow cedo para evitar leaks nos corredores laterais.',
            'An air-coverage unit helps a lot in the final chapter.'
          ],
          chapters: [
            { title: 'Chapter A', description: 'First multi-route stage, ideal for testing crossroad setups.' },
            { title: 'Chapter B', description: 'Boss wave with high defense and strong resistance to single-target units.' }
          ]
        },
        {
          id: 3,
          label: 'Chapter 3',
          badge: 'Medium',
          gradient: 'linear-gradient(135deg, #3477ff, #133f9b 52%, #0a2254)',
          previewTitle: 'Chapter 3 - Blue Abyss',
          previewText: 'A more technical map with tight turns and durable enemies. This is where story pacing really changes.',
          meta: ['Medium', '3 chapters', 'Boss: Leviathan'],
          rewards: ['Trait reroll', 'Gems', 'EXP'],
          notes: [
            'The best burst window appears near the final turn.',
            'Bring at least one area-damage unit to clear summons.'
          ],
          chapters: [
            { title: 'Chapter A', description: 'Long waves with high-health aquatic enemies.' },
            { title: 'Chapter B', description: 'Mini-boss that speeds up if not eliminated quickly.' },
            { title: 'Chapter C', description: 'Map conclusion with Leviathan and higher rewards.' }
          ]
        },
        {
          id: 4,
          label: 'Chapter 4',
          badge: 'Medium',
          gradient: 'linear-gradient(135deg, #4db7ff, #1d7fbd 50%, #145462)',
          previewTitle: 'Chapter 4 - Flame Forge',
          previewText: 'Stage where area damage becomes mandatory. Flaming enemies arrive in large groups and pressure the center of the map.',
          meta: ['Medium', '2 chapters', 'Boss: Colosso Igneo'],
          rewards: ['Gems', 'Essencia flamejante', 'Rare ticket'],
          notes: [
            'Prioritize splash damage and mid upgrades instead of rushing an expensive carry.',
            'The final boss has a shield phase, so prepare sustained damage.'
          ],
          chapters: [
            { title: 'Chapter A', description: 'Central corridor with compact hordes and high wave speed.' },
            { title: 'Chapter B', description: 'Boss with a temporary shield and zone AoE at the end of the map.' }
          ]
        },
        {
          id: 5,
          label: 'Chapter 5',
          badge: 'Hard',
          gradient: 'linear-gradient(135deg, #cad6ff, #8ca0d7 45%, #d96537 70%, #602310)',
          previewTitle: 'Chapter 5 - Shadow Realm',
          previewText: 'Introduces stealth enemies and mirrored routes. This chapter starts requiring a deeper roster and stronger upgrades.',
          meta: ['Hard', '3 chapters', 'Boss: Umbral King'],
          rewards: ['Trait shard', 'Rare gems', 'Scroll'],
          notes: [
            'Units with reveal or zone damage help handle invisibility.',
            'Split the setup between both entrances to avoid losing control early.'
          ],
          chapters: [
            { title: 'Chapter A', description: 'First half focused on stealth and dual routes.' },
            { title: 'Chapter B', description: 'Mixed wave with elites and sprint mobs.' },
            { title: 'Chapter C', description: 'Final fight against the Umbral King and add spawns.' }
          ]
        },
        {
          id: 6,
          label: 'Chapter 6',
          badge: 'Hard',
          gradient: 'linear-gradient(135deg, #1c56ca, #122d71 56%, #02061c)',
          previewTitle: 'Chapter 6 - Eclipse Ascension',
          previewText: 'Transition chapter into endgame, with chained bosses and much tighter economy than previous stages.',
          meta: ['Hard', '3 chapters', 'Boss: Eclipse Emperor'],
          rewards: ['Rare gems', 'Golden ticket', 'Soul'],
          notes: [
            'Economy that is too slow here usually means defeat before the final boss.',
            'Prepare a main carry and a strong support for the final waves.'
          ],
          chapters: [
            { title: 'Chapter A', description: 'Boss intermedio logo cedo, forcando um setup agressivo.' },
            { title: 'Chapter B', description: 'Armored waves and stun-resistant elites.' },
            { title: 'Chapter C', description: 'Eclipse Emperor with two phases and support summons.' }
          ]
        },
        {
          id: 7,
          label: 'Chapter 7',
          badge: 'Endgame',
          gradient: 'linear-gradient(180deg, #3d3d3d, #191919)',
          previewTitle: 'Chapter 7 - Final Portal',
          previewText: 'The hardest stage of the current campaign, designed for players with near-complete rosters and final upgrades.',
          meta: ['Endgame', '2 chapters', 'Boss: Nexus Void'],
          rewards: ['Gems premium', 'Title', 'Drop raro'],
          notes: [
            'Bring your best boss-damage setup and a reliable support unit.',
            'It is worth studying the spawn order before entering this chapter.'
          ],
          chapters: [
            { title: 'Chapter A', description: 'Preparacao para o gauntlet final com wave pressure constante.' },
            { title: 'Chapter B', description: 'Nexus Void boss, the final check for damage, economy, and control.' }
          ]
        }
      ]
    },
    infinite: {
      hashPrefix: 'wave',
      listTitle: 'Infinite - Loops',
      centerTitle: 'Objectives',
      previewKicker: 'Mode Infinite',
      items: [
        {
          id: 1,
          label: 'Waves 1-50',
          badge: 'Warm-up',
          gradient: 'linear-gradient(135deg, #4ce08d, #1b7e43 58%, #0d2415)',
          previewTitle: 'Loop Inicial - Waves 1-50',
          previewText: 'Perfect window to stabilize economy, define the main line, and prepare the carry for mid game.',
          meta: ['Safe start', 'Economy', 'No extreme boss'],
          rewards: ['Gems', 'EXP', 'Fast time'],
          notes: [
            'Do not spend everything early; the goal here is preparing scaling.',
            'A cheap AoE unit speeds up the early clear a lot.'
          ],
          chapters: [
            { title: 'Initial Setup', description: 'Open with economy and cheap wave clear to buy time.' },
            { title: 'Primeiro Power Spike', description: 'Faz os upgrades chave antes da onda 35 para manter consistencia.' }
          ]
        },
        {
          id: 2,
          label: 'Waves 51-100',
          badge: 'Farm',
          gradient: 'linear-gradient(135deg, #59d1ff, #1e70bf 54%, #102944)',
          previewTitle: 'Loop de Farming - Waves 51-100',
          previewText: 'Difficulty stays controlled, but preparing air coverage and sprint bosses starts paying off.',
          meta: ['Fast farm', 'Boss a cada bloco', 'Good for leaderboard'],
          rewards: ['Trophies', 'Rare gems', 'Upgrade materials'],
          notes: [
            'Keep upgrades spread out instead of investing everything into one unit.',
            'Este intervalo costuma separar runs limpas de runs inconsistentes.'
          ],
          chapters: [
            { title: 'Full Coverage', description: 'Secure ground and air damage before wave 70.' },
            { title: 'Boss Check', description: 'Sprint bosses appear earlier and punish greedy setups.' }
          ]
        },
        {
          id: 3,
          label: 'Waves 101-200',
          badge: 'Mid Game',
          gradient: 'linear-gradient(135deg, #68b6ff, #3658dd 55%, #121f64)',
          previewTitle: 'Mid Game - Waves 101-200',
          previewText: 'This is where unit choices, supports, and upgrade order decide whether the run survives.',
          meta: ['Mid game', 'Upgrades importam', 'Boss stack'],
          rewards: ['Golden ticket', 'Trophies', 'Upgrade material'],
          notes: [
            'Your economy should be locked in before this zone.',
            'Buff and slow supports start becoming more valuable than raw damage.'
          ],
          chapters: [
            { title: 'Controle de Mapa', description: 'Posiciona support units onde influenciam o maior numero de curvas.' },
            { title: 'Boss Stack', description: 'Bosses stack up and require burst in short windows.' }
          ]
        },
        {
          id: 4,
          label: 'Waves 201-350',
          badge: 'Hard',
          gradient: 'linear-gradient(135deg, #84f0d0, #0f7d6d 50%, #062d32)',
          previewTitle: 'Late Game - Waves 201-350',
          previewText: 'The run stops being farm and becomes endurance. Every team slot needs to justify its place.',
          meta: ['Late game', 'Elite waves', 'Precise rotation'],
          rewards: ['Rare material', 'Title', 'Leaderboard'],
          notes: [
            'Cut weak units and focus the slots that truly scale.',
            'If you have an execute, this is where it pays off most.'
          ],
          chapters: [
            { title: 'Slot Management', description: 'Swap redundant supports for useful damage or extra control.' },
            { title: 'Elite Rush', description: 'High-health enemies arrive in long blocks and exhaust the setup.' }
          ]
        },
        {
          id: 5,
          label: 'Waves 351-500',
          badge: 'Elite',
          gradient: 'linear-gradient(135deg, #c6f15b, #698300 48%, #283208)',
          previewTitle: 'Push Competitivo - Waves 351-500',
          previewText: 'A range where many players stall. It requires a strong roster and patience with final upgrade order.',
          meta: ['Competitivo', 'Burst alto', 'Tight margin'],
          rewards: ['Badge exclusiva', 'Trophies altos', 'Soul mythic'],
          notes: [
            'Buff uptime becomes as important as carry damage.',
            'If your line breaks here, it usually lacks sustained area damage.'
          ],
          chapters: [
            { title: 'Burst Window', description: 'Sync ultimates and buffs before double bosses.' },
            { title: 'Final Sustain', description: 'The map requires continuous damage without losing coverage on turns.' }
          ]
        },
        {
          id: 6,
          label: '500+',
          badge: 'Legend',
          gradient: 'linear-gradient(135deg, #f9e27d, #d47d08 52%, #4c1f05)',
          previewTitle: 'Legendary Zone - Wave 500+',
          previewText: 'From here, the run enters record territory. Every wave tests micro, scaling, and perfect composition.',
          meta: ['Record', 'Endurance', 'Best roster'],
          rewards: ['Prestige', 'Ranking', 'Clip de destaque'],
          notes: [
            'Have a locked upgrade plan before entering this zone.',
            'Runs boas aqui costumam depender de units mythic bem optimizadas.'
          ],
          chapters: [
            { title: 'Record Push', description: 'Ideal moment to play more conservatively and protect consistency.' },
            { title: 'Wave Ceiling', description: 'Each block gains so much health that small mistakes can cost the whole run.' }
          ]
        }
      ]
    },
    raid: {
      hashPrefix: 'raid',
      listTitle: 'Raid - Bosses',
      centerTitle: 'Mechanics',
      previewKicker: 'Mode Raid',
      items: [
        {
          id: 1,
          label: 'Ancient Dragon',
          badge: 'Normal',
          gradient: 'linear-gradient(135deg, #ffb35f, #8e3e00 58%, #341404)',
          previewTitle: 'Raid - Ancient Dragon',
          previewText: 'Introductory raid for small groups. Good for learning timing, buffs, and damage distribution across the party.',
          meta: ['3 players', 'Normal', 'Accessible drop'],
          rewards: ['Raid medals', 'Gems', 'Gear raro'],
          notes: [
            'One buff support already changes raid stability a lot.',
            'The boss telegraphs cycles clearly, so it is excellent for learning.'
          ],
          chapters: [
            { title: 'Phase 1', description: 'Slow boss with a large hitbox and occasional adds.' },
            { title: 'Phase 2', description: 'Gains a fire aura and requires more concentrated damage.' }
          ]
        },
        {
          id: 2,
          label: 'Titan Dimensional',
          badge: 'Active',
          gradient: 'linear-gradient(135deg, #b57aff, #5e24b5 55%, #22083e)',
          previewTitle: 'Raid Weekly - Titan Dimensional',
          previewText: 'The active weekly raid. It has high health, multiple burst windows, and excellent rewards for mid or late game players.',
          meta: ['Weekly', 'Boss', 'High medals'],
          rewards: ['Mythic material', 'Golden ticket', 'Raid medals'],
          notes: [
            'Coordinate buffs before stagger to maximize team damage.',
            'Too many slows usually reduce the group total damage output.'
          ],
          chapters: [
            { title: 'Initial Shield', description: 'The Titan opens with high defense and needs sustained damage.' },
            { title: 'Burst Window', description: 'When the shield drops, the team should focus everything in the same window.' },
            { title: 'Enrage', description: 'Faster final phase with simultaneous support adds.' }
          ]
        },
        {
          id: 3,
          label: 'Eclipse Boss',
          badge: 'Hard',
          gradient: 'linear-gradient(135deg, #6d87ff, #1e2c8f 52%, #080f2f)',
          previewTitle: 'Raid - Eclipse Boss',
          previewText: 'Technical raid mixing durable adds with the main boss. Excellent for teams with strong AoE and single-target rotation.',
          meta: ['Co-op', 'Hard', 'Drops fortes'],
          rewards: ['Gear lendario', 'Scroll', 'Raid medals'],
          notes: [
            'Split the team between add control and boss focus.',
            'Area buffs work better than overly spread setups.'
          ],
          chapters: [
            { title: 'Adds de Eclipse', description: 'Frequent summons force AoE to stay active.' },
            { title: 'Boss Central', description: 'Phase mais limpa para gastar cooldowns ofensivos.' }
          ]
        },
        {
          id: 4,
          label: 'Zero Fortress',
          badge: 'Hardcore',
          gradient: 'linear-gradient(135deg, #85c8ff, #2760a6 48%, #081a33)',
          previewTitle: 'Raid - Zero Fortress',
          previewText: 'Armored boss with several absorption phases. Ideal for groups with strong debuffs and steady damage.',
          meta: ['Hardcore', 'Defesa alta', 'Run longa'],
          rewards: ['Legendary core', 'Raid medals', 'Rare gems'],
          notes: [
            'Leva units que escalem bem em lutas longas.',
            'Running burst rotation too early often wastes damage.'
          ],
          chapters: [
            { title: 'Barrier', description: 'Absorption phase where the boss greatly reduces incoming damage.' },
            { title: 'Collapse', description: 'When the barrier falls, the best offensive raid window opens.' }
          ]
        },
        {
          id: 5,
          label: 'Void King',
          badge: 'Endgame',
          gradient: 'linear-gradient(135deg, #d8d8d8, #7e7e7e 46%, #151515)',
          previewTitle: 'Raid - Void King',
          previewText: 'One of the most demanding raids in the game, designed for late-game teams with good communication and highly optimized units.',
          meta: ['Endgame', 'Coordenacao', 'Boss triplo'],
          rewards: ['Soul exclusiva', 'Title', 'Max medals'],
          notes: [
            'As melhores runs aqui dependem de uptime perfeito de buffs.',
            'If the group does not clear phase 2 quickly, the boss snowballs.'
          ],
          chapters: [
            { title: 'Phase 1', description: 'Main boss with support adds and a simple route.' },
            { title: 'Phase 2', description: 'Clones appear and the group needs to decide priorities.' },
            { title: 'Phase 3', description: 'Final enrage with massive damage and a very tight margin for error.' }
          ]
        }
      ]
    },
    event: {
      hashPrefix: 'event',
      listTitle: 'Events - Rotation',
      centerTitle: 'Challenges',
      previewKicker: 'Special Event',
      items: [
        {
          id: 1,
          label: 'Battle of Worlds',
          badge: 'Active',
          gradient: 'linear-gradient(135deg, #f0a53d, #8f4c0a 56%, #2d1704)',
          previewTitle: 'Event - Battle of Worlds',
          previewText: 'Limited event focused on dense waves, global goals, and exclusive rewards that do not appear in other modes.',
          meta: ['Active now', 'Co-op', 'Exclusive drop'],
          rewards: ['Exclusive unit', 'Gems', 'Theme pack'],
          notes: [
            'Playing in a group is better for speeding up event goals.',
            'Guarda as tuas melhores supports para o boss da ultima rota.'
          ],
          chapters: [
            { title: 'Phase 1', description: 'Initial waves with mixed enemies and moderate economy.' },
            { title: 'Phase 2', description: 'Central boss with mirrored routes and constant pressure.' }
          ]
        },
        {
          id: 2,
          label: 'Night of Shadows',
          badge: 'Weekly',
          gradient: 'linear-gradient(135deg, #5f62ff, #24166f 56%, #09061c)',
          previewTitle: 'Event - Night of Shadows',
          previewText: 'Weekly rotation focused on stealth mobs and fast progression rewards for active players.',
          meta: ['Weekly', 'Stealth mobs', 'Upgrades'],
          rewards: ['Trait shard', 'Scroll', 'Rare ticket'],
          notes: [
            'Leva deteccao or aoe consistente para compensar as waves invisiveis.',
            'Os bosses desta rotacao punem setups demasiado lentos.'
          ],
          chapters: [
            { title: 'Entry', description: 'First waves with stealth enemies and sprint elites.' },
            { title: 'Final', description: 'Boss with clones and a short burst window.' }
          ]
        },
        {
          id: 3,
          label: 'Eternal Winter',
          badge: 'Next',
          gradient: 'linear-gradient(135deg, #b9e8ff, #3f7ebd 54%, #10243f)',
          previewTitle: 'Event - Eternal Winter',
          previewText: 'Seasonal rotation centered on freeze, slow, and long routes, ideal for control teams and sustained damage.',
          meta: ['Sazonal', 'Freeze', 'Control'],
          rewards: ['Limited skin', 'Rare soul', 'Gems premium'],
          notes: [
            'As melhores runs aproveitam slows em sobreposicao.',
            'Area damage is more valuable than isolated burst.'
          ],
          chapters: [
            { title: 'Storm', description: 'Long corridors and mobs resistant to short burst.' }
          ]
        }
      ]
    }
  };

  let gamemodeRequest;

  function resolveModeImage(path) {
    const value = typeof safeAssetUrl === 'function'
      ? safeAssetUrl(path)
      : String(path || '').trim();
    if (!value) return '';
    const base = window.APP_BASE || '';
    return /^(https?:|data:|blob:|\/)/i.test(value) ? value : base + value;
  }

  function cssUrlValue(path) {
    const resolved = resolveModeImage(path);
    if (!resolved) return 'none';
    return `url("${encodeURI(resolved).replace(/[()'"]/g, (match) => ({
      '(': '%28',
      ')': '%29',
      "'": '%27',
      '"': '%22',
    }[match]))}")`;
  }

  function safeGradient(value) {
    const fallback = 'linear-gradient(135deg, #5fa7ff, #153c78 58%, #0c1f3c)';
    return typeof safeCssBackground === 'function'
      ? safeCssBackground(value, fallback)
      : String(value || fallback).replace(/[<>"'`;{}]/g, '') || fallback;
  }

  function fetchRemoteGamemodeData() {
    if (!gamemodeRequest) {
      gamemodeRequest = fetch('api/gamemodes.php?action=list')
        .then((response) => {
          if (!response.ok) {
            throw new Error('Gamemode API unavailable');
          }
          return response.json();
        })
        .catch(() => ({}));
    }

    return gamemodeRequest;
  }

  function normalizeRemoteItem(item, fallbackItem, index) {
    const safeItem = item || {};
    const chapters = Array.isArray(safeItem.chapters) ? safeItem.chapters : [];

    return {
      id: Number(safeItem.id || fallbackItem?.id || index + 1),
      label: safeItem.label || fallbackItem?.label || `Bloco ${index + 1}`,
      badge: safeItem.badge || fallbackItem?.badge || '',
      gradient: safeGradient(safeItem.gradient || fallbackItem?.gradient),
      bgImage: safeItem.bgImage || safeItem.bg_image || fallbackItem?.bgImage || '',
      previewTitle: safeItem.previewTitle || fallbackItem?.previewTitle || safeItem.label || `Bloco ${index + 1}`,
      previewText: safeItem.previewText || fallbackItem?.previewText || '',
      meta: Array.isArray(safeItem.meta) ? safeItem.meta : (fallbackItem?.meta || []),
      rewards: Array.isArray(safeItem.rewards) ? safeItem.rewards : (fallbackItem?.rewards || []),
      notes: Array.isArray(safeItem.notes) ? safeItem.notes : (fallbackItem?.notes || []),
      chapters: chapters.length
        ? chapters.map((chapter, chapterIndex) => ({
            id: Number(chapter.id || chapterIndex + 1),
            title: chapter.title || `Chapter ${chapterIndex + 1}`,
            description: chapter.description || '',
          }))
        : (fallbackItem?.chapters || []),
    };
  }

  async function getModeConfig(modeKey) {
    const fallbackConfig = browserData[modeKey];

    if (!fallbackConfig) {
      return null;
    }

    const remotePayload = await fetchRemoteGamemodeData();
    const remoteMode = remotePayload?.[modeKey];

    return {
      ...fallbackConfig,
      items: Array.isArray(remoteMode?.items)
        ? remoteMode.items.map((item, index) => normalizeRemoteItem(item, fallbackConfig.items[index], index))
        : [],
    };
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function renderPills(items, className) {
    return (items || [])
      .map((item) => `<span class="${className}">${escapeHtml(item)}</span>`)
      .join('');
  }

  function getHashId(config) {
    const match = window.location.hash.match(new RegExp(`^#${config.hashPrefix}-(\\d+)$`));
    return match ? Number(match[1]) : null;
  }

  async function morntBrowser(root) {
    if (root.dataset.modeMornted === 'true' || root.dataset.modeMornted === 'pending') {
      return;
    }

    root.dataset.modeMornted = 'pending';

    const config = await getModeConfig(root.dataset.modeBrowser);

    if (!config) {
      root.dataset.modeMornted = 'false';
      return;
    }

    root.dataset.modeMornted = 'true';

    const syncHash = root.dataset.syncHash !== 'false';
    const ctaHref = root.dataset.ctaHref || '';
    const ctaLabel = root.dataset.ctaLabel || '';

    root.innerHTML = `
      <div class="mode-browser-app">
        <section class="mode-browser-panel mode-browser-sidebar">
          <h3 class="mode-browser-panel-title">${escapeHtml(config.listTitle)}</h3>
          <div class="mode-browser-card-list"></div>
        </section>
        <section class="mode-browser-panel mode-browser-chapter">
          <h3 class="mode-browser-panel-title">${escapeHtml(config.centerTitle)}</h3>
          <div class="mode-browser-chapter-host">
            <p class="mode-browser-placeholder">Seleciona uma fase a esquerda.</p>
          </div>
        </section>
        <section class="mode-browser-panel mode-browser-preview">
          <div class="mode-browser-preview-host" style="width:100%">
            <p class="mode-browser-placeholder">Seleciona um bloco para ver os detalhes.</p>
          </div>
        </section>
      </div>
    `;

    const list = root.querySelector('.mode-browser-card-list');
    const chapterHost = root.querySelector('.mode-browser-chapter-host');
    const previewHost = root.querySelector('.mode-browser-preview-host');

    if (!config.items.length) {
      list.innerHTML = '<p class="mode-browser-placeholder">No entries created for this category.</p>';
      chapterHost.innerHTML = '<p class="mode-browser-placeholder">Cria os blocks no painel admin para eles aparecerem aqui.</p>';
      previewHost.innerHTML = '<p class="mode-browser-placeholder">When you add a mode in admin, the preview will appear in this column.</p>';
      return;
    }

    list.innerHTML = config.items
      .map(
        (item) => `
          <button
            class="mode-browser-stage-card"
            style="--mode-card-gradient:${item.gradient};--mode-card-image:${cssUrlValue(item.bgImage)}"
            data-mode-id="${escapeHtml(item.id)}"
            type="button"
          >
            <span class="mode-browser-card-badge">${escapeHtml(item.badge)}</span>
            <span class="mode-browser-card-label">${escapeHtml(item.label)}</span>
          </button>
        `
      )
      .join('');

    function renderItem(item, updateHash) {
      list.querySelectorAll('.mode-browser-stage-card').forEach((card) => {
        card.classList.toggle('active', card.dataset.modeId === String(item.id));
      });

      chapterHost.innerHTML = `
        <div class="mode-browser-chapter-content">
          ${
            item.chapters && item.chapters.length
              ? item.chapters
                  .map(
                    (chapter) => `
                      <article class="mode-browser-chapter-card">
                        <h4>${escapeHtml(chapter.title)}</h4>
                        <p>${escapeHtml(chapter.description)}</p>
                      </article>
                    `
                  )
                  .join('')
              : '<p class="mode-browser-placeholder">No chapters registered for this entry.</p>'
          }
        </div>
      `;

      previewHost.innerHTML = `
        <div class="mode-browser-preview-wrap" style="--mode-preview-image:${cssUrlValue(item.bgImage)}">
          <div>
            <div class="mode-browser-preview-kicker">${escapeHtml(config.previewKicker)}</div>
            <h2 class="mode-browser-preview-title">${escapeHtml(item.previewTitle)}</h2>
          </div>
          <p class="mode-browser-preview-text">${escapeHtml(item.previewText)}</p>
          <div class="mode-browser-meta">${renderPills(item.meta, 'mode-browser-meta-pill')}</div>
          <div class="mode-browser-preview-block">
            <h3>Rewards</h3>
            <div class="mode-browser-rewards">${renderPills(item.rewards, 'mode-browser-reward')}</div>
          </div>
          <div class="mode-browser-preview-block">
            <h3>Quick Tips</h3>
            <ul class="mode-browser-notes">
              ${(item.notes || []).map((note) => `<li>${escapeHtml(note)}</li>`).join('')}
            </ul>
          </div>
          ${
            ctaHref && ctaLabel
              ? `<div class="mode-link-row"><a class="btn btn-outline" href="${escapeHtml(typeof safeAssetUrl === 'function' ? safeAssetUrl(ctaHref) : ctaHref)}">${escapeHtml(ctaLabel)}</a></div>`
              : ''
          }
        </div>
      `;

      if (syncHash && updateHash) {
        window.history.replaceState(null, '', `#${config.hashPrefix}-${item.id}`);
      }
    }

    function renderById(id, updateHash) {
      const selectedItem = config.items.find((item) => item.id === id) || config.items[0];
      if (!selectedItem) return;
      renderItem(selectedItem, updateHash);
    }

    list.querySelectorAll('.mode-browser-stage-card').forEach((card) => {
      card.addEventListener('click', () => {
        renderById(Number(card.dataset.modeId), true);
      });
    });

    if (syncHash) {
      window.addEventListener('hashchange', () => {
        const idFromHash = getHashId(config);
        if (idFromHash) {
          renderById(idFromHash, false);
        }
      });
    }

    renderById(syncHash ? getHashId(config) : null, false);
  }

  async function morntAllBrowsers() {
    const roots = Array.from(document.querySelectorAll('[data-mode-browser]'));
    await Promise.all(roots.map((root) => morntBrowser(root)));
  }

  window.FSModeBrowser = {
    data: browserData,
    morntAll: morntAllBrowsers,
    mornt: morntBrowser
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', morntAllBrowsers);
  } else {
    morntAllBrowsers();
  }
})();
