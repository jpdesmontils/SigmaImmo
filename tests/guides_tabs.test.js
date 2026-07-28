const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const styles = fs.readFileSync(path.join(root, 'assets/css/guides.css'), 'utf8');
const guides = [
  'guide-mdb-division-parcellaire.html',
  'guide-investissement-locatif.html',
];

test('affiche le contenu actif après le masquage propre aux guides', () => {
  const hiddenRule = styles.lastIndexOf('.tab-content{display:none');
  const activeRule = styles.lastIndexOf('.tab-content.active{display:block}');

  assert.notEqual(hiddenRule, -1);
  assert.ok(activeRule > hiddenRule, 'la règle active doit suivre les règles qui masquent les onglets');
});

for (const guide of guides) {
  test(`${guide} active son premier onglet au chargement`, () => {
    const html = fs.readFileSync(path.join(root, guide), 'utf8');

    assert.match(html, /<button class="tab-btn active"[^>]*>Cadrage financier<\/button>/);
    assert.match(html, /<section class="tab-content active" id="cadrage">/);
  });
}
