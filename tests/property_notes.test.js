const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');

const propertyScript = fs.readFileSync(require.resolve('../assets/js/property.js'), 'utf8');
const galleryScript = fs.readFileSync(require.resolve('../assets/js/app.js'), 'utf8');
const tagApi = fs.readFileSync(require.resolve('../api/tag.php'), 'utf8');
const propertyApi = fs.readFileSync(require.resolve('../api/property.php'), 'utf8');

test('les deux classements de visite sont proposés et validés par l’API', () => {
  assert.match(galleryScript, /a_visiter:\s*\{[^}]*label: 'A visiter'/);
  assert.match(galleryScript, /visite:\s*\{[^}]*label: 'Visité'/);
  assert.match(tagApi, /'a_visiter', 'visite'/);
});

test('la fiche propose un onglet Notes avec le rendez-vous et l’adresse canonique', () => {
  assert.match(propertyScript, /tabButton\('notes','Notes'\)/);
  assert.match(propertyScript, /type="datetime-local"/);
  assert.match(propertyScript, /item\.address \|\| item\.location/);
  assert.match(propertyScript, /Adresse reprise de l’onglet Annonce/);
});

test('les coordonnées structurées créent les liens de contact demandés', () => {
  assert.match(propertyScript, /name="agentName"/);
  assert.match(propertyScript, /name="agentPhone" type="tel"/);
  assert.match(propertyScript, /name="agentEmail" type="email"/);
  assert.match(propertyScript, /contactLink\('tel'/);
  assert.match(propertyScript, /contactLink\('mailto'/);
});

test('l’API persiste et valide les champs de suivi sans dupliquer l’adresse', () => {
  for (const field of ['notes', 'visitAt', 'agentName', 'agentPhone', 'agentEmail']) {
    assert.match(propertyApi, new RegExp(`'${field}'\\s*=>`));
  }
  assert.match(propertyApi, /FILTER_VALIDATE_EMAIL/);
  assert.match(propertyApi, /\\d\{4\}-\\d\{2\}-\\d\{2\}T\\d\{2\}:\\d\{2\}/);
  assert.doesNotMatch(propertyApi, /visitAddress/);
});
