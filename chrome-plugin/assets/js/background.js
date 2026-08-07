// ============================================================
// BienAuFait — Background Service Worker
// Agrège les données des content scripts et les envoie au serveur
// ============================================================

const CONFIG_KEY = 'immo_config';
const QUEUE_KEY  = 'immo_queue';
const QUEUE_VERSION_KEY = 'immo_queue_version';
const QUEUE_VERSION = 2;

const DEFAULT_CONFIG = {
  serverUrl: 'https://bienaufait.fr/api',
  galleryUrl: 'https://bienaufait.fr/app.html',
  apiToken: '',
  autoSync: true,
  syncIntervalMinutes: 5
};

// ── Init ─────────────────────────────────────────────────────
chrome.runtime.onInstalled.addListener(async () => {
  const { [CONFIG_KEY]: cfg } = await chrome.storage.local.get(CONFIG_KEY);
  if (!cfg) {
    await chrome.storage.local.set({ [CONFIG_KEY]: DEFAULT_CONFIG });
    console.log('[BienAuFait] Config initialisée avec les valeurs par défaut');
  } else if (/\/api\/v1\/sync\/?$/.test(cfg.serverUrl || '')) {
    await chrome.storage.local.set({
      [CONFIG_KEY]: { ...cfg, serverUrl: cfg.serverUrl.replace(/\/v1\/sync\/?$/, '') }
    });
  }
  // La version précédente conservait toute la collection localement. Cette
  // migration la vide afin qu'une annonce supprimée ne soit jamais recréée.
  const { [QUEUE_VERSION_KEY]: queueVersion } = await chrome.storage.local.get(QUEUE_VERSION_KEY);
  if (queueVersion !== QUEUE_VERSION) {
    await chrome.storage.local.remove(QUEUE_KEY);
    await chrome.storage.local.set({ [QUEUE_VERSION_KEY]: QUEUE_VERSION });
  }
  scheduleAlarm();
});

chrome.runtime.onStartup.addListener(scheduleAlarm);

function scheduleAlarm() {
  // MV3 : utiliser Promise (pas callback) pour chrome.alarms
  chrome.alarms.clearAll().then(() => {
    chrome.alarms.create('sync', { periodInMinutes: 5 });
  });
}

chrome.alarms.onAlarm.addListener(async (alarm) => {
  if (alarm.name === 'sync') await flushQueue();
});

// ── Réception des messages des content scripts ────────────────
chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
  if (msg.type === 'GA_FAVORITES')  handleFavorites(msg.data, sendResponse);
  if (msg.type === 'GA_LISTING')    handleListing(msg.data, sendResponse);
  if (msg.type === 'GET_STATS')     getStats(sendResponse);
  if (msg.type === 'FORCE_SYNC')    flushQueue().then(sendResponse);
  if (msg.type === 'SAVE_CONFIG')   saveConfig(msg.data, sendResponse);
  if (msg.type === 'GET_CONFIG')    getConfig(sendResponse);
  if (msg.type === 'LOGIN')         login(msg.data).then(sendResponse);
  return true; // async response
});

// ── Handlers ─────────────────────────────────────────────────

async function handleFavorites(listings, sendResponse) {
  const queue = await getQueue();
  let added = 0;
  for (const listing of listings) {
    const key = normalizeUrl(listing.url);
    if (!queue.favorites[key]) {
      queue.favorites[key] = { ...listing, capturedAt: Date.now(), source: 'ga_favorite' };
      added++;
    } else {
      // Mise à jour si nouvelles données
      queue.favorites[key] = { ...queue.favorites[key], ...listing, updatedAt: Date.now() };
    }
  }
  await saveQueue(queue);
  console.log(`[BienAuFait] ${added} nouveaux favoris, ${listings.length - added} mis à jour`);
  sendResponse({ ok: true, added });
  await autoSync();
}

async function handleListing(listing, sendResponse) {
  const queue = await getQueue();
  const key = normalizeUrl(listing.url);
  if (queue.favorites[key]) {
    // Enrichit un favori existant avec les détails de la fiche
    queue.favorites[key] = { ...queue.favorites[key], ...listing, enrichedAt: Date.now() };
    await saveQueue(queue);
    console.log(`[BienAuFait] Favori enrichi : ${listing.url}`);
  }
  sendResponse({ ok: true });
}

// ── Sync serveur ──────────────────────────────────────────────

async function autoSync() {
  const { [CONFIG_KEY]: cfg } = await chrome.storage.local.get(CONFIG_KEY);
  if (cfg?.autoSync) await flushQueue();
}

