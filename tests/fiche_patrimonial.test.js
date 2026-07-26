const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync(require.resolve('../templates/js/fiche.js'), 'utf8').replace('  render();', '  window.testApi = { negotiationContext };');
const context = {
  URL,
  URLSearchParams,
  location: { href: 'https://example.test/templates/fiche-investissement-patrimonial.html', search: '' },
  window: {},
  document: { currentScript: { src: 'https://example.test/templates/js/fiche.js', dataset: { analysisType: 'patrimonial' } }, documentElement: { dataset: {} } },
};
vm.createContext(context);
vm.runInContext(source, context);

const completeNegotiation = {
  echelle_valeur: {
    valeur_prudente_min_eur: 205000, valeur_prudente_max_eur: 240000, valeur_prudente_centrale_eur: 222000,
    offre_recommandee_min_eur: 215000, offre_recommandee_max_eur: 232000, prix_demande_eur: 265000,
    cout_total_eur: 294200, valeur_prudente_estimee: true, argumentaire: 'Écart central.',
  },
  coherence_prix: {
    prix_demande_m2_eur: 2208, surface_reference_m2: 120,
    reference_commune: { prix_m2_eur: 1609, ecart_pct: 37, source: 'Source commune', date: '2026-03' },
    reference_quartier: { prix_m2_eur: 1326, ecart_pct: 67, source: 'Source quartier', date: '2025-09' },
    references_marche: [{ source: 'Marché', type_bien: 'Maison', prix_m2_eur: 1609, fourchette_min_eur_m2: 703, fourchette_max_eur_m2: 3011, precision: '', date: '2026-03' }],
    bien_analyse: { type_bien: 'Maison', prix_m2_eur: 2208, fourchette_ou_commentaire: 'Surcote apparente', date: '2026-07' },
    perimetre_et_limites: 'Comparables à confirmer.',
  },
};

test('prépare les deux blocs de négociation à partir de données complètes', () => {
  const result = context.window.testApi.negotiationContext(completeNegotiation);
  assert.equal(result.negotiationAvailable, true);
  assert.equal(result.communeGap, '+37 %');
  assert.equal(result.marketRows.length, 2);
  assert.equal(result.marketRows[1].analyzed, true);
  assert.match(result.prudentStyle, /^left:[\d.]+%;width:[\d.]+%$/);
});

test('masque les blocs lorsque les anciennes analyses sont incomplètes', () => {
  assert.equal(context.window.testApi.negotiationContext({ prix_recommande_eur: 220000 }).negotiationAvailable, false);
});
