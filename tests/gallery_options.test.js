const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');

const script = fs.readFileSync(require.resolve('../assets/js/app.js'), 'utf8');
const page = fs.readFileSync(require.resolve('../app.html'), 'utf8');

test('les classements de visite sont placés dans le menu Plus d’options', () => {
  assert.match(page, /data-modal-selection="a_visiter"[^>]*>A visiter</);
  assert.match(page, /data-modal-selection="visite"[^>]*>Visité</);
  assert.match(script, /querySelectorAll\('\[data-modal-selection\]'\)/);
  assert.match(script, /toggleSelection\(deleteTargetIdx, button\.dataset\.modalSelection\)/);
});

test('la vignette conserve seulement les actions principales hors visite', () => {
  const cardFunction = script.slice(script.indexOf('function cardHTML'), script.indexOf('// ── Vue Liste'));
  assert.doesNotMatch(cardFunction, /selectionButtonHTML\(idx, 'a_visiter'/);
  assert.doesNotMatch(cardFunction, /selectionButtonHTML\(idx, 'visite'/);
  assert.match(cardFunction, /aria-label="Plus d’options"/);
});
