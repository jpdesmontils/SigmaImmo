// ============================================================
// ImmoAggregator — app.js
// SPA : Galerie / Liste / Carte + Visionneuse d'annonce
// ============================================================

// ── Config ────────────────────────────────────────────────────
const API_URL = 'https://solenis-studio.fr/sigma-immo/api/listings.php';

// ── État global ───────────────────────────────────────────────
let allListings = [];
let filtered    = [];
let map         = null;
let markers     = null;
let currentView = 'gallery';

const filters = {
  selection: 'all',
  city:      '',
  priceMin:  null,
  priceMax:  null,
  surfMin:   null,
  surfMax:   null,
  sort:      'date_desc'
};

const viewer = {
  listingIndex: 0,
  photos:       [],
  photoIndex:   0
};

// ── Init ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
  console.log('[ImmoAgg] DOM prêt, init...');
  initViewSwitcher();
  initFilters();
  initViewer();
  initDeleteModal();
  await loadData();
});

// ── Chargement données ────────────────────────────────────────
async function loadData() {
  console.log('[ImmoAgg] Chargement depuis', API_URL);
  try {
    const res = await fetch(API_URL + '?limit=500');
    console.log('[ImmoAgg] HTTP status:', res.status, res.ok);

    const text = await res.text();
    console.log('[ImmoAgg] Réponse brute (200 chars):', text.slice(0, 200));

    let json;
    try {
      json = JSON.parse(text);
    } catch (parseErr) {
      console.error('[ImmoAgg] JSON parse error:', parseErr);
      showError();
      return;
    }

    console.log('[ImmoAgg] JSON ok, clés:', Object.keys(json));

    // Support results ET items selon version API
    allListings = json.results || json.items || [];
    console.log('[ImmoAgg] allListings:', allListings.length, 'entrées');

    if (allListings.length > 0) {
      console.log('[ImmoAgg] Premier item:', JSON.stringify(allListings[0]).slice(0, 200));
    }

    updateHeaderStats();
    applyFiltersAndRender();

  } catch (e) {
    console.error('[ImmoAgg] Erreur chargement:', e);
    showError();
  }
}

function updateHeaderStats() {
  document.getElementById('hdr-fav').textContent = allListings.length;
}

// ── Init modale suppression ──────────────────────────────────
function initDeleteModal() {
  document.getElementById('delete-modal-cancel').addEventListener('click', closeDeleteModal);
  document.getElementById('delete-modal-confirm').addEventListener('click', confirmDelete);
  document.getElementById('delete-modal').addEventListener('click', function(e) {
    if (e.target === document.getElementById('delete-modal')) closeDeleteModal();
  });
}

// ── Filtres ───────────────────────────────────────────────────
function initFilters() {
  const debounced = debounce(applyFiltersAndRender, 300);

  document.getElementById('f-city').addEventListener('input', e => {
    filters.city = e.target.value.toLowerCase().trim();
    debounced();
  });
  document.getElementById('f-price-min').addEventListener('input', e => {
    filters.priceMin = e.target.value ? parseFloat(e.target.value) : null;
    debounced();
  });
  document.getElementById('f-price-max').addEventListener('input', e => {
    filters.priceMax = e.target.value ? parseFloat(e.target.value) : null;
    debounced();
  });
  document.getElementById('f-surf-min').addEventListener('input', e => {
    filters.surfMin = e.target.value ? parseFloat(e.target.value) : null;
    debounced();
  });
  document.getElementById('f-surf-max').addEventListener('input', e => {
    filters.surfMax = e.target.value ? parseFloat(e.target.value) : null;
    debounced();
  });
  document.getElementById('f-sort').addEventListener('change', e => {
    filters.sort = e.target.value;
    applyFiltersAndRender();
  });

  document.getElementById('btn-reset').addEventListener('click', resetFilters);

  document.querySelectorAll('.selection-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.selection-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      filters.selection = btn.dataset.selection;
      applyFiltersAndRender();
    });
  });
}

function applyFiltersAndRender() {
  filtered = allListings.filter(item => {
    if (filters.selection !== 'all' && item.selection !== filters.selection) return false;
    if (filters.city && !getLoc(item).toLowerCase().includes(filters.city)) return false;
    if (filters.priceMin !== null && (item.price === null || item.price < filters.priceMin)) return false;
    if (filters.priceMax !== null && (item.price === null || item.price > filters.priceMax)) return false;
    if (filters.surfMin  !== null && (item.surface === null || item.surface < filters.surfMin))  return false;
    if (filters.surfMax  !== null && (item.surface === null || item.surface > filters.surfMax))  return false;
    return true;
  });

  const [sortField, sortOrder] = filters.sort.split('_');
  filtered.sort((a, b) => {
    let va, vb;
    switch (sortField) {
      case 'price':   va = a.price   || Infinity; vb = b.price   || Infinity; break;
      case 'surface': va = a.surface || 0;        vb = b.surface || 0;        break;
      default:        va = a.capturedAt || 0;     vb = b.capturedAt || 0;
    }
    return sortOrder === 'asc' ? va - vb : vb - va;
  });

  console.log('[ImmoAgg] Filtré:', filtered.length, '/', allListings.length);
  document.getElementById('result-count').textContent = filtered.length;

  if (currentView === 'gallery') renderGallery();
  if (currentView === 'list')    renderList();
  if (currentView === 'map')     renderMap();
}

