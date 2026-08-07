const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const styles = fs.readFileSync(path.join(root, 'public/assets/css/guides.css'), 'utf8');
const publicLayout = fs.readFileSync(path.join(root, 'app/Views/layouts/public.mustache'), 'utf8');
const guides = [
  'public/guide-mdb-division-parcellaire.html',
  'public/guide-investissement-locatif.html',
];
const dataUis = ['guide-mdb', 'guide-locatif'];

function siteHeader(html) {
  const match = html.match(/<header class="site-header">[\s\S]*?<\/header>/);
  assert.ok(match, 'le bandeau public doit être présent');
  return match[0];
}

// Spécificité CSS (id, classes/attributs/pseudo-classes, éléments), cf. https://www.w3.org/TR/selectors/#specificity
function specificity(selector) {
  let s = selector.trim();
  const ids = (s.match(/#[\w-]+/g) || []).length;
  const classesAttrsPseudo = (s.match(/\.[\w-]+|\[[^\]]*\]|:[\w-]+/g) || []).length;
  s = s.replace(/#[\w-]+/g, '').replace(/\.[\w-]+/g, '').replace(/\[[^\]]*\]/g, '').replace(/:[\w-]+/g, '');
  const types = (s.match(/[a-zA-Z][\w-]*/g) || []).length;
  return [ids, classesAttrsPseudo, types];
}

function compare([a1, a2, a3], [b1, b2, b3]) {
  return a1 - b1 || a2 - b2 || a3 - b3;
}

for (const dataUi of dataUis) {
  test(`la règle .active de ${dataUi} l'emporte sur le masquage des onglets (spécificité CSS)`, () => {
    const hideSelector = `html[data-ui="${dataUi}"] .tab-content`;
    const activeSelector = 'html[data-ui] .tab-content.active';

    assert.ok(styles.includes(`${hideSelector}{display:none`) || styles.includes(`${hideSelector},`),
      `la règle de masquage pour ${dataUi} doit exister`);
    assert.ok(styles.includes(activeSelector), 'la règle active générique doit exister');
    assert.ok(compare(specificity(activeSelector), specificity(hideSelector)) > 0,
      'la règle active doit avoir une spécificité CSS supérieure à la règle de masquage, quel que soit son ordre dans le fichier');
  });
}

for (const guide of guides) {
  test(`${guide} utilise le bandeau commun de la page Guides`, () => {
    const html = fs.readFileSync(path.join(root, guide), 'utf8');

    assert.equal(siteHeader(html), siteHeader(publicLayout));
    assert.match(html, /<link rel="stylesheet" href="assets\/css\/public\.css">/);
  });

  test(`${guide} active son premier onglet au chargement`, () => {
    const html = fs.readFileSync(path.join(root, guide), 'utf8');

    assert.match(html, /<button class="tab-btn active"[^>]*>Cadrage financier<\/button>/);
    assert.match(html, /<section class="tab-content active" id="cadrage">/);
  });
}
