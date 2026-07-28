(() => {
  const sidebar = document.querySelector('[data-app-sidebar]');
  if (!sidebar) return;

  sidebar.innerHTML = `
    <nav class="in-app-nav" aria-label="Navigation">
      <button class="in-app-nav-group-toggle" id="guides-toggle" aria-expanded="true" aria-controls="guides-list"><span class="in-app-nav-title">Guides disponibles</span><span class="in-app-nav-chevron" aria-hidden="true">▾</span></button>
      <div class="in-app-nav-group-items" id="guides-list">
        <button class="in-app-nav-btn" data-in-app-url="guide-mdb-division-parcellaire.html">Guide opérationnel — Marchand de biens<small>Division parcellaire · 100 k€ d'apport</small></button>
        <button class="in-app-nav-btn" data-in-app-url="guide-investissement-locatif.html">Guide opérationnel — Investissement locatif<small>Sélection à mise en location · 125 k€ d'apport</small></button>
      </div><div data-in-app-favorites></div>
    </nav><hr class="in-app-nav-separator">
    <div class="filter-group"><h3>Ville</h3><input class="filter-input" id="f-city" type="text" placeholder="Ex: Paris, Lyon…"></div>
    <div class="filter-group"><h3>Prix (€)</h3><div class="range-row"><input class="filter-input" id="f-price-min" type="number" placeholder="Min"><input class="filter-input" id="f-price-max" type="number" placeholder="Max"></div></div>
    <div class="filter-group"><h3>Surface (m²)</h3><div class="range-row"><input class="filter-input" id="f-surf-min" type="number" placeholder="Min"><input class="filter-input" id="f-surf-max" type="number" placeholder="Max"></div></div>
    <div class="filter-group"><h3>Tri</h3><select class="sort-select" id="f-sort"><option value="date_desc">Plus récent</option><option value="date_asc">Plus ancien</option><option value="price_asc">Prix croissant</option><option value="price_desc">Prix décroissant</option><option value="surface_desc">Surface décroissante</option><option value="surface_asc">Surface croissante</option><option value="score_desc">Note décroissante</option><option value="score_asc">Note croissante</option></select></div>
    <div class="filter-group"><h3>Classement</h3><div class="selection-btns" data-filter-group="user"><button class="selection-btn active" data-user-filter="all" aria-pressed="true">Tous</button><button class="selection-btn" data-user-filter="untagged" aria-pressed="false">Sans tag</button><button class="selection-btn sel-shortlist" data-user-filter="shortlist" aria-pressed="false">⭐ ShortList</button><button class="selection-btn sel-ecartee" data-user-filter="ecartee" aria-pressed="false">✕ Écartés</button></div></div>
    <div class="filter-group"><h3>Types d’analyse</h3><div class="selection-btns" data-filter-group="analysis"><button class="selection-btn" data-analysis-filter="patrimonial" aria-pressed="false">Patrimonial</button><button class="selection-btn" data-analysis-filter="locatif" aria-pressed="false">Locatif</button><button class="selection-btn" data-analysis-filter="mdb" aria-pressed="false">Marchand de biens</button></div></div>
    <button class="btn-reset" id="btn-reset">↺ Réinitialiser les filtres</button>`;

  if (document.documentElement.dataset.ui !== 'property') return;
  const key = 'immoagg.gallery.state';
  let state;
  try { state = JSON.parse(localStorage.getItem(key) || '{}'); } catch (_) { state = {}; }
  const fields = { 'f-city': 'city', 'f-price-min': 'priceMin', 'f-price-max': 'priceMax', 'f-surf-min': 'surfMin', 'f-surf-max': 'surfMax', 'f-sort': 'sort' };
  Object.keys(fields).forEach(id => {
    const input = document.getElementById(id), value = state[fields[id]];
    if (value !== null && value !== undefined) input.value = value;
    input.addEventListener('change', () => openGallery({ [fields[id]]: input.value || null }));
  });
  restoreButtons('userSelections', '[data-user-filter]', 'userFilter');
  restoreButtons('analysisTypes', '[data-analysis-filter]', 'analysisFilter');
  document.getElementById('btn-reset').addEventListener('click', () => openGallery({ city: '', priceMin: null, priceMax: null, surfMin: null, surfMax: null, sort: 'date_desc', userSelections: [], analysisTypes: [] }));
  sidebar.querySelectorAll('[data-in-app-url]').forEach(button => button.addEventListener('click', () => { location.href = `index.html?content=${encodeURIComponent(button.dataset.inAppUrl)}`; }));
  document.getElementById('btn-filters-mobile').addEventListener('click', () => { sidebar.classList.add('open'); document.getElementById('sidebar-overlay').classList.add('open'); });
  document.getElementById('sidebar-overlay').addEventListener('click', () => { sidebar.classList.remove('open'); document.getElementById('sidebar-overlay').classList.remove('open'); });

  function restoreButtons(field, selector, datasetKey) {
    const selected = new Set(Array.isArray(state[field]) ? state[field] : []);
    sidebar.querySelectorAll(selector).forEach(button => {
      const value = button.dataset[datasetKey];
      button.classList.toggle('active', value === 'all' ? selected.size === 0 : selected.has(value));
      button.setAttribute('aria-pressed', String(button.classList.contains('active')));
      button.addEventListener('click', () => openGallery({ [field]: value === 'all' ? [] : [value] }));
    });
  }
  function openGallery(changes) {
    localStorage.setItem(key, JSON.stringify(Object.assign({}, state, changes, { view: 'gallery', scrollY: 0 })));
    location.href = 'index.html';
  }
})();