function resetFilters() {
  filters.selection = 'all';
  filters.city      = '';
  filters.priceMin = null;
  filters.priceMax = null;
  filters.surfMin  = null;
  filters.surfMax  = null;
  filters.sort     = 'date_desc';

  document.getElementById('f-city').value      = '';
  document.getElementById('f-price-min').value = '';
  document.getElementById('f-price-max').value = '';
  document.getElementById('f-surf-min').value  = '';
  document.getElementById('f-surf-max').value  = '';
  document.getElementById('f-sort').value      = 'date_desc';

  document.querySelectorAll('.selection-btn').forEach(b => b.classList.remove('active'));
  document.querySelector('.selection-btn[data-selection="all"]').classList.add('active');

  applyFiltersAndRender();
}

// ── Vue switcher ──────────────────────────────────────────────
function initViewSwitcher() {
  document.querySelectorAll('.view-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      currentView = btn.dataset.view;

      ['gallery', 'list', 'map'].forEach(v => {
        document.getElementById('view-' + v).classList.toggle('active', v === currentView);
      });

      if (currentView === 'gallery') renderGallery();
      if (currentView === 'list')    renderList();
      if (currentView === 'map')     renderMap();
    });
  });
}

// ── Vue Galerie ───────────────────────────────────────────────
function renderGallery() {
  console.log('[ImmoAgg] renderGallery:', filtered.length, 'items');
  const grid = document.getElementById('gallery-grid');

  if (!grid) { console.error('[ImmoAgg] #gallery-grid introuvable'); return; }

  if (filtered.length === 0) {
    grid.innerHTML = emptyHTML();
    return;
  }

  grid.innerHTML = filtered.map((item, idx) => cardHTML(item, idx)).join('');

  grid.querySelectorAll('.card').forEach(card => {
    card.addEventListener('click', (e) => {
      // Ne pas ouvrir la visionneuse si clic sur un bouton action
      if (e.target.closest('.card-btn-delete') || e.target.closest('.card-btn-map') || e.target.closest('.card-btn-tag')) return;
      openViewer(parseInt(card.dataset.idx));
    });
  });

  // Boutons carte
  grid.querySelectorAll('.card-btn-map').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const idx = parseInt(btn.dataset.idx);
      showOnMap(idx);
    });
  });

  // Boutons tag sélection
  grid.querySelectorAll('.card-btn-tag').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const idx = parseInt(btn.dataset.idx);
      const sel = btn.dataset.sel;
      toggleSelection(idx, sel);
    });
  });

  // Boutons suppression
  grid.querySelectorAll('.card-btn-delete').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const idx = parseInt(btn.dataset.idx);
      openDeleteModal(idx);
    });
  });
}

function cardHTML(item, idx) {
  const imgSrc = getImageUrl(item);
  const imgEl  = imgSrc
    ? `<img class="card-img" src="${esc(imgSrc)}" alt="${esc(item.title || '')}" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">`
    : '';
  const placeholder = `<div class="card-img-placeholder" style="${imgSrc ? 'display:none' : ''}">🏠</div>`;

  const sel = item.selection || '';
  const badge = selectionBadgeHTML(sel);

  const btnShort = selectionButtonHTML(idx, 'shortlist', 'card-btn-tag');
  const btnEcart = selectionButtonHTML(idx, 'ecartee', 'card-btn-tag');
  const btnInvest = selectionButtonHTML(idx, 'invest', 'card-btn-tag');

  // Lien vers la fiche d'opportunité — affiché uniquement si l'annonce a déjà une fiche
  // renseignée (champ item.ficheUrl). Aucune génération de fiche ici.
  const hasFiche = !!item.ficheUrl;
  const btnFiche = hasFiche
    ? `<a class="card-btn-fiche" href="${esc(item.ficheUrl)}" target="_blank" rel="noopener" title="Voir la fiche d'opportunité" onclick="event.stopPropagation()">📄</a>`
    : '';

  return `
    <div class="card" data-idx="${idx}" data-id="${esc(item.id || '')}">
      ${badge}
      ${imgEl}${placeholder}
      <div class="card-body">
        <div class="card-tags">
          <span class="tag tag-fav">⭐ Favori</span>${selectionTagHTML(sel)}${hasFiche ? '<span class="tag tag-fiche">📄 Fiche</span>' : ''}
        </div>
        <div class="card-title">${esc(item.title || 'Annonce immobilière')}</div>
        <div class="card-meta">
          ${item.price   ? `<span class="card-price">${formatPrice(item.price)}</span>` : ''}
          ${item.surface ? `<span>${item.surface} m²</span>` : ''}
          ${item.rooms   ? `<span>${item.rooms}</span>` : ''}
        </div>
        <div class="card-location">${esc(getLoc(item))}</div>
        <div class="card-actions">
          ${btnShort}
          ${btnEcart}
          ${btnInvest}
          <button class="card-btn-map" data-idx="${idx}" title="Voir sur la carte">🗺</button>
          ${btnFiche}
          <button class="card-btn-delete" data-idx="${idx}" title="Supprimer">🗑</button>
        </div>
      </div>
    </div>`;
}

