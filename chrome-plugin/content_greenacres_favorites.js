// ============================================================
// ImmoAggregator — Green Acres /favorite/all scraper
// Basé sur la structure réelle du DOM Green Acres (juillet 2025)
// ============================================================

(async function () {
  console.log('[ImmoAgg] Scraping favoris Green Acres…');

  // Attendre que les cartes soient présentes
  await waitForEl('.announce-card', 5000);

  const listings = scrapeCards();

  if (listings.length === 0) {
    console.warn('[ImmoAgg] Aucune carte trouvée');
    injectBadge('⚠️ 0 annonces — voir console');
    return;
  }

  injectBadge(`✅ ${listings.length} favoris capturés`);
  console.log('[ImmoAgg] Favoris :', listings);

  const response = await chrome.runtime.sendMessage({ type: 'GA_FAVORITES', data: listings });
  console.log('[ImmoAgg] Background response :', response);
})();

// ── Extraction ────────────────────────────────────────────────

function scrapeCards() {
  const cards = document.querySelectorAll('div.announce-card[data-advertid]');
  const results = [];

  cards.forEach(card => {
    try {
      const advertId = card.dataset.advertid;

      // URL : encodée en base64 dans data-o
      const rawUrl = decodeObfuscatedUrl(card.dataset.o);
      // Fallback : construire l'URL depuis l'id si le décodage échoue
      const url = rawUrl || (advertId ? 'https://www.green-acres.fr/fr/properties/immobilier/' + advertId + '.htm' : '');

      // Titre depuis l'attribut title de la carte
      const title = card.getAttribute('title') || '';

      // Image principale : première img.announce-card-img avec src
      const imgEl = card.querySelector('img.announce-card-img[src]');
      const imageUrl = imgEl ? imgEl.src : (card.querySelector('img.announce-card-img')?.dataset?.thumbSrc || '');

      // Prix : strong.info-price (contient des &nbsp; → nettoyer)
      const priceEl = card.querySelector('strong.info-price');
      const priceText = priceEl ? priceEl.textContent.replace(/\s/g, '').replace(/€/, '').trim() : '';
      const price = priceText ? parseFloat(priceText.replace(/[^\d]/g, '')) : null;

      // Tags info (surface, terrain, pièces)
      const tags = [...card.querySelectorAll('div.info-tag.shown')].map(t => ({
        label: t.getAttribute('title') || '',
        value: t.textContent.trim()
      }));

      // Surface habitable
      const surfaceTag = tags.find(t => t.label.toLowerCase().includes('surface'));
      const surface = surfaceTag ? parseSurface(surfaceTag.value) : null;

      // Terrain
      const terrainTag = tags.find(t => t.label.toLowerCase().includes('terrain'));
      const terrain = terrainTag ? parseSurface(terrainTag.value) : null;

      // Pièces
      const roomsTag = tags.find(t => t.label.toLowerCase().includes('pi'));
      const rooms = roomsTag ? roomsTag.value.trim() : '';

      // Chambres
      const bedsTag = tags.find(t => t.label.toLowerCase().includes('chambre'));
      const bedrooms = bedsTag ? bedsTag.value.trim() : '';

      // Localisation
      const locEl = card.querySelector('div.announce-localisation');
      const location = locEl ? locEl.textContent.trim() : '';

      // Description
      const descEl = card.querySelector('div.description-details');
      const description = descEl ? descEl.textContent.trim().slice(0, 600) : '';

      // Agence
      const agencyEl = card.querySelector('div.company-name');
      const agency = agencyEl ? agencyEl.textContent.trim() : '';

      // Réduction de prix
      const reductionEl = card.querySelector('span.reduction-rate');
      const priceReduction = reductionEl ? reductionEl.textContent.trim() : null;

      // Nombre de photos
      const photoCountEl = card.querySelector('span.tag.picture');
      const photoCount = photoCountEl ? parseInt(photoCountEl.textContent.trim()) : 0;

      // Toutes les images du carousel (data-thumb-src)
      const images = [...card.querySelectorAll('[data-thumb-src]')]
        .map(el => el.dataset.thumbSrc)
        .filter(Boolean);

      results.push({
        id: advertId,
        url,
        title,
        imageUrl,
        images,
        price,
        priceText: priceEl ? priceEl.textContent.trim() : '',
        priceReduction,
        surface,
        terrain,
        rooms,
        bedrooms,
        location,
        description,
        agency,
        photoCount,
        source: 'ga_favorite',
        scrapedAt: Date.now()
      });

    } catch (e) {
      console.error('[ImmoAgg] Erreur carte :', e);
    }
  });

  return results;
}

// ── Helpers ───────────────────────────────────────────────────

function decodeObfuscatedUrl(encoded) {
  if (!encoded) return '';
  try {
    return atob(encoded);
  } catch {
    return '';
  }
}

function parseSurface(text) {
  if (!text) return null;
  const match = text.match(/([\d\s]+)\s*(m²|m2)/i);
  if (match) return parseFloat(match[1].replace(/\s/g, ''));
  return null;
}

function waitForEl(selector, timeout = 5000) {
  return new Promise(resolve => {
    if (document.querySelector(selector)) return resolve(true);
    const start = Date.now();
    const check = () => {
      if (document.querySelector(selector)) return resolve(true);
      if (Date.now() - start > timeout) return resolve(false);
      requestAnimationFrame(check);
    };
    check();
  });
}

function injectBadge(text) {
  const badge = document.createElement('div');
  badge.style.cssText = `
    position:fixed;top:70px;right:12px;z-index:999999;
    background:#1a1a2e;color:#fff;padding:8px 14px;
    border-radius:8px;font-size:13px;font-family:monospace;
    box-shadow:0 2px 12px rgba(0,0,0,.5);
  `;
  badge.textContent = '🏠 ImmoAgg — ' + text;
  document.body.appendChild(badge);
  setTimeout(() => badge.remove(), 5000);
}