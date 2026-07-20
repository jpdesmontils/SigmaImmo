// ============================================================
// ImmoAggregator — content_capture.js
// Détecte les pages d'annonces immobilières et injecte
// un bouton "Ajouter aux favoris" avec extraction DOM algo A
// ============================================================

(function () {
  if (window.__immoCapture) return;
  window.__immoCapture = true;

  // ── Détection page d'annonce ────────────────────────────────
  function isListingPage() {
    const url = location.href.toLowerCase();
    const urlPatterns = [
      '/annonce', '/bien', '/property', '/properties',
      '/vente', '/location', '/maison', '/appartement',
      '/immo', '/immobilier', '/logement', '/achat'
    ];
    const hasImmoUrl = urlPatterns.some(p => url.includes(p));

    // Chercher un prix en € dans la page
    const text = document.body ? document.body.innerText : '';
    const hasPrice = /\d[\d\s]{2,}[\s]?€/.test(text);

    return hasImmoUrl || hasPrice;
  }

  if (!isListingPage()) return;

  // Attendre que le DOM soit bien chargé
  if (document.readyState !== 'complete') {
    window.addEventListener('load', init);
  } else {
    setTimeout(init, 500);
  }

  // ── Injection bouton flottant ─────────────────────────────
  function init() {
    if (document.getElementById('immo-capture-btn')) return;

    const btn = document.createElement('div');
    btn.id = 'immo-capture-btn';
    btn.innerHTML = '⭐ Ajouter aux favoris';
    btn.style.cssText = `
      position: fixed;
      bottom: 24px;
      right: 24px;
      z-index: 999999;
      background: #2563eb;
      color: #fff;
      padding: 10px 18px;
      border-radius: 24px;
      font-size: 14px;
      font-weight: 600;
      font-family: system-ui, sans-serif;
      cursor: pointer;
      box-shadow: 0 4px 20px rgba(37,99,235,.5);
      transition: all .2s;
      user-select: none;
    `;
    btn.addEventListener('mouseenter', () => btn.style.background = '#1d4ed8');
    btn.addEventListener('mouseleave', () => btn.style.background = '#2563eb');
    btn.addEventListener('click', onCapture);
    document.body.appendChild(btn);
  }

  // ── Extraction DOM — Algorithme A ─────────────────────────
  function extractListing() {
    const data = {
      url:         location.href,
      title:       '',
      price:       null,
      priceText:   '',
      surface:     null,
      surfaceText: '',
      location:    '',
      description: '',
      imageUrl:    '',
      images:      [],
      confidence:  0,
      source:      'capture'
    };

    // ── Titre ──────────────────────────────────────────────
    // Priorité 1 : og:title
    const ogTitle = document.querySelector('meta[property="og:title"]');
    if (ogTitle && ogTitle.content) {
      data.title = ogTitle.content.trim();
    }
    // Priorité 2 : h1
    if (!data.title) {
      const h1 = document.querySelector('h1');
      if (h1) data.title = h1.innerText.trim();
    }
    // Priorité 3 : title tag (nettoyé)
    if (!data.title) {
      data.title = document.title.split(/[|\-–]/)[0].trim();
    }

    // ── Prix ───────────────────────────────────────────────
    // Priorité 1 : prix dans le titre (h1 ou og:title) — le plus fiable
    const titleForPrice = data.title || document.title || '';
    const titlePriceMatch = titleForPrice.match(/([\d][\d\s]{2,8})\s*€/);
    if (titlePriceMatch) {
      const p = parseInt(titlePriceMatch[1].replace(/\s/g, ''));
      if (p > 10000 && p < 50000000) {
        data.price = p;
        data.priceText = p.toLocaleString('fr-FR') + ' €';
      }
    }

    // Priorité 2 : itemprop="price" ou og:price:amount
    if (!data.price) {
      const ogPrice = document.querySelector('meta[property="og:price:amount"]');
      if (ogPrice && ogPrice.content) {
        const p = parseFloat(ogPrice.content);
        if (p > 10000) { data.price = Math.round(p); data.priceText = Math.round(p).toLocaleString('fr-FR') + ' €'; }
      }
    }
    if (!data.price) {
      const priceEl = document.querySelector('[itemprop="price"]');
      if (priceEl) {
        const raw = priceEl.getAttribute('content') || priceEl.textContent;
        const p = parseInt(raw.replace(/\s/g, '').replace(/[^\d]/g, ''));
        if (p > 10000) { data.price = p; data.priceText = priceEl.textContent.trim(); }
      }
    }

    // Priorité 3 : premier prix trouvé dans le texte visible (pas le max)
    // On prend le PREMIER match significatif, pas le plus grand
    const allText = document.body.innerText;
    if (!data.price) {
      const priceMatches = allText.match(/([\d][\d\s]{2,8})\s*€/g) || [];
      for (const m of priceMatches) {
        const p = parseInt(m.replace(/\s/g, '').replace('€', ''));
        if (p > 50000 && p < 50000000) {
          data.price = p;
          data.priceText = p.toLocaleString('fr-FR') + ' €';
          break;
        }
      }
    }

    // ── Surface ────────────────────────────────────────────
    const surfMatch = allText.match(/(\d+[\d,.]?)\s*m[²2]/i);
    if (surfMatch) {
      data.surface = parseFloat(surfMatch[1].replace(',', '.'));
      data.surfaceText = surfMatch[0];
    }
    const surfEl = document.querySelector('[itemprop="floorSize"], [class*="surface"], [class*="area"]');
    if (surfEl) {
      const m = surfEl.textContent.match(/(\d+)/);
      if (m) data.surface = parseInt(m[1]);
    }

    // ── Localisation ───────────────────────────────────────
    const locEl = document.querySelector(
      '[itemprop="addressLocality"], [itemprop="address"], ' +
      '[class*="location"], [class*="localisation"], [class*="ville"], [class*="city"]'
    );
    if (locEl) {
      data.location = locEl.textContent.trim().slice(0, 100);
    }
    // Fallback : og:description souvent contient la ville
    if (!data.location) {
      const ogDesc = document.querySelector('meta[property="og:description"]');
      if (ogDesc) {
        const cityMatch = ogDesc.content.match(/\b([A-ZÀ-Ü][a-zà-ü]+(?:[-\s][A-ZÀ-Ü][a-zà-ü]+)*)\s*\(\d{2}/);
        if (cityMatch) data.location = cityMatch[1];
      }
    }

    // ── Image principale ───────────────────────────────────
    // Priorité 1 : og:image
    const ogImg = document.querySelector('meta[property="og:image"]');
    if (ogImg && ogImg.content) {
      data.imageUrl = ogImg.content;
      data.images.push(ogImg.content);
    }

    // Priorité 2 : toutes les grandes images visibles
    const allImgs = [...document.querySelectorAll('img')];
    const bigImgs = allImgs
      .filter(img => {
        const src = img.src || img.dataset.src || '';
        if (!src || src.startsWith('data:') || src.includes('logo') || src.includes('icon')) return false;
        const rect = img.getBoundingClientRect();
        return (img.naturalWidth > 200 || rect.width > 200) && img.naturalHeight > 100;
      })
      .map(img => img.src || img.dataset.src || img.dataset.lazy || '');

    bigImgs.forEach(src => { if (src && !data.images.includes(src)) data.images.push(src); });
    if (!data.imageUrl && bigImgs[0]) data.imageUrl = bigImgs[0];

    // Priorité 3 : data-thumb-src (Green Acres carousel)
    const thumbs = [...document.querySelectorAll('[data-thumb-src]')].map(el => el.dataset.thumbSrc);
    thumbs.forEach(src => { if (src && !data.images.includes(src)) data.images.push(src); });
    if (!data.imageUrl && thumbs[0]) data.imageUrl = thumbs[0];

    // ── Description — densité texte ────────────────────────
    // Trouver le bloc avec le plus de texte pur
    const candidates = [...document.querySelectorAll('p, div, section, article')];
    let bestBlock = null;
    let bestScore = 0;

    candidates.forEach(el => {
      const text = el.innerText || '';
      const htmlLen = el.innerHTML.length;
      if (text.length < 150 || htmlLen === 0) return;
      // Ratio texte/html : plus c'est proche de 1, moins il y a de balises
      const ratio = text.length / htmlLen;
      const score = text.length * ratio;
      if (score > bestScore && text.length < 3000) {
        bestScore = score;
        bestBlock = text;
      }
    });

    // Vérifier aussi itemprop="description"
    const descEl = document.querySelector('[itemprop="description"], [class*="description"]');
    if (descEl && descEl.innerText.length > (bestBlock || '').length) {
      bestBlock = descEl.innerText;
    }

    if (bestBlock) {
      data.description = bestBlock.trim().slice(0, 600);
    }

    // ── Score de confiance ─────────────────────────────────
    let confidence = 0;
    if (data.title && data.title.length > 5)       confidence += 25;
    if (data.price && data.price > 0)               confidence += 25;
    if (data.imageUrl)                              confidence += 25;
    if (data.surface || data.location)              confidence += 25;
    data.confidence = confidence;

    return data;
  }

  // ── Modale de confirmation ────────────────────────────────
  function onCapture() {
    const listing = extractListing();
    showConfirmModal(listing);
  }

  function showConfirmModal(listing) {
    // Supprimer modale existante
    const existing = document.getElementById('immo-modal');
    if (existing) existing.remove();

    const confidenceColor = listing.confidence >= 75 ? '#22c55e'
      : listing.confidence >= 50 ? '#f59e0b' : '#ef4444';
    const confidenceLabel = listing.confidence >= 75 ? '✅ Données complètes'
      : listing.confidence >= 50 ? '⚠️ Données partielles' : '❌ Données insuffisantes';

    const modal = document.createElement('div');
    modal.id = 'immo-modal';
    modal.style.cssText = `
      position: fixed; inset: 0; z-index: 9999999;
      background: rgba(0,0,0,.75); backdrop-filter: blur(4px);
      display: flex; align-items: center; justify-content: center;
      font-family: system-ui, sans-serif;
    `;

    modal.innerHTML = `
      <div style="
        background: #111827; border: 1px solid #1f2d40;
        border-radius: 12px; padding: 24px; width: 480px; max-width: 95vw;
        max-height: 90vh; overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0,0,0,.8);
        color: #e2e8f0;
      ">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
          <h2 style="font-size:16px;font-weight:700;margin:0;">⭐ Ajouter aux favoris</h2>
          <span style="font-size:12px;color:${confidenceColor}">${confidenceLabel} (${listing.confidence}%)</span>
        </div>

        ${listing.imageUrl ? `<img src="${escHtml(listing.imageUrl)}" style="width:100%;height:160px;object-fit:cover;border-radius:8px;margin-bottom:14px;">` : ''}

        <div style="margin-bottom:12px;">
          <label style="font-size:11px;color:#64748b;display:block;margin-bottom:4px;">TITRE</label>
          <input id="immo-f-title" value="${escHtml(listing.title)}" style="
            width:100%;background:#0f172a;border:1px solid #334155;
            border-radius:6px;padding:7px 10px;color:#e2e8f0;font-size:13px;box-sizing:border-box;
          ">
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
          <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:4px;">PRIX (€)</label>
            <input id="immo-f-price" type="number" value="${listing.price || ''}" style="
              width:100%;background:#0f172a;border:1px solid #334155;
              border-radius:6px;padding:7px 10px;color:#e2e8f0;font-size:13px;box-sizing:border-box;
            ">
          </div>
          <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:4px;">SURFACE (m²)</label>
            <input id="immo-f-surface" type="number" value="${listing.surface || ''}" style="
              width:100%;background:#0f172a;border:1px solid #334155;
              border-radius:6px;padding:7px 10px;color:#e2e8f0;font-size:13px;box-sizing:border-box;
            ">
          </div>
        </div>

        <div style="margin-bottom:16px;">
          <label style="font-size:11px;color:#64748b;display:block;margin-bottom:4px;">LOCALISATION</label>
          <input id="immo-f-location" value="${escHtml(listing.location)}" style="
            width:100%;background:#0f172a;border:1px solid #334155;
            border-radius:6px;padding:7px 10px;color:#e2e8f0;font-size:13px;box-sizing:border-box;
          ">
        </div>

        <div style="display:flex;gap:10px;justify-content:flex-end;">
          <button id="immo-modal-cancel" style="
            padding:8px 18px;border-radius:7px;border:1px solid #334155;
            background:none;color:#94a3b8;font-size:13px;cursor:pointer;
          ">Annuler</button>
          <button id="immo-modal-save" style="
            padding:8px 18px;border-radius:7px;border:none;
            background:#2563eb;color:#fff;font-size:13px;font-weight:600;cursor:pointer;
          ">⭐ Enregistrer</button>
        </div>
      </div>
    `;

    document.body.appendChild(modal);

    document.getElementById('immo-modal-cancel').addEventListener('click', () => modal.remove());
    modal.addEventListener('click', e => { if (e.target === modal) modal.remove(); });

    document.getElementById('immo-modal-save').addEventListener('click', () => {
      // Récupérer les valeurs éditées
      listing.title    = document.getElementById('immo-f-title').value.trim();
      listing.price    = parseFloat(document.getElementById('immo-f-price').value) || null;
      listing.surface  = parseFloat(document.getElementById('immo-f-surface').value) || null;
      listing.location = document.getElementById('immo-f-location').value.trim();
      listing.scrapedAt = Date.now();
      listing.id = 'cap_' + Date.now();

      console.log('[ImmoCapture] Envoi au background:', listing);
      chrome.runtime.sendMessage({ type: 'GA_FAVORITES', data: [listing] }, (res) => {
        console.log('[ImmoCapture] Réponse background:', res, 'lastError:', chrome.runtime.lastError);
        modal.remove();
        if (chrome.runtime.lastError) {
          showToast('❌ Erreur runtime: ' + chrome.runtime.lastError.message);
          return;
        }
        showToast(res && res.ok ? '✅ Annonce ajoutée aux favoris !' : '❌ Erreur: ' + JSON.stringify(res));
      });
    });
  }

  function showToast(msg) {
    const toast = document.createElement('div');
    toast.style.cssText = `
      position: fixed; bottom: 80px; right: 24px; z-index: 9999999;
      background: #1a1a2e; color: #fff; padding: 10px 18px;
      border-radius: 8px; font-size: 13px; font-family: system-ui, sans-serif;
      box-shadow: 0 4px 20px rgba(0,0,0,.5);
      animation: fadeIn .2s ease;
    `;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
  }

  function escHtml(str) {
    return (str || '').toString()
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

})();