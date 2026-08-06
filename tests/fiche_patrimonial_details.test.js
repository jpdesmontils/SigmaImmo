const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync(require.resolve('../public/assets/js/fiche.js'), 'utf8').replace('  render();', '  window.testApi = { patrimonialContext, renderTemplate };');
const templateSource = fs.readFileSync(require.resolve('../app/Views/fiche-investissement-patrimonial.html'), 'utf8');
const template = templateSource.match(/<script id="fiche-template" type="x-template">([\s\S]*?)<\/script>/)[1];
const context = {
  URL,
  URLSearchParams,
  location: { href: 'https://example.test/templates/fiche-investissement-patrimonial.html', search: '' },
  window: {},
  document: { currentScript: { src: 'https://example.test/templates/js/fiche.js', dataset: { analysisType: 'patrimonial' } }, documentElement: { dataset: {} } },
};
vm.createContext(context);
vm.runInContext(source, context);

test('prépare les détails patrimoniaux, financiers et ferroviaires', () => {
  const result = context.window.testApi.patrimonialContext({
    acquisition: { travaux_immediats_eur: 60000, travaux_patrimoniaux_eur: 20000, frais_annexes_eur: 5000 },
    qualite_patrimoniale: { adequation_usage: 'mixte', commentaire_argumente: 'Commentaire patrimonial.' },
    leviers_optimisation: {
      levier_retenu: 'longue_duree',
      justification_levier_retenu: 'Stratégie stable.',
      location: { faisabilite: 'probable', applicable: true, travaux_eur: 20000, delai_estime_mois: 3, sources: [{ label: 'PLU', url: 'https://example.test/plu', date_consultation: '2026-07-26' }] },
    },
    financement: { justification_scenario: 'Approche prudente.', scenarios: [{ code: 'apport_75', taux_credit_pct: 3.5, duree_credit_ans: 20, condition_activation: 'Loyers validés' }] },
    decision: {},
    accessibilite_depuis_paris: {
      itineraire_recommande: { trains: [{ type: 'TGV INOUI' }], duree_train_typique: 165 },
      alternatives: [{ gare_arrivee: { nom: 'Avignon Centre' }, nombre_correspondances: 1, duree_train_typique: 190, commentaire: 'Accès aux bus.' }],
      evaluation: { criteres: { duree: { points: 30, maximum: 35, justification: 'Rapide.' } } },
      sources: [{ label: 'SNCF', url: 'https://www.sncf-connect.com', date_consultation: '2026-07-26' }],
      avertissements: ['Horaires à confirmer.'],
    },
    avertissements: ['Localisation imprécise.'],
  });

  assert.equal(result.trainTypes, 'TGV INOUI');
  assert.equal(result.trainTypical, '2 h 45 min');
  assert.equal(result.alternatives[0].station, 'Avignon Centre');
  assert.equal(result.alternatives[0].duration, '3 h 10 min');
  assert.equal(result.alternatives[0].comment, 'Accès aux bus.');
  assert.equal(result.criteria[0].score, '30/35');
  assert.equal(result.retainedLever, 'Longue Duree');
  assert.equal(result.levers[0].works.replace(/\s/g, ''), '20000€');
  assert.equal(result.scenarios[0].activationCondition, 'Loyers validés');
  assert.equal(result.immediateWorks.replace(/\s/g, ''), '60000€');
  assert.equal(result.accessSources[0].url, 'https://www.sncf-connect.com/');
  assert.equal(result.warnings[0], 'Localisation imprécise.');
});

test('écarte les URL de source non HTTP', () => {
  const result = context.window.testApi.patrimonialContext({ sources: [{ label: 'Source', url: 'javascript:alert(1)' }] });
  assert.equal(result.sources[0].url, '');
});

test('rend les détails enrichis sans convertir les objets ferroviaires en texte', () => {
  const data = {
    decision: {},
    accessibilite_depuis_paris: {
      itineraire_recommande: { trains: [{ type: 'TGV INOUI' }] },
      alternatives: [{ gare_arrivee: { nom: 'Avignon Centre' }, nombre_correspondances: 1, duree_train_typique: 190, commentaire: 'Accès aux bus.' }],
    },
    sources: [{ label: 'Annonce', url: 'https://example.test/annonce', date_consultation: '2026-07-26' }],
  };
  const html = context.window.testApi.renderTemplate(template, context.window.testApi.patrimonialContext(data));

  assert.match(html, /TGV INOUI/);
  assert.match(html, /Avignon Centre/);
  assert.match(html, /Accès aux bus\./);
  assert.match(html, /href="https:\/\/example\.test\/annonce"/);
  assert.doesNotMatch(html, /\[object Object\]/);
});
