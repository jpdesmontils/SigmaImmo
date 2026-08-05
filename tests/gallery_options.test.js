const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');

const script = fs.readFileSync(require.resolve('../assets/js/app.js'), 'utf8');
const page = fs.readFileSync(require.resolve('../app.html'), 'utf8');

test('les actions secondaires sont placées dans le menu déroulant Plus d’options', () => {
  const cardFunction = script.slice(script.indexOf('function cardHTML'), script.indexOf('// ── Vue Liste'));
  assert.match(cardFunction, /data-option-action=\"map\"[^>]*>Voir sur la carte</);
  assert.match(cardFunction, /data-option-action=\"a_visiter\"[^>]*>A visiter</);
  assert.match(cardFunction, /data-option-action=\"visite\"[^>]*>Visité</);
  assert.match(cardFunction, /data-option-action=\"delete\"[^>]*>Supprimer</);
  assert.match(script, /if \(action === 'map'\) await showOnMap\(idx\)/);
  assert.match(script, /if \(action === 'a_visiter' \|\| action === 'visite'\) await toggleSelection\(idx, action\)/);
});

test('la vignette conserve seulement les actions principales hors visite', () => {
  const cardFunction = script.slice(script.indexOf('function cardHTML'), script.indexOf('// ── Vue Liste'));
  assert.doesNotMatch(cardFunction, /selectionButtonHTML\(idx, 'a_visiter'/);
  assert.doesNotMatch(cardFunction, /selectionButtonHTML\(idx, 'visite'/);
  assert.match(cardFunction, /aria-label="Plus d’options"/);
  assert.doesNotMatch(cardFunction, /class=\"card-btn-map\"/);
});
