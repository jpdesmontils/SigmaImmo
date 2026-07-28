const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');

const script = fs.readFileSync(require.resolve('../assets/js/property.js'), 'utf8');
const styles = fs.readFileSync(require.resolve('../assets/css/property.css'), 'utf8');

test('notifie les fins d’analyse en succès comme en erreur', () => {
  assert.match(script, /\['completed','failed'\]\.includes\(job\.status\)/);
  assert.match(script, /succeeded\?'Analyse terminée':'Échec de l’analyse'/);
  assert.match(script, /data\.listing\.title\|\|'Fiche du bien'/);
});

test('le toast d’analyse reste 30 secondes et permet d’afficher la fiche', () => {
  assert.match(script, /label:'Voir la fiche',onClick:\(\)=>selectTab\(type\)/);
  assert.match(script, /duration:30000/);
});

test('le toast dispose d’une fermeture accessible et de variantes visuelles', () => {
  assert.match(script, /aria-label="Fermer la notification"/);
  assert.match(styles, /\.property-toast-success\{border-left-color:var\(--go\)\}/);
  assert.match(styles, /\.property-toast-error\{border-left-color:var\(--danger\)\}/);
});