// ── Vue Liste ─────────────────────────────────────────────────
async function renderList() {
  console.log('[ImmoAgg] renderList:', filtered.length, 'items');
  const tbody = document.getElementById('list-tbody');

  if (!tbody) { console.error('[ImmoAgg] #list-tbody introuvable'); return; }

  if (filtered.length === 0) {
    tbody.innerHTML = `<tr><td colspan="7">${emptyHTML()}</td></tr>`;
    return;
  }

  // Enrichir avec code postal si absent
  var needPostal = filtered.filter(function(i) { return !i.postalCode && i.location; });
  if (needPostal.length > 0) {
    await Promise.all(needPostal.map(async function(item) {
      try {
        var cityMatch = item.location.match(/^([^(]+)/);
        var city = cityMatch ? cityMatch[1].trim() : '';
        if (!city) return;
        var r = await fetch('https://geo.api.gouv.fr/communes?nom=' + encodeURIComponent(city) + '&fields=codesPostaux&limit=1&boost=population');
        var res = await r.json();
        if (res && res[0] && res[0].codesPostaux && res[0].codesPostaux[0]) {
          item.postalCode = res[0].codesPostaux[0];
        }
      } catch(e) {}
    }));
  }

  tbody.innerHTML = filtered.map(function(item, idx) {
    var imgSrc = getImageUrl(item);
    var thumb = imgSrc
      ? '<img class="list-thumb" src="' + esc(imgSrc) + '" alt="" loading="lazy">'
      : '<div class="list-thumb" style="display:flex;align-items:center;justify-content:center;font-size:20px;background:var(--surface2)">🏠</div>';
    var cp = item.postalCode ? '<br><small style="color:var(--muted)">' + esc(item.postalCode) + ' · ' + esc(getDept(item)) + '</small>' : '<br><small style="color:var(--muted)">' + esc(getDept(item)) + '</small>';

    var ficheLink = item.ficheUrl
      ? '<a href="' + esc(item.ficheUrl) + '" target="_blank" rel="noopener" onclick="event.stopPropagation()" style="color:var(--go);font-size:12px;">📄 Fiche</a>'
      : '';

    return '<tr style="cursor:pointer" onclick="openViewer(' + idx + ')">'
      + '<td>' + thumb + '</td>'
      + '<td>' + esc(item.title || '—') + '</td>'
      + '<td>' + (item.price ? formatPrice(item.price) : '—') + '</td>'
      + '<td>' + (item.surface ? item.surface + ' m²' : '—') + '</td>'
      + '<td>' + esc(getLoc(item)) + cp + '</td>'
      + '<td><span class="tag tag-fav">⭐ Favori</span>' + selectionTagHTML(item.selection) + '</td>'
      + '<td style="display:flex;gap:10px;align-items:center;">'
      +   (item.url ? '<a href="' + esc(item.url) + '" target="_blank" rel="noopener" onclick="event.stopPropagation()" style="color:var(--text);font-size:12px;">Voir →</a>' : '')
      +   ficheLink
      + '</td>'
      + '</tr>';
  }).join('');
}

// ── Vue Carte ─────────────────────────────────────────────────
async function renderMap() {
  console.log('[ImmoAgg] renderMap:', filtered.length, 'items');

  if (!map) {
    map = L.map('map').setView([46.8, 2.3], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors',
      crossOrigin: true
    }).addTo(map);
  }

  // Supprimer ancien cluster + listeners
  if (markers) { map.removeLayer(markers); }
  map.off('popupopen');

  // Cluster group avec compteur
  markers = L.markerClusterGroup({
    maxClusterRadius: 60,
    iconCreateFunction: function(cluster) {
      var count = cluster.getChildCount();
      return L.divIcon({
        html: '<div style="width:36px;height:36px;background:#16150f;border:2px solid #fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;font-family:\'JetBrains Mono\',monospace;color:#f5f3ee;box-shadow:0 2px 8px rgba(22,21,15,.4);">' + count + '</div>',
        iconSize: [36, 36],
        iconAnchor: [18, 18],
        className: ''
      });
    }
  });
  map.addLayer(markers);

  // Géocoder les items sans coords
  var toGeocode = filtered.filter(function(item) { return !item.coords && item.location; });
  console.log('[ImmoAgg] Items à géocoder:', toGeocode.length);

  await Promise.all(toGeocode.map(async function(item) {
    try {
      var cityMatch = item.location.match(/^([^(]+)/);
      var city = cityMatch ? cityMatch[1].trim() : item.location;
      var r = await fetch('https://geo.api.gouv.fr/communes?nom=' + encodeURIComponent(city) + '&fields=centre&limit=1&boost=population');
      var results = await r.json();
      if (results && results[0] && results[0].centre) {
        var coords = results[0].centre.coordinates;
        item.coords = { lat: coords[1], lng: coords[0] };
      }
    } catch(e) {
      console.warn('[ImmoAgg] Coords échouées:', item.location, e);
    }
  }));

  var withCoords = filtered.filter(function(item) { return item.coords && item.coords.lat && item.coords.lng; });
  console.log('[ImmoAgg] Items avec coords:', withCoords.length);
  if (withCoords.length === 0) { return; }

  // Calcul dégradé prix
  var prices = withCoords.map(function(i) { return i.price || 0; }).filter(function(p) { return p > 0; });
  var minPrice = prices.length ? Math.min.apply(null, prices) : 0;
  var maxPrice = prices.length ? Math.max.apply(null, prices) : 1;
  var bounds = [];

  withCoords.forEach(function(item, idx) {
    var lat = item.coords.lat;
    var lng = item.coords.lng;

    var color = '#9a9890';
    if (item.price && maxPrice > minPrice) {
      var t = (item.price - minPrice) / (maxPrice - minPrice);
      var rv = Math.round(20  + (131 - 20)  * t);
      var gv = Math.round(90  + (21  - 90)  * t);
      var bv = Math.round(46  + (21  - 46)  * t);
      color = 'rgb(' + rv + ',' + gv + ',' + bv + ')';
    }

    var icon = L.divIcon({
      html: '<div style="width:28px;height:28px;background:' + color + ';border:2px solid #fff;border-radius:50% 50% 50% 0;transform:rotate(-45deg);box-shadow:0 2px 8px rgba(22,21,15,.4);"></div>',
      iconSize: [28, 28],
      iconAnchor: [14, 28],
      className: ''
    });

    var marker = L.marker([lat, lng], { icon: icon });
    marker.bindPopup(popupHTML(item, idx), { maxWidth: 280 });
    markers.addLayer(marker);
    bounds.push([lat, lng]);
  });

  if (bounds.length > 0) { map.fitBounds(bounds, { padding: [40, 40] }); }

  // Villes de référence — hors cluster, directement sur map
  var refCities = [
    { name: 'Avignon',         lat: 43.9493, lng: 4.8055  },
    { name: 'Marseille',       lat: 43.2965, lng: 5.3698  },
    { name: 'Aix-en-Provence', lat: 43.5297, lng: 5.4474  },
    { name: 'La Rochelle',     lat: 46.1603, lng: -1.1511 },
    { name: 'Bordeaux',        lat: 44.8378, lng: -0.5792 },
    { name: 'Nantes',          lat: 47.2184, lng: -1.5536 },
    { name: 'Nimes',           lat: 43.8367, lng: 4.3607  },
    { name: 'Gare Agen',    lat: 44.2010, lng: 0.6215  }
  ];

  var cityIcon = L.divIcon({
    html: '<div style="width:22px;height:22px;background:#7a4108;border:2px solid #fff;border-radius:50%;box-shadow:0 2px 8px rgba(22,21,15,.4);"></div>',
    iconSize: [22, 22], iconAnchor: [11, 11], className: ''
  });

  refCities.forEach(function(city) {
    L.marker([city.lat, city.lng], { icon: cityIcon, zIndexOffset: -100 })
      .addTo(map)
      .bindPopup('<div style="font-family:Inter,sans-serif;font-size:13px;font-weight:600;padding:4px 2px;color:#16150f;">' + (city.name.includes('Gare') ? '🚉' : '📍') + ' ' + city.name + '</div>', { maxWidth: 160 });
  });

  // Listener popup — une seule fois grâce au map.off() en début de fonction
  map.on('popupopen', function(e) {
    var popup = e.popup.getElement();
    if (!popup) return;

    var btnSlide = popup.querySelector('[data-open-viewer]');
    if (btnSlide && !btnSlide._bound) {
      btnSlide._bound = true;
      btnSlide.addEventListener('click', function() { openViewer(parseInt(btnSlide.dataset.openViewer)); });
    }

    popup.querySelectorAll('[data-popup-tag]').forEach(function(btn) {
      if (btn._bound) return;
      btn._bound = true;
      btn.addEventListener('click', function() {
        var popupIdx = parseInt(btn.dataset.popupIdx);
        var tagVal   = btn.dataset.popupTag;
        toggleSelection(popupIdx, tagVal);
        map.closePopup();
        renderMap();
      });
    });
  });
}


function popupHTML(item, idx) {
  const imgSrc = getImageUrl(item);
  const sel = item.selection || '';

  const btnStyle = 'flex:1;padding:6px 4px;border-radius:3px;cursor:pointer;font-size:11px;font-weight:600;text-align:center;border:1px solid';
  const shortActive = sel === 'shortlist';
  const ecartActive = sel === 'ecartee';

  const investActive = sel === 'invest';

  const btnShort = `<button data-popup-tag="shortlist" data-popup-idx="${idx}" style="${btnStyle} ${shortActive ? '#7ec99a;background:#d6f0df;color:#145a2e' : '#d0ccc3;background:#edeae3;color:#5a5850'}">⭐ ShortList</button>`;
  const btnEcart = `<button data-popup-tag="ecartee"   data-popup-idx="${idx}" style="${btnStyle} ${ecartActive ? '#d97373;background:#fce8e8;color:#831515'   : '#d0ccc3;background:#edeae3;color:#5a5850'}">✕ Écarter</button>`;
  const btnInvest = `<button data-popup-tag="invest" data-popup-idx="${idx}" style="${btnStyle} ${investActive ? '#e0a84a;background:#faefd5;color:#7a4108' : '#d0ccc3;background:#edeae3;color:#5a5850'}">$ Invest</button>`;

  return `
    <div style="font-family:Inter,sans-serif;font-size:13px;min-width:220px;color:#16150f;">
      ${imgSrc ? `<img src="${esc(imgSrc)}" style="width:100%;height:110px;object-fit:cover;border-radius:3px;margin-bottom:8px;" loading="lazy">` : ''}
      <div style="font-weight:600;margin-bottom:4px;line-height:1.3;">${esc(item.title || 'Annonce')}</div>
      ${selectionTagHTML(sel)}
      ${item.price ? `<div style="font-family:'JetBrains Mono',monospace;color:#7a4108;font-weight:700;margin-bottom:2px;">${formatPrice(item.price)}</div>` : ''}
      ${item.surface ? `<div style="color:#9a9890;font-size:12px;margin-bottom:6px;">${item.surface} m²</div>` : ''}
      <div style="display:flex;gap:5px;margin-bottom:6px;">
        ${btnShort}${btnEcart}${btnInvest}
      </div>
      <div style="display:flex;gap:5px;">
        <button data-open-viewer="${idx}" style="${btnStyle} #16150f;background:#16150f;color:#f5f3ee;flex:1;">🖼 Voir l'annonce</button>
        ${item.url ? `<a href="${esc(item.url)}" target="_blank" rel="noopener" style="${btnStyle} #d0ccc3;background:#edeae3;color:#16150f;flex:1;text-decoration:none;display:block;">→ Annonce</a>` : ''}
      </div>
    </div>`;
}

// ── Visionneuse d'annonce ──────────────────────────────────────
// Le carrousel fait défiler les PHOTOS de l'annonce affichée.
// Les contrôles en haut à droite font défiler les annonces filtrées.
function initViewer() {
  document.getElementById('viewer-close').addEventListener('click', closeViewer);
  document.getElementById('viewer-listing-prev').addEventListener('click', () => listingStep(-1));
  document.getElementById('viewer-listing-next').addEventListener('click', () => listingStep(1));
  document.getElementById('viewer-photo-prev').addEventListener('click', () => photoStep(-1));
  document.getElementById('viewer-photo-next').addEventListener('click', () => photoStep(1));
  document.getElementById('viewer-map-btn').addEventListener('click', () => showOnMap(viewer.listingIndex));
  document.getElementById('viewer-delete-btn').addEventListener('click', () => openDeleteModal(viewer.listingIndex));

  document.addEventListener('keydown', e => {
    if (!document.getElementById('viewer').classList.contains('open')) return;
    if (e.key === 'ArrowLeft')  photoStep(-1);
    if (e.key === 'ArrowRight') photoStep(1);
    if (e.key === 'Escape')     closeViewer();
  });

  document.getElementById('viewer').addEventListener('click', e => {
    if (e.target === document.getElementById('viewer')) closeViewer();
  });

  // Balayage tactile pour parcourir les photos sur mobile
  const media = document.getElementById('viewer-media');
  let touchX = null;
  media.addEventListener('touchstart', e => { touchX = e.touches[0].clientX; }, { passive: true });
  media.addEventListener('touchend', e => {
    if (touchX === null) return;
    const dx = e.changedTouches[0].clientX - touchX;
    if (Math.abs(dx) > 40) photoStep(dx > 0 ? -1 : 1);
    touchX = null;
  });
}

function openViewer(startIdx) {
  viewer.listingIndex = startIdx;
  document.getElementById('viewer').classList.add('open');
  document.body.style.overflow = 'hidden';
  renderViewer();
}

function closeViewer() {
  document.getElementById('viewer').classList.remove('open');
  document.body.style.overflow = '';
}

function renderViewer() {
  const item = filtered[viewer.listingIndex];
  if (!item) { closeViewer(); return; }

  renderViewerListingNav();
  viewer.photos     = getImages(item);
  viewer.photoIndex = 0;

  renderViewerPhoto();
  renderViewerThumbs();
  renderViewerInfo(item);
}

// ── Navigation entre annonces ─────────────────────────────────
function listingStep(dir) {
  if (filtered.length <= 1) return;
  viewer.listingIndex = (viewer.listingIndex + dir + filtered.length) % filtered.length;
  renderViewer();
}

function renderViewerListingNav() {
  const nav = document.getElementById('viewer-listing-nav');
  nav.hidden = filtered.length <= 1;
  document.getElementById('viewer-listing-counter').textContent =
    (viewer.listingIndex + 1) + ' / ' + filtered.length;
}

// ── Carrousel photos (annonce en cours) ────────────────────────
function photoStep(dir) {
  if (viewer.photos.length === 0) return;
  viewer.photoIndex = (viewer.photoIndex + dir + viewer.photos.length) % viewer.photos.length;
  renderViewerPhoto();
  updateActiveThumb();
}

function goToPhoto(i) {
  viewer.photoIndex = i;
  renderViewerPhoto();
  updateActiveThumb();
}

function renderViewerPhoto() {
  const img         = document.getElementById('viewer-img');
  const placeholder = document.getElementById('viewer-placeholder');
  const counter     = document.getElementById('viewer-photo-counter');
  const hasPhotos    = viewer.photos.length > 0;
  const hasMultiple  = viewer.photos.length > 1;

  img.style.display    = hasPhotos ? '' : 'none';
  placeholder.hidden   = hasPhotos;
  img.src              = hasPhotos ? viewer.photos[viewer.photoIndex] : '';

  counter.hidden     = !hasMultiple;
  counter.textContent = (viewer.photoIndex + 1) + ' / ' + viewer.photos.length;

  document.getElementById('viewer-photo-prev').hidden = !hasMultiple;
  document.getElementById('viewer-photo-next').hidden = !hasMultiple;
}

function renderViewerThumbs() {
  const wrap = document.getElementById('viewer-thumbs');
  if (viewer.photos.length <= 1) { wrap.innerHTML = ''; wrap.hidden = true; return; }

  wrap.hidden = false;
  wrap.innerHTML = viewer.photos.map((src, i) =>
    `<button class="viewer-thumb${i === viewer.photoIndex ? ' active' : ''}" data-photo-idx="${i}" style="background-image:url('${esc(src)}')" aria-label="Photo ${i + 1}"></button>`
  ).join('');

  wrap.querySelectorAll('.viewer-thumb').forEach(btn => {
    btn.addEventListener('click', () => goToPhoto(parseInt(btn.dataset.photoIdx)));
  });
}

function updateActiveThumb() {
  document.querySelectorAll('.viewer-thumb').forEach((btn, i) => {
    btn.classList.toggle('active', i === viewer.photoIndex);
  });
  const active = document.querySelector('.viewer-thumb.active');
  if (active) active.scrollIntoView({ block: 'nearest', inline: 'center' });
}

// ── Panneau d'informations ──────────────────────────────────────
function renderViewerInfo(item) {
  const idx = viewer.listingIndex;
  const sel = item.selection || '';

  document.getElementById('viewer-tags').innerHTML =
    '<span class="tag tag-fav">⭐ Favori</span>' + selectionTagHTML(sel) + (item.ficheUrl ? '<span class="tag tag-fiche">📄 Fiche</span>' : '');

  document.getElementById('viewer-title').textContent = item.title || 'Annonce immobilière';

  document.getElementById('viewer-price').textContent = item.price ? formatPrice(item.price) : (item.priceText || 'Prix non renseigné');
  const reduction = document.getElementById('viewer-price-reduction');
  if (item.priceReduction) { reduction.textContent = item.priceReduction; reduction.hidden = false; }
  else { reduction.hidden = true; }

  document.getElementById('viewer-location').textContent = getLoc(item) || 'Localisation non renseignée';

  document.getElementById('viewer-stats').innerHTML = viewerStatsHTML(item);

  const descSection = document.getElementById('viewer-desc-section');
  if (item.description) {
    descSection.hidden = false;
    document.getElementById('viewer-description').textContent = item.description;
  } else {
    descSection.hidden = true;
  }

  const featSection = document.getElementById('viewer-features-section');
  const featEntries = item.features && typeof item.features === 'object' ? Object.entries(item.features) : [];
  if (featEntries.length > 0) {
    featSection.hidden = false;
    document.getElementById('viewer-features').innerHTML = featEntries.map(([k, v]) =>
      `<div class="viewer-feature"><span class="viewer-feature-k">${esc(k)}</span><span class="viewer-feature-v">${esc(v)}</span></div>`
    ).join('');
  } else {
    featSection.hidden = true;
  }

  document.getElementById('viewer-meta').innerHTML = viewerMetaHTML(item);
  document.getElementById('viewer-selection-btns').innerHTML = viewerSelectionButtonsHTML(idx, sel);
  document.getElementById('viewer-selection-btns').querySelectorAll('[data-sel]').forEach(btn => {
    btn.addEventListener('click', () => toggleSelection(idx, btn.dataset.sel));
  });

  const link = document.getElementById('viewer-link');
  const linkUrl = item.url || item.destinationUrl || '';
  if (linkUrl) { link.href = linkUrl; link.style.display = ''; }
  else { link.style.display = 'none'; }

  // Lien fiche d'opportunité — visible uniquement si déjà renseigné (item.ficheUrl)
  const ficheLink = document.getElementById('viewer-fiche-link');
  if (item.ficheUrl) { ficheLink.href = item.ficheUrl; ficheLink.hidden = false; }
  else { ficheLink.hidden = true; }
}

function viewerStatsHTML(item) {
  const stats = [];
  if (item.surface)  stats.push(['📐 Surface',  item.surface + ' m²']);
  if (item.terrain)  stats.push(['🌳 Terrain',  item.terrain + ' m²']);
  if (item.rooms)    stats.push(['🚪 Pièces',   item.rooms]);
  if (item.bedrooms) stats.push(['🛏 Chambres', item.bedrooms]);

  if (stats.length === 0) return '';

  return stats.map(([label, value]) =>
    `<div class="viewer-stat"><div class="viewer-stat-label">${esc(label)}</div><div class="viewer-stat-value">${esc(value)}</div></div>`
  ).join('');
}

function viewerMetaHTML(item) {
  const rows = [];
  if (item.agency)     rows.push(`Agence : <b>${esc(item.agency)}</b>`);
  if (item.source)     rows.push(`Source : <b>${esc(sourceLabel(item.source))}</b>`);
  const capturedDate = formatDate(item.capturedAt);
  if (capturedDate)    rows.push(`Ajouté le : <b>${esc(capturedDate)}</b>`);
  if (item.postalCode) rows.push(`Code postal : <b>${esc(item.postalCode)}</b>`);

  if (rows.length === 0) return '<span>Aucune information complémentaire.</span>';
  return rows.map(r => `<div>${r}</div>`).join('');
}

function viewerSelectionButtonsHTML(idx, sel) {
  const options = [
    { key: 'shortlist', label: '⭐ ShortList' },
    { key: 'ecartee',   label: '✕ Écarter' },
    { key: 'invest',    label: '$ Investissement' }
  ];
  return options.map(o =>
    `<button class="viewer-sel-btn${sel === o.key ? ` tag-${o.key}-active` : ''}" data-sel="${o.key}" data-idx="${idx}">${o.label}</button>`
  ).join('');
}

function sourceLabel(source) {
  if (source === 'ga_favorite') return 'Favoris Green Acres';
  if (source === 'capture')     return 'Capture manuelle';
  return source;
}

function formatDate(ts) {
  if (!ts) return '';
  try { return new Date(ts).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }); }
  catch (e) { return ''; }
}

