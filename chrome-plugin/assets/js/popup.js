// ImmoAggregator — Popup JS

const $ = id => document.getElementById(id);

let config = {};
let autoSyncOn = true;

// ── Init ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
  await loadStats();
  await loadConfig();
});

async function loadStats() {
  try {
    const stats = await sendMsg({ type: 'GET_STATS' });
    $('stat-favorites').textContent = stats.favorites;
    if (stats.lastSync) {
      const d = new Date(stats.lastSync);
      $('last-sync').textContent = 'Sync ' + d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    }
  } catch (e) {
    setStatus('Erreur stats', 'error');
  }
}

async function loadConfig() {
  config = await sendMsg({ type: 'GET_CONFIG' });
  $('cfg-server').value  = config.serverUrl  || '';
  $('cfg-apikey').value  = config.apiKey     || '';
  $('cfg-gallery').value = config.galleryUrl || '';
  autoSyncOn = config.autoSync !== false;
  updateToggle();

  // Mettre à jour le lien galerie
  if (config.galleryUrl) {
    $('btn-gallery').href = config.galleryUrl;
  }
}

// ── Bouton Sync ───────────────────────────────────────────────
$('btn-sync').addEventListener('click', async () => {
  $('btn-sync').disabled = true;
  setStatus('Synchronisation en cours…', '');
  try {
    await sendMsg({ type: 'FORCE_SYNC' });
    setStatus('✅ Synchronisation réussie', '');
    await loadStats();
  } catch (e) {
    setStatus('❌ Erreur de synchronisation', 'error');
  } finally {
    $('btn-sync').disabled = false;
  }
});

// ── Bouton Scraper favoris ────────────────────────────────────
$('btn-scrape').addEventListener('click', async () => {
  setStatus('Ouverture de la page favoris…', '');
  // Ouvrir la page favoris Green Acres, le content script s'en chargera
  await chrome.tabs.create({ url: 'https://www.green-acres.fr/fr/favorite/all' });
  window.close();
});

// ── Config toggle ─────────────────────────────────────────────
$('btn-config').addEventListener('click', () => {
  $('config-section').classList.toggle('open');
});

$('toggle-auto').addEventListener('click', () => {
  autoSyncOn = !autoSyncOn;
  updateToggle();
});

$('btn-save-config').addEventListener('click', async () => {
  const newConfig = {
    ...config,
    serverUrl:  $('cfg-server').value.trim(),
    apiKey:     $('cfg-apikey').value.trim(),
    galleryUrl: $('cfg-gallery').value.trim(),
    autoSync:   autoSyncOn
  };
  await sendMsg({ type: 'SAVE_CONFIG', data: newConfig });
  config = newConfig;
  if (config.galleryUrl) $('btn-gallery').href = config.galleryUrl;
  $('config-section').classList.remove('open');
  setStatus('✅ Configuration enregistrée', '');
});

// ── Clear cache ───────────────────────────────────────────────
$('btn-clear').addEventListener('click', async () => {
  if (!confirm('Vider le cache local ? (les données sur le serveur sont conservées)')) return;
  await chrome.storage.local.remove('immo_queue');
  await loadStats();
  setStatus('Cache vidé', '');
});

// ── Helpers ───────────────────────────────────────────────────
function sendMsg(msg) {
  return chrome.runtime.sendMessage(msg);
}

function setStatus(text, type = '') {
  const bar = $('status-bar');
  bar.textContent = text;
  bar.className = 'status-bar ' + type;
}

function updateToggle() {
  const t = $('toggle-auto');
  t.className = 'toggle' + (autoSyncOn ? ' on' : '');
}
