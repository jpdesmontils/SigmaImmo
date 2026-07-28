const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync(require.resolve('../app.js'), 'utf8');
const context = { console, URL, window: {}, document: { addEventListener() {} } };
vm.createContext(context);
vm.runInContext(`${source}\nthis.testApi = { listingMatchesFilters, normalizedSelection, availableAnalysisTypes, scoreCircleHTML, renderCard: item => { filtered = [item]; return cardHTML(item, 0); }, compareListings, shouldOpenInApp, setInAppPropertyId };`, context);

const baseFilters = overrides => ({
  userSelections: new Set(), analysisTypes: new Set(), city: '', priceMin: null,
  priceMax: null, surfMin: null, surfMax: null, ...overrides,
});
const analyzed = {
  selection: 'shortlist', location: 'Lyon', price: 200000, surface: 60,
  analyses: { locatif: { available: true }, patrimonial: false, mdb: { available: true } },
};

test('combine les filtres en OU dans les groupes et en ET entre les groupes', () => {
  const filters = baseFilters({
    userSelections: new Set(['untagged', 'shortlist']),
    analysisTypes: new Set(['patrimonial', 'mdb']),
  });
  assert.equal(context.testApi.listingMatchesFilters(analyzed, filters), true);
  assert.equal(context.testApi.listingMatchesFilters({ ...analyzed, selection: 'ecartee' }, filters), false);
  assert.equal(context.testApi.listingMatchesFilters({ ...analyzed, analyses: { locatif: true } }, filters), false);
});

test('traite les anciens investissements comme sans tag', () => {
  assert.equal(context.testApi.normalizedSelection('invest'), '');
  assert.equal(context.testApi.listingMatchesFilters({ ...analyzed, selection: 'invest' }, baseFilters({ userSelections: new Set(['untagged']) })), true);
});

test('n’affiche pas de cercle sans score mais conserve zéro sur cent', () => {
  assert.equal(context.testApi.scoreCircleHTML({ analyses: { locatif: true }, latestAnalysis: { type: 'locatif', score: null } }), '');
  assert.match(context.testApi.scoreCircleHTML({ analyses: { locatif: true }, latestAnalysis: { type: 'locatif', score: 0 } }), />0<\/span>/);
});

test('superpose le cercle du score de la dernière analyse sur la vignette', () => {
  const html = context.testApi.renderCard({
    id: 'bien-note', title: 'Bien analysé', analyses: {
      locatif: { available: true }, patrimonial: { available: true }, mdb: { available: true },
    },
    latestAnalysis: { type: 'locatif', score: 72 },
  });
  assert.match(html, /<div class="card-media"[^>]*>.*<div class="card-score"/s);
  assert.match(html, />72<\/span>/);
  assert.match(html, /class="card-analysis-tags"/);
  assert.match(html, />Patrimonial<\/span>.*>Locatif<\/span>.*>Marchands de biens<\/span>/s);
});

test('le filtre sans note se combine avec les filtres de classement', () => {
  const filters = baseFilters({
    withoutScore: true,
    userSelections: new Set(['shortlist']),
  });
  assert.equal(context.testApi.listingMatchesFilters({ ...analyzed, latestAnalysis: { score: null } }, filters), true);
  assert.equal(context.testApi.listingMatchesFilters({ ...analyzed, latestAnalysis: { score: 72 } }, filters), false);
  assert.equal(context.testApi.listingMatchesFilters({ ...analyzed, selection: 'ecartee', latestAnalysis: { score: null } }, filters), false);
});

test('trie les notes dans les deux sens en laissant les annonces sans note à la fin', () => {
  const listings = [
    { id: 'sans-note', latestAnalysis: { score: null } },
    { id: 'haute', latestAnalysis: { score: 80 } },
    { id: 'zero', latestAnalysis: { score: 0 } },
    { id: 'basse', latestAnalysis: { score: 25 } },
  ];
  assert.deepEqual(listings.toSorted((a, b) => context.testApi.compareListings(a, b, 'score_asc')).map(item => item.id), ['zero', 'basse', 'haute', 'sans-note']);
  assert.deepEqual(listings.toSorted((a, b) => context.testApi.compareListings(a, b, 'score_desc')).map(item => item.id), ['haute', 'basse', 'zero', 'sans-note']);
});

test('ouvre la fiche dans l’application uniquement pour un clic principal sans modificateur', () => {
  assert.equal(context.testApi.shouldOpenInApp({ button: 0, ctrlKey: false, metaKey: false, shiftKey: false, altKey: false }), true);
  assert.equal(context.testApi.shouldOpenInApp({ button: 0, ctrlKey: true, metaKey: false, shiftKey: false, altKey: false }), false);
  assert.equal(context.testApi.shouldOpenInApp({ button: 1, ctrlKey: false, metaKey: false, shiftKey: false, altKey: false }), false);
});

test('transmet l’identifiant du bien au document chargé dans l’application', () => {
  const propertyApp = { dataset: {} };
  context.testApi.setInAppPropertyId({ getElementById: () => propertyApp }, { id: 'bien-42' });
  assert.equal(propertyApp.dataset.propertyId, 'bien-42');
});