// ── Toggle sélection ─────────────────────────────────────────
async function toggleSelection(idx, sel) {
  const item = filtered[idx];
  if (!item) return;

  // Les sélections sont exclusives : un second clic retire le tag actif.
  const previousSel = item.selection || null;
  const newSel = previousSel === sel ? null : sel;
  item.selection = newSel;

  try {
    const response = await fetch('https://solenis-studio.fr/sigma-immo/api/tag.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: item.id, selection: newSel })
    });
    const result = await response.json();
    if (!response.ok || !result.ok || !result.updated) throw new Error(result.error || 'Tag non enregistré');
  } catch(e) {
    item.selection = previousSel;
    console.warn('[ImmoAgg] Tag serveur échoué, modification annulée:', e);
  }

  applyFiltersAndRender();
  if (document.getElementById('viewer').classList.contains('open')) renderViewer();
}

function selectionTagHTML(selection) {
  return selection === 'invest' ? '<span class="tag tag-invest">$ Invest</span>' : '';
}

function selectionBadgeHTML(selection) {
  if (selection === 'shortlist') return '<div class="card-selection-badge badge-shortlist">⭐ ShortList</div>';
  if (selection === 'ecartee') return '<div class="card-selection-badge badge-ecartee">✕ Écartée</div>';
  if (selection === 'invest') return '<div class="card-selection-badge badge-invest">$ Invest</div>';
  return '';
}

