// ============================================================
// ImmoAggregator — Green Acres fiche annonce enricher
// Injecté sur les pages /annonce/, /property/, /bien/
// N'agit que si c'est une page de fiche individuelle
// ============================================================

(async function () {
  // Ne traiter que les fiches individuelles
  const isListing = /\/(annonce|property|bien|listing)\//.test(location.pathname)
    || /\/\d{6,}/.test(location.pathname);

  if (!isListing) return;

  await waitForEl('h1, .property-title, [class*="detail"]', 3000);

  const data = extractDetailPage();
  if (!data) return;

  console.log('[ImmoAgg] Détails fiche :', data);

  await chrome.runtime.sendMessage({
    type: 'GA_LISTING',
    data
  });
})();

function extractDetailPage() {
  const url = location.href;

  // Titre
  const titleEl = document.querySelector('h1, .property-title');
  const title = titleEl ? titleEl.textContent.trim() : '';

  // Prix
  const priceEl = document.querySelector('[class*="price"], [itemprop="price"], .prix');
  const priceText = priceEl ? priceEl.textContent.trim() : '';
  const price = parsePrice(priceText);

  // Surface
  const surfaceEl = document.querySelector('[class*="surface"], [class*="area"]');
  const surface = surfaceEl ? parseSurface(surfaceEl.textContent) : null;

  // Adresse / localisation — le plus précis disponible
  const addressEl = document.querySelector(
    '[itemprop="streetAddress"], [class*="address"], [class*="adresse"], '
    + '.location, [class*="location"], [class*="city"]'
  );
  const address = addressEl ? addressEl.textContent.trim() : '';

  // Coordonnées GPS si présentes dans le DOM ou les scripts
  const coords = extractCoords();

  // Toutes les images de la galerie
  const imgEls = document.querySelectorAll(
    '.gallery img, .slider img, [class*="photo"] img, [class*="carousel"] img'
  );
  const images = [...imgEls]
    .map(i => i.dataset.src || i.dataset.lazy || i.src)
    .filter(src => src && src.startsWith('http') && !src.includes('placeholder'));

  // Description
  const descEl = document.querySelector(
    '[class*="description"], [itemprop="description"], .detail-description'
  );
  const description = descEl ? descEl.textContent.trim().slice(0, 500) : '';

  // Caractéristiques
  const features = {};
  document.querySelectorAll('[class*="feature"], [class*="caracteristique"], [class*="detail-item"]')
    .forEach(el => {
      const key = el.querySelector('[class*="label"], dt, .key')?.textContent.trim();
      const val = el.querySelector('[class*="value"], dd, .val')?.textContent.trim();
      if (key && val) features[key] = val;
    });

  return { url, title, price, priceText, surface, address, coords, images, description, features };
}

function extractCoords() {
  // Cherche dans les scripts inline (JSON-LD, data attributes, window vars)
  const scripts = document.querySelectorAll('script:not([src])');
  for (const s of scripts) {
    const text = s.textContent;

    // JSON-LD
    if (text.includes('"latitude"') || text.includes('"lat"')) {
      const latMatch = text.match(/"latitude"\s*:\s*([\d.+-]+)/);
      const lngMatch = text.match(/"longitude"\s*:\s*([\d.+-]+)/);
      if (latMatch && lngMatch) {
        return { lat: parseFloat(latMatch[1]), lng: parseFloat(lngMatch[1]) };
      }
    }

    // Patterns courants
    const m = text.match(/lat[itude]*['"]?\s*[:=]\s*([\d.+-]+)[,\s]+lon[gitude]*['"]?\s*[:=]\s*([\d.+-]+)/i);
    if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };
  }

  // data attributes sur la carte
  const mapEl = document.querySelector('[data-lat], [data-latitude]');
  if (mapEl) {
    const lat = parseFloat(mapEl.dataset.lat || mapEl.dataset.latitude);
    const lng = parseFloat(mapEl.dataset.lng || mapEl.dataset.longitude);
    if (!isNaN(lat) && !isNaN(lng)) return { lat, lng };
  }

  return null;
}

function parsePrice(text) {
  const clean = text.replace(/\s/g, '').replace(',', '.').replace(/[^\d.]/g, '');
  const n = parseFloat(clean);
  return isNaN(n) ? null : n;
}

function parseSurface(text) {
  const match = text.match(/(\d+[\d,.]*)[\s]*(m²|m2|sqm)/i);
  if (match) return parseFloat(match[1].replace(',', '.'));
  return null;
}

function waitForEl(selector, timeout = 3000) {
  return new Promise(resolve => {
    const start = Date.now();
    const check = () => {
      if (document.querySelector(selector)) return resolve(true);
      if (Date.now() - start > timeout) return resolve(false);
      requestAnimationFrame(check);
    };
    check();
  });
}
