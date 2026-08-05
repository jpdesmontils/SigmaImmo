(() => {
  const API_ROOT = new URL('api/', document.baseURI);
  const types = { patrimonial: 'Patrimoine', locatif: 'Locatif', mdb: 'Marchand de biens' };
  const app = document.getElementById('property-app');
  const resolvePropertyId = (container, search) => container?.dataset?.propertyId || new URLSearchParams(search).get('id');
  let id = resolvePropertyId(app, location.search);
  let data, pollTimer, activeTab;
  const templateCache = new Map();
  const galleryState = (() => { try { return JSON.parse(localStorage.getItem('immoagg.gallery.state') || '{}'); } catch (_) { return {}; } })();
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const money = value => Number.isFinite(Number(value)) ? Number(value).toLocaleString('fr-FR',{style:'currency',currency:'EUR',maximumFractionDigits:0}) : 'Prix non renseigné';
  const landSurface = value => Number.isFinite(Number(value)) && Number(value) > 0 ? `${formatNumber(value)} m²` : '-';
  const lastTabKey = 'immoagg.property.lastTab';
  const editableKpis = {
    surface: { label: 'Surface', type: 'number', suffix: ' m²' },
    terrain: { label: 'Terrain', type: 'number', suffix: ' m²' },
    dpe: { label: 'DPE', type: 'energy', suffix: '' },
    ges: { label: 'GES', type: 'energy', suffix: '' }
  };

  async function request(url, options) {
    const response = await fetch(url, options), payload = await response.json();
    if (!response.ok) { const error = new Error(payload.error || `Erreur HTTP ${response.status}`); error.payload = payload; throw error; }
    return payload;
  }
  async function load() {
    if (!id) return fail('Identifiant du bien manquant.');
    try { data = await request(new URL(`property.php?id=${encodeURIComponent(id)}`, API_ROOT)); renderShell(); selectTab(validTab(localStorage.getItem(lastTabKey)) || 'annonce'); }
    catch (error) { fail(error.message); }
  }
  function validTab(value) { return ['annonce', 'notes', 'prix', ...Object.keys(types)].includes(value) ? value : null; }
  function renderShell() {
    const listing = data.listing, locationText = listing.location || listing.address || 'Localisation non renseignée', slider = listingSlider();
    document.title = `${listing.title || 'Fiche du bien'} — ImmoAggregator`;
    app.className = '';
    app.innerHTML = `<header class="property-header"><div class="property-top"><a class="property-back" href="app.html" data-in-app-return>← Retour à la galerie</a><span class="property-brand">ImmoAggregator</span><button class="property-config-button" type="button" data-config>⚙ Config</button><div class="listing-slider" aria-label="Navigation entre les annonces"><button data-listing-prev aria-label="Annonce précédente" ${slider.previous?'':'disabled'}>‹</button><span>${slider.position} / ${slider.total}</span><button data-listing-next aria-label="Annonce suivante" ${slider.next?'':'disabled'}>›</button></div></div><nav class="property-tabs" role="tablist" aria-label="Fiche du bien">${tabButton('annonce','Annonce')}${tabButton('prix','Prix')}${tabButton('patrimonial','Patrimoine')}${tabButton('locatif','Locatif')}${tabButton('mdb','<span class="mdb-long">Marchand de biens</span><span class="mdb-short">MDB</span>')}${tabButton('notes','Notes')}</nav></header><section class="property-content" id="property-panel" role="tabpanel"></section>`;
    app.querySelectorAll('[data-tab]').forEach(button => button.addEventListener('click', () => selectTab(button.dataset.tab)));
    app.querySelector('[data-config]')?.addEventListener('click', openConfigModal);
    app.querySelector('[data-listing-prev]')?.addEventListener('click', () => navigateListing(slider.previous));
    app.querySelector('[data-listing-next]')?.addEventListener('click', () => navigateListing(slider.next));
    app.dataset.location = locationText;
  }
  function tabButton(key,label) { return `<button class="property-tab" role="tab" data-tab="${key}" aria-selected="false">${label}</button>`; }
  function listingSlider() { let ids = Array.isArray(galleryState.listingIds) && galleryState.listingIds.length ? galleryState.listingIds : [id]; if (!ids.includes(id)) ids = [id]; const index = ids.indexOf(id); return { previous: index > 0 ? ids[index-1] : null, next: index < ids.length-1 ? ids[index+1] : null, position: index+1, total: ids.length }; }
  async function navigateListing(nextId) { if (!nextId) return; const active = validTab(localStorage.getItem(lastTabKey)) || 'annonce'; clearTimeout(pollTimer); id = nextId; history.replaceState(null, '', `?id=${encodeURIComponent(id)}`); try { data = await request(new URL(`property.php?id=${encodeURIComponent(id)}`, API_ROOT)); renderShell(); selectTab(active); scrollTo(0,0); } catch (error) { fail(error.message); } }
  function selectTab(tab) {
    activeTab = tab; localStorage.setItem(lastTabKey, tab);
    app.querySelectorAll('[data-tab]').forEach(button => button.setAttribute('aria-selected', String(button.dataset.tab === tab)));
    if (tab === 'annonce') renderListing(); else if (tab === 'notes') renderNotes(); else if (tab === 'prix') renderPrices(); else renderAnalysis(tab);
  }
  async function renderPrices(recalculate = false) {
    panel().innerHTML = state('prix', recalculate ? 'Recalcul du positionnement' : 'Recherche des ventes comparables', 'Interrogation des mutations DVF les plus récentes…', '<div class="analysis-spinner" aria-hidden="true"></div>');
    try {
      const prices = await request(new URL(`prices.php?id=${encodeURIComponent(id)}`, API_ROOT), recalculate ? { method: 'POST' } : undefined);
      if (localStorage.getItem(lastTabKey) !== 'prix') return;
      panel().innerHTML = priceContent(prices);
      panel().querySelector('[data-price-recalculate]').addEventListener('click', () => renderPrices(true));
      bindPriceTransactions(prices);
    } catch (error) {
      if (localStorage.getItem(lastTabKey) !== 'prix') return;
      const missing = Array.isArray(error.payload?.missing) && error.payload.missing.length;
      panel().innerHTML = state('prix', 'Comparables indisponibles', error.message, missing ? '<button class="property-primary" data-price-complete>Compléter l’annonce</button>' : '<button class="property-primary" data-price-retry>Réessayer</button>', 'analysis-error');
      panel().querySelector('[data-price-retry]')?.addEventListener('click', renderPrices);
      panel().querySelector('[data-price-complete]')?.addEventListener('click', () => selectTab('annonce'));
    }
  }
  function priceContent(prices) {
    const summary = prices.summary, items = prices.transactions || [];
    if (!summary) return state('prix', 'Positionnement indisponible', 'Les ventes trouvées ne permettent pas de calculer un prix au mètre carré.', '');
    const gap = summary.asking_gap_percent, above = Number(gap) > 0;
    const gapText = Number.isFinite(Number(gap)) ? `${Math.abs(Number(gap)).toLocaleString('fr-FR')} % ${above ? 'au-dessus' : 'au-dessous'} de la médiane` : 'Écart non calculable';
    const argument = above
      ? `Le prix demandé dépasse de ${Math.abs(Number(gap)).toLocaleString('fr-FR')} % la médiane des ${items.length} ventes retenues. Une première offre entre ${money(summary.offer_low)} et ${money(summary.offer_high)} est cohérente avec les mutations publiées, sous réserve de l’état réel du bien.`
      : `Le prix demandé se situe ${gapText}. La négociation doit prioritairement s’appuyer sur les travaux et défauts constatés plutôt que sur une décote générale du marché.`;
    const rows = items.map(item => `<tr><td><strong>${esc(formatDate(item.date))}</strong><small>${esc(item.address)}</small></td><td>${esc(item.type)}<small>${item.rooms ? `${esc(item.rooms)} pièce(s)` : '—'}</small></td><td>${esc(formatNumber(item.surface))} m²</td><td>${esc(landSurface(item.land_surface))}</td><td>${esc(money(item.value))}</td><td><strong>${esc(money(item.price_per_sqm))}/m²</strong></td><td>${item.distance_km === null ? '—' : `${esc(formatNumber(item.distance_km, 1))} km`}</td></tr>`).join('');
    return `<section class="price-page"><div class="price-heading"><div><div class="analysis-eyebrow">Marché réel · DVF</div><h1>Positionnement du prix</h1><p>${esc(prices.location.label)} · périmètre retenu : ${esc(prices.perimeter)} · données du ${esc(formatDateTime(prices.captured_at))}</p></div><div class="price-heading-actions"><button class="property-primary" data-price-recalculate>Recalculer</button><a href="${esc(prices.source.url)}" target="_blank" rel="noopener" class="property-secondary">Consulter la source ↗</a></div></div><div class="price-summary-grid"><article class="price-summary-card"><small>Prix demandé</small><strong>${esc(money(prices.property.asking_price))}</strong><span>${esc(money(summary.asking_price_per_sqm))}/m²</span></article><article class="price-summary-card median" style="${medianCardStyle(gap)}"><small>Médiane des comparables</small><strong>${esc(money(summary.median_price_per_sqm))}/m²</strong><span>${esc(gapText)}</span></article><article class="price-summary-card"><small>Valeur prudente</small><strong>${esc(money(summary.prudent_value_low))} – ${esc(money(summary.prudent_value_high))}</strong><span>Quartiles des ventes retenues</span></article><article class="price-summary-card offer"><small>Contre-offre recommandée</small><strong>${esc(money(summary.offer_low))} – ${esc(money(summary.offer_high))}</strong><span>Point de départ à ajuster après visite</span></article></div><article class="price-argument"><div class="analysis-eyebrow">Argumentaire de négociation</div><h2>${above ? 'Un écart objectivable avec le marché' : 'Un prix déjà aligné avec le marché'}</h2><p>${esc(argument)}</p><ul><li>Médiane observée : ${esc(money(summary.median_price_per_sqm))}/m².</li><li>Fourchette interquartile : ${esc(money(summary.low_price_per_sqm))} à ${esc(money(summary.high_price_per_sqm))}/m².</li><li>${items.length} mutation${items.length > 1 ? 's' : ''} comparable${items.length > 1 ? 's' : ''}, regroupée${items.length > 1 ? 's' : ''} pour éviter les doublons de lots.</li></ul></article><section class="price-transactions"><div class="price-section-title"><div><div class="analysis-eyebrow">Transactions retenues</div><h2>Les ${items.length} ventes comparables les plus récentes</h2></div><span>${esc(prices.source.name)}</span></div><div class="price-view-tabs" role="tablist" aria-label="Vue des ventes comparables"><button role="tab" aria-selected="true" data-price-view="list">Liste</button><button role="tab" aria-selected="false" data-price-view="map">Carte</button></div><div data-price-view-panel="list" role="tabpanel"><div class="price-table-wrap"><table><thead><tr><th>Date et adresse</th><th>Bien</th><th>Surface</th><th>Terrain</th><th>Prix de vente</th><th>Prix/m²</th><th>Distance</th></tr></thead><tbody>${rows}</tbody></table></div></div><div class="price-map-panel" data-price-view-panel="map" role="tabpanel" hidden><div class="price-map" data-price-map aria-label="Carte des ventes comparables"></div><p class="price-map-legend"><span>Prix au m² faible</span><i aria-hidden="true"></i><span>Prix au m² élevé</span></p></div><p class="price-limitations">${esc(prices.source.limitations)}</p></section></section>`;
  }
  function medianCardStyle(gap) {
    const numericGap = Number(gap);
    if (!Number.isFinite(numericGap)) return '--median-bg:rgb(255, 255, 255);--median-text:var(--text);--median-muted:var(--text-2)';
    const boundedGap = Math.max(-35, Math.min(35, numericGap));
    const target = boundedGap < 0 ? [198, 239, 206] : [139, 0, 0];
    const ratio = Math.abs(boundedGap) / 35;
    const background = target.map(channel => Math.round(255 + (channel - 255) * ratio));
    const lightText = boundedGap >= 22;
    return `--median-bg:rgb(${background.join(', ')});--median-text:${lightText ? '#fff' : 'var(--text)'};--median-muted:${lightText ? 'rgba(255,255,255,.82)' : 'var(--text-2)'}`;
  }
  function priceMarkerColor(value, minimum, maximum) {
    const ratio = maximum > minimum ? Math.max(0, Math.min(1, (Number(value) - minimum) / (maximum - minimum))) : 0;
    const low = [134, 217, 147], high = [220, 76, 76];
    return `rgb(${low.map((channel, index) => Math.round(channel + (high[index] - channel) * ratio)).join(', ')})`;
  }
  function priceHouseColor(summary, items) {
    const values = items.map(item => Number(item.price_per_sqm));
    const askingPrice = summary ? summary.asking_price_per_sqm : null;
    return items.length && Number.isFinite(Number(askingPrice)) ? priceMarkerColor(askingPrice, Math.min(...values), Math.max(...values)) : 'var(--text)';
  }
  function bindPriceTransactions(prices) {
    const buttons = panel().querySelectorAll('[data-price-view]');
    let map;
    buttons.forEach(button => button.addEventListener('click', () => {
      buttons.forEach(item => item.setAttribute('aria-selected', String(item === button)));
      panel().querySelectorAll('[data-price-view-panel]').forEach(item => { item.hidden = item.dataset.priceViewPanel !== button.dataset.priceView; });
      if (button.dataset.priceView === 'map') map = map || renderPriceMap(prices);
    }));
  }
  function hasCoordinates(item) { return item && item.latitude !== null && item.latitude !== '' && item.longitude !== null && item.longitude !== '' && Number.isFinite(Number(item.latitude)) && Number.isFinite(Number(item.longitude)); }
  function renderPriceMap(prices) {
    const target = panel().querySelector('[data-price-map]');
    const items = (prices.transactions || []).filter(hasCoordinates);
    const origin = prices.location || {};
    if (!target || typeof L === 'undefined') { if (target) target.textContent = 'La carte est temporairement indisponible.'; return null; }
    const center = hasCoordinates(origin) ? [Number(origin.latitude), Number(origin.longitude)] : (items[0] ? [Number(items[0].latitude), Number(items[0].longitude)] : [46.8, 2.3]);
    const map = L.map(target).setView(center, 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap', maxZoom: 19 }).addTo(map);
    const bounds = [];
    const values = items.map(item => Number(item.price_per_sqm)), minimum = Math.min(...values), maximum = Math.max(...values);
    if (hasCoordinates(origin)) {
      const houseColor = priceHouseColor(prices.summary, items);
      L.marker(center, { icon: L.divIcon({ className: 'price-house-marker', html: `<span aria-hidden="true" style="background:${houseColor}">⌂</span>`, iconSize: [34, 34], iconAnchor: [17, 17] }), title: `Adresse retenue : ${origin.label || ''}` }).addTo(map).bindPopup(priceHousePopup(origin, prices.summary), { maxWidth: 300 });
      bounds.push(center);
    }
    items.forEach(item => {
      const coordinates = [Number(item.latitude), Number(item.longitude)];
      const color = priceMarkerColor(item.price_per_sqm, minimum, maximum);
      L.circleMarker(coordinates, { radius: 10, color: '#fff', weight: 2, fillColor: color, fillOpacity: 1 }).addTo(map).bindTooltip(`${money(item.price_per_sqm)}/m²`).bindPopup(priceTransactionPopup(item), { maxWidth: 320 });
      bounds.push(coordinates);
    });
    if (bounds.length > 1) map.fitBounds(bounds, { padding: [28, 28], maxZoom: 16 });
    if (!items.length) target.insertAdjacentHTML('beforeend', '<p class="price-map-empty">Aucune transaction géolocalisée.</p>');
    setTimeout(() => map.invalidateSize(), 0);
    return map;
  }
  function priceTransactionPopup(item) {
    return `<div class="price-map-popup"><small>Vente comparable</small><strong>${esc(item.address)}</strong><dl><div><dt>Date</dt><dd>${esc(formatDate(item.date))}</dd></div><div><dt>Bien</dt><dd>${esc(item.type)} · ${item.rooms ? `${esc(item.rooms)} pièce(s)` : 'pièces non publiées'}</dd></div><div><dt>Surface</dt><dd>${esc(formatNumber(item.surface))} m²</dd></div><div><dt>Terrain</dt><dd>${esc(landSurface(item.land_surface))}</dd></div><div><dt>Prix de vente</dt><dd>${esc(money(item.value))}</dd></div><div><dt>Prix au m²</dt><dd><b>${esc(money(item.price_per_sqm))}/m²</b></dd></div><div><dt>Distance</dt><dd>${item.distance_km === null ? '—' : `${esc(formatNumber(item.distance_km, 1))} km`}</dd></div></dl></div>`;
  }
  function priceHousePopup(origin, summary) {
    return `<div class="price-map-popup"><small>Bien analysé</small><strong>${esc(origin.label || 'Adresse retenue')}</strong><span>Prix demandé au m²</span><b>${esc(money(summary ? summary.asking_price_per_sqm : null))}/m²</b></div>`;
  }
  function formatDate(value) { if (!value) return 'Date inconnue'; const parsed = new Date(`${value}T00:00:00`); return Number.isNaN(parsed.getTime()) ? value : parsed.toLocaleDateString('fr-FR'); }
  function formatDateTime(value) { if (!value) return 'date inconnue'; const parsed = new Date(value); return Number.isNaN(parsed.getTime()) ? value : parsed.toLocaleString('fr-FR'); }
  function formatNumber(value, digits = 0) { return Number(value).toLocaleString('fr-FR', { maximumFractionDigits: digits }); }

  function residenceCityValue() { return data.listing.primaryResidenceCity || data.settings?.primaryResidenceCity || 'Paris'; }
  function residenceOptionsHtml(prefix) {
    const city = residenceCityValue();
    return `<div class="analysis-residence-options"><div class="property-field"><label for="${prefix}-primary-residence-city">Ville de résidence principale utilisée pour l’analyse</label><input id="${prefix}-primary-residence-city" name="primaryResidenceCity" maxlength="120" value="${esc(city)}" required><small>Cette ville sert d’origine au trajet de l’analyse patrimoniale.</small></div><label class="property-checkbox"><input type="checkbox" name="saveDefault" value="1" checked> Utiliser cette ville pour toutes les autres annonces par défaut</label></div>`;
  }
  async function saveDefaultResidenceCity(city) {
    data.settings = (await request(new URL('settings.php', API_ROOT), { method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ primaryResidenceCity: city }) })).settings;
  }
  async function persistResidenceOptions(form) {
    const city = form.get('primaryResidenceCity');
    await saveFields({ primaryResidenceCity: city });
    if (form.get('saveDefault')) await saveDefaultResidenceCity(city);
  }
  function openConfigModal() {
    const modal = document.createElement('div');
    modal.className = 'property-modal';
    modal.innerHTML = `<form class="property-modal-box property-config-form" role="dialog" aria-modal="true"><h2>Configuration de la fiche</h2><p>Ajustez les paramètres utilisés pour les analyses de ce bien.</p>${residenceOptionsHtml('config')}<div class="analysis-form-actions"><button type="button" class="property-secondary" data-close>Annuler</button><button class="property-primary">Enregistrer</button></div></form>`;
    const close = () => modal.remove();
    modal.addEventListener('click', event => { if (event.target === modal) close(); });
    modal.querySelector('[data-close]').addEventListener('click', close);
    modal.querySelector('form').addEventListener('submit', async event => {
      event.preventDefault();
      try { await persistResidenceOptions(new FormData(event.currentTarget)); close(); toast('Configuration enregistrée.'); }
      catch (error) { toast(error.message); }
    });
    document.body.append(modal);
    modal.querySelector('input').focus();
  }
  function openPatrimonialRecalculationModal() {
    const modal = document.createElement('div');
    modal.className = 'property-modal';
    modal.innerHTML = `<form class="property-modal-box property-config-form" role="dialog" aria-modal="true"><h2>Recalculer l’analyse patrimoine</h2><p>Confirmez le recalcul et ajustez si besoin la ville d’origine utilisée pour l’analyse trajet.</p>${residenceOptionsHtml('recalc')}<div class="analysis-form-actions"><button type="button" class="property-secondary" data-close>Annuler</button><button class="property-primary">Recalculer</button></div></form>`;
    const close = () => modal.remove();
    modal.addEventListener('click', event => { if (event.target === modal) close(); });
    modal.querySelector('[data-close]').addEventListener('click', close);
    modal.querySelector('form').addEventListener('submit', async event => {
      event.preventDefault();
      try { await persistResidenceOptions(new FormData(event.currentTarget)); close(); await startAnalysis('patrimonial'); }
      catch (error) { toast(error.message); }
    });
    document.body.append(modal);
    modal.querySelector('input').focus();
  }

  function renderListing() {
    const item = data.listing, images = [...new Set((item.images?.length ? item.images : [item.imageUrl]).filter(Boolean))];
    const hasGallery = images.length > 1;
    const image = images[0] ? `<div class="property-gallery-stage"><img class="property-gallery-main" data-main-image src="${esc(images[0])}" alt="${esc(item.title || 'Photo du bien')}">${hasGallery ? '<button class="property-gallery-arrow previous" type="button" data-gallery-step="-1" aria-label="Photo précédente">‹</button><button class="property-gallery-arrow next" type="button" data-gallery-step="1" aria-label="Photo suivante">›</button>' : ''}</div>` : '<div class="property-gallery-main"></div>';
    const thumbs = hasGallery ? `<div class="property-thumbnail-strip"><button class="property-thumbnail-arrow" type="button" data-thumbnail-step="-1" aria-label="Miniature précédente" disabled>‹</button><div class="property-thumbnails-viewport"><div class="property-thumbnails">${images.map((src,i)=>`<button class="${i?'':'active'}" data-image-index="${i}" style="background-image:url('${esc(src)}')" aria-label="Afficher la photo ${i+1}"></button>`).join('')}</div></div><button class="property-thumbnail-arrow" type="button" data-thumbnail-step="1" aria-label="Miniature suivante" ${images.length <= 8 ? 'disabled' : ''}>›</button></div>` : '';
    panel().innerHTML = `<div class="listing-heading"><small>Annonce sauvegardée</small><h1>${esc(item.title || 'Annonce immobilière')}</h1><p>${esc(item.location || 'Localisation à compléter')}</p></div><div class="listing-layout"><div class="property-gallery-wrap">${image}${listingOverlays()}${thumbs}</div><aside class="listing-summary"><div class="listing-price">${esc(money(item.price))}</div><div class="listing-kpis">${kpiField('surface')}${kpiField('terrain')}${kpiField('dpe')}${kpiField('ges')}<div><small>Pièces</small><strong>${esc(item.rooms || '—')}</strong></div></div><form class="property-panel property-field" id="address-form"><label for="exact-address">Adresse exacte</label><input id="exact-address" name="address" value="${esc(item.address || item.location || '')}"><small>Facultative — améliore la précision géographique, mais n’est pas nécessaire pour lancer une analyse.</small><div class="analysis-form-actions"><button class="property-secondary">Enregistrer l’adresse</button></div></form>${item.url?`<a class="property-primary property-source-link" href="${esc(item.url)}" target="_blank" rel="noopener">Voir l’annonce d’origine ↗</a>`:''}<div class="property-actions property-classification-actions"><button class="property-action" data-selection="shortlist">${item.selection==='shortlist'?'★ ShortList':'☆ Favori'}</button><button class="property-action" data-selection="a_visiter">${item.selection==='a_visiter'?'✓ A visiter':'○ A visiter'}</button><button class="property-action" data-selection="visite">${item.selection==='visite'?'✓ Visité':'○ Visité'}</button><button class="property-action" data-selection="ecartee">${item.selection==='ecartee'?'✓ Écarté':'× Écarter'}</button></div><div class="property-actions property-utility-actions"><button class="property-action" data-map>⌖ Carte</button><button class="property-action property-danger" data-delete-listing>Supprimer</button></div></aside></div><section class="property-panel listing-description"><h2>Description</h2><p>${esc(item.description || 'Aucune description disponible.')}</p></section>`;
    bindGallery(images);
    panel().querySelector('#address-form').addEventListener('submit', saveAddress);
    panel().querySelectorAll('[data-selection]').forEach(button => button.addEventListener('click', () => updateSelection(button.dataset.selection)));
    panel().querySelectorAll('[data-edit-field]').forEach(value => value.addEventListener('dblclick', () => editKpi(value.dataset.editField)));
    panel().querySelectorAll('[data-edit-trigger]').forEach(button => button.addEventListener('click', () => editKpi(button.dataset.editTrigger)));
    panel().querySelector('[data-map]').addEventListener('click', () => location.href = `app.html?view=map&listing=${encodeURIComponent(id)}`);
    panel().querySelector('[data-delete-listing]').addEventListener('click', deleteListing);
  }
  function renderNotes() {
    const item = data.listing;
    const address = item.address || item.location || 'Adresse non renseignée dans l’annonce';
    panel().innerHTML = `<section class="notes-page"><div class="listing-heading"><small>Suivi de la visite</small><h1>Notes et rendez-vous</h1><p>Ces informations sont propres à ce bien et restent modifiables.</p></div><form class="notes-form" data-notes-form><article class="property-panel notes-card"><h2>Rendez-vous de visite</h2><div class="notes-grid"><div class="property-field"><label for="visit-at">Date et heure</label><input id="visit-at" name="visitAt" type="datetime-local" value="${esc(item.visitAt || '')}"></div><div class="property-field"><label for="visit-address">Adresse du bien</label><input id="visit-address" value="${esc(address)}" readonly aria-describedby="visit-address-help"><small id="visit-address-help">Adresse reprise de l’onglet Annonce.</small></div></div></article><article class="property-panel notes-card"><h2>Agent immobilier</h2><div class="notes-grid notes-agent-grid"><div class="property-field"><label for="agent-name">Nom</label><input id="agent-name" name="agentName" maxlength="300" value="${esc(item.agentName || '')}"></div><div class="property-field"><label for="agent-phone">Téléphone</label><input id="agent-phone" name="agentPhone" type="tel" maxlength="40" value="${esc(item.agentPhone || '')}">${contactLink('tel', item.agentPhone, 'Appeler l’agent')}</div><div class="property-field"><label for="agent-email">E-mail</label><input id="agent-email" name="agentEmail" type="email" maxlength="254" value="${esc(item.agentEmail || '')}">${contactLink('mailto', item.agentEmail, 'Écrire à l’agent')}</div></div></article><article class="property-panel notes-card property-field"><label for="property-notes">Notes libres</label><textarea id="property-notes" name="notes" maxlength="10000" rows="12" placeholder="Observations, questions à poser, travaux à vérifier…">${esc(item.notes || '')}</textarea></article><div class="notes-actions"><button class="property-primary">Enregistrer les notes</button></div></form></section>`;
    panel().querySelector('[data-notes-form]').addEventListener('submit', saveNotes);
  }
  function contactLink(protocol, value, label) {
    if (!value) return '';
    const hrefValue = protocol === 'tel' ? String(value).replace(/[^0-9+]/g, '') : value;
    return `<a class="notes-contact-link" href="${protocol}:${esc(hrefValue)}">${esc(label)}</a>`;
  }
  async function saveNotes(event) {
    event.preventDefault();
    const fields = Object.fromEntries(new FormData(event.currentTarget));
    try { await saveFields(fields); renderNotes(); toast('Notes enregistrées.'); }
    catch (error) { toast(error.message); }
  }
  function bindGallery(images) {
    if (images.length < 2) return;
    let imageIndex = 0;
    const thumbnails = [...panel().querySelectorAll('[data-image-index]')];
    const viewport = panel().querySelector('.property-thumbnails-viewport');
    const thumbnailArrows = [...panel().querySelectorAll('[data-thumbnail-step]')];
    const selectImage = nextIndex => {
      imageIndex = (nextIndex + images.length) % images.length;
      panel().querySelector('[data-main-image]').src = images[imageIndex];
      thumbnails.forEach((thumbnail, index) => thumbnail.classList.toggle('active', index === imageIndex));
      thumbnails[imageIndex].scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
      updateThumbnailArrows();
    };
    const updateThumbnailArrows = () => {
      const maximum = Math.max(0, viewport.scrollWidth - viewport.clientWidth);
      thumbnailArrows[0].disabled = viewport.scrollLeft <= 1;
      thumbnailArrows[1].disabled = viewport.scrollLeft >= maximum - 1;
    };
    panel().querySelectorAll('[data-gallery-step]').forEach(button => button.addEventListener('click', () => selectImage(imageIndex + Number(button.dataset.galleryStep))));
    thumbnails.forEach(button => button.addEventListener('click', () => selectImage(Number(button.dataset.imageIndex))));
    thumbnailArrows.forEach(button => button.addEventListener('click', () => {
      const thumbnailWidth = thumbnails[0].getBoundingClientRect().width + 6;
      viewport.scrollBy({ left: Number(button.dataset.thumbnailStep) * thumbnailWidth, behavior: 'smooth' });
    }));
    viewport.addEventListener('scroll', updateThumbnailArrows, { passive: true });
  }
  function isMissing(value) { return value === '' || value === null || value === undefined; }
  function kpiField(field) {
    const config = editableKpis[field], value = data.listing[field], missing = isMissing(value);
    const pencil = missing ? `<button class="kpi-edit-trigger" type="button" data-edit-trigger="${field}" aria-label="Renseigner ${esc(config.label)}" title="Renseigner ce champ">✎</button>` : '';
    return `<div class="listing-kpi" data-kpi="${field}"><small>${esc(config.label)}${pencil}</small><strong data-edit-field="${field}" title="Double-cliquer pour modifier">${missing ? '-' : esc(value)+config.suffix}</strong></div>`;
  }
  function editKpi(field) {
    const config = editableKpis[field], container = panel().querySelector(`[data-kpi="${field}"]`);
    if (!config || !container || container.querySelector('input')) return;
    const current = isMissing(data.listing[field]) ? '' : data.listing[field];
    const attributes = config.type === 'number' ? 'type="number" min="0" step="any"' : 'type="text" maxlength="1" pattern="[A-Ga-g]"';
    container.querySelector('strong').outerHTML = `<input class="kpi-edit-input" data-kpi-input="${field}" ${attributes} value="${esc(current)}" aria-label="${esc(config.label)}">`;
    const input = container.querySelector('input');
    let finished = false;
    const finish = async save => {
      if (finished) return;
      if (save && !input.reportValidity()) return;
      finished = true;
      if (save) {
        try { await saveFields({[field]: input.value}); toast(`${config.label} enregistré${config.label === 'Surface' ? 'e' : ''}.`); }
        catch (error) { toast(error.message); }
      }
      renderListing();
    };
    input.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); finish(true); } else if (event.key === 'Escape') finish(false); });
    input.addEventListener('blur', () => finish(true));
    input.focus(); input.select();
  }
  function listingOverlays() { const selection = data.listing.selection, ribbons = {shortlist:['shortlist','★ ShortList'],ecartee:['ecartee','× Écarté'],a_visiter:['a-visiter','A visiter'],visite:['visite','Visité']}, ribbonData=ribbons[selection], ribbon=ribbonData?`<span class="property-ribbon ${ribbonData[0]}">${ribbonData[1]}</span>`:''; const labels={patrimonial:'Patrimoine',locatif:'Locatif',mdb:'Marchand de biens'}, tags=Object.keys(labels).filter(type=>data.analyses[type]&&data.analyses[type].available!==false).map(type=>`<span class="property-analysis-tag ${type}">${labels[type]}</span>`).join(''); return `${ribbon}${tags?`<div class="property-analysis-tags">${tags}</div>`:''}`; }
  function renderAnalysis(type) {
    const summary = data.analyses[type], job = data.job;
    if (job && job.type === type && ['queued','running'].includes(job.status)) return renderRunning(type, job);
    if (job && job.type === type && job.status === 'failed' && !summary) return renderFailed(type, job);
    if (summary && summary.available !== false) return renderAvailable(type);
    const missing = data.requirements[type]?.missing || [];
    if (missing.length) return renderForm(type, missing);
    if (type === 'patrimonial') {
      panel().innerHTML = state(type, `Lancer l’analyse ${types[type].toLowerCase()}`, 'Les données indispensables sont présentes. Choisissez la ville d’origine utilisée pour le trajet.', `<form data-analysis-launch>${residenceOptionsHtml('launch')}<div class="analysis-form-actions"><button class="property-primary">Lancer l’analyse</button></div></form>`);
      panel().querySelector('[data-analysis-launch]').addEventListener('submit', async event => { event.preventDefault(); try { await persistResidenceOptions(new FormData(event.currentTarget)); await startAnalysis(type); } catch (error) { toast(error.message); } });
      return;
    }
    panel().innerHTML = state(type, `Lancer l’analyse ${types[type].toLowerCase()}`, 'Les données indispensables sont présentes. L’analyse est calculée indépendamment des autres opportunités.', `<button class="property-primary" data-start>Lancer l’analyse</button>`);
    panel().querySelector('[data-start]').addEventListener('click',()=>startAnalysis(type));
  }
  function renderForm(type, missing) {
    panel().innerHTML = `<div class="analysis-state"><form class="analysis-state-card analysis-form" id="requirements-form"><div class="analysis-eyebrow">${esc(types[type])} · données indispensables</div><h2>Compléter avant l’analyse</h2><p>Seuls les champs absents et réellement nécessaires au prompt sont demandés.</p><div class="analysis-form-grid">${missing.map(field=>`<div class="property-field"><label for="req-${field.field}">${esc(field.label)} *</label><input id="req-${field.field}" name="${field.field}" type="${field.type}" ${field.type === 'number' ? 'min="0"' : ''} ${field.pattern ? `pattern="${esc(field.pattern)}"` : ''} ${field.maxlength ? `maxlength="${esc(field.maxlength)}"` : ''} value="${esc(data.listing[field.field] || '')}" required></div>`).join('')}</div>${type === 'patrimonial' ? residenceOptionsHtml('requirements') : ''}<div class="property-panel property-field"><label for="req-address">Adresse exacte — facultatif</label><input id="req-address" name="address" value="${esc(data.listing.address || data.listing.location || '')}"><small>Son absence ne bloque pas le calcul.</small></div><div class="analysis-form-actions"><button type="button" class="property-secondary" data-cancel>Annuler</button><button class="property-primary">Enregistrer et lancer</button></div></form></div>`;
    panel().querySelector('[data-cancel]').addEventListener('click',()=>selectTab('annonce'));
    panel().querySelector('form').addEventListener('submit', async event => { event.preventDefault(); const form = new FormData(event.currentTarget), values=Object.fromEntries(form); if (type === 'patrimonial') { await persistResidenceOptions(form); delete values.saveDefault; } await saveFields(values); await startAnalysis(type); });
  }
  function renderRunning(type, job) { panel().innerHTML = state(type,'Analyse en cours',`Lancée ${job.started_at ? new Date(job.started_at).toLocaleString('fr-FR') : 'il y a quelques instants'}. Vous pouvez changer d’onglet et revenir plus tard.`, '<div class="analysis-spinner" aria-hidden="true"></div>'); scheduleAnalysisRefresh(type); }
  function renderFailed(type, job) { panel().innerHTML=state(type,"L’analyse n’a pas abouti",job.error||'Une erreur technique est survenue.',`<button class="property-primary" data-retry>Réessayer</button> <button class="property-danger" data-delete>Supprimer l’analyse</button>`,'analysis-error'); panel().querySelector('[data-retry]').onclick=()=>startAnalysis(type);panel().querySelector('[data-delete]').onclick=()=>deleteAnalysis(type); }
  async function renderAvailable(type) { const renderId=id; panel().innerHTML=`<div class="analysis-toolbar"><button class="property-secondary" data-recalculate>Recalculer</button><button class="property-danger" data-delete>Supprimer l’analyse</button></div><div class="native-analysis" data-analysis-content>Chargement de l’analyse…</div>`;panel().querySelector('[data-recalculate]').onclick=()=>type === 'patrimonial' ? openPatrimonialRecalculationModal() : startAnalysis(type);panel().querySelector('[data-delete]').onclick=()=>deleteAnalysis(type); try { const template=await analysisTemplate(type); if(id!==renderId||localStorage.getItem(lastTabKey)!==type)return; await window.ImmoAnalysisRenderer.render({target:panel().querySelector('[data-analysis-content]'),type,id,template}); } catch(error) { const target=panel().querySelector('[data-analysis-content]'); if(target)target.textContent=`Analyse indisponible : ${error.message}`; } }
  async function analysisTemplate(type) { if (templateCache.has(type)) return templateCache.get(type); const response=await fetch(`templates/fiche-investissement-${type}.html`); if(!response.ok)throw new Error(`modèle HTTP ${response.status}`); const documentTemplate=new DOMParser().parseFromString(await response.text(),'text/html'); const template=documentTemplate.getElementById('fiche-template')?.innerHTML; if(!template)throw new Error('modèle de fiche absent'); templateCache.set(type,template); return template; }
  function state(type,title,message,actions,extra=''){return `<div class="analysis-state"><div class="analysis-state-card ${extra}"><div class="analysis-eyebrow">${type === 'prix' ? 'Données de marché' : `Analyse ${esc(types[type])}`}</div><h2>${esc(title)}</h2><p>${esc(message)}</p>${actions}</div></div>`}
  async function saveAddress(event){event.preventDefault();await saveFields({address:new FormData(event.currentTarget).get('address')});toast('Adresse enregistrée.');}
  async function saveFields(fields){data=await request(new URL(`property.php?id=${encodeURIComponent(id)}`,API_ROOT),{method:'PATCH',headers:{'Content-Type':'application/json'},body:JSON.stringify(fields)});}
  async function startAnalysis(type){try{await request(new URL('analyze.php',API_ROOT),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id,type})});window.ImmoAnalysisNotifications.track({id,type,title:data.listing.title});await refresh(type)}catch(error){toast(error.message)}}
  function scheduleAnalysisRefresh(type){clearTimeout(pollTimer);pollTimer=setTimeout(()=>refresh(type),2500)}
  async function refresh(type){
    try{
      data=await request(new URL(`property.php?id=${encodeURIComponent(id)}`,API_ROOT));
      const job=data.job;
      if(activeTab===type) renderAnalysis(type);
      else if(job&&job.type===type&&['queued','running'].includes(job.status)) scheduleAnalysisRefresh(type);
      else if(activeTab==='annonce') renderListing();
    }catch(error){toast(error.message);scheduleAnalysisRefresh(type)}
  }
  document.addEventListener?.('immoagg:open-analysis',event=>{if(String(event.detail.id)!==String(id))return;event.preventDefault();selectTab(event.detail.type)});
  function confirmDeletion(message, action){window.ImmoModal.open({title:'Confirmer la suppression',message,actions:[{label:'Annuler'},{label:'Supprimer',type:'delete',onClick:async()=>{try{await action()}catch(error){toast(error.message)}}}]})}
  function deleteAnalysis(type){confirmDeletion(`Supprimer définitivement l’analyse ${types[type]} ?`,async()=>{await request(new URL(`property.php?id=${encodeURIComponent(id)}&type=${type}`,API_ROOT),{method:'DELETE'});await refresh(type)})}
  async function updateSelection(selection){const current=data.listing.selection===selection?null:selection;await request(new URL('tag.php',API_ROOT),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id,selection:current})});data.listing.selection=current;renderListing();toast(current?'Classement enregistré.':'Classement retiré.');}
  function deleteListing(){confirmDeletion('Supprimer définitivement cette annonce et toutes ses analyses ?',async()=>{await request(new URL('delete.php',API_ROOT),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});location.href='app.html'})}
  function panel(){return document.getElementById('property-panel')}
  function toast(message,options={}){window.ImmoAnalysisNotifications.toast(message,options)}
  function fail(message){app.className='property-error';app.textContent=message}
  load();
})();