function selectionButtonHTML(idx, selection, className) {
  const labels = {
    shortlist: { label: '⭐', title: 'ShortList' },
    ecartee: { label: '✕', title: 'Écarter' },
    invest: { label: '$', title: 'Marquer comme investissement locatif' }
  };
  const option = labels[selection];
  const active = filtered[idx].selection === selection ? ` tag-${selection}-active` : '';
  return `<button class="${className}${active}" data-idx="${idx}" data-sel="${selection}" title="${option.title}" aria-label="${option.title}">${option.label}</button>`;
}

// ── Actions carte depuis galerie ─────────────────────────────
async function showOnMap(idx) {
  const item = filtered[idx];
  if (!item) return;

  // Passer en vue carte
  document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
  document.querySelector('.view-btn[data-view="map"]').classList.add('active');
  currentView = 'map';
  ['gallery', 'list', 'map'].forEach(v => {
    document.getElementById('view-' + v).classList.toggle('active', v === 'map');
  });

  await renderMap();

  // Centrer sur cet item et ouvrir son popup
  if (item.coords && item.coords.lat) {
    map.setView([item.coords.lat, item.coords.lng], 13);
    // Trouver le marker correspondant et ouvrir son popup
    markers.eachLayer(function(layer) {
      if (layer.getLatLng) {
        var ll = layer.getLatLng();
        if (Math.abs(ll.lat - item.coords.lat) < 0.001 && Math.abs(ll.lng - item.coords.lng) < 0.001) {
          layer.openPopup();
        }
      }
    });
  }
}

