const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync(require.resolve('../app.js'), 'utf8');
const context = { console, URL, window: {}, document: { addEventListener() {} } };
vm.createContext(context);
vm.runInContext(`${source}\nthis.testApi = { listingMatchesFilters, normalizedSelection, availableAnalysisTypes, scoreCircleHTML };`, context);

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
