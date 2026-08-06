const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync(require.resolve('../public/assets/js/app.js'), 'utf8');
const context = { console, URL, window: {}, document: { addEventListener() {} } };
vm.createContext(context);
vm.runInContext(`${source}\nthis.testApi = { listingMatchesFilters, normalizedSelection, availableAnalysisTypes, scoreCircleHTML, renderCard: item => { filtered = [item]; return cardHTML(item, 0); }, popupHTML, compareListings, shouldOpenInApp, setInAppPropertyId };`, context);

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

test('affiche les données locatives disponibles sur la vignette', () => {
  const html = context.testApi.renderCard({
    id: 'bien-locatif', title: 'Bien locatif', analyses: {
      locatif: { available: true, revenuBrutAnnuel: 18800, rendementNetPct: 6.1 },
    },
  });
  assert.match(html, /class="card-locatif-summary"/);
  assert.match(html, />18\s?800\s?€ brut\/an</);
  assert.match(html, />6,1% net annuel</);
});

test('affiche le prix au m² à droite de la surface sur la vignette', () => {
  const html = context.testApi.renderCard({
    id: 'bien-prix-m2', title: 'Bien avec prix au mètre carré', price: 250000, surface: 50,
  });
  assert.match(html, /<span>50 m²<\/span>\s*<span>5\s?000\s?€\/m²<\/span>/);
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

test('ouvre la fiche courante depuis la carte', () => {
  const html = context.testApi.popupHTML({ id: 'bien avec espace', title: 'Bien cartographié' }, 0);
  assert.match(html, /href="fiche-bien\.html\?id=bien%20avec%20espace"/);
  assert.match(html, />Voir Fiche<\/a>/);
  assert.doesNotMatch(html, /data-open-viewer/);
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

test('trie les critères financiers locatifs dans les deux sens avec les valeurs manquantes à la fin', () => {
  const listings = [
    { id: 'incomplet', analyses: { locatif: { available: true } } },
    { id: 'fort', analyses: { locatif: { available: true, rendementNetPct: 7.2, revenuBrutAnnuel: 24000, cashflowMensuel: 320 } } },
    { id: 'faible', analyses: { locatif: { available: true, rendementNetPct: 4.4, revenuBrutAnnuel: 12000, cashflowMensuel: -80 } } },
  ];
  assert.deepEqual(listings.toSorted((a, b) => context.testApi.compareListings(a, b, 'yield_desc')).map(item => item.id), ['fort', 'faible', 'incomplet']);
  assert.deepEqual(listings.toSorted((a, b) => context.testApi.compareListings(a, b, 'revenue_asc')).map(item => item.id), ['faible', 'fort', 'incomplet']);
  assert.deepEqual(listings.toSorted((a, b) => context.testApi.compareListings(a, b, 'cashflow_asc')).map(item => item.id), ['faible', 'fort', 'incomplet']);
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

test('la carte ouvre la fiche actuelle du bien', () => {
  const html = context.testApi.popupHTML({ id: 'bien 42', title: 'Bien cartographié' }, 3);
  assert.match(html, /href="fiche-bien\.html\?id=bien%2042"/);
  assert.match(html, /data-open-property="3"/);
  assert.match(html, />Voir Fiche<\/a>/);
  assert.doesNotMatch(html, /data-open-viewer|Voir l'annonce/);
});