// ── Modale suppression ────────────────────────────────────────
let deleteTargetIdx = null;

function openDeleteModal(idx) {
  deleteTargetIdx = idx;
  const item = filtered[idx];
  document.getElementById('delete-modal-title').textContent = item ? item.title || 'cette annonce' : 'cette annonce';
  document.getElementById('delete-modal').classList.add('open');
}

function closeDeleteModal() {
  deleteTargetIdx = null;
  document.getElementById('delete-modal').classList.remove('open');
}

async function confirmDelete() {
  if (deleteTargetIdx === null) return;
  const item = filtered[deleteTargetIdx];
  if (!item) { closeDeleteModal(); return; }

  // Supprimer de allListings
  const globalIdx = allListings.indexOf(item);
  if (globalIdx !== -1) allListings.splice(globalIdx, 1);

  // Appeler API suppression
  try {
    await fetch('https://solenis-studio.fr/sigma-immo/api/delete.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: item.id })
    });
  } catch(e) {
    console.warn('[ImmoAgg] Suppression serveur échouée (locale OK):', e);
  }

  closeDeleteModal();
  applyFiltersAndRender();
}

// ── Helpers ───────────────────────────────────────────────────
function getImageUrl(item) {
  if (item.imageUrl)    return item.imageUrl;
  if (item.images && item.images[0]) return item.images[0];
  return '';
}

