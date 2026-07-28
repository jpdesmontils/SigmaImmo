const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');

const script = fs.readFileSync(require.resolve('../assets/js/property.js'), 'utf8');
const notifications = fs.readFileSync(require.resolve('../assets/js/analysis-notifications.js'), 'utf8');
const styles = fs.readFileSync(require.resolve('../assets/css/notifications.css'), 'utf8');

test('notifie les fins d’analyse en succès comme en erreur', () => {
  assert.match(notifications, /\['completed', 'failed'\]\.includes\(job\.status\)/);
  assert.match(notifications, /succeeded \? 'Analyse terminée' : 'Échec de l’analyse'/);
  assert.match(notifications, /currentTitle \|\| tracked\.title/);
});

test('le toast d’analyse reste 30 secondes et permet d’afficher la fiche', () => {
  assert.match(notifications, /label: 'Voir la fiche'/);
  assert.match(notifications, /duration: 30000/);
});

test('le toast dispose d’une fermeture accessible et de variantes visuelles', () => {
  assert.match(notifications, /aria-label="Fermer la notification"/);
  assert.match(styles, /\.property-toast-success\{border-left-color:var\(--go\)\}/);
  assert.match(styles, /\.property-toast-error\{border-left-color:var\(--danger\)\}/);
});

test('le suivi persiste pendant la navigation vers la galerie ou la carte', () => {
  assert.match(script, /ImmoAnalysisNotifications\.track\(\{id,type,title:data\.listing\.title\}\)/);
  assert.match(notifications, /localStorage\.setItem\(STORAGE_KEY/);
  assert.match(notifications, /property\.php\?id=/);
});
