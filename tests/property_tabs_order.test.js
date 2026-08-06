const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');

const script = fs.readFileSync(require.resolve('../public/assets/js/property.js'), 'utf8');

test('place l’onglet Notes en dernière position à droite', () => {
  const navigation = script.slice(script.indexOf('<nav class="property-tabs"'), script.indexOf('</nav>'));
  assert.ok(navigation.indexOf("tabButton('mdb'") < navigation.indexOf("tabButton('notes','Notes')"));
  assert.match(navigation, /tabButton\('notes','Notes'\)\}$/);
});