// Toutes les photos disponibles pour une annonce (dédupliquées), utilisées par la visionneuse.
function getImages(item) {
  let imgs = (item.images && item.images.length) ? item.images.filter(Boolean) : [];
  if (imgs.length === 0 && item.imageUrl) imgs = [item.imageUrl];
  return [...new Set(imgs)];
}

function getLoc(item) {
  return item.location || item.address || '';
}

function getDept(item) {
  const loc = item.location || '';
  const match = loc.match(/\(([^)]+)\)/);
  return match ? match[1] : '';
}

function formatPrice(price) {
  if (!price) return '';
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(price);
}

function esc(str) {
  return (str || '').toString()
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

function emptyHTML() {
  return `
    <div class="empty" style="grid-column:1/-1">
      <div class="empty-icon">🏚</div>
      <p>Aucune annonce ne correspond à vos filtres.<br>
      Utilisez l'extension Chrome pour capturer vos annonces.</p>
    </div>`;
}

function showError() {
  const grid = document.getElementById('gallery-grid');
  if (grid) grid.innerHTML = `
    <div class="empty" style="grid-column:1/-1">
      <div class="empty-icon">⚠️</div>
      <p>Impossible de charger les données.<br>
      Vérifiez la console pour plus de détails.</p>
    </div>`;
}

function debounce(fn, delay) {
  let timer;
  return function() {
    clearTimeout(timer);
    var args = arguments;
    timer = setTimeout(function() { fn.apply(null, args); }, delay);
  };
}

window.openViewer = openViewer;