async function flushQueue() {
  const { [CONFIG_KEY]: cfg } = await chrome.storage.local.get(CONFIG_KEY);

  console.group('[BienAuFait][SYNC] flushQueue');
  console.log('Config:', cfg);

  if (!cfg?.serverUrl || cfg.serverUrl.includes('YOUR_SERVER')) {
    console.warn('[BienAuFait][SYNC] Serveur non configuré, sync ignorée');
    console.groupEnd();
    return { ok: false, reason: 'SERVER_NOT_CONFIGURED' };
  }

  const queue = await getQueue();
  const favorites = Object.values(queue.favorites || {});
  console.log('Queue count:', { favorites: favorites.length });

  if (favorites.length === 0) {
    console.warn('[BienAuFait][SYNC] Queue vide');
    console.groupEnd();
    return { ok: true, reason: 'EMPTY_QUEUE' };
  }

  const payload = {
    favorites,
    syncedAt: Date.now()
  };

  try {
    const syncUrl = apiEndpoint(cfg.serverUrl, 'sync');
    console.log('[BienAuFait][SYNC] POST:', syncUrl);
    console.log('[BienAuFait][SYNC] Payload size:', JSON.stringify(payload).length);

    const res = await fetch(syncUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${cfg.apiToken || ''}`
      },
      body: JSON.stringify(payload)
    });

    const raw = await res.text();
 
    console.log('[BienAuFait][SYNC] HTTP status:', res.status);
    console.log('[BienAuFait][SYNC] Raw response:', raw);

    let result = null;
    try {
      result = JSON.parse(raw);
    } catch (e) {
      console.error('[BienAuFait][SYNC] Réponse non JSON:', e.message);
    }

    if (res.ok) {
      // Le stockage local est une file de livraison, pas une seconde base de
      // données. Ne supprimer que les éléments inclus dans cette requête pour
      // préserver les captures éventuelles arrivées pendant la synchronisation.
      const currentQueue = await getQueue();
      for (const listing of favorites) {
        delete currentQueue.favorites[normalizeUrl(listing.url)];
      }
      await saveQueue(currentQueue);
      await chrome.storage.local.set({ immo_last_sync: Date.now() });
      console.log('[BienAuFait][SYNC] Sync OK:', result);
      console.groupEnd();
      return { ok: true, result };
    }

    console.error('[BienAuFait][SYNC] Sync échouée:', res.status, result || raw);
    console.groupEnd();
    return { ok: false, status: res.status, result, raw };

  } catch (e) {
    console.error('[BienAuFait][SYNC] Erreur fetch:', e.name, e.message, e.stack);
    console.groupEnd();
    return { ok: false, error: e.message };
  }
}
// ── Helpers ───────────────────────────────────────────────────

async function getQueue() {
  const { [QUEUE_KEY]: q } = await chrome.storage.local.get(QUEUE_KEY);
  return { favorites: q?.favorites || {} };
}

async function saveQueue(queue) {
  await chrome.storage.local.set({ [QUEUE_KEY]: queue });
}

async function getStats(sendResponse) {
  const queue = await getQueue();
  const { immo_last_sync } = await chrome.storage.local.get('immo_last_sync');
  sendResponse({
    favorites: Object.keys(queue.favorites).length,
    lastSync:  immo_last_sync || null
  });
}

async function saveConfig(data, sendResponse) {
  await chrome.storage.local.set({ [CONFIG_KEY]: data });
  sendResponse({ ok: true });
}

async function getConfig(sendResponse) {
  const { [CONFIG_KEY]: cfg } = await chrome.storage.local.get(CONFIG_KEY);
  sendResponse(cfg || DEFAULT_CONFIG);
}

async function login(credentials) {
  const { [CONFIG_KEY]: stored } = await chrome.storage.local.get(CONFIG_KEY);
  const cfg = { ...DEFAULT_CONFIG, ...(stored || {}) };
  try {
    const res = await fetch(apiEndpoint(credentials.serverUrl || cfg.serverUrl, 'login'), {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: credentials.email, password: credentials.password })
    });
    const result = await res.json();
    if (!res.ok || !result?.data?.token) return { ok: false, status: res.status, error: result?.error?.message || 'Authentification impossible.' };
    cfg.serverUrl = credentials.serverUrl || cfg.serverUrl;
    cfg.apiToken = result.data.token;
    await chrome.storage.local.set({ [CONFIG_KEY]: cfg });
    return { ok: true };
  } catch (error) { return { ok: false, error: error.message }; }
}

function normalizeUrl(url) {
  try {
    const u = new URL(url);
    return u.pathname.replace(/\/$/, '');
  } catch {
    return url;
  }
}

function apiEndpoint(serverUrl, endpoint) {
  return `${String(serverUrl || '').replace(/\/+$/, '')}/v1/${endpoint}`;
}
