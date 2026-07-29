const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync(require.resolve('../assets/js/property.js'), 'utf8')
  .replace('  load();', '  window.priceTestApi = { validTab, priceContent, medianCardStyle, priceMarkerColor, priceHouseColor, priceTransactionPopup, priceHousePopup, hasCoordinates, resolvePropertyId };');
const panel = { innerHTML: '', querySelectorAll() { return []; } };
const context = {
  console, URL, URLSearchParams, Intl,
  location: { href: 'http://localhost/fiche-bien.html?id=test', search: '?id=test' },
  localStorage: { getItem() { return 'prix'; }, setItem() {} },
  document: { baseURI: 'http://localhost/', getElementById() { return panel; } },
  window: {}, setTimeout() {}, clearTimeout() {}, fetch() {},
};
vm.createContext(context);
vm.runInContext(source, context);

test('reconnaît Prix comme onglet principal persistant', () => {
  assert.equal(context.window.priceTestApi.validTab('prix'), 'prix');
});

test('privilégie l’identifiant transmis par la vue intégrée', () => {
  assert.equal(context.window.priceTestApi.resolvePropertyId({ dataset: { propertyId: 'in-app' } }, '?id=hors-app'), 'in-app');
  assert.equal(context.window.priceTestApi.resolvePropertyId({ dataset: {} }, '?id=hors-app'), 'hors-app');
});

test('rend une synthèse, une contre-offre et les transactions DVF', () => {
  const html = context.window.priceTestApi.priceContent({
    property: { asking_price: 330000 }, captured_at: '2026-07-28T10:30:00Z',
    location: { label: '12 rue Test, Lyon' }, perimeter: '1 km et surface ± 30 %',
    source: { name: 'DVF', url: 'https://data.gouv.fr', limitations: 'Limites DVF' },
    summary: { asking_gap_percent: 10, asking_price_per_sqm: 5500, median_price_per_sqm: 5000, low_price_per_sqm: 4700, high_price_per_sqm: 5200, prudent_value_low: 282000, prudent_value_high: 312000, offer_low: 270000, offer_high: 285000 },
    transactions: [{ id: 'v1', date: '2025-06-02', address: '12 rue Test', type: 'Appartement', rooms: 3, surface: 60, land_surface: 200, value: 300000, price_per_sqm: 5000, distance_km: 0.4 }],
  });
  assert.match(html, /Positionnement du prix/);
  assert.match(html, /Contre-offre recommandée/);
  assert.match(html, /12 rue Test/);
  assert.match(html, /10 % la médiane/);
  assert.match(html, /data-price-recalculate>Recalculer/);
  assert.match(html, /data-price-view="list"/);
  assert.match(html, /data-price-view="map"/);
  assert.match(html, /data-price-map/);
  assert.match(html, /Terrain/);
  assert.match(html, /200 m²/);
});

test('calcule un dégradé du vert clair au rouge selon le prix au m²', () => {
  assert.equal(context.window.priceTestApi.priceMarkerColor(4000, 4000, 6000), 'rgb(134, 217, 147)');
  assert.equal(context.window.priceTestApi.priceMarkerColor(6000, 4000, 6000), 'rgb(220, 76, 76)');
});

test('colore le bloc médiane de vert clair à rouge foncé avec un point médian blanc', () => {
  const style = context.window.priceTestApi.medianCardStyle;
  assert.match(style(-35), /--median-bg:rgb\(198, 239, 206\)/);
  assert.match(style(0), /--median-bg:rgb\(255, 255, 255\)/);
  assert.match(style(35), /--median-bg:rgb\(139, 0, 0\)/);
  assert.equal(style(-50), style(-35));
  assert.equal(style(50), style(35));
  assert.match(style(35), /--median-text:#fff/);
});

test('colore la maison avec le même dégradé que les ventes', () => {
  assert.equal(context.window.priceTestApi.priceHouseColor({ asking_price_per_sqm: 5000 }, [{ price_per_sqm: 4000 }, { price_per_sqm: 6000 }]), context.window.priceTestApi.priceMarkerColor(5000, 4000, 6000));
});

test('écarte de la carte les ventes sans coordonnées publiées', () => {
  assert.equal(context.window.priceTestApi.hasCoordinates({ latitude: null, longitude: null }), false);
  assert.equal(context.window.priceTestApi.hasCoordinates({ latitude: 48.85, longitude: 2.35 }), true);
});

test('affiche la ligne complète de la vente dans une popup cartographique', () => {
  const html = context.window.priceTestApi.priceTransactionPopup({ date: '2025-06-02', address: '12 rue Test', type: 'Appartement', rooms: 3, surface: 60, land_surface: 200, value: 300000, price_per_sqm: 5000, distance_km: 0.4 });
  assert.match(html, /Vente comparable/);
  assert.match(html, /12 rue Test/);
  assert.match(html, /300[\s\u00a0]000/);
  assert.match(html, /5[\s\u00a0]000/);
  assert.match(html, /0,4 km/);
  assert.match(html, /Terrain/);
  assert.match(html, /200 m²/);
});

test('affiche le prix demandé au m² dans la popup de la maison', () => {
  const html = context.window.priceTestApi.priceHousePopup({ label: '8 avenue Centrale' }, { asking_price_per_sqm: 5500 });
  assert.match(html, /8 avenue Centrale/);
  assert.match(html, /Prix demandé au m²/);
  assert.match(html, /5[\s\u00a0]500/);
});

test('affiche un tiret quand la superficie du terrain est absente', () => {
  const html = context.window.priceTestApi.priceTransactionPopup({ address: 'Sans terrain', type: 'Appartement', surface: 40, value: 200000, price_per_sqm: 5000, distance_km: null });
  assert.match(html, /<dt>Terrain<\/dt><dd>-<\/dd>/);
});
