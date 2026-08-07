const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');

const script = fs.readFileSync(require.resolve('../public/assets/js/property.js'), 'utf8');

test('les deux choix de visibilité déclenchent leur enregistrement', () => {
  assert.match(script, /value="shared" data-visibility/);
  assert.match(script, /value="private" data-visibility/);
  assert.match(script, /querySelectorAll\('\[data-visibility\]'\)\.forEach\(control => control\.addEventListener\('change', updateVisibility\)\)/);
  assert.match(script, /saveFields\(\{ visibility: event\.target\.value \}\)/);
});
