// ============================================================
// ImmoAggregator — Background Service Worker
// Agrège les données des content scripts et les envoie au serveur
// ============================================================

const CONFIG_KEY = 'immo_config';
const QUEUE_KEY  = 'immo_queue';

const DEFAULT_CONFIG = {
  serverUrl: 'https://solenis-studio.fr/sigma-immo/api/sync.php',
  apiKey: 'CHANGE_ME',
  autoSync: true,
  syncIntervalMinutes: 5
};

// ── Init ─────────────────────────────────────────────────────
chrome.runtime.onInstalled.addListener(async () => {
  const { [CONFIG_KEY]: cfg } = await chrome.storage.local.get(CONFIG_KEY);
  if (!cfg) {
    await chrome.storage.local.set({ [CONFIG_KEY]: DEFAULT_CONFIG });
    console.log('[ImmoAgg] Config initialisée avec les valeurs par défaut');
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
  if (msg.type === 'CRITEO_ADS')    handleCriteo(msg.data, sendResponse);
  if (msg.type === 'GET_STATS')     getStats(sendResponse);
  if (msg.type === 'FORCE_SYNC')    flushQueue().then(() => sendResponse({ ok: true }));
  if (msg.type === 'SAVE_CONFIG')   saveConfig(msg.data, sendResponse);
  if (msg.type === 'GET_CONFIG')    getConfig(sendResponse);
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
  console.log(`[ImmoAgg] ${added} nouveaux favoris, ${listings.length - added} mis à jour`);
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
    console.log(`[ImmoAgg] Favori enrichi : ${listing.url}`);
  }
  sendResponse({ ok: true });
}

async function handleCriteo(ads, sendResponse) {
  const queue = await getQueue();
  let added = 0;
  for (const ad of ads) {
    const key = ad.imageUrl;
    if (!queue.criteo[key]) {
      queue.criteo[key] = { ...ad, capturedAt: Date.now(), source: 'criteo' };
      added++;
    }
  }
  await saveQueue(queue);
  console.log(`[ImmoAgg] ${added} nouvelles annonces Criteo capturées`);
  sendResponse({ ok: true, added });
  await autoSync();
}

// ── Sync serveur ──────────────────────────────────────────────

async function autoSync() {
  const { [CONFIG_KEY]: cfg } = await chrome.storage.local.get(CONFIG_KEY);
  if (cfg?.autoSync) await flushQueue();
}

async function flushQueue() {
  const { [CONFIG_KEY]: cfg } = await chrome.storage.local.get(CONFIG_KEY);

  console.group('[ImmoAgg][SYNC] flushQueue');
  console.log('Config:', cfg);

  if (!cfg?.serverUrl || cfg.serverUrl.includes('YOUR_SERVER')) {
    console.warn('[ImmoAgg][SYNC] Serveur non configuré, sync ignorée');
    console.groupEnd();
    return { ok: false, reason: 'SERVER_NOT_CONFIGURED' };
  }

  const queue = await getQueue();
  const favorites = Object.values(queue.favorites || {});
  const criteo = Object.values(queue.criteo || {});

  console.log('Queue counts:', {
    favorites: favorites.length,
    criteo: criteo.length
  });

  console.log('First favorite:', favorites[0] || null);
  console.log('First criteo:', criteo[0] || null);

  if (favorites.length === 0 && criteo.length === 0) {
    console.warn('[ImmoAgg][SYNC] Queue vide');
    console.groupEnd();
    return { ok: true, reason: 'EMPTY_QUEUE' };
  }

  const payload = {
    favorites,
    criteo,
    syncedAt: Date.now()
  };

  try {
    console.log('[ImmoAgg][SYNC] POST:', cfg.serverUrl);
    console.log('[ImmoAgg][SYNC] Payload size:', JSON.stringify(payload).length);

    const res = await fetch(cfg.serverUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Api-Key': cfg.apiKey || ''
      },
      body: JSON.stringify(payload)
    });

    const raw = await res.text();
 
    console.log('[ImmoAgg][SYNC] HTTP status:', res.status);
    console.log('[ImmoAgg][SYNC] Raw response:', raw);

    let result = null;
    try {
      result = JSON.parse(raw);
    } catch (e) {
      console.error('[ImmoAgg][SYNC] Réponse non JSON:', e.message);
    }

    if (res.ok) {
      await chrome.storage.local.set({ immo_last_sync: Date.now() });
      console.log('[ImmoAgg][SYNC] Sync OK:', result);
      console.groupEnd();
      return { ok: true, result };
    }

    console.error('[ImmoAgg][SYNC] Sync échouée:', res.status, result || raw);
    console.groupEnd();
    return { ok: false, status: res.status, result, raw };

  } catch (e) {
    console.error('[ImmoAgg][SYNC] Erreur fetch:', e.name, e.message, e.stack);
    console.groupEnd();
    return { ok: false, error: e.message };
  }
}
// ── Helpers ───────────────────────────────────────────────────

async function getQueue() {
  const { [QUEUE_KEY]: q } = await chrome.storage.local.get(QUEUE_KEY);
  return q || { favorites: {}, criteo: {} };
}

async function saveQueue(queue) {
  await chrome.storage.local.set({ [QUEUE_KEY]: queue });
}

async function getStats(sendResponse) {
  const queue = await getQueue();
  const { immo_last_sync } = await chrome.storage.local.get('immo_last_sync');
  sendResponse({
    favorites: Object.keys(queue.favorites).length,
    criteo:    Object.keys(queue.criteo).length,
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

function normalizeUrl(url) {
  try {
    const u = new URL(url);
    return u.pathname.replace(/\/$/, '');
  } catch {
    return url;
  }
}
