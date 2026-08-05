const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');

const script = fs.readFileSync(require.resolve('../assets/js/app.js'), 'utf8');
const page = fs.readFileSync(require.resolve('../app.html'), 'utf8');

test('Plus d’options ouvre un menu déroulant avec les actions demandées', () => {
  const cardFunction = script.slice(script.indexOf('function cardHTML'), script.indexOf('// ── Vue Liste'));
  assert.match(cardFunction, /class="card-options-menu"/);
  assert.match(cardFunction, /data-card-option="map"[^>]*>Voir sur la carte</);
  assert.match(cardFunction, /data-card-option="a_visiter"[^>]*>A visiter</);
  assert.match(cardFunction, /data-card-option="visite"[^>]*>Visité</);
  assert.match(cardFunction, /data-card-option="delete"[^>]*>Supprimer</);
  assert.doesNotMatch(cardFunction, /class="card-btn-map"/);
});

test('Supprimer du menu déclenche la modale de confirmation dédiée', () => {
  assert.match(script, /if \(btn\.dataset\.cardOption === 'delete'\) return openDeleteModal\(idx\)/);
  assert.match(page, /<h3>🗑 Supprimer l'annonce<\/h3>/);
  assert.doesNotMatch(page, /data-modal-selection=/);
});

test('la vignette conserve seulement les actions principales hors visite', () => {
  const cardFunction = script.slice(script.indexOf('function cardHTML'), script.indexOf('// ── Vue Liste'));
  assert.doesNotMatch(cardFunction, /selectionButtonHTML\(idx, 'a_visiter'/);
  assert.doesNotMatch(cardFunction, /selectionButtonHTML\(idx, 'visite'/);
  assert.match(cardFunction, /aria-label="Plus d’options"/);
});
